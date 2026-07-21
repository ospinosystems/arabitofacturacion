<?php

namespace App\Console\Commands;

use App\Http\Controllers\TransferenciaDespachoController;
use App\Models\inventario;
use App\Models\usuarios;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Prueba end-to-end del despacho con recolección y bultos:
 *   guardar orden (sin descontar) → asignar a 2 pasilleros → recolección parcial
 *   (deja 1 ítem sin recolectar) → armar 2 bultos → despachar escaneando → finalizar.
 *
 * Valida:
 *   - guardar/asignar/empacar NO descuentan inventario;
 *   - despachar bulto SÍ descuenta, y EXACTO lo empacado;
 *   - lo no recolectado queda EXCLUIDO y no se descuenta;
 *   - el espejo central refleja lo despachado.
 *
 *   php artisan prueba:despacho-bultos --force
 */
class PruebaDespachoBultos extends Command
{
    protected $signature = 'prueba:despacho-bultos {--productos=4 : ítems en la orden} {--concurrente : despacha los bultos en paralelo} {--force} {--fire-despacho= : (interno) worker: despacha un bulto por código}';
    protected $description = 'Prueba el ciclo de despacho con recolección y bultos (sin descontar hasta despachar).';

    public function handle(): int
    {
        if ($this->option('fire-despacho')) {
            session(['id_usuario' => 1, 'tipo_usuario' => 7]);
            $r = $this->invocar(new TransferenciaDespachoController(), 'despacharBulto', ['codigo' => $this->option('fire-despacho')]);
            $this->output->writeln(!empty($r['estado']) ? 'OK' : ('ERR:' . ($r['msj'] ?? '')));
            return self::SUCCESS;
        }
        if ($this->option('concurrente')) {
            return $this->correrConcurrente();
        }

        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $ctrl = new TransferenciaDespachoController();

        $nProd = max(2, (int) $this->option('productos'));
        $this->warn('⚠️  Prueba REAL: descuenta inventario al despachar bultos.');
        if (!$this->option('force') && !$this->confirm('¿Continuar?', false)) {
            return self::SUCCESS;
        }

        // ── pasilleros tipo 8 (crea 2 si faltan) ──
        $pasilleros = $this->asegurarPasilleros();
        $this->line('Pasilleros: ' . $pasilleros->map(fn ($p) => "#{$p->id} {$p->nombre}")->implode(', '));

        // ── pool de productos con stock ──
        $pool = inventario::whereNotNull('codigo_barras')->where('codigo_barras', '<>', '')
            ->where('cantidad', '>=', 5)->whereColumn('precio_base', '<', 'precio')->where('precio', '>', 0)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('arabitocentral.inventario_sucursals as s')
                    ->whereColumn('s.id', 'inventarios.id')->where('s.id_sucursal', 13);
            })
            ->inRandomOrder()->limit($nProd)->get();
        if ($pool->count() < $nProd) {
            $this->error("No hay {$nProd} productos sembrados con stock. Encontrados: {$pool->count()}.");
            return self::FAILURE;
        }

        $stockAntes = [];
        $items = [];
        foreach ($pool as $p) {
            $stockAntes[$p->id] = (float) $p->cantidad;
            $items[] = ['id_producto_insucursal' => $p->id, 'cantidad' => 2];
        }

        // ── 1) guardar orden (sin descontar) ──
        $r = $this->invocar($ctrl, 'guardarOrden', ['id_destino' => 8, 'observaciones' => 'prueba bultos', 'items' => $items]);
        $this->assert(!empty($r['estado']), 'guardarOrden: ' . ($r['msj'] ?? ''));
        $orden = $r['orden'];
        $idOrden = $orden['id'];
        $this->info("Orden #{$idOrden} creada (estado {$orden['estado']}).");
        $this->assertStockSinCambios($stockAntes, 'tras guardar orden');

        // ── 2) asignar líneas a 2 pasilleros (mitad y mitad) ──
        $asigs = [];
        foreach ($orden['items'] as $i => $it) {
            $asigs[] = ['id_transferencia_item' => $it['id'], 'pasillero_id' => $pasilleros[$i % 2]->id, 'cantidad' => $it['cantidad']];
        }
        $r = $this->invocar($ctrl, 'asignarLineas', ['id_transferencia' => $idOrden, 'asignaciones' => $asigs]);
        $this->assert(!empty($r['estado']), 'asignarLineas: ' . ($r['msj'] ?? ''));
        $this->info('Líneas asignadas a pasilleros.');

        // ── 3) recolectar: TODO menos el último ítem (ese queda EXCLUIDO) ──
        $r = $this->invocar($ctrl, 'getAsignaciones', ['id_transferencia' => $idOrden]);
        $asignaciones = $r['orden']['asignaciones'];
        $ultimoItemId = end($orden['items'])['id'];
        $itemExcluidoProd = end($orden['items'])['id_producto'];
        foreach ($asignaciones as $a) {
            $recolecta = ($a['id_transferencia_item'] == $ultimoItemId) ? 0 : $a['cantidad'];
            session(['id_usuario' => $a['pasillero_id'], 'tipo_usuario' => 8]); // actuar como el pasillero
            $r = $this->invocar($ctrl, 'recolectarLinea', ['id_asignacion' => $a['id'], 'cantidad_recolectada' => $recolecta]);
            $this->assert(!empty($r['estado']), 'recolectarLinea: ' . ($r['msj'] ?? ''));
        }
        session(['id_usuario' => 1, 'tipo_usuario' => 7]); // volver a DICI
        $this->info("Recolección hecha (ítem #{$ultimoItemId} / producto #{$itemExcluidoProd} dejado SIN recolectar → debe excluirse).");
        $this->assertStockSinCambios($stockAntes, 'tras recolectar');

        // ── 4) armar 2 bultos con lo recolectado ──
        $bultoA = $this->invocar($ctrl, 'crearBulto', ['id_transferencia' => $idOrden])['bulto'];
        $bultoB = $this->invocar($ctrl, 'crearBulto', ['id_transferencia' => $idOrden])['bulto'];
        $empacadoPorProd = [];
        foreach ($orden['items'] as $idx => $it) {
            if ($it['id'] == $ultimoItemId) {
                continue; // excluido
            }
            $bulto = $idx % 2 === 0 ? $bultoA : $bultoB;
            $r = $this->invocar($ctrl, 'agregarItemBulto', ['id_bulto' => $bulto['id'], 'id_transferencia_item' => $it['id'], 'cantidad' => $it['cantidad']]);
            $this->assert(!empty($r['estado']), 'agregarItemBulto: ' . ($r['msj'] ?? ''));
            $empacadoPorProd[$it['id_producto']] = ($empacadoPorProd[$it['id_producto']] ?? 0) + $it['cantidad'];
        }
        $this->assert(!empty($this->invocar($ctrl, 'cerrarBulto', ['id_bulto' => $bultoA['id']])['estado']), 'cerrarBulto A');
        $this->assert(!empty($this->invocar($ctrl, 'cerrarBulto', ['id_bulto' => $bultoB['id']])['estado']), 'cerrarBulto B');
        $this->info("Bultos {$bultoA['codigo_barras']} y {$bultoB['codigo_barras']} cerrados.");
        $this->assertStockSinCambios($stockAntes, 'tras armar/cerrar bultos');

        // ── 5) despachar escaneando bulto por bulto (AQUÍ descuenta) ──
        foreach ([$bultoA, $bultoB] as $b) {
            $r = $this->invocar($ctrl, 'despacharBulto', ['codigo' => $b['codigo_barras']]);
            $this->assert(!empty($r['estado']), 'despacharBulto: ' . ($r['msj'] ?? ''));
            $this->line("  ✓ {$r['msj']}");
        }

        // ── 6) validar descuentos EXACTOS = empacado ──
        $okStock = true;
        $filas = [];
        foreach ($stockAntes as $pid => $antes) {
            $despues = (float) inventario::whereKey($pid)->value('cantidad');
            $real = $antes - $despues;
            $exp = $empacadoPorProd[$pid] ?? 0; // el excluido espera 0
            $ok = abs($real - $exp) < 0.0001;
            $okStock = $okStock && $ok;
            $filas[] = ["#{$pid}", $antes, $despues, $real, $exp, $ok ? '✓' : '✗'];
        }
        $this->table(['Producto', 'Antes', 'Después', 'Descontado', 'Esperado(empacado)', 'OK'], $filas);
        $this->assert($okStock, 'El descuento no coincide con lo empacado.');

        // ── 7) finalizar despacho → espejo central ──
        $r = $this->invocar($ctrl, 'finalizarDespacho', ['id_transferencia' => $idOrden]);
        $this->assert(!empty($r['estado']), 'finalizarDespacho: ' . ($r['msj'] ?? ''));
        $central = $r['orden']['id_transferencia_central'] ?? null;
        $this->info("Despacho finalizado. Espejo central #" . ($central ?? '—') . ", orden estado {$r['orden']['estado']}.");
        $this->assert($central !== null, 'No se creó el espejo en central.');

        // ── 8) reporte de excluidos ──
        $r = $this->invocar($ctrl, 'reporteExcluidos', ['id_transferencia' => $idOrden]);
        $excluidos = $r['excluidos'] ?? [];
        $this->newLine();
        $this->info('Reporte de EXCLUIDOS (mandado a recolectar pero no empacado):');
        foreach ($excluidos as $e) {
            $this->line("  #{$e['id_producto']} {$e['descripcion']}  solicitado {$e['solicitado']}  empacado {$e['empacado']}  EXCLUIDO {$e['excluido']}");
        }
        $this->assert(collect($excluidos)->firstWhere('id_producto', $itemExcluidoProd) !== null, 'El ítem no recolectado no aparece como excluido.');

        $this->newLine();
        $this->info('✅ TODO OK: sin descuento hasta despachar, descuento = empacado, excluido fuera y central con lo despachado.');
        return self::SUCCESS;
    }

    /**
     * Despacha varios bultos EN PARALELO que comparten los mismos productos, para validar que
     * el descuento por bulto es robusto bajo concurrencia (sin deadlock ni lost updates).
     */
    private function correrConcurrente(): int
    {
        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $ctrl = new TransferenciaDespachoController();
        $nProd = 4;
        $nBultos = 3; // cada producto se reparte 1 por bulto → los 3 bultos comparten productos

        $pool = inventario::whereNotNull('codigo_barras')->where('codigo_barras', '<>', '')
            ->where('cantidad', '>=', $nBultos + 2)->whereColumn('precio_base', '<', 'precio')->where('precio', '>', 0)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('arabitocentral.inventario_sucursals as s')
                    ->whereColumn('s.id', 'inventarios.id')->where('s.id_sucursal', 13);
            })->inRandomOrder()->limit($nProd)->get();
        $this->assert($pool->count() >= $nProd, 'No hay productos con stock suficiente.');

        $stockAntes = [];
        $items = [];
        foreach ($pool as $p) {
            $stockAntes[$p->id] = (float) $p->cantidad;
            $items[] = ['id_producto_insucursal' => $p->id, 'cantidad' => $nBultos]; // 1 por bulto
        }

        $orden = $this->invocar($ctrl, 'guardarOrden', ['id_destino' => 8, 'observaciones' => 'concurrente', 'items' => $items])['orden'];
        $this->info("Orden #{$orden['id']} con {$nProd} productos × {$nBultos} u. (1 por bulto).");

        // crear N bultos y meter 1 de CADA producto en CADA bulto (sin recolección → empacable = solicitado)
        $bultos = [];
        for ($i = 0; $i < $nBultos; $i++) {
            $b = $this->invocar($ctrl, 'crearBulto', ['id_transferencia' => $orden['id']])['bulto'];
            foreach ($orden['items'] as $it) {
                $r = $this->invocar($ctrl, 'agregarItemBulto', ['id_bulto' => $b['id'], 'id_transferencia_item' => $it['id'], 'cantidad' => 1]);
                $this->assert(!empty($r['estado']), 'agregarItemBulto: ' . ($r['msj'] ?? ''));
            }
            $this->assert(!empty($this->invocar($ctrl, 'cerrarBulto', ['id_bulto' => $b['id']])['estado']), 'cerrarBulto');
            $bultos[] = $b;
        }
        $this->info("{$nBultos} bultos cerrados, cada uno con los {$nProd} productos. Despachando en PARALELO…");

        // disparar despacho en paralelo (procesos OS)
        $procs = [];
        foreach ($bultos as $b) {
            $p = new \Symfony\Component\Process\Process(['php', 'artisan', 'prueba:despacho-bultos', '--fire-despacho=' . $b['codigo_barras']], base_path());
            $p->setTimeout(120);
            $p->start();
            $procs[] = [$b, $p];
        }
        $okCount = 0;
        foreach ($procs as [$b, $p]) {
            $p->wait();
            $out = trim($p->getOutput());
            if (strpos($out, 'OK') !== false) { $okCount++; $this->line("  ✓ {$b['codigo_barras']} despachado"); }
            else { $this->line("  ✗ {$b['codigo_barras']}: " . preg_replace('/\s+/', ' ', $out)); }
        }

        // validar descuento exacto = nBultos por producto
        $ok = true;
        $filas = [];
        foreach ($stockAntes as $pid => $antes) {
            $despues = (float) inventario::whereKey($pid)->value('cantidad');
            $real = $antes - $despues;
            $esperado = $okCount; // 1 por bulto despachado con éxito
            $cuadra = abs($real - $esperado) < 0.0001;
            $ok = $ok && $cuadra;
            $filas[] = ["#{$pid}", $antes, $despues, $real, $esperado, $cuadra ? '✓' : '✗ LOST UPDATE'];
        }
        $this->table(['Producto', 'Antes', 'Después', 'Descontado', 'Esperado', 'OK'], $filas);
        $this->assert($okCount === $nBultos, "Solo {$okCount}/{$nBultos} bultos se despacharon (¿deadlock?).");
        $this->assert($ok, 'El descuento concurrente no cuadra (lost update).');
        $this->info('✅ CONCURRENCIA OK: despacho paralelo de bultos sin deadlock ni lost updates.');
        return self::SUCCESS;
    }

    private function asegurarPasilleros()
    {
        $pas = usuarios::where('tipo_usuario', 8)->orderBy('id')->limit(2)->get();
        while ($pas->count() < 2) {
            $n = $pas->count() + 1;
            usuarios::create([
                'nombre' => 'PASILLERO PRUEBA ' . $n,
                'usuario' => 'pasillero_prueba_' . $n,
                'clave' => Hash::make('1234'),
                'tipo_usuario' => 8,
            ]);
            $pas = usuarios::where('tipo_usuario', 8)->orderBy('id')->limit(2)->get();
        }
        return $pas->values();
    }

    private function invocar(TransferenciaDespachoController $ctrl, string $metodo, array $payload): array
    {
        $res = $ctrl->{$metodo}(Request::create('/x', 'POST', $payload));
        if ($res instanceof \Illuminate\Http\JsonResponse) {
            return $res->getData(true);
        }
        return is_array($res) ? $res : ['estado' => false, 'msj' => 'respuesta no JSON'];
    }

    private function assert($cond, string $msj): void
    {
        if (!$cond) {
            $this->error('❌ ' . $msj);
            exit(1);
        }
    }

    private function assertStockSinCambios(array $stockAntes, string $cuando): void
    {
        foreach ($stockAntes as $pid => $antes) {
            $ahora = (float) inventario::whereKey($pid)->value('cantidad');
            if (abs($ahora - $antes) > 0.0001) {
                $this->assert(false, "El inventario del producto #{$pid} cambió {$cuando} (antes {$antes}, ahora {$ahora}) — no debía descontarse aún.");
            }
        }
    }
}
