<?php

namespace App\Http\Controllers;

use App\Models\moneda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Response;
use Session;
use App\Models\pedidos;
use App\Models\items_pedidos;

class MonedasController extends Controller
{
    /**
     * Verificar si hay pedidos pendientes
     */
    private function hayPedidosPendientes()
    {
        $pedidosPendientes = pedidos::where("estado", 0)->pluck('id');
        if ($pedidosPendientes->isNotEmpty()) {
            return $pedidosPendientes;
        }
        return false;
    }

    /**
     * Actualizar tasas de items de pedidos pendientes
     */
    private function actualizarTasasPedidosPendientes($nuevaTasaDolar, $nuevaTasaCop)
    {
        try {
            // Obtener IDs de pedidos pendientes
            $pedidosPendientesIds = pedidos::where("estado", 0)->pluck('id');
            
            if ($pedidosPendientesIds->isEmpty()) {
                return ['actualizados' => 0, 'pedidos' => []];
            }

            // Actualizar items de pedidos pendientes
            $itemsActualizados = items_pedidos::whereIn('id_pedido', $pedidosPendientesIds)
                ->update([
                    'tasa' => $nuevaTasaDolar,
                    'tasa_cop' => $nuevaTasaCop,
                    'updated_at' => now()
                ]);

            return [
                'actualizados' => $itemsActualizados,
                'pedidos' => $pedidosPendientesIds->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('Error actualizando tasas de pedidos pendientes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['actualizados' => 0, 'pedidos' => [], 'error' => $e->getMessage()];
        }
    }
    /**
     * Obtener todas las monedas
     */
    public function getMonedas()
    {
        try {
            $monedas = DB::table('monedas')
                ->orderBy('tipo')
                ->get();

            return Response::json([
                "estado" => true,
                "monedas" => $monedas
            ]);
        } catch (\Exception $e) {
            return Response::json([
                "estado" => false,
                "msj" => "Error al obtener monedas: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener valor actual del dólar
     */
    public function getDollarRate()
    {
        try {
            $dollar = DB::table('monedas')
                ->where('tipo', 1) // Dólar
                ->where('estatus', 'activo')
                ->first();

            if (!$dollar) {
                return Response::json([
                    "estado" => false,
                    "msj" => "No se encontró información del dólar"
                ]);
            }

            return Response::json([
                "estado" => true,
                "dollar" => $dollar
            ]);
        } catch (\Exception $e) {
            return Response::json([
                "estado" => false,
                "msj" => "Error al obtener valor del dólar: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Verificar si la fecha actual es un día feriado
     * Basado en los días feriados comentados en el código
     */
    private function isHoliday($date = null)
    {
        if (!$date) {
            $date = now();
        }
        
        $month = $date->month;
        $day = $date->day;
        $year = $date->year;
        
        // Días feriados fijos según los comentarios
        $fixedHolidays = [
            [1, 1],   // Enero 1: Año Nuevo
            [1, 6],   // Enero 6: Día de Reyes
            [1, 13],  // Enero 13: Día de la Divina Pastora (se ejecuta el lunes 13 en lugar del martes 14)
            [3, 19],  // Marzo 19: Día de San José
            [5, 1],   // Mayo 1: Día del Trabajador
            [6, 24],  // Junio 24: Batalla de Carabobo
            [7, 5],   // Julio 5: Día de la Independencia
            [7, 24],  // Julio 24: Natalicio del Libertador
            [10, 12], // Octubre 12: Día de la Resistencia Indígena
            [12, 8],  // Diciembre 8: Día de la Inmaculada Concepción
            [12, 24], // Diciembre 24: Nochebuena
            [12, 25], // Diciembre 25: Natividad de Nuestro Señor
            [12, 31], // Diciembre 31: Fin de Año
        ];
        
        // Verificar días feriados fijos
        foreach ($fixedHolidays as $holiday) {
            if ($month == $holiday[0] && $day == $holiday[1]) {
                return true;
            }
        }
        
        // Días feriados variables según los comentarios
        // Carnaval (lunes 3 y martes 4 de marzo)
        if ($month == 3 && ($day == 3 || $day == 4)) {
            return true;
        }
        
        // Semana Santa (jueves 17 y viernes 18 de abril)
        if ($month == 4 && ($day == 17 || $day == 18)) {
            return true;
        }
        
        // Abril 19: Movimiento Precursor de la Independencia
        if ($month == 4 && $day == 19) {
            return true;
        }
        
        // Junio - días feriados variables (lunes)
        if ($month == 6) {
            if ($day == 2 || $day == 16 || $day == 23) {
                return true; // Ascensión del Señor, Día de San Antonio, Corpus Christi
            }
        }
        
        // Agosto 18: Asunción de Nuestra Señora (se ejecuta el lunes 18 en lugar del viernes 15)
        if ($month == 8 && $day == 18) {
            return true;
        }
        
        // Septiembre 15: Día de la Virgen de Coromoto (se ejecuta el lunes 15 en lugar del jueves 11)
        if ($month == 9 && $day == 15) {
            return true;
        }
        
        // Noviembre 24: Día de la Virgen del Rosario de Chiquinquirá (se ejecuta el lunes 24 en lugar del martes 18)
        if ($month == 11 && $day == 24) {
            return true;
        }
        
        return false;
    }
    


    /**
     * URL de Arabito Central para obtener tasas
     * Usa el mismo método que sendCentral
     */
    private function getCentralUrl()
    {
        return (new sendCentral)->path();
    }
    
    /**
     * Actualizar valor del dólar desde Arabito Central
     * Central maneja la lógica de feriados y consulta a API externa si es necesario
     */
    public function updateDollarRate()
    {
        try {
            // Verificar si es necesario actualizar (máximo cada 30 minutos)
            $lastUpdate = DB::table('monedas')
                ->where('tipo', 1) // Dólar
                ->whereNotNull('fecha_ultima_actualizacion')
                ->orderBy('fecha_ultima_actualizacion', 'desc')
                ->first();

            // Solo verificar límite si no es una actualización forzada desde login
            if (!request()->is('forceUpdateDollar')) {
                if ($lastUpdate && Carbon::parse($lastUpdate->fecha_ultima_actualizacion)->diffInMinutes(now()) < 30) {
                    return Response::json([
                        "estado" => false,
                        "msj" => "La última actualización fue hace menos de 30 minutos",
                        "valor" => $lastUpdate->valor ?? null
                    ]);
                }
            }

            // Obtener ID de sucursal actual
            $idSucursal = session('id_sucursal') ?? env('SUCURSAL_ID');
            
            // Consultar a Arabito Central
            $centralUrl = $this->getCentralUrl();
            $response = Http::timeout(15)->get("{$centralUrl}/api/tasas/hoy", [
                'fecha' => now()->toDateString(),
                'id_sucursal' => $idSucursal,
            ]);

           

            $data = $response->json();
            
            if (!$data['estado']) {
                throw new \Exception($data['mensaje'] ?? 'Error desconocido de Central');
            }

            $dollarValue = (float) $data['tasa_bcv'];
            $source = "Central - " . ($data['api_source'] ?? $data['origen'] ?? 'API');
            
            // Información adicional de Central
            $esFeriado = $data['es_feriado'] ?? false;
            $esFinSemana = $data['es_fin_semana'] ?? false;
            $desdeCacheCentral = $data['desde_cache'] ?? false;

            // Actualizar en la base de datos local
            $updated = DB::table('monedas')
                ->where('tipo', 1) // Dólar
                ->update([
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => "Desde Central. " . 
                              ($esFeriado ? "Día feriado. " : "") . 
                              ($esFinSemana ? "Fin de semana. " : "") .
                              ($desdeCacheCentral ? "Cache Central." : "API actualizada."),
                    'estatus' => 'activo'
                ]);

            if (!$updated) {
                // Si no hay registros, crear uno nuevo
                DB::table('monedas')->insert([
                    'tipo' => 1,
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => "Creado desde Central",
                    'estatus' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Obtener tasa COP actual
            $copRate = DB::table('monedas')->where('tipo', 2)->value('valor') ?? 0;
            
            // Actualizar tasas de pedidos pendientes
            $resultadoActualizacion = $this->actualizarTasasPedidosPendientes($dollarValue, $copRate);
            
            // Limpiar caches
            Cache::forget('bs');
            Cache::forget('cop');
            Cache::forget('moneda_rates_' . md5($dollarValue));
            
            Log::info('Dollar rate updated from Central', [
                'value' => $dollarValue,
                'source' => $source,
                'es_feriado' => $esFeriado,
                'es_fin_semana' => $esFinSemana,
                'desde_cache_central' => $desdeCacheCentral,
                'pedidos_actualizados' => $resultadoActualizacion
            ]);

            $mensaje = "Valor del dólar actualizado desde Central";
            if ($esFeriado) $mensaje .= " (día feriado)";
            if ($esFinSemana) $mensaje .= " (fin de semana)";
            if ($resultadoActualizacion['actualizados'] > 0) {
                $mensaje .= ". Se actualizaron {$resultadoActualizacion['actualizados']} items";
            }

            return Response::json([
                "estado" => true,
                "msj" => $mensaje,
                "valor" => $dollarValue,
                "fecha_actualizacion" => now()->toDateTimeString(),
                "origen" => $source,
                "es_feriado" => $esFeriado,
                "es_fin_semana" => $esFinSemana,
                "desde_cache_central" => $desdeCacheCentral,
                "items_actualizados" => $resultadoActualizacion['actualizados'],
                "pedidos_afectados" => $resultadoActualizacion['pedidos']
            ]);

        } catch (\Exception $e) {
            Log::error('Dollar rate update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

          
                return Response::json([
                    "estado" => false,
                    "msj" => "Error al actualizar: " . $e->getMessage() . ". Fallback también falló: " . $fallbackError->getMessage()
                ]);
        }
    }
    
   

  

    /**
     * Obtener monedas (compatibilidad con MonedaController)
     */
    public function getMoneda(Request $req)
    {
        $mon = (new PedidosController)->get_moneda();
        return Response::json(["dolar"=>$mon["bs"],"peso"=>$mon["cop"]]);
    }

    /**
     * Establecer moneda (compatibilidad con MonedaController)
     */
    public function setMoneda(Request $req)
    {
        
        // Verificar que sea admin (tipo_usuario 1) o superadmin (tipo_usuario 0)
            // Si es actualización manual desde login, agregar fecha de actualización
            $updateData = [
                "tipo" => $req->tipo,
                "valor" => $req->valor,
                "fecha_ultima_actualizacion" => now(),
            ];
            $moneda = moneda::where("tipo",$req->tipo)->get();

            foreach($moneda as $m){
                $m->update(["fecha_ultima_actualizacion"=>now(),"updated_at"=>now()]);
            }
            
            // Si viene del login, agregar fecha de actualización
            if ($req->has('from_login') && $req->from_login) {
                $updateData["fecha_ultima_actualizacion"] = now();
                $updateData["origen"] = "Manual";
                $updateData["notas"] = "Actualización manual desde login";
            }
            
            // Usar el modelo moneda
            $moneda = new \App\Models\moneda();
            $moneda->updateOrCreate(["tipo" => $req->tipo], $updateData);
            
            // Obtener ambas tasas actuales después de la actualización
            $dollarRate = DB::table('monedas')->where('tipo', 1)->value('valor') ?? 0;
            $copRate = DB::table('monedas')->where('tipo', 2)->value('valor') ?? 0;
            
            // Actualizar tasas de pedidos pendientes
            $resultadoActualizacion = $this->actualizarTasasPedidosPendientes($dollarRate, $copRate);
            
            // Clear all related caches
            Cache::forget('bs');
            Cache::forget('cop');
            Cache::forget('moneda_rates_' . md5($req->valor));
            
            $mensaje = "Moneda actualizada exitosamente";
            if ($resultadoActualizacion['actualizados'] > 0) {
                $mensaje .= ". Se actualizaron las tasas de {$resultadoActualizacion['actualizados']} items en los pedidos pendientes: " . implode(', ', $resultadoActualizacion['pedidos']);
            }
            
            return Response::json([
                "msj" => $mensaje,
                "estado" => true,
                "items_actualizados" => $resultadoActualizacion['actualizados'],
                "pedidos_afectados" => $resultadoActualizacion['pedidos']
            ]);
        return Response::json(["msj" => "No tienes permisos para realizar esta acción", "estado" => false]);
    }
}
