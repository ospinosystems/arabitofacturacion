<?php

namespace App\Console\Commands;

use App\Http\Controllers\InventarioController;
use App\Http\Controllers\sendCentral;
use App\Models\factura;
use App\Models\inventario;
use App\Models\items_factura;
use App\Models\movimientosInventariounitario;
use App\Models\transferencias_inventario;
use App\Models\transferencias_inventario_items;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Prueba REAL del ciclo completo de transferencia con panel de debug en vivo:
 *   armar transferencia → enviar a central → editar → validar descuento →
 *   validar espejo en central → recepción automática (drive 1→3, fuerza 4, importa) →
 *   validar que la mercancía ingresa al inventario y deja movimiento.
 *
 * El foco es la INTEGRIDAD de las cantidades: un ciclo envío+recepción del mismo
 * producto debe dejar el stock EXACTAMENTE como estaba (neto cero).
 *
 * Requiere central arriba (127.0.0.1:8001). Muta datos reales pero limpia al final
 * (borra factura, transferencia y espejo de prueba; restaura el stock si quedó mal).
 *
 *   php artisan prueba:transferencia-ciclo --force
 *   php artisan prueba:transferencia-ciclo --producto=173277 --cantidad=2 --force
 */
class PruebaTransferenciaCiclo extends Command
{
    protected $signature = 'prueba:transferencia-ciclo
                            {--producto= : id de inventario local a usar (default: uno sembrado con stock)}
                            {--cantidad=2 : unidades a transferir}
                            {--race : modo concurrencia: dispara N envíos del mismo producto en paralelo y valida integridad}
                            {--falla-red : valida que una caída de red al enviar NO descuadre el inventario (atómico)}
                            {--watch : modo video: panel que se actualiza en el lugar y pausa entre pasos para verlo en vivo}
                            {--n=8 : cantidad de peticiones concurrentes en --race}
                            {--fire= : (interno) ejecuta UNA acción y sale; lo usan los procesos de --race}
                            {--force : correr sin confirmación}';

    protected $description = 'Ciclo real de transferencia (envío→editar→recepción) con panel de debug en vivo e integridad de inventario.';

    /** filas del panel: cada paso registra el stock del producto */
    private array $panel = [];
    private int $idDestino = 8;
    /** dashboard en vivo (modo --watch): líneas de estado + cabecera fija */
    private array $statusLog = [];
    private string $cabecera = '';

