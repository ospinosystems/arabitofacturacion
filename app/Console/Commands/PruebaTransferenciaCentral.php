<?php

namespace App\Console\Commands;

use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PedidosController;
use App\Http\Controllers\sendCentral;
use App\Models\inventario;
use App\Models\items_pedidos;
use App\Models\pedidos;
use App\Models\sucursal;
use App\Models\usuarios;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;

/**
 * SMOKE TEST de envío/transferencia de pedidos contra la central REAL.
 *
 * Hace dos rondas sobre productos REALES del inventario local:
 *  Ronda 1 (ida y vuelta): crea pedido → valida descuento → exporta a la misma
 *           sucursal → valida creación en central → desexporta → valida que el
 *           inventario se reintegre y que central elimine el espejo.
 *  Ronda 2 (deja servido): igual hasta la exportación, pero NO desexporta: deja
 *           el pedido en central para que hagas la RECEPCIÓN manual.
 *
 * Si CUALQUIER validación falla, se detiene de inmediato y explica qué pasó
 * (no corre la ronda 2 si la ronda 1 falló).
 *
 * ⚠️ Muta inventario real y crea/borra pedidos en central. Requiere --force
 *    (o confirmación interactiva).
 *
 * Limitación conocida: a estado=1 (recién exportado) central no expone los ítems
 * del espejo (/getPedidosEspejoByOrigen no los trae; /getPedidoCentralImport exige
 * estado=4). Por eso la validación "cantidades en central" se hace sobre el payload
 * EXACTO que se envió (central inserta `cantidad` = item enviado, verbatim) + la
 * confirmación de creación del espejo. La verificación por ítem en central ocurre
 * en la recepción (ronda 2, manual).
 */
class PruebaTransferenciaCentral extends Command
{
    protected $signature = 'prueba:transferencia-central
                            {--productos= : Cantidad de productos distintos (default: aleatorio entre 10 y 20, mínimo 10)}
                            {--cantidad=1 : Unidades por producto a transferir}
                            {--ids= : Ids de producto local (coma-separados) a usar en vez de elegir al azar}
                            {--force : Ejecutar sin confirmación (muta datos reales)}';

    protected $description = 'Smoke test real de envío/transferencia de pedidos a central (ronda ida-y-vuelta + ronda que deja servido para recepción manual).';

