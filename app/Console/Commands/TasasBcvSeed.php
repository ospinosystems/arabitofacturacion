<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\TasasBcvItemsPedidosSeeder;

class TasasBcvSeed extends Command
{
    protected $signature = 'tasas-bcv:seed
                            {path? : Ruta al CSV de tasas BCV (opcional; por defecto database/data/tasas_bcv_diarias.csv, formato fecha,tasa_bcv; también acepta el formato por cuatrimestres)}
                            {--meses= : Solo actualizar ítems de estos meses, separados por coma (YYYY-MM,YYYY-MM,…)}';

    protected $description = 'Actualiza tasa y monto_bs en items_pedidos según la tasa BCV del día (created_at)';

    public function handle(): int
    {
        $path = $this->argument('path');
        $seeder = new TasasBcvItemsPedidosSeeder($path);
        if ($this->option('meses')) {
            $seeder->meses = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('meses')))));
        }
        $seeder->setCommand($this);
        $seeder->run();

        return Command::SUCCESS;
    }
}
