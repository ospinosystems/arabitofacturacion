<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * MUDANZA COMPLETA de esta sucursal a TitanioPOS, en un solo comando.
 *
 * Inventario primero y pedidos después, y el orden NO es cosmético: cada línea
 * de un pedido se engancha a un lote de inventario de la tienda destino. Si el
 * inventario no está cargado, TitanioPOS tiene que inventar lotes de respaldo
 * en cero para poder registrar la venta, y el histórico queda colgando de
 * fichas vacías en vez de del inventario real.
 *
 * Si el inventario no termina verificado al 100%, los pedidos NO se envían: es
 * preferible una mudanza detenida a medias —que se reanuda— que un histórico
 * enganchado a lotes que no existen.
 *
 *   php artisan sucursal:mudanza --dry-run     cuenta las dos cargas
 *   php artisan sucursal:mudanza               ejecuta y verifica las dos
 *   php artisan sucursal:mudanza --solo=pedidos   reanuda solo la segunda parte
 */
class MudanzaSucursalCommand extends Command
{
    protected $signature = 'sucursal:mudanza
        {--desde=2026-01-01 : fecha de corte de los pedidos}
        {--solo= : "inventario" o "pedidos" para correr una sola parte}
        {--dry-run : solo cuenta lo que enviaría, no transmite}
        {--allow-partial : deja que TitanioPOS cargue lo que pueda}';

    protected $description = 'Mudanza completa de la sucursal a TitanioPOS: inventario y luego pedidos.';

    public function handle(): int
    {
        @set_time_limit(0);

        $solo = $this->option('solo');
        $comunes = [
            '--dry-run'       => (bool) $this->option('dry-run'),
            '--allow-partial' => (bool) $this->option('allow-partial'),
        ];

        if ($solo !== 'pedidos') {
            $this->info('════ 1/2 · INVENTARIO ════');

            if ($this->call('inventario:migrar', $comunes) !== self::SUCCESS) {
                $this->error('El inventario no quedó verificado. Los pedidos NO se enviaron:');
                $this->error('cada línea de un pedido se engancha a un lote, y sin inventario');
                $this->error('el histórico quedaría colgando de fichas vacías.');
                $this->line('');
                $this->line('Resuelve el inventario y luego:  php artisan sucursal:mudanza --solo=pedidos');

                return self::FAILURE;
            }

            $this->newLine();
        }

        if ($solo === 'inventario') {
            return self::SUCCESS;
        }

        $this->info('════ 2/2 · PEDIDOS ════');

        return $this->call('pedidos:migrar', array_merge($comunes, [
            '--desde' => (string) $this->option('desde'),
        ]));
    }
}