    public function handle(): int
    {
        $prodOpt = $this->option('productos');
        $nProductos = ($prodOpt !== null && $prodOpt !== '')
            ? max(10, (int) $prodOpt)   // si lo pasás, mínimo 10
            : rand(10, 20);             // default: aleatorio 10-20
        $cantidad   = (float) $this->option('cantidad');
        if ($cantidad < 0.1) {
            $this->error('--cantidad debe ser >= 0.1');
            return self::FAILURE;
        }

        $this->warn('⚠️  Esta prueba MODIFICA inventario real y crea/borra pedidos en CENTRAL.');
        if (!$this->option('force') && !$this->confirm('¿Continuar?', false)) {
            $this->line('Cancelado. Usá --force para correr sin preguntar.');
            return self::SUCCESS;
        }

        // ── Contexto: usuario de sesión y sucursal propia ──
        try {
            $uid = (int) (usuarios::where('tipo_usuario', 7)->value('id') ?? usuarios::query()->value('id'));
            if (!$uid) {
                $this->error('No hay usuarios en la BD local; no puedo crear el pedido.');
                return self::FAILURE;
            }
            session(['id_usuario' => $uid]);

            $suc = sucursal::query()->first();
            if (!$suc) {
                $this->error('No hay sucursal configurada (tabla sucursal vacía).');
                return self::FAILURE;
            }
            $codigoOrigen = $suc->codigo;

            $destinoId = $this->resolverSucursalDestino($codigoOrigen);
            $this->info("Sucursal: {$codigoOrigen} (destino central id={$destinoId}) · usuario sesión id={$uid}");
        } catch (\Throwable $e) {
            $this->error('Error preparando contexto: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── Ronda 1: ida y vuelta (con desexport) ──
        $this->newLine();
        $this->info('========== RONDA 1: exportar y DESEXPORTAR (ida y vuelta) ==========');
        try {
            $this->correrRonda(true, $nProductos, $cantidad, $destinoId, $codigoOrigen);
        } catch (\Throwable $e) {
            $this->fallo('RONDA 1', $e);
            return self::FAILURE;
        }
        $this->info('✔ RONDA 1 OK: descuento, exportación, creación en central, reintegro y borrado en central verificados.');

        // ── Ronda 2: dejar servido para recepción manual ──
        $this->newLine();
        $this->info('========== RONDA 2: exportar y DEJAR en central (recepción manual) ==========');
        try {
            $pedidoId = $this->correrRonda(false, $nProductos, $cantidad, $destinoId, $codigoOrigen);
            $this->newLine();
            $this->info("✔ RONDA 2 OK: el pedido local #{$pedidoId} quedó EXPORTADO en central (estado 1).");
            $this->line("   Hacé la RECEPCIÓN manual de ese pedido desde la Torre de Transferencia (TCR).");
        } catch (\Throwable $e) {
            $this->fallo('RONDA 2', $e);
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('TODO OK ✅');
        return self::SUCCESS;
    }

    /**
     * Corre una ronda completa. Devuelve el id del pedido local creado.
     * Lanza \RuntimeException con detalle si alguna validación falla.
     */
    private function correrRonda(bool $desexportar, int $nProductos, float $cantidad, $destinoId, string $codigoOrigen): int
    {
        $sc = new sendCentral();

        // 1) Elegir productos reales con stock suficiente
        $productos = $this->elegirProductos($nProductos, $cantidad);

        // 2) Snapshot del inventario ANTES
        $antes = [];
        foreach ($productos as $p) {
            $antes[$p->id] = (float) inventario::whereKey($p->id)->value('cantidad');
            $this->line("  • {$p->descripcion} (id {$p->id}) — antes: {$antes[$p->id]}");
        }

        // 3) Crear pedido
        $pedidoId = $this->crearPedido();
        $this->line("  Pedido local creado: #{$pedidoId}");

        // 4) Agregar cada producto al pedido (descuenta inventario)
        $invCtrl = new InventarioController();
        foreach ($productos as $p) {
            $invCtrl->hacer_pedido($p->id, $pedidoId, $cantidad, 'ins');
        }

        // 5) Validar el descuento: antes - despues == cantidad
        foreach ($productos as $p) {
            $despues = (float) inventario::whereKey($p->id)->value('cantidad');
            $resta = round($antes[$p->id] - $despues, 4);
            if (abs($resta - round($cantidad, 4)) > 0.0001) {
                throw new \RuntimeException(
                    "Descuento incorrecto en '{$p->descripcion}' (id {$p->id}): antes={$antes[$p->id]}, despues={$despues}, " .
                    "resta={$resta}, esperado={$cantidad}."
                );
            }
            $this->line("  ✓ descuento {$p->descripcion}: {$antes[$p->id]} - {$cantidad} = {$despues}");
        }

        // 6) Validar el payload que se ENVIARÁ a central (lo que central inserta verbatim)
        $payload = $sc->pedidosExportadosFun($pedidoId)->first();
        $itemsEnviados = collect($payload['items'] ?? $payload->items ?? []);
        if ($itemsEnviados->count() !== $productos->count()) {
            throw new \RuntimeException("El payload a central tiene {$itemsEnviados->count()} ítems, esperados {$productos->count()}.");
        }
        foreach ($productos as $p) {
            $env = $itemsEnviados->first(fn ($it) => (int) ($it['id_producto'] ?? $it->id_producto ?? 0) === (int) $p->id);
            $ctEnv = $env ? (float) ($env['cantidad'] ?? $env->cantidad ?? 0) : null;
            if ($ctEnv === null || abs($ctEnv - $cantidad) > 0.0001) {
                throw new \RuntimeException("Payload: cantidad enviada de '{$p->descripcion}' = " . var_export($ctEnv, true) . ", esperado {$cantidad}.");
            }
        }
        $this->line('  ✓ payload a central coincide con lo agregado.');

        // 7) Exportar (transferir) a mi misma sucursal
        $resExport = $this->asArray($sc->setPedidoInCentralFromMaster($pedidoId, $destinoId, 'add'));
        if (empty($resExport['estado'])) {
            throw new \RuntimeException('Central rechazó la exportación: ' . ($resExport['msj'] ?? 'sin mensaje'));
        }
        $this->line('  ✓ exportado a central.');

        // 8) Validar creación en central (espejo presente con idinsucursal == pedido local)
        $espejo = $this->buscarEspejo($sc, $pedidoId);
        if (!$espejo) {
            throw new \RuntimeException("Central no reporta el espejo del pedido #{$pedidoId} tras exportar.");
        }
        if ((int) $espejo['estado'] !== 1) {
            throw new \RuntimeException("Espejo en central con estado inesperado: {$espejo['estado']} (esperado 1=exportado).");
        }
        $destinoCodigo = $espejo['destino']['codigo'] ?? null;
        $this->line("  ✓ central confirmó creación: espejo central id={$espejo['id']}, estado=1, destino=" . ($destinoCodigo ?? $espejo['id_destino'] ?? '?'));

        if (!$desexportar) {
            // Ronda 2: dejar el pedido servido en central.
            return $pedidoId;
        }

        // 9) Desexportar + reintegrar (flujo real delPedidoFun: desexporta en central y, si
        //    confirma, repone inventario y elimina el pedido local).
        $resDel = $this->asArray((new PedidosController)->delPedidoFun($pedidoId, 'PRUEBA smoke: desexport + reintegro'));
        if (array_key_exists('estado', $resDel) && $resDel['estado'] === false) {
            throw new \RuntimeException('delPedidoFun no completó: ' . ($resDel['msj'] ?? 'sin mensaje'));
        }

        // 10) Validar que central eliminó el espejo
        $espejoPost = $this->buscarEspejo($sc, $pedidoId);
        if ($espejoPost) {
            throw new \RuntimeException("Central TODAVÍA reporta el espejo del pedido #{$pedidoId} (estado {$espejoPost['estado']}) tras desexportar.");
        }
        $this->line('  ✓ central eliminó el espejo.');

        // 11) Validar reintegro del inventario (vuelve al valor original)
        foreach ($productos as $p) {
            $final = (float) inventario::whereKey($p->id)->value('cantidad');
            if (abs($final - $antes[$p->id]) > 0.0001) {
                throw new \RuntimeException(
                    "No se reintegró '{$p->descripcion}' (id {$p->id}): original={$antes[$p->id]}, final={$final}."
                );
            }
            $this->line("  ✓ reintegro {$p->descripcion}: volvió a {$final}");
        }

        return $pedidoId;
    }

    /**
     * Elige los productos a transferir. Con --ids usa esos exactos; si no, elige al
     * azar productos con stock y precio_base < precio (central rechaza precio_base >= precio).
     */
    private function elegirProductos(int $n, float $cantidad)
    {
        $idsOpt = trim((string) $this->option('ids'));
        if ($idsOpt !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $idsOpt))));
            $prods = inventario::whereIn('id', $ids)->where('cantidad', '>=', $cantidad)->get();
            if ($prods->count() < count($ids)) {
                throw new \RuntimeException('Algún --ids no existe o no tiene stock >= ' . $cantidad . '.');
            }
            return $prods;
        }

