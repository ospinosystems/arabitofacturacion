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
     * Actualizar valor del dólar desde API
     */
    public function updateDollarRate()
    {
        try {
            
            // Verificar si es necesario actualizar (máximo cada 30 minutos)
            // Solo para rutas protegidas, no para actualización forzada desde login
            $lastUpdate = DB::table('monedas')
                ->where('tipo', 1) // Dólar
                ->whereNotNull('fecha_ultima_actualizacion')
                ->orderBy('fecha_ultima_actualizacion', 'desc')
                ->first();

            // Solo verificar límite si no es una actualización forzada desde login
            if (request()->is('forceUpdateDollar')) {
                // Para actualización forzada, no hay límite de tiempo
            } else {
                // Si no hay fecha de actualización, permitir actualizar
                if ($lastUpdate && Carbon::parse($lastUpdate->fecha_ultima_actualizacion)->diffInMinutes(now()) < 30) {
                    return Response::json([
                        "estado" => false,
                        "msj" => "La última actualización fue hace menos de 30 minutos"
                    ]);
                }
            }

            // Obtener datos de la API
            $response = Http::timeout(30)->get('https://pydolarve.org/api/v1/dollar?page=bcv&rounded_price=false');

    /*         
    Dias Feriados:
    Enero:

                Miércoles 1: Año Nuevo
                Lunes 6: Día de Reyes
                Lunes 13: Día de la Divina Pastora (se ejecuta el lunes 13 en lugar del martes 14)

            Marzo:

                Lunes 3 y Martes 4: Carnaval

                Miércoles 19: Día de San José

            Abril:

                Jueves 17 y Viernes 18: Semana Santa

                Sábado 19: Movimiento Precursor de la Independencia

            Mayo:

                Jueves 1: Día del Trabajador

            Junio:

                Lunes 2: Ascensión del Señor (se ejecuta el lunes 2 en lugar del jueves 29 de mayo)

                Lunes 16: Día de San Antonio (se ejecuta el lunes 16 en lugar del viernes 13)

                Lunes 23: Corpus Christi (se ejecuta el lunes 23 en lugar del jueves 19)

                Martes 24: Batalla de Carabobo

            Julio:

                Sábado 5: Día de la Independencia

                Jueves 24: Natalicio del Libertador

            Agosto:

                Lunes 18: Asunción de Nuestra Señora (se ejecuta el lunes 18 en lugar del viernes 15)

            Septiembre:

                Lunes 15: Día de la Virgen de Coromoto (se ejecuta el lunes 15 en lugar del jueves 11)

            Octubre:

                Domingo 12: Día de la Resistencia Indígena

            Noviembre:

                Lunes 24: Día de la Virgen del Rosario de Chiquinquirá (se ejecuta el lunes 24 en lugar del martes 18)

            Diciembre:

                Lunes 8: Día de la Inmaculada Concepción

                Miércoles 24: Nochebuena

                Jueves 25: Natividad de Nuestro Señor

                Miércoles 31: Fin de Año */
            
            if (!$response->successful()) {
                throw new \Exception('Error al conectar con la API: ' . $response->status());
            }

            $data = $response->json();
            
            // Verificar estructura de la respuesta
            if (!isset($data['monitors'])) {
                throw new \Exception('Formato de respuesta inesperado de la API');
            }

            // Buscar específicamente el monitor "usd"
            $usdRate = null;
            if (isset($data['monitors']['usd'])) {
                $usdRate = $data['monitors']['usd'];
            }

            if (!$usdRate) {
                throw new \Exception('No se pudo obtener el valor del dólar desde el monitor USD');
            }

            // Determinar si es fin de semana o día feriado
            $now = now();
            $isWeekend = $now->isWeekend();
            $isHoliday = $this->isHoliday($now);
            
            // Elegir el campo de precio apropiado
            $priceField = 'price';
            $priceSource = 'price';
            
            if ($isWeekend || $isHoliday) {
                $priceField = 'price_old';
                $priceSource = 'price_old (fin de semana/feriado)';
            }
            
            // Verificar que el campo de precio existe
            if (!isset($usdRate[$priceField])) {
                // Si no existe price_old en fin de semana/feriado, usar price como fallback
                if ($isWeekend || $isHoliday) {
                    $priceField = 'price';
                    $priceSource = 'price (fallback)';
                } else {
                    throw new \Exception("No se pudo obtener el valor del dólar desde el monitor USD (campo {$priceField} no disponible)");
                }
            }

            $dollarValue = (float) $usdRate[$priceField];
            $lastUpdate = $usdRate['last_update'] ?? $data['datetime']['date'] ?? now()->toDateString();
            $source = "USD Monitor - API pydolarvenezuela ({$priceSource})";

            // Actualizar en la base de datos
            $updated = DB::table('monedas')
                ->where('tipo', 1) // Dólar
                ->update([
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => "Actualizado automáticamente. Última actualización: {$lastUpdate}. Datetime: {$data['datetime']['date']} {$data['datetime']['time']}",
                    'estatus' => 'activo'
                ]);

            if ($updated) {
                // Obtener tasa COP actual
                $copRate = DB::table('monedas')->where('tipo', 2)->value('valor') ?? 0;
                
                // Actualizar tasas de pedidos pendientes
                $resultadoActualizacion = $this->actualizarTasasPedidosPendientes($dollarValue, $copRate);
                
                // Clear all related caches
                Cache::forget('bs');
                Cache::forget('cop');
                Cache::forget('moneda_rates_' . md5($dollarValue));
                
                // Log de la actualización
                Log::info('Dollar rate updated via web', [
                    'value' => $dollarValue,
                    'source' => $source,
                    'last_update' => $lastUpdate,
                    'datetime' => $data['datetime'] ?? null,
                    'pedidos_actualizados' => $resultadoActualizacion
                ]);

                $mensaje = "Valor del dólar actualizado exitosamente";
                if ($resultadoActualizacion['actualizados'] > 0) {
                    $mensaje .= ". Se actualizaron las tasas de {$resultadoActualizacion['actualizados']} items en los pedidos pendientes: " . implode(', ', $resultadoActualizacion['pedidos']);
                }

                return Response::json([
                    "estado" => true,
                    "msj" => $mensaje,
                    "valor" => $dollarValue,
                    "fecha_actualizacion" => now()->toDateTimeString(),
                    "origen" => $source,
                    "items_actualizados" => $resultadoActualizacion['actualizados'],
                    "pedidos_afectados" => $resultadoActualizacion['pedidos']
                ]);
            } else {
                // Si no hay registros, crear uno nuevo
                DB::table('monedas')->insert([
                    'tipo' => 1, // Dólar
                    'valor' => $dollarValue,
                    'fecha_ultima_actualizacion' => now(),
                    'origen' => $source,
                    'notas' => "Creado automáticamente. Última actualización: {$lastUpdate}. Datetime: {$data['datetime']['date']} {$data['datetime']['time']}",
                    'estatus' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Clear all related caches
                Cache::forget('bs');
                Cache::forget('cop');
                Cache::forget('moneda_rates_' . md5($dollarValue));

                return Response::json([
                    "estado" => true,
                    "msj" => "Registro de dólar creado exitosamente",
                    "valor" => $dollarValue,
                    "fecha_actualizacion" => now()->toDateTimeString(),
                    "origen" => $source
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Dollar rate update failed via web', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Response::json([
                "estado" => false,
                "msj" => "Error al actualizar el valor del dólar: " . $e->getMessage()
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