    public function handle(): int
    {
        // Worker interno de concurrencia: hace UNA acción y sale.
        if ($this->option('fire')) {
            return $this->runFire();
        }
        if ($this->option('race')) {
            return $this->runRace();
        }
        if ($this->option('falla-red')) {
            return $this->runFallaRed();
        }

        $cantidad = max(1, (int) $this->option('cantidad'));
        $cantEdit = $cantidad + 1; // la edición cambia la cantidad para probar el delta

        $this->warn('⚠️  Muta inventario real y habla con central. Limpia al final.');
        if (!$this->option('force') && !$this->confirm('¿Continuar?', false)) {
            return self::SUCCESS;
        }

        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $sc = new sendCentral();

        // ── producto ──
        $prod = $this->elegirProducto($cantEdit);
        if (!$prod) {
            $this->error('No encontré un producto sembrado en central con stock suficiente. Usá --producto=ID.');
            return self::FAILURE;
        }
        $stockOriginal = (float) $prod->cantidad;
        $this->cabecera = "Producto: #{$prod->id} {$prod->descripcion}  | stock inicial: {$stockOriginal}"
            . PHP_EOL . "Sucursal misma (origen=destino, id central {$this->idDestino}). Cantidad: {$cantidad}, editada: {$cantEdit}";
        if (!$this->option('watch')) {
            $this->line($this->cabecera);
        }
        $this->registrar('0. INICIAL', $prod->id, $stockOriginal, null, $stockOriginal);

        $localId = null;
        $centralId = null;
        try {
            // ── 1) CREAR (descuenta) ──
            $antes = $this->stock($prod->id);
            $c = $this->asArray($sc->settransferenciaDici($this->reqTransfer(false, null, $prod->id, $cantidad)));
            $this->assert(!empty($c['estado']), 'Crear transferencia: ' . ($c['msj'] ?? 'sin respuesta'));
            $localId = $c['transferencia']['id'] ?? null;
            $centralId = $c['transferencia']['id_transferencia_central'] ?? null;
            $despues = $this->stock($prod->id);
            $this->registrar("1. ENVÍO ×{$cantidad}", $prod->id, $antes, -$cantidad, $despues);
            $this->assert(abs(($antes - $despues) - $cantidad) < 0.0001, "Descuento mal: {$antes}→{$despues}, esperado -{$cantidad}");

            // ── 2) EDITAR (delta) ──
            $antes = $this->stock($prod->id);
            $u = $this->asArray($sc->settransferenciaDici($this->reqTransfer(true, $centralId, $prod->id, $cantEdit)));
            $this->assert(!empty($u['estado']), 'Editar transferencia: ' . ($u['msj'] ?? 'sin respuesta'));
            $despues = $this->stock($prod->id);
            // tras editar, el total descontado debe ser cantEdit respecto al original
            $descontadoTotal = $stockOriginal - $despues;
            $this->registrar("2. EDITAR ×{$cantEdit}", $prod->id, $antes, $despues - $antes, $despues);
            $this->assert(abs($descontadoTotal - $cantEdit) < 0.0001, "Delta de edición mal: descontado total {$descontadoTotal}, esperado {$cantEdit}");

            // ── 3) Validar espejo en central ──
            $esp = $this->buscarEspejo($sc, $localId);
            $this->assert($esp !== null, "Central no reporta el espejo (idinsucursal {$localId}) tras editar");
            $this->status("  ✓ central tiene el espejo (central id {$esp['id']}, estado {$esp['estado']})");

            // ── 4) RECEPCIÓN automática (1→3, fuerza 4, importa) ──
            $movAntes = $this->contarMovs($prod->id);
            $antes = $this->stock($prod->id);
            $this->recibir($sc, $centralId);
            $despues = $this->stock($prod->id);
            $movDespues = $this->contarMovs($prod->id);
            $this->registrar("3. RECEPCIÓN ×{$cantEdit}", $prod->id, $antes, $despues - $antes, $despues);
            $this->assert(abs(($despues - $antes) - $cantEdit) < 0.0001, "Ingreso mal: {$antes}→{$despues}, esperado +{$cantEdit}");

            // movimiento de la recepción
            if ($movDespues > $movAntes) {
                $this->status("  ✓ recepción dejó " . ($movDespues - $movAntes) . " movimiento(s)");
            } else {
                $this->status("  ⚠ la recepción NO dejó movimiento en inventario general (stock sí subió)");
            }

            // ── 5) INTEGRIDAD: neto cero ──
            $stockFinal = $this->stock($prod->id);
            if (abs($stockFinal - $stockOriginal) < 0.0001) {
                $this->status("✅ INTEGRIDAD OK: stock volvió a {$stockFinal} (== inicial {$stockOriginal}). Envío y recepción cuadran.");
            } else {
                $this->status("❌ DESCUADRE: stock final {$stockFinal} != inicial {$stockOriginal} (diff " . round($stockFinal - $stockOriginal, 4) . ")");
            }
        } catch (\Throwable $e) {
            $this->status('✖ FALLÓ: ' . $e->getMessage());
        } finally {
            $this->limpiar($sc, $localId, $centralId, $prod->id ?? null, $stockOriginal ?? null);
        }

        return self::SUCCESS;
    }

    // ───────────────────────── concurrencia (race) ─────────────────────────

