<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;

class CuadrePedidosReset extends Command
{
    protected $signature = 'cuadre:pedidos-reset {--fecha_desde=} {--fecha_hasta=} {--dry-run}';
    protected $description = 'Resetea cuadre diario para re-ejecutar cuadre:pedidos-diario.';

    public function handle(): int
    {
        $fechaDesde = $this->option('fecha_desde') ? trim($this->option('fecha_desde')) : null;
        $fechaHasta = $this->option('fecha_hasta') ? trim($this->option('fecha_hasta')) : null;
        $dryRun = $this->option('dry-run');

        $query = pedidos::where('valido', true);
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $query->whereRaw('DATE(COALESCE(fecha_factura, created_at)) >= ?', [$fechaDesde]);
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $query->whereRaw('DATE(COALESCE(fecha_factura, created_at)) <= ?', [$fechaHasta]);
        }
        $pedidosReset = $query->get();
        $total = $pedidosReset->count();

        if ($total === 0) {
            $this->info('No hay pedidos validos que resetear.');
            return Command::SUCCESS;
        }

        $this->info('Se resetearan ' . $total . ' pedidos.');
        if ($dryRun) {
            $this->warn('Dry-run: no se modificara la BD.');
            return Command::SUCCESS;
        }

        if (!$this->confirm('Continuar?', true)) {
            return Command::SUCCESS;
        }

        $ids = $pedidosReset->pluck('id')->all();

        DB::transaction(function () use ($ids) {
            $itemsAjuste = items_pedidos::whereIn('id_pedido', $ids)
                ->whereNull('id_producto')->where('cantidad', 1)->where('monto', 0)->get();

            foreach ($itemsAjuste as $item) {
                $item->delete();
            }

            foreach ($itemsAjuste->pluck('id_pedido')->unique() as $idPedido) {
                $suma = (string) items_pedidos::where('id_pedido', $idPedido)->sum(DB::raw('COALESCE(monto_bs, 0)'));
                $pago = pago_pedidos::where('id_pedido', $idPedido)->first();
                if ($pago) {
                    $pago->monto = (float) $suma;
                    if (Schema::hasColumn('pago_pedidos', 'monto_bs')) {
                        $pago->monto_bs = (float) $suma;
                    }
                    $pago->save();
                }
            }

            $data = ['numero_factura' => null, 'valido' => false];
            if (Schema::hasColumn('pedidos', 'maquina_fiscal')) {
                $data['maquina_fiscal'] = null;
            }
            pedidos::whereIn('id', $ids)->update($data);
        });

        $this->info('Listo. ' . $total . ' pedidos reseteados.');
        return Command::SUCCESS;
    }
}
