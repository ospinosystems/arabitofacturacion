<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copia a la BD local las ENTRADAS de inventario de esta sucursal registradas en central: cada pedido de central con
 * destino esta sucursal, con sus ítems, el costo unitario (items_pedidos.base, USD) y el producto mapeado al
 * inventario local. Tipos: FACTURA (factura fiscal de compra, CxP), NOTA (CxP sin factura fiscal) y TRANSFERENCIA
 * (pedidos sin CxP: traslados entre sucursales). El libro de inventario usa por defecto solo FACTURA.
 *
 * Central se lee en SOLO LECTURA: la conexión se abre con `SET SESSION TRANSACTION READ ONLY` y solo se hacen SELECT.
 *
 * Mapeo de productos: el id de producto es el mismo en central y en las sucursales (inventario_sucursals.idinsucursal);
 * si no existe localmente se busca por código de barras, código de proveedor o descripción del almacén de origen, y si
 * tampoco, se crea en `inventarios` con el mismo id (salvo --sin-crear-productos).
 */
class InventarioImportarEntradas extends Command
{
    protected $signature = 'inventario:importar-entradas
                            {--sucursal-central= : id de esta sucursal en central (Punto Fijo = 46); default env INVENTARIO_SUCURSAL_CENTRAL_ID}
                            {--central-env= : ruta al .env de la app de central para tomar DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD; si no se indica se usan DB_CENTRAL_* de este .env}
                            {--tipos=FACTURA,NOTA,TRANSFERENCIA : tipos de entrada a importar}
                            {--desde= : solo pedidos de central creados desde esta fecha (YYYY-MM-DD)}
                            {--hasta= : solo pedidos de central creados hasta esta fecha (YYYY-MM-DD)}
                            {--sin-crear-productos : no crear en inventarios los productos que no existan localmente}
                            {--dry-run : solo leer central y mostrar el resumen; no escribe nada}';

    protected $description = 'Importa (solo lectura sobre central) las entradas de inventario de esta sucursal: facturas de compra, notas y traslados con sus ítems y costos.';

    public function handle(): int
    {
        $sucursalCentral = (int) ($this->option('sucursal-central') ?: env('INVENTARIO_SUCURSAL_CENTRAL_ID', 0));
        if ($sucursalCentral <= 0) {
            $this->error('Indique --sucursal-central=<id de esta sucursal en central> (o INVENTARIO_SUCURSAL_CENTRAL_ID en .env).');
            return self::FAILURE;
        }
        $tipos = array_values(array_filter(array_map('trim', explode(',', strtoupper((string) $this->option('tipos'))))));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $central = $this->conectarCentral();
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar a central: ' . $e->getMessage());
            return self::FAILURE;
        }
        $suc = $central->table('sucursals')->where('id', $sucursalCentral)->first(['id', 'codigo', 'nombre']);
        if (!$suc) {
            $this->error("La sucursal {$sucursalCentral} no existe en central.");
            return self::FAILURE;
        }
        $local = DB::table('sucursals')->first();
        $this->info(sprintf('Central: sucursal %d «%s» (%s) | BD local: %s | tipos: %s%s', $suc->id, $suc->nombre, $suc->codigo, $local->codigo ?? '?', implode(',', $tipos), $dryRun ? ' | DRY-RUN' : ''));
        if ($local && strtolower((string) $local->codigo) !== strtolower((string) $suc->codigo)) {
            $this->warn("Atención: el código local «{$local->codigo}» no coincide con el de central «{$suc->codigo}».");
            if (!$dryRun && !$this->confirm('¿Continuar de todos modos?', false)) {
                return self::FAILURE;
            }
        }