    /** Worker interno: hace UN settransferenciaDici create y sale. Imprime CENTRALID:x:LOCAL:y */
    private function runFire(): int
    {
        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $prodId = (int) $this->option('producto');
        $cant = max(1, (int) $this->option('cantidad'));
        $sc = new sendCentral();
        $r = $this->asArray($sc->settransferenciaDici($this->reqTransfer(false, null, $prodId, $cant)));
        if (!empty($r['estado'])) {
            $this->output->writeln('CENTRALID:' . ($r['transferencia']['id_transferencia_central'] ?? 0) . ':LOCAL:' . ($r['transferencia']['id'] ?? 0));
        } else {
            $this->output->writeln('ERR:' . ($r['msj'] ?? 'sin respuesta'));
        }
        return self::SUCCESS;
    }

    /** Dispara N envíos del mismo producto en paralelo (procesos OS) y valida que no haya lost updates. */
    private function runRace(): int
    {
        $n = max(2, (int) $this->option('n'));
        $cant = max(1, (int) $this->option('cantidad'));

        $prod = $this->elegirProducto($n * $cant);
        if (!$prod) {
            $this->error('Necesito un producto sembrado con stock >= ' . ($n * $cant) . '. Usá --producto=ID con stock suficiente.');
            return self::FAILURE;
        }
        $prodId = (int) $prod->id;
        $S = $this->stock($prodId);

        $this->warn("⚠️  RACE: {$n} envíos concurrentes de #{$prodId} {$prod->descripcion} ×{$cant}. Muta y limpia al final.");
        if (!$this->option('force') && !$this->confirm('¿Continuar?', false)) return self::SUCCESS;
        $this->info("Stock inicial: {$S}  |  esperado tras {$n}×{$cant} = " . ($S - $n * $cant));

        // lanzar procesos en paralelo
        $procs = [];
        for ($i = 0; $i < $n; $i++) {
            $p = new \Symfony\Component\Process\Process(
                ['php', 'artisan', 'prueba:transferencia-ciclo', '--fire=create-send', '--producto=' . $prodId, '--cantidad=' . $cant],
                base_path()
            );
            $p->setTimeout(180);
            $p->start();
            $procs[] = $p;
        }
        foreach ($procs as $p) { $p->wait(); }

        $centralIds = [];
        $errores = 0;
        foreach ($procs as $p) {
            if (preg_match('/CENTRALID:(\d+):LOCAL:(\d+)/', $p->getOutput(), $m) && (int) $m[1] > 0) {
                $centralIds[] = (int) $m[1];
            } else {
                $errores++;
            }
        }

        $real = $this->stock($prodId);
        $creadas = count($centralIds);
        $esperadoSegunCreadas = $S - $creadas * $cant;

        $this->newLine();
        $this->table(['Métrica', 'Valor'], [
            ['Concurrencia (N)', $n],
            ['Transferencias creadas OK', $creadas],
            ['Procesos con error/rechazo', $errores],
            ['Stock inicial', $S],
            ['Descuento esperado (creadas × ' . $cant . ')', $creadas * $cant],
            ['Descuento real', $S - $real],
            ['Stock real', $real],
        ]);

        if (abs($real - $esperadoSegunCreadas) < 0.0001) {
            $this->info("✅ INTEGRIDAD OK: el descuento real (" . ($S - $real) . ") == creadas {$creadas} × {$cant}. Sin lost updates.");
        } else {
            $this->error("❌ RACE CONDITION detectada: se crearon {$creadas} transferencias (debían descontar " . ($creadas * $cant) . ") pero el stock solo bajó " . ($S - $real) . ". Hay LOST UPDATES — `descontarInventario` hace read-modify-write sin lock.");
        }

        $this->limpiarRace($centralIds, $prodId, $S);
        return self::SUCCESS;
    }

    // ───────────────────────── caída de red ─────────────────────────

