<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Observers\SyncObserver;

// Modelos sincronizables
use App\Models\inventario;
use App\Models\pedidos;
use App\Models\pago_pedidos;
use App\Models\items_pedidos;
use App\Models\cierres;
use App\Models\cierres_puntos;
use App\Models\pagos_referencias;
use App\Models\cajas;
use App\Models\movimientos;

class SyncObserverServiceProvider extends ServiceProvider
{
    /**
     * Lista de modelos que se sincronizan con Central
     */
    protected $modelosSincronizables = [
        inventario::class,
        pedidos::class,
        pago_pedidos::class,
        items_pedidos::class,
        cierres::class,
        cierres_puntos::class,
        pagos_referencias::class,
        cajas::class,
        movimientos::class,
    ];
    
    /**
     * Register services.
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Registrar el SyncObserver en todos los modelos sincronizables
        $observer = new SyncObserver();
        
        foreach ($this->modelosSincronizables as $modelo) {
            if (class_exists($modelo)) {
                $modelo::observe($observer);
            }
        }
    }
}