        // ── Cabeceras: pedidos de central con destino esta sucursal ──────────────────────────────────
        $q = $central->table('pedidos as p')
            ->leftJoin('cuentasporpagars as c', 'c.id', '=', 'p.id_cxp')
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'c.id_proveedor')
            ->leftJoin('sucursals as s', 's.id', '=', 'p.id_origen')
            ->where('p.id_destino', $sucursalCentral)
            ->select('p.id as pedido_id', 'p.id_cxp', 'p.id_origen', 's.codigo as origen_codigo', 'p.created_at as recibido',
                'c.tipo_documento', 'c.numfact', 'c.numnota', 'c.fechaemision', 'c.fecharecepcion', 'c.tasa_bs', 'c.monto', 'c.anulado', 'pr.descripcion as proveedor', 'pr.rif');
        if ($this->option('desde')) {
            $q->where('p.created_at', '>=', $this->option('desde') . ' 00:00:00');
        }
        if ($this->option('hasta')) {
            $q->where('p.created_at', '<=', $this->option('hasta') . ' 23:59:59');
        }
        $cabeceras = [];
        foreach ($q->orderBy('p.id')->get() as $r) {
            $tipo = ((int) $r->id_cxp > 0) ? strtoupper(trim((string) ($r->tipo_documento ?: 'NOTA'))) : 'TRANSFERENCIA';
            if (!in_array($tipo, $tipos, true)) {
                continue;
            }
            $cabeceras[(int) $r->pedido_id] = [
                'sucursal_central_id' => $sucursalCentral,
                'tipo'                => $tipo,
                'central_pedido_id'   => (int) $r->pedido_id,
                'central_cxp_id'      => (int) $r->id_cxp > 0 ? (int) $r->id_cxp : null,
                'origen_sucursal_id'  => $r->id_origen !== null ? (int) $r->id_origen : null,
                'origen_sucursal'     => $r->origen_codigo,
                'numero_documento'    => $r->numfact,
                'numero_nota'         => $r->numnota,
                'proveedor'           => $r->proveedor,
                'proveedor_rif'       => $r->rif,
                'fecha_emision'       => $r->fechaemision,
                'fecha_recepcion'     => substr((string) ($r->fecharecepcion ?: $r->recibido), 0, 10),
                'tasa_bs'             => $r->tasa_bs,
                'monto_usd'           => $r->monto !== null ? abs((float) $r->monto) : null,
                'anulado'             => (int) ($r->anulado ?? 0) === 1,
            ];
        }
        $this->info('Pedidos con destino esta sucursal: ' . count($cabeceras) . ' → ' . json_encode(array_count_values(array_column($cabeceras, 'tipo'))));
        if (empty($cabeceras)) {
            return self::SUCCESS;
        }

        // ── Ítems ────────────────────────────────────────────────────────────────────────────────────
        $items = [];
        foreach (array_chunk(array_keys($cabeceras), 500) as $chunk) {
            foreach ($central->table('items_pedidos')->whereIn('id_pedido', $chunk)->get(['id_pedido', 'id_producto', 'cantidad', 'ct_real', 'base', 'venta']) as $it) {
                $items[] = $it;
            }
        }
        $this->info('Ítems: ' . count($items));

        // ── Fichas de los productos en el almacén de origen (códigos y descripción) ──────────────────
        $porOrigen = [];
        foreach ($items as $it) {
            $porOrigen[$cabeceras[(int) $it->id_pedido]['origen_sucursal_id'] ?? 0][(int) $it->id_producto] = true;
        }
        $fichas = []; // id_producto central → ficha
        foreach ($porOrigen as $origen => $ids) {
            $ids = array_keys($ids);
            foreach (array_chunk($ids, 1000) as $chunk) {
                $rows = $central->table('inventario_sucursals')->whereIn('idinsucursal', $chunk)
                    ->orderByRaw('id_sucursal = ? desc', [(int) $origen])
                    ->get(['idinsucursal', 'id_sucursal', 'codigo_barras', 'codigo_proveedor', 'descripcion', 'precio_base', 'precio', 'unidad']);
                foreach ($rows as $f) {
                    $fichas[(int) $f->idinsucursal] = $fichas[(int) $f->idinsucursal] ?? $f; // la del origen va primero
                }
            }
        }

        // ── Mapeo a productos locales ────────────────────────────────────────────────────────────────
        $locales = DB::table('inventarios')->get(['id', 'codigo_barras', 'codigo_proveedor', 'descripcion']);
        $porId = $locales->keyBy('id');
        $norm = fn ($v) => mb_strtoupper(trim((string) $v));
        $porCb = []; $porCp = []; $porDesc = [];
        foreach ($locales as $l) {
            if ($norm($l->codigo_barras) !== '') $porCb[$norm($l->codigo_barras)][] = (int) $l->id;
            if ($norm($l->codigo_proveedor) !== '') $porCp[$norm($l->codigo_proveedor)][] = (int) $l->id;
            if ($norm($l->descripcion) !== '') $porDesc[$norm($l->descripcion)][] = (int) $l->id;
        }
        $mapeo = []; $stats = ['id' => 0, 'codigo_barras' => 0, 'codigo_proveedor' => 0, 'descripcion' => 0, 'creado' => 0, 'ninguno' => 0, 'ambiguo' => 0];
        $crear = [];
        foreach (array_unique(array_map(fn ($it) => (int) $it->id_producto, $items)) as $pid) {
            if (isset($porId[$pid])) {
                $mapeo[$pid] = ['id' => $pid, 'metodo' => 'id']; $stats['id']++;
                continue;
            }
            $f = $fichas[$pid] ?? null;
            $cands = null; $metodo = 'ninguno';
            if ($f) {
                foreach ([['codigo_barras', $porCb, $f->codigo_barras], ['codigo_proveedor', $porCp, $f->codigo_proveedor], ['descripcion', $porDesc, $f->descripcion]] as [$m, $idx, $val]) {
                    $k = $norm($val);
                    if ($k !== '' && isset($idx[$k])) { $cands = $idx[$k]; $metodo = $m; break; }
                }
            }
            if ($cands) {
                if (count($cands) > 1) $stats['ambiguo']++;
                $mapeo[$pid] = ['id' => $cands[0], 'metodo' => $metodo]; $stats[$metodo]++;
            } elseif ($f && !$this->option('sin-crear-productos')) {
                $crear[$pid] = $f;
                $mapeo[$pid] = ['id' => $pid, 'metodo' => 'creado']; $stats['creado']++;
            } else {
                $mapeo[$pid] = ['id' => null, 'metodo' => 'ninguno']; $stats['ninguno']++;
            }
        }
        $this->info('Mapeo de productos: ' . json_encode($stats));

        // ── Resumen ──────────────────────────────────────────────────────────────────────────────────
        $tot = [];
        foreach ($items as $it) {
            $t = $cabeceras[(int) $it->id_pedido]['tipo'];
            $tot[$t] = $tot[$t] ?? ['lineas' => 0, 'unidades' => 0.0, 'costo_usd' => 0.0];
            $tot[$t]['lineas']++;
            $tot[$t]['unidades'] += (float) $it->cantidad;
            $tot[$t]['costo_usd'] += (float) $it->cantidad * (float) $it->base;
        }
        foreach ($tot as $t => $v) {
            $this->line(sprintf('  %-14s líneas %6d | unidades %12s | costo %14s USD', $t, $v['lineas'], number_format($v['unidades'], 2, ',', '.'), number_format($v['costo_usd'], 2, ',', '.')));
        }
        if ($dryRun) {
            $this->warn('Dry-run: no se escribió nada.');
            return self::SUCCESS;
        }

        // ── Escritura local ──────────────────────────────────────────────────────────────────────────
        $now = now();
        DB::transaction(function () use ($cabeceras, $items, $mapeo, $crear, $fichas, $now) {
            foreach ($crear as $pid => $f) {
                DB::table('inventarios')->insert([
                    'id' => $pid, 'codigo_barras' => (string) ($f->codigo_barras ?: 'CENTRAL-' . $pid), 'codigo_proveedor' => $f->codigo_proveedor,
                    'descripcion' => mb_substr((string) $f->descripcion, 0, 191), 'unidad' => $f->unidad ?: 'UND',
                    'precio_base' => min(99999.999, (float) $f->precio_base), 'precio' => min(99999.999, (float) $f->precio), 'cantidad' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $idsEntrada = [];
            foreach ($cabeceras as $pid => $c) {
                DB::table('inventario_entradas')->updateOrInsert(['central_pedido_id' => $pid], $c + ['importado_at' => $now, 'updated_at' => $now, 'created_at' => $now]);
                $idsEntrada[$pid] = (int) DB::table('inventario_entradas')->where('central_pedido_id', $pid)->value('id');
            }
            DB::table('inventario_entrada_items')->whereIn('entrada_id', array_values($idsEntrada))->delete();
            $filas = [];
            foreach ($items as $it) {
                $pid = (int) $it->id_producto;
                $f = $fichas[$pid] ?? null;
                $filas[] = [
                    'entrada_id' => $idsEntrada[(int) $it->id_pedido], 'central_id_producto' => $pid,
                    'id_producto' => $mapeo[$pid]['id'], 'mapeo' => $mapeo[$pid]['metodo'],
                    'cantidad' => $it->cantidad, 'cantidad_real' => $it->ct_real, 'costo_unitario_usd' => $it->base, 'precio_venta_usd' => $it->venta,
                    'codigo_barras_origen' => $f->codigo_barras ?? null, 'codigo_proveedor_origen' => $f->codigo_proveedor ?? null,
                    'descripcion_origen' => $f ? mb_substr((string) $f->descripcion, 0, 191) : null,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                if (count($filas) >= 500) { DB::table('inventario_entrada_items')->insert($filas); $filas = []; }
            }
            if ($filas) DB::table('inventario_entrada_items')->insert($filas);
        });
        $this->info(sprintf('Guardado: %d entradas, %d ítems, %d productos creados en inventarios.', count($cabeceras), count($items), count($crear)));
        return self::SUCCESS;
    }

    /**
     * Conexión de SOLO LECTURA a central: credenciales del .env de central (--central-env) o DB_CENTRAL_* del .env local.
     */
    protected function conectarCentral(): \Illuminate\Database\Connection
    {
        $env = [];
        if ($ruta = $this->option('central-env')) {
            if (!is_readable($ruta)) {
                throw new \RuntimeException("No se puede leer {$ruta}");
            }
            foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $l, $m)) {
                    $env[$m[1]] = trim($m[2], " \t\"'");
                }
            }
        } else {
            $env = ['DB_HOST' => env('DB_CENTRAL_HOST', '127.0.0.1'), 'DB_PORT' => env('DB_CENTRAL_PORT', 3306), 'DB_DATABASE' => env('DB_CENTRAL_DATABASE'), 'DB_USERNAME' => env('DB_CENTRAL_USERNAME'), 'DB_PASSWORD' => env('DB_CENTRAL_PASSWORD')];
        }
        if (empty($env['DB_DATABASE']) || empty($env['DB_USERNAME'])) {
            throw new \RuntimeException('faltan credenciales (DB_DATABASE/DB_USERNAME); use --central-env=/ruta/al/.env de central o DB_CENTRAL_* en el .env local.');
        }
        config(['database.connections.central' => [
            'driver' => 'mysql', 'host' => $env['DB_HOST'] ?: '127.0.0.1', 'port' => $env['DB_PORT'] ?: 3306,
            'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => false, 'engine' => null,
            'options' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY'],
        ]]);
        DB::purge('central');
        $c = DB::connection('central');
        $c->select('select 1');
        return $c;
    }
}