    /** Valida que si la red a central se cae AL ENVIAR, no se descuente local ni quede inconsistencia. */
    private function runFallaRed(): int
    {
        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $prod = $this->elegirProducto(2);
        if (!$prod) {
            $this->error('Necesito un producto sembrado con stock. Usá --producto=ID.');
            return self::FAILURE;
        }
        $prodId = (int) $prod->id;
        $S = $this->stock($prodId);
        $transfAntes = transferencias_inventario::count();
        $movAntes = $this->contarMovs($prodId);

        $this->info('=== CASO A: la conexión a central se CAE al enviar ===');
        $this->info("Producto #{$prodId} {$prod->descripcion}, stock {$S}. Apunto central a un puerto muerto...");

        // sendCentral con central caído: nadie escucha en ese puerto → connection refused.
        $scDead = new class extends sendCentral {
            public function path()
            {
                return 'http://127.0.0.1:59999';
            }
        };
        $r = $this->asArray($scDead->settransferenciaDici($this->reqTransfer(false, null, $prodId, 2)));

        $stockDespues = $this->stock($prodId);
        $transfDespues = transferencias_inventario::count();
        $movDespues = $this->contarMovs($prodId);

        $okFallo = empty($r['estado']);
        $okStock = abs($stockDespues - $S) < 0.0001;
        $okTransf = $transfDespues === $transfAntes;
        $okMov = $movDespues === $movAntes;

        $this->newLine();
        $this->table(['Chequeo', 'Esperado', 'Real', 'OK'], [
            ['settransferenciaDici falló', 'estado=false', $okFallo ? 'estado=false' : 'estado=true', $okFallo ? '✓' : '✗'],
            ['stock sin cambios', $S, $stockDespues, $okStock ? '✓' : '✗'],
            ['no se creó transferencia local', $transfAntes, $transfDespues, $okTransf ? '✓' : '✗'],
            ['no se creó movimiento', $movAntes, $movDespues, $okMov ? '✓' : '✗'],
        ]);
        $this->line('  msj: ' . ($r['msj'] ?? ''));

        if ($okFallo && $okStock && $okTransf && $okMov) {
            $this->info('✅ CASO A OK: la red se cayó, NO se descontó en sucursal y central no recibió nada. Abortó atómico.');
        } else {
            $this->error('❌ CASO A FALLÓ: hubo cambios locales pese a la caída de red → riesgo de descuadre.');
        }

        $this->newLine();
        $this->warn('=== CASO B: la respuesta se pierde DESPUÉS de que central commiteó ===');
        $this->line('Si la red se corta tras que central ya creó el espejo (timeout leyendo la respuesta),');
        $this->line('settransferenciaDici hace rollback local → ESPEJO HUÉRFANO en central (central lo tiene,');
        $this->line('la sucursal no descontó). El destino podría extraerlo y duplicar inventario.');
        $this->line('Hoy `pedidos:sanear-exportados` NO limpia espejos de transferencias DICI → mitigación');
        $this->line('pendiente: un saneador de transferencias (cruza espejos de central vs transferencias_inventario).');

        return self::SUCCESS;
    }

    private function limpiarRace(array $centralIds, int $prodId, float $stockOriginal): void
    {
        $this->line('Limpiando ' . count($centralIds) . ' transferencia(s) de prueba...');
        $sc = new sendCentral();
        foreach ($centralIds as $cid) {
            try {
                $local = transferencias_inventario::where('id_transferencia_central', $cid)->first();
                $sc->deletePedidosEspejoCentral([$local ? $local->id : $cid]);
                if ($local) {
                    transferencias_inventario_items::where('id_transferencia', $local->id)->delete();
                    $local->delete();
                }
                DB::statement('DELETE FROM arabitocentral.items_pedidos WHERE id_pedido = ?', [$cid]);
                DB::statement('DELETE FROM arabitocentral.pedidos WHERE id = ?', [$cid]);
            } catch (\Throwable $e) {
                // best-effort
            }
        }
        inventario::whereKey($prodId)->update(['cantidad' => $stockOriginal]);
        $this->line("  ✓ limpieza ok (stock restaurado a {$stockOriginal})");
    }

