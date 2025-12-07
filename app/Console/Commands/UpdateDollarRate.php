<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Controllers\sendCentral;

class UpdateDollarRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dollar:update {--force : Forzar actualización sin verificar última actualización}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualizar el valor del dólar desde Arabito Central';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔄 Iniciando actualización del valor del dólar...');

        try {
            // Verificar si es necesario actualizar (máximo cada 30 minutos)
            if (!$this->option('force')) {
                $lastUpdate = DB::table('monedas')
                    ->where('tipo', 1) // Dólar
                    ->whereNotNull('fecha_ultima_actualizacion')
                    ->orderBy('fecha_ultima_actualizacion', 'desc')
                    ->first();

                if ($lastUpdate && Carbon::parse($lastUpdate->fecha_ultima_actualizacion)->diffInMinutes(now()) < 30) {
                    $this->warn('⚠️  La última actualización fue hace menos de 30 minutos. Use --force para forzar la actualización.');
                    return 0;
                }
            }

            // Consultar a Arabito Central
            $this->info('📡 Consultando Arabito Central...');
            
            $centralUrl = $this->getCentralUrl();
            $idSucursal = env('SUCURSAL_ID');
            
            $response = Http::timeout(15)->get("{$centralUrl}/api/tasas/hoy", [
                'fecha' => now()->toDateString(),
                'id_sucursal' => $idSucursal,
            ]);
            
            if (!$response->successful()) {
                $this->warn('⚠️  Central no disponible, intentando API directa como fallback...');
                return $this->updateFromDirectApi();
            }

            $data = $response->json();
            
            if (!$data['estado']) {
                throw new \Exception($data['mensaje'] ?? 'Error desconocido de Central');
            }

            $dollarValue = (float) $data['tasa_bcv'];
            $source = "Central - " . ($data['api_source'] ?? $data['origen'] ?? 'API');
            $esFeriado = $data['es_feriado'] ?? false;
            $esFinSemana = $data['es_fin_semana'] ?? false;
            $desdeCacheCentral = $data['desde_cache'] ?? false;

            $this->info("💱 Valor del dólar BCV: {$dollarValue} Bs");
            if ($esFeriado) $this->info("   📅 Día feriado");
            if ($esFinSemana) $this->info("   📅 Fin de semana");
            if ($desdeCacheCentral) $this->info("   💾 Desde cache de Central");

            // Actualizar en la base de datos
            $notas = "Desde Central. " . 
                     ($esFeriado ? "Día feriado. " : "") . 
                     ($esFinSemana ? "Fin de semana. " : "") .
                     ($desdeCacheCentral ? "Cache Central." : "API actualizada.");
                     
            $updated = DB::table('monedas')
                ->where('tipo', 1) // Dólar
                ->update([
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => $notas,
                    'estatus' => 'activo'
                ]);

            if ($updated) {
                $this->info('✅ Valor del dólar actualizado exitosamente desde Central');
                
                Log::info('Dollar rate updated from Central', [
                    'value' => $dollarValue,
                    'source' => $source,
                    'es_feriado' => $esFeriado,
                    'es_fin_semana' => $esFinSemana,
                    'desde_cache_central' => $desdeCacheCentral
                ]);
                
                return 0;
            } else {
                // Si no hay registros, crear uno nuevo
                DB::table('monedas')->insert([
                    'tipo' => 1, // Dólar
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => "Creado desde Central",
                    'estatus' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $this->info('✅ Registro de dólar creado exitosamente desde Central');
                return 0;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error al actualizar el valor del dólar: ' . $e->getMessage());
            
            Log::error('Dollar rate update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Intentar fallback directo
            $this->warn('🔄 Intentando fallback directo...');
            return $this->updateFromDirectApi();
        }
    }
    
    /**
     * Obtener URL de Arabito Central
     */
    private function getCentralUrl()
    {
        return (new sendCentral)->path();
    }
    
    /**
     * Fallback: Actualizar directamente desde API externa si Central no está disponible
     */
    private function updateFromDirectApi()
    {
        try {
            $this->info('📡 Consultando API directa (PyDolarVe)...');
            
            $response = Http::timeout(15)->get('https://pydolarve.org/api/v1/dollar?page=bcv');
            
            if (!$response->successful()) {
                $this->error('❌ API directa no disponible');
                return 1;
            }
            
            $data = $response->json();
            
            if (!isset($data['monitors']['usd']['price'])) {
                $this->error('❌ Formato de respuesta inesperado de API directa');
                return 1;
            }
            
            $dollarValue = (float) $data['monitors']['usd']['price'];
            $source = "API Directa (fallback) - PyDolarVe";
            
            $this->info("💱 Valor del dólar BCV (fallback): {$dollarValue} Bs");
            
            // Actualizar en la base de datos
            $updated = DB::table('monedas')
                ->where('tipo', 1)
                ->update([
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => "Fallback directo - Central no disponible",
                    'estatus' => 'activo'
                ]);
            
            if (!$updated) {
                DB::table('monedas')->insert([
                    'tipo' => 1,
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => "Creado via fallback directo",
                    'estatus' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $this->warn('⚠️  Valor actualizado via fallback (Central no disponible)');
            
            Log::warning('Dollar rate updated via direct fallback', [
                'value' => $dollarValue,
            ]);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Fallback también falló: ' . $e->getMessage());
            
            Log::error('Dollar rate fallback failed', [
                'error' => $e->getMessage()
            ]);
            
            return 1;
        }
    }
}