        $prods = inventario::query()
            ->whereNotNull('codigo_barras')->where('codigo_barras', '<>', '')
            ->where('cantidad', '>=', $cantidad)
            ->whereColumn('precio_base', '<', 'precio')->where('precio', '>', 0)
            ->where(function ($q) { $q->where('activo', 1)->orWhereNull('activo'); })
            ->inRandomOrder()
            ->limit($n)
            ->get();

        if ($prods->count() < $n) {
            throw new \RuntimeException("No hay {$n} producto(s) con stock >= {$cantidad} y precio_base < precio. Encontrados: {$prods->count()}.");
        }
        return $prods;
    }

    /** Crea un pedido local (estado 0) usando el flujo real addNewPedido(). */
    private function crearPedido(): int
    {
        $res = (new PedidosController)->addNewPedido();
        if (is_numeric($res)) {
            return (int) $res;
        }
        // addNewPedido devolvió un JsonResponse de error (p.ej. cierre guardado)
        $arr = $this->asArray($res);
        throw new \RuntimeException('No se pudo crear el pedido: ' . ($arr['msj'] ?? 'addNewPedido no devolvió un id'));
    }

    /** Busca el espejo del pedido local en central por idinsucursal. Null si no está. */
    private function buscarEspejo(sendCentral $sc, int $pedidoLocalId): ?array
    {
        $res = $this->asArray($sc->getPedidosEspejoCentral());
        if (empty($res['estado'])) {
            throw new \RuntimeException('Central rechazó getPedidosEspejoCentral: ' . ($res['msj'] ?? 'sin mensaje'));
        }
        foreach (($res['espejos'] ?? []) as $esp) {
            if ((int) ($esp['idinsucursal'] ?? -1) === $pedidoLocalId) {
                return $esp;
            }
        }
        return null;
    }

    /** Resuelve el id (en central) de mi propia sucursal a partir de su código. */
    private function resolverSucursalDestino(string $codigoOrigen)
    {
        $res = $this->asArray((new sendCentral)->getSucursales());
        if (empty($res['estado'])) {
            throw new \RuntimeException('No pude listar sucursales de central: ' . (is_string($res['msj'] ?? null) ? $res['msj'] : 'sin mensaje'));
        }
        foreach (($res['msj'] ?? []) as $s) {
            if (($s['codigo'] ?? null) === $codigoOrigen) {
                return $s['id'];
            }
        }
        throw new \RuntimeException("No encontré mi sucursal (codigo {$codigoOrigen}) en la lista de central.");
    }

    /** Normaliza respuestas (array | JsonResponse | string JSON) a array. */
    private function asArray($res): array
    {
        if (is_array($res)) {
            return $res;
        }
        if ($res instanceof JsonResponse) {
            $d = $res->getData(true);
            return is_array($d) ? $d : ['estado' => false, 'msj' => (string) $res->getContent()];
        }
        if (is_string($res)) {
            $d = json_decode($res, true);
            return is_array($d) ? $d : ['estado' => false, 'msj' => $res];
        }
        return ['estado' => false, 'msj' => 'Respuesta no interpretable de central.'];
    }

    private function fallo(string $ronda, \Throwable $e): void
    {
        $this->newLine();
        $this->error("✖ {$ronda} FALLÓ — se detiene la prueba.");
        $this->error('Motivo: ' . $e->getMessage());
        $this->warn('Revisá el estado actual (puede haber un pedido a medias / inventario descontado).');
    }
}
