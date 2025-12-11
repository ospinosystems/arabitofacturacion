<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\enviarCierre;

class PostSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $correo;
    protected $emails;

    /**
     * Create a new job instance.
     *
     * @param array|null $correo - Datos para el correo de cierre
     * @param array $emails - Lista de destinatarios
     */
    public function __construct($correo = null, $emails = [])
    {
        $this->correo = $correo;
        $this->emails = $emails;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info('=== INICIO POST-SYNC JOB (Background) ===');
        
        // Si la cola es 'sync', saltar operaciones pesadas para no bloquear
        $queueConnection = config('queue.default');
        $isSync = $queueConnection === 'sync';
        
        if ($isSync) {
            \Log::info('    Cola sync detectada - saltando operaciones pesadas');
        }
        
        try {
            // Enviar correo y backup solo si hay datos de correo Y no es cola sync
            if ($this->correo && is_array($this->correo) && count($this->correo) >= 4) {
                if (!$isSync) {
                    \Log::info('    Enviando correo de cierre...');
                    Mail::to($this->emails)->send(new enviarCierre(
                        $this->correo[0], 
                        $this->correo[1], 
                        $this->correo[2], 
                        $this->correo[3]
                    ));
                    
                    \Log::info('    Ejecutando backup de base de datos...');
                    \Artisan::call('database:backup');
                    \Artisan::call('backup:run');
                } else {
                    \Log::info('    Saltando correo y backup (cola sync)');
                }
            }
            
            // Actualizar IVA en inventarios
            \Log::info('    Actualizando IVA en inventarios...');
            \DB::statement("UPDATE `inventarios` SET iva=1");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MACHETE%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'PEINILLA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MOTOBOMBA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'ELECTROBOMBA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'DESMALEZADORA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MOTOSIERRA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'CUCHILLA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'FUMIGADORA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MANGUERA%'");
            
            \Log::info('=== POST-SYNC JOB COMPLETADO ===');
            
        } catch (\Exception $e) {
            \Log::error('Error en PostSyncJob: ' . $e->getMessage());
        }
    }
}
