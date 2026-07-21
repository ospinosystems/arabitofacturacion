<?php

namespace App\Console\Commands;

use App\Http\Controllers\InventarioController;
use App\Http\Controllers\sendCentral;
use App\Models\inventario;
use App\Models\movimientosInventariounitario;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RECEPCIÓN de prueba: recibe las transferencias que el usuario ya marcó como
 * "revisado"/4 en central, validando en tiempo real que el inventario SUME exacto
 * lo recibido y que quede su registro de movimiento.
 *
 * Empareja con `prueba:despacho`: despacho crea espejos en estado 1, el usuario los
 * pasa a 4 a mano, y este comando los recibe (estado 4 → 2 en central + factura +
 * suma de inventario local).
 *
 * Con --concurrente dispara las recepciones en paralelo (procesos OS) para probar
 * que la suma es robusta bajo concurrencia (sin lost updates cuando un mismo
 * producto viene en varios pedidos a la vez).
 *
 *   php artisan prueba:recepcion --force
 *   php artisan prueba:recepcion --concurrente --force
 *
 * (Tip: corré `php artisan transferencias:watch` en otra terminal para verlo en vivo.)
 */
class PruebaRecepcion extends Command
{
    protected $signature = 'prueba:recepcion
                            {--concurrente : recibir los pedidos en paralelo (robustez)}
                            {--force : sin confirmación}
                            {--fire= : (interno) worker: recibe UN pedido}
                            {--id= : (interno) id central del pedido para el worker}';

    protected $description = 'Recibe los espejos en estado 4 (revisado) y valida las sumas de inventario (con opción concurrente).';

    public function handle(): int
    {
        if ($this->option('fire')) {
            return $this->runFire();
        }

        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $concurrente = (bool) $this->option('concurrente');

        // ── pedidos espejo de NUESTRAS transferencias que el usuario marcó revisado (4) ──
        $transfTable = (new \App\Models\transferencias_inventario)->getTable();
        $centralIds = DB::table($transfTable)
            ->whereNotNull('id_transferencia_central')
            ->pluck('id_transferencia_central')->unique()->values()->all();

        if (empty($centralIds)) {
            $this->error('No hay transferencias locales con espejo en central. Corré primero `prueba:despacho`.');
            return self::FAILURE;
        }

        $revisados = DB::table('arabitocentral.pedidos')
            ->whereIn('id', $centralIds)->where('estado', 4)
            ->orderBy('id')->pluck('id')->all();

        if (empty($revisados)) {
            $this->error('Ninguno de tus espejos está en estado 4 (revisado).');
            $this->line('  Marcá su estado a 4 en central, p.ej.:');
            $this->line('  UPDATE arabitocentral.pedidos SET estado=4 WHERE id IN (' . implode(',', $centralIds) . ');');
            return self::FAILURE;
        }

        $this->warn('⚠️  Recibe transferencias REALES (suma inventario y cierra el espejo en central → estado 2).');
        $this->info(($concurrente ? 'CONCURRENTE' : 'SECUENCIAL') . ': ' . count($revisados) . ' pedido(s) en estado 4 → ' . implode(', ', $revisados));
        if (!$this->option('force') && !$this->confirm('¿Continuar?', false)) {
            return self::SUCCESS;
        }

        $sc = new sendCentral();

        // ── pre-cargar cada pedido y calcular lo esperado a SUMAR por producto ──
        $pedidos = [];        // centralId => pedido[] (array)
        $esperado = [];       // producto => cantidad total esperada a sumar
        foreach ($revisados as $cid) {
            $ped = $this->fetchPedidoCentral($sc, $cid);
            if (!$ped) {
                $this->line("  ⚠ no pude traer el pedido #{$cid} de central; lo salto.");
                continue;
            }
            $pedidos[$cid] = $ped;
            foreach ($ped['items'] as $it) {
                $pid = (int) $it['id_producto'];
                $esperado[$pid] = ($esperado[$pid] ?? 0) + (float) $it['cantidad'];
            }
        }
        if (empty($pedidos)) {
            $this->error('No pude traer ningún pedido de central.');
            return self::FAILURE;
        }

        // ── snapshot ANTES (stock + último movimiento) ──
        $antes = [];
        foreach (array_keys($esperado) as $pid) {
            $antes[$pid] = $this->stock($pid);
        }
        $movTable = (new movimientosInventariounitario)->getTable();
        $lastMovId = (int) DB::table($movTable)->max('id');

        $this->newLine();
        $recibidos = []; // central ids OK
        $espejoOk = [];  // central ids efectivamente recibidos (para esperado real)

        if ($concurrente) {
            $procs = [];
            foreach (array_keys($pedidos) as $cid) {
                $p = new \Symfony\Component\Process\Process(
                    ['php', 'artisan', 'prueba:recepcion', '--fire=1', '--id=' . $cid],
                    base_path()
                );
                $p->setTimeout(300);
                $p->start();
                $procs[$cid] = $p;
            }
            foreach ($procs as $cid => $p) {
                $p->wait();
                if (preg_match('/OK:central:(\d+)/', $p->getOutput(), $m)) {
                    $recibidos[] = $cid;
                    $espejoOk[] = $cid;
                    $this->line("  ✓ pedido {$cid} recibido");
                } else {
                    $this->line("  ✗ pedido {$cid} falló: " . trim(preg_replace('/\s+/', ' ', $p->getOutput())));
                }
            }
        } else {
            $inv = new InventarioController();
            foreach ($pedidos as $cid => $ped) {
                [$ok, $msj] = $this->recibirUno($inv, $ped);
                if ($ok) {
                    $recibidos[] = $cid;
                    $espejoOk[] = $cid;
                    $this->line("  ✓ pedido {$cid} recibido");
                } else {
                    $this->line("  ✗ pedido {$cid} falló: {$msj}");
                }
            }
        }

        // ── esperado SOLO de los pedidos efectivamente recibidos ──
        $esperadoOk = [];
        foreach ($espejoOk as $cid) {
            foreach ($pedidos[$cid]['items'] as $it) {
                $pid = (int) $it['id_producto'];
                $esperadoOk[$pid] = ($esperadoOk[$pid] ?? 0) + (float) $it['cantidad'];
            }
        }

        // ── validar sumas ──
        $this->newLine();
        $filas = [];
        $okTotal = true;
        foreach ($antes as $pid => $stockAntes) {
            $stockDespues = $this->stock($pid);
            $real = $stockDespues - $stockAntes;
            $exp = $esperadoOk[$pid] ?? 0;
            $ok = abs($real - $exp) < 0.0001;
            if (!$ok) $okTotal = false;
            $filas[] = ["#{$pid}", $stockAntes, $stockDespues, '+' . $real, '+' . $exp, $ok ? '✓' : '✗ LOST UPDATE'];
        }
        $this->table(['Producto', 'Antes', 'Después', 'Sumado real', 'Esperado', 'OK'], $filas);

        // ── validar que quedaron movimientos de la recepción ──
        $movsNuevos = DB::table($movTable)->where('id', '>', $lastMovId)
            ->whereIn('id_producto', array_keys($esperado))->where('cantidad', '>', 0)->count();
        $this->line("  Movimientos de inventario (suma) registrados: {$movsNuevos}");

        if ($okTotal) {
            $this->info('✅ SUMAS OK: el inventario sumó EXACTO lo recibido. Sin lost updates.');
        } else {
            $this->error('❌ DESCUADRE: la suma real no coincide con lo recibido → hay lost updates en la recepción.');
        }

        return $okTotal ? self::SUCCESS : self::FAILURE;
    }

