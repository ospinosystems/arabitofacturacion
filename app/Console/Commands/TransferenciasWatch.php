<?php

namespace App\Console\Commands;

use App\Models\factura;
use App\Models\inventario;
use App\Models\movimientosInventariounitario;
use App\Models\transferencias_inventario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Observador EN TIEMPO REAL: lo dejás corriendo en una terminal y usás la app normal
 * (crear transferencia, agregar productos, editar, desexportar, recibir). Cada acción
 * aparece al instante: el descuento/suma de inventario, la transferencia creada, lo que
 * recibió central, la recepción. Es para "ver el accionar de los botones" mientras operás vos.
 *
 *   php artisan transferencias:watch                 # observa todo
 *   php artisan transferencias:watch --producto=173277
 *
 * Ctrl+C para salir.
 */
class TransferenciasWatch extends Command
{
    protected $signature = 'transferencias:watch
                            {--producto= : observar solo este id de producto}
                            {--intervalo=400 : ms entre refrescos}
                            {--ticks=0 : 0 = infinito; >0 corre N vueltas y sale (para probar)}';

    protected $description = 'Observa en tiempo real cantidades, transferencias, espejos en central y recepciones mientras operás la app.';

    public function handle(): int
    {
        $prod = $this->option('producto') ? (int) $this->option('producto') : null;
        $sleep = max(100, (int) $this->option('intervalo')) * 1000;
        $ticks = (int) $this->option('ticks');

        $movTable = (new movimientosInventariounitario)->getTable();
        $transfTable = (new transferencias_inventario)->getTable();
        $factTable = (new factura)->getTable();

        $this->info('👀  Observando en tiempo real. Operá la app normalmente (crear / editar / desexportar / recibir).');
        $this->line('    🔻 descuento   🔺 suma   🆕 transferencia local   📡 central recibió   📥 recepción');
        if ($prod) {
            $this->line("    (filtrando producto #{$prod})");
        }
        $this->newLine();

        // baseline: solo eventos NUEVOS desde que arranca el watcher
        $lastMov    = (int) DB::table($movTable)->max('id');
        $lastTransf = (int) DB::table($transfTable)->max('id');
        $lastFact   = (int) DB::table($factTable)->max('id');
        $lastEspejo = $this->maxCentral();

        $v = 0;
        while ($ticks === 0 || $v < $ticks) {
            $v++;

            // 1) Movimientos de inventario (las cantidades ±, lo más importante)
            $q = DB::table($movTable)->where('id', '>', $lastMov);
            if ($prod) {
                $q->where('id_producto', $prod);
            }
            foreach ($q->orderBy('id')->get() as $m) {
                $lastMov = max($lastMov, (int) $m->id);
                $delta  = (float) $m->cantidad;
                $after  = (float) $m->cantidadafter;
                $before = $after - $delta;
                $desc   = mb_strimwidth((string) inventario::whereKey($m->id_producto)->value('descripcion'), 0, 28, '…');
                $color  = $delta < 0 ? 'red' : 'green';
                $arrow  = $delta < 0 ? '🔻' : '🔺';
                $signo  = $delta >= 0 ? '+' : '';
                $this->line(
                    $this->hora($m->created_at) . " {$arrow} <fg={$color}>#{$m->id_producto} {$desc}</>  "
                    . number_format($before, 2) . ' → ' . number_format($after, 2)
                    . "  <fg={$color};options=bold>({$signo}" . number_format($delta, 2) . ')</>'
                    . "  <fg=blue>" . $m->origen . '</>'
                );
            }

            // 2) Transferencias locales creadas (la petición de envío)
            foreach (DB::table($transfTable)->where('id', '>', $lastTransf)->orderBy('id')->get() as $t) {
                $lastTransf = max($lastTransf, (int) $t->id);
                $this->line($this->hora($t->created_at) . " 🆕 <fg=cyan;options=bold>TRANSFERENCIA local #{$t->id}</>"
                    . "  destino:{$t->id_destino}  central:" . ($t->id_transferencia_central ?? '—') . "  estado:{$t->estado}");
            }

            // 3) Espejos en central (central recibió la petición)
            try {
                foreach (DB::table('arabitocentral.pedidos')->where('id', '>', $lastEspejo)->orderBy('id')->get() as $e) {
                    $lastEspejo = max($lastEspejo, (int) $e->id);
                    $this->line($this->hora($e->created_at ?? null) . " 📡 <fg=magenta;options=bold>CENTRAL recibió pedido #{$e->id}</>"
                        . "  idinsucursal:" . ($e->idinsucursal ?? '—') . "  destino:" . ($e->id_destino ?? '—') . "  estado:{$e->estado}");
                }
            } catch (\Throwable $ex) {
                // central no accesible desde esta conexión → seguimos sin esa parte
            }

            // 4) Facturas de recepción (la mercancía ingresó)
            foreach (DB::table($factTable)->where('id', '>', $lastFact)->whereNotNull('id_pedido_central')->orderBy('id')->get() as $f) {
                $lastFact = max($lastFact, (int) $f->id);
                $this->line($this->hora($f->created_at) . " 📥 <fg=yellow;options=bold>RECEPCIÓN factura #{$f->id}</>  ← pedido central {$f->id_pedido_central}");
            }

            if ($ticks === 0 || $v < $ticks) {
                usleep($sleep);
            }
        }

        return self::SUCCESS;
    }

    private function maxCentral(): int
    {
        try {
            return (int) (DB::table('arabitocentral.pedidos')->max('id') ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function hora($ts): string
    {
        $h = $ts ? substr((string) $ts, 11, 8) : date('H:i:s');
        return "<fg=gray>{$h}</>";
    }
}