    // ───────────────────────── helpers de flujo ─────────────────────────

    private function reqTransfer(bool $upd, $centralId, int $prodId, $cantidad): Request
    {
        $data = [
            'id_destino' => $this->idDestino,
            'observaciones' => 'prueba ciclo',
            'actualizando' => $upd,
            'items' => [['id_producto_insucursal' => $prodId, 'cantidad' => $cantidad]],
        ];
        if ($upd) $data['id'] = $centralId;
        return Request::create('/x', 'POST', $data);
    }

    /** Recepción: drive 1→3, fuerza estado 4 en central por DB, importa (estado 4→2). */
    private function recibir(sendCentral $sc, $centralId): void
    {
        $pedido = $this->fetchPedidoCentral($sc, $centralId);
        $this->assert($pedido !== null, "No pude traer el pedido #{$centralId} de central para recibir");
        foreach ($pedido['items'] as &$it) {
            $it['aprobado'] = true; // aprobamos todos los ítems
            // reqMipedidos no sugiere vínculo: lo resolvemos por código de barras al producto local.
            if (empty($it['vinculo_real'])) {
                $cb = $it['producto']['codigo_barras'] ?? null;
                $local = $cb ? inventario::where('codigo_barras', $cb)->first() : null;
                if ($local) {
                    $it['vinculo_real'] = $local->id;
                    $it['barras_real'] = $it['barras_real'] ?? $local->codigo_barras;
                }
            }
        }
        unset($it);

        $inv = new InventarioController();

        // Paso A: 1 → 3 (guarda vínculos). Devuelve estado=false "en revisión".
        $inv->checkPedidosCentral(Request::create('/x', 'POST', ['pedido' => $pedido]));

        // checkPedidosCentral deja una transacción abierta en su return temprano (estado 3);
        // la cerramos para que el UPDATE de abajo auto-commitee y central (vía HTTP) lo vea.
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        // Paso B: forzar 3 → 4 en central (aprobación del checkeador, atajo de prueba).
        DB::statement('UPDATE arabitocentral.pedidos SET estado = 4 WHERE id = ?', [$centralId]);

        // Paso C: estado 4 → importar (suma inventario + factura + central 4→2).
        $r = $inv->checkPedidosCentral(Request::create('/x', 'POST', ['pedido' => $pedido]));
        $r = $this->asArray($r);
        $this->assert(!empty($r['estado']), 'Recepción (checkPedidosCentral): ' . ($r['msj'] ?? json_encode($r)));
    }

    /** Trae el pedido espejo desde central (vía reqMipedidos) con sus ítems y vínculos sugeridos. */
    private function fetchPedidoCentral(sendCentral $sc, $centralId): ?array
    {
        $res = $this->asArray($sc->reqMipedidos(Request::create('/x', 'POST', [
            'q' => (string) $centralId, 'limit' => 50, 'estatus_string' => '', 'id_destino' => '',
        ])));
        foreach (($res['data'] ?? []) as $p) {
            if ((int) ($p['id'] ?? 0) === (int) $centralId) return $p;
        }
        return null;
    }

    private function buscarEspejo(sendCentral $sc, $localId): ?array
    {
        $res = $this->asArray($sc->getPedidosEspejoCentral());
        foreach (($res['espejos'] ?? []) as $e) {
            if ((int) ($e['idinsucursal'] ?? -1) === (int) $localId) return $e;
        }
        return null;
    }