    /** Worker: recibe UN pedido (--id) y reporta OK:central:X o ERR:... */
    private function runFire(): int
    {
        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $cid = (int) $this->option('id');
        $ped = $this->fetchPedidoCentral(new sendCentral(), $cid);
        if (!$ped) {
            $this->output->writeln('ERR:no pude traer pedido ' . $cid);
            return self::SUCCESS;
        }
        [$ok, $msj] = $this->recibirUno(new InventarioController(), $ped);
        $this->output->writeln($ok ? "OK:central:{$cid}" : ('ERR:' . $msj));
        return self::SUCCESS;
    }

    /**
     * Recibe un pedido espejo que YA está en estado 4: resuelve vínculos por código de
     * barras, aprueba todos los ítems e importa (suma inventario + factura + central 4→2).
     * @return array{0:bool,1:string} [ok, msj]
     */
    private function recibirUno(InventarioController $inv, array $ped): array
    {
        foreach ($ped['items'] as &$it) {
            $it['aprobado'] = true;
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

        try {
            $r = $this->asArray($inv->checkPedidosCentral(Request::create('/x', 'POST', ['pedido' => $ped])));
        } catch (\Throwable $e) {
            while (DB::transactionLevel() > 0) DB::rollBack();
            return [false, $e->getMessage()];
        }
        // por si checkPedidosCentral dejó una transacción colgada
        while (DB::transactionLevel() > 0) DB::rollBack();

        return [!empty($r['estado']), $r['msj'] ?? json_encode($r)];
    }

    /** Trae el pedido espejo desde central (vía reqMipedidos) con sus ítems. */
    private function fetchPedidoCentral(sendCentral $sc, int $centralId): ?array
    {
        $res = $this->asArray($sc->reqMipedidos(Request::create('/x', 'POST', [
            'q' => (string) $centralId, 'limit' => 50, 'estatus_string' => '', 'id_destino' => '',
        ])));
        foreach (($res['data'] ?? []) as $p) {
            if ((int) ($p['id'] ?? 0) === $centralId) return $p;
        }
        return null;
    }

    private function stock($pid): float
    {
        return (float) inventario::whereKey($pid)->value('cantidad');
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
