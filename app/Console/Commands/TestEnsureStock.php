<?php

namespace App\Console\Commands;

use App\Models\inventario;
use Illuminate\Console\Command;

/**
 * Helper de pruebas: asegura el stock de un producto para que los E2E sean repetibles
 * (cada demo va descontando inventario). Solo para entornos de prueba.
 */
class TestEnsureStock extends Command
{
    protected $signature = 'test:ensure-stock {id} {cantidad=100}';
    protected $description = 'Fija el stock de un producto (para demos/E2E repetibles).';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $cant = (float) $this->argument('cantidad');
        $n = inventario::whereKey($id)->update(['cantidad' => $cant]);
        $this->info($n ? "stock #{$id} = {$cant}" : "producto #{$id} no existe");
        return self::SUCCESS;
    }
}