    private function limpiar(sendCentral $sc, $localId, $centralId, $prodId, $stockOriginal): void
    {
        $this->newLine();
        $this->line('Limpiando datos de prueba...');
        try {
            if ($centralId) {
                items_factura::whereIn('id_factura', factura::where('id_pedido_central', $centralId)->pluck('id'))->delete();
                factura::where('id_pedido_central', $centralId)->delete();
                DB::statement('DELETE FROM arabitocentral.items_pedidos WHERE id_pedido = ?', [$centralId]);
                DB::statement('DELETE FROM arabitocentral.pedidos WHERE id = ?', [$centralId]);
            }
            if ($localId) {
                transferencias_inventario_items::where('id_transferencia', $localId)->delete();
                transferencias_inventario::where('id', $localId)->delete();
            }
            // restaurar stock por si quedó descuadrado
            if ($prodId && $stockOriginal !== null) {
                inventario::whereKey($prodId)->update(['cantidad' => $stockOriginal]);
            }
            $this->line('  ✓ limpieza ok' . ($prodId ? " (stock restaurado a {$stockOriginal})" : ''));
        } catch (\Throwable $e) {
            $this->warn('  ⚠ limpieza parcial: ' . $e->getMessage());
        }
    }

    // ───────────────────────── panel / utilidades ─────────────────────────

    private function registrar(string $paso, int $prodId, float $antes, $delta, float $despues): void
    {
        $this->panel[] = [
            $paso,
            number_format($antes, 2),
            $delta === null ? '—' : ($delta > 0 ? '+' . number_format($delta, 2) : number_format($delta, 2)),
            number_format($despues, 2),
        ];
        if ($this->option('watch')) {
            $this->repaint();
            usleep(1200000); // ~1.2s para alcanzar a ver cada acción como un video
        } else {
            $this->panelImprimir();
        }
    }

    private function panelImprimir(): void
    {
        $this->newLine();
        $this->line('┌─ PANEL INVENTARIO ─────────────────────────────────');
        $this->table(['Paso', 'Antes', 'Δ', 'Después'], $this->panel);
    }

    /** Mensaje de estado: en --watch entra al dashboard; si no, se imprime normal. */
    private function status(string $msg): void
    {
        $this->statusLog[] = $msg;
        if ($this->option('watch')) {
            $this->repaint();
            usleep(700000);
        } else {
            $this->line($msg);
        }
    }

    /** Repinta TODO el dashboard limpiando la pantalla (efecto video). Solo en --watch. */
    private function repaint(): void
    {
        // limpia scrollback + pantalla y manda el cursor arriba a la izquierda
        $this->output->write("\033[3J\033[2J\033[H");
        if ($this->cabecera !== '') {
            $this->line($this->cabecera);
            $this->newLine();
        }
        $this->table(['Paso', 'Antes', 'Δ', 'Después'], $this->panel);
        foreach ($this->statusLog as $s) {
            $this->line($s);
        }
    }

    private function elegirProducto($minStock): ?inventario
    {
        if ($this->option('producto')) {
            return inventario::find((int) $this->option('producto'));
        }
        return inventario::query()
            ->whereNotNull('codigo_barras')->where('codigo_barras', '<>', '')
            ->where('cantidad', '>=', $minStock)
            ->whereColumn('precio_base', '<', 'precio')->where('precio', '>', 0)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('arabitocentral.inventario_sucursals as s')
                    ->whereColumn('s.id', 'inventarios.id')->where('s.id_sucursal', 13);
            })
            ->inRandomOrder()->first();
    }

    private function stock(int $prodId): float
    {
        return (float) inventario::whereKey($prodId)->value('cantidad');
    }

    private function contarMovs(int $prodId): int
    {
        return (int) movimientosInventariounitario::where('id_producto', $prodId)->count();
    }

    private function assert($cond, string $msg): void
    {
        if (!$cond) throw new \RuntimeException($msg);
    }

    private function asArray($res): array
    {
        if (is_array($res)) return $res;
        if ($res instanceof \Illuminate\Http\JsonResponse) {
            $d = $res->getData(true);
            return is_array($d) ? $d : ['estado' => false, 'msj' => (string) $res->getContent()];
        }
        if (is_string($res)) {
            $d = json_decode($res, true);
            return is_array($d) ? $d : ['estado' => false, 'msj' => $res];
        }
        return ['estado' => false, 'msj' => 'respuesta no interpretable'];
    }
}
