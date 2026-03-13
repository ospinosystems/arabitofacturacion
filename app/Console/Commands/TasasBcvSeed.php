<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\TasasBcvItemsPedidosSeeder;

class TasasBcvSeed extends Command
{
    protected $signature = 'tasas-bcv:seed
                            {path? : Ruta al CSV de tasas BCV (opcional; por defecto database/data/Tasas_BCV_2023_2024_2025.csv)}';

    protected $description = 'Actualiza tasa en items_pedidos según tasas BCV del CSV por fecha created_at';

    public function handle(): int
    {
        $path = $this->argument('path');
        $seeder = new TasasBcvItemsPedidosSeeder($path);
        $seeder->setCommand($this);
        $seeder->run();

        return Command::SUCCESS;
    }
}
