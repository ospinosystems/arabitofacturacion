<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\pedidos;
use App\Models\cierres;
use App\Models\cierres_puntos;
use App\Models\usuarios;
use App\Models\pago_pedidos;
use App\Models\items_pedidos;
use App\Models\clientes;
use App\Models\moneda;
use App\Models\cajas;
use App\Models\sucursal;
use App\Models\pagos_referencias;
use App\Models\movimientos;
use App\Models\movimientosInventario;
use App\Models\catcajas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CierreV2Controller extends Controller
{
    private function today() {
        return Carbon::now()->toDateString();
    }

    private function get_moneda() {
        if (Cache::has('moneda_v2')) {
            return Cache::get('moneda_v2');
        } else {
            return Cache::remember('moneda_v2', 3600, function () {
                return moneda::all()->keyBy("codigo");
            });
        }
    }

    private function selectUsersTotalizar($totalizarcierre, $fecha = "") {
        if ($totalizarcierre) {
            return pedidos::select('id_vendedor')->distinct()->get()->map(function ($e) {
                return $e->id_vendedor;
            });
        } else {
            $id = session("id_usuario");
            if (!$id) {
                $id = Auth::id();
            }
            // Fallback logic: if no ID found, return empty array (will result in 0 sales, but avoids null error)
            // Or default to 1? Better to be strict but safe.
            return $id ? [$id] : [];
        }
    }

    private function ultimoCierre($fecha, $id_vendedor) {
        if (empty($id_vendedor)) {
            // If no user ID, we can't find previous closure for them.
            // Return empty collection (using get() on empty query)
            return collect([]); 
        }

        $fecha_pasadaquery = cierres::where("fecha", "<", $fecha)
            ->whereIn("id_usuario", $id_vendedor)
            ->orderBy("fecha", "desc")
            ->first();

        if ($fecha_pasadaquery) {
            $fecha_pasada = $fecha_pasadaquery->fecha;
        } else {
            $fecha_pasada = "2023-01-01";
            $id_vendedor = [1]; 
        }

        // Return Collection instead of Builder to safely allow multiple sums
        return cierres::where("fecha", $fecha_pasada)
            ->whereIn("id_usuario", $id_vendedor)
            ->where("tipo_cierre", 0)
            ->orderBy("fecha", "desc")
            ->get();
    }

    private function processCalculation($fecha, $totalizarcierre, $usuario_id = null) {
        $check_pendiente = true;

        if ($check_pendiente) {
            $pedido_pendientes_check = pedidos::where("estado", 0)->pluck('id');
            if ($pedido_pendientes_check->isNotEmpty()) {
                throw new \Exception("Error: Hay pedidos pendientes " . $pedido_pendientes_check->implode(','));
            }
        }

        $id_vendedor = $usuario_id ? [$usuario_id] : $this->selectUsersTotalizar($totalizarcierre, $fecha);
        
        $ultimo_cierre = $this->ultimoCierre($fecha, $id_vendedor);
        
        $moneda = $this->get_moneda();
        $cop = $moneda["cop"]["valor"] ?? 1;
        $bs = $moneda["bs"]["valor"] ?? 1;

        $caja_inicial = 0;
        if ($ultimo_cierre->isNotEmpty()) {
            $caja_inicial = round($ultimo_cierre->sum("dejar_dolar") + ($ultimo_cierre->sum("dejar_peso") / $cop) + ($ultimo_cierre->sum("dejar_bss") / $bs), 3);
        }

        // Fetch orders
        // If id_vendedor is empty, this returns empty result, handling the "no user" case gracefully
        $pedidosDiaIds = pedidos::where("created_at", "LIKE", $fecha . "%")
                                ->where("estado", 1)
                                ->when(!empty($id_vendedor), function($q) use ($id_vendedor) {
                                    return $q->whereIn("id_vendedor", $id_vendedor);
                                })
                                ->pluck('id');

        $pagosDia = pago_pedidos::whereIn('id_pedido', $pedidosDiaIds)->get();

        // Inventory calculations
        $inv = items_pedidos::with([
            "producto",
            "pedido.cliente"
        ])
        ->whereIn("id_pedido", $pedidosDiaIds)
        ->get()
        ->map(function ($q) {
            $q->monto_abono = 0;
            if (isset($q->producto)) {
                $base_total = $q->producto->precio_base * $q->cantidad;
                $venta_total = $q->producto->precio * $q->cantidad;

                $descuentopromedio = ($q->descuento / 100);

                $q->base_total = $base_total - ($base_total * $descuentopromedio);
                $q->venta_total = $venta_total - ($venta_total * $descuentopromedio);

                $q->sin_base_total = $base_total;
                $q->sin_venta_total = $venta_total;

            } else {
                $q->monto_abono = $q->monto;
            }
            return $q;
        });

        $total_export = items_pedidos::whereIn("id_pedido",pedidos::where("created_at", "LIKE", $fecha . "%")->where("export",1)->select("id") )->sum("monto");
        
        $total_credito = $pagosDia->where("tipo", 4)->sum("monto") + $total_export;

        $base_total = $inv->sum("base_total");
        $venta_total = $inv->sum("venta_total");
        $monto_abono = $inv->sum("monto_abono");
        $sin_base_total = $inv->sum("sin_base_total");
        $sin_venta_total = $inv->sum("sin_venta_total");

        $ganancia_bruta = ($venta_total - $base_total);
        $porcentaje = $base_total == 0 ? 100 : round((($ganancia_bruta * 100) / $base_total), 2);

        $divisor = ($porcentaje / 100) + 1;

        if ($divisor == 0 || $total_credito == 0) {
            $base_credito = 0;
        } else {
            $base_credito = round($total_credito / $divisor, 2);
        }
        $venta_credito = $total_credito;

        $base_abono = $divisor == 0 ? 0 : round($monto_abono / $divisor, 2);
        $venta_abono = $monto_abono;

        // Profits
        $precio_base = ($base_total + $base_abono) - $base_credito;
        
        $desc_total = ($venta_total + $venta_abono) - $venta_credito;
        $ganancia = $desc_total - $precio_base;

        // Payments
        $arr_pagos = [
            "total" => 0,
            "caja_inicial" => $caja_inicial,
            "entregado" => 0,
            "pendiente" => 0,
            "total_punto" => 0,
            "total_biopago" => 0,
        ];
        
        $numventas_arr = [];
        
        $pagosDia->sortByDesc("id")->each(function ($q) use (&$arr_pagos, &$numventas_arr) {
            if (array_key_exists($q->tipo, $arr_pagos)) {
                $arr_pagos[$q->tipo] += $q->monto;
            } else {
                $arr_pagos[$q->tipo] = $q->monto;
            }
            
            if ($q->tipo != 4 && $q->tipo != 6) { // Not credit, not vuelta
                if (!array_key_exists($q->id_pedido, $numventas_arr)) {
                    $numventas_arr[$q->id_pedido] = 1;
                }
                $arr_pagos["total"] += $q->monto;
            }
        });
        
        if (isset($arr_pagos[6])) { // Vueltos
            $arr_pagos["pendiente"] += $arr_pagos[6];
        }
        
        $arr_pagos["entregado"] = $arr_pagos["total"]; 
        
        $sistema_punto = isset($arr_pagos[2]) ? $arr_pagos[2] : 0;
        $sistema_biopago = isset($arr_pagos[5]) ? $arr_pagos[5] : 0;
        $sistema_efectivo = isset($arr_pagos[3]) ? $arr_pagos[3] : 0;
        
        // Handle transfers (type 1) as cash? Or check legacy behavior?
        // Legacy adds type 1 to 'total_caja' implicitly by adding ($total_caja_neto - caja_inicial).
        // But legacy checks: `if (isset($arr_pagos[1])) { ... }`
        // Let's check if 'sistema_efectivo' should include type 1.
        // Usually Transfer is digital, not cash drawer.
        // But if user declares it... 
        // Legacy doesn't explicitly sum type 3 into a variable named "sistema_efectivo".
        // It calculates `total_caja` from USER INPUT.
        // AND it calculates `efectivo_digital`?
        // Legacy `cerrarFun`:
        // `efectivo: cierre["total_caja"]` -> user input.
        
        // For the "Esperado (Sistema)" column in my V2 UI:
        // It shows `calcResult.sistema_efectivo`.
        // This MUST match what should be in the drawer.
        // Usually: Type 3 (Cash) + Type 1 (Transfer)? No, transfer is bank.
        // So Type 3 is correct.
        
        // Handle legacy closure totalization logic
        if ($totalizarcierre) {
            $cierres_cajeros = cierres::where("fecha", $fecha)
                ->where("tipo_cierre", 0)
                ->with("usuario")
                ->get();

            $cajeros_declarado = [
                "caja_usd" => $cierres_cajeros->sum("total_caja"),
                "caja_cop" => $cierres_cajeros->sum("efectivo_actual_cop"),
                "caja_bs" => $cierres_cajeros->sum("efectivo_actual_bs"),
                "caja_punto" => $cierres_cajeros->sum("puntodeventa_actual_bs"),
                "caja_biopago" => $cierres_cajeros->sum("caja_biopago"), // Is this stored as biopago? yes.
            ];
            
            // Return extended response for Admin
            return [
                "fecha" => $fecha,
                "caja_inicial" => $caja_inicial,
                "sistema_efectivo" => $sistema_efectivo,
                "sistema_punto" => $sistema_punto,
                "sistema_biopago" => $sistema_biopago,
                
                "desc_total" => $desc_total,
                "ganancia" => $ganancia,
                "porcentaje" => $porcentaje,
                "numventas" => count($numventas_arr),
                
                "precio_base" => $precio_base,
                "precio" => $desc_total,
                
                "entregadomenospend" => ($arr_pagos["entregado"] - $arr_pagos["pendiente"]),
                "pendiente" => $arr_pagos["pendiente"],
                
                // Admin specific data
                "detalles_cajeros" => $cierres_cajeros,
                "cajeros_declarado" => $cajeros_declarado
            ];
        }

        return [
            "fecha" => $fecha,
            "caja_inicial" => $caja_inicial,
            "sistema_efectivo" => $sistema_efectivo,
            "sistema_punto" => $sistema_punto,
            "sistema_biopago" => $sistema_biopago,
            
            "desc_total" => $desc_total,
            "ganancia" => $ganancia,
            "porcentaje" => $porcentaje,
            "numventas" => count($numventas_arr),
            
            "precio_base" => $precio_base,
            "precio" => $desc_total, 
            
            "entregadomenospend" => ($arr_pagos["entregado"] - $arr_pagos["pendiente"]),
            "pendiente" => $arr_pagos["pendiente"],
        ];
    }

    public function calcular(Request $req) {
        try {
            $data = $this->processCalculation($this->today(), $req->totalizarcierre);
            return response()->json(["estado" => true, "data" => $data]);
        } catch (\Exception $e) {
            return response()->json(["estado" => false, "msj" => $e->getMessage()]);
        }
    }

    public function guardar(Request $req) {
        $today = $this->today();
        $totalizarcierre = $req->totalizarcierre;
        
        try {
            DB::beginTransaction();

            $lotes = $req->lotes;
            $batchNumbers = [];
            foreach($lotes as $l) {
                if (in_array($l['lote'], $batchNumbers)) {
                    throw new \Exception("Lote duplicado en la lista: " . $l['lote']);
                }
                $batchNumbers[] = $l['lote'];
            }
            
            $calc = $this->processCalculation($today, $totalizarcierre);
            
            $usuario_id = session("id_usuario");
            if (!$usuario_id) $usuario_id = Auth::id();
            
            $cierre = cierres::updateOrCreate(
                [
                    "fecha" => $today,
                    "id_usuario" => $usuario_id
                ],
                [
                    "total_caja" => $req->caja_usd, 
                    "total_caja_neto" => $req->caja_usd, 
                    
                    "dejar_dolar" => $req->dejar_usd,
                    "dejar_peso" => $req->dejar_cop,
                    "dejar_bss" => $req->dejar_bs,
                    
                    "efectivo_actual" => $req->caja_usd,
                    "efectivo_actual_cop" => $req->caja_cop,
                    "efectivo_actual_bs" => $req->caja_bs,
                    
                    "puntodeventa_actual_bs" => $req->total_punto_input,
                    
                    "caja_biopago" => $req->biopago_monto,
                    "biopagoserial" => $req->biopago_serial,
                    "biopagoserialmontobs" => $req->biopago_monto, 
                    
                    // Metrics
                    "caja_inicial" => $calc["caja_inicial"],
                    "precio" => $calc["precio"],
                    "precio_base" => $calc["precio_base"],
                    "ganancia" => $calc["ganancia"],
                    "porcentaje" => $calc["porcentaje"],
                    "desc_total" => $calc["desc_total"],
                    "numventas" => $calc["numventas"],
                    
                    "entregadomenospend" => $calc["entregadomenospend"],
                    "pendiente" => $calc["pendiente"],
                    
                    "tipo_cierre" => $totalizarcierre ? 1 : 0,
                ]
            );

            cierres_puntos::where("fecha", $today)->where("id_usuario", $usuario_id)->delete();
            foreach($lotes as $l) {
                cierres_puntos::create([
                    "id_usuario" => $usuario_id,
                    "fecha" => $today,
                    "banco" => $l['banco'], 
                    "lote" => $l['lote'],
                    "monto" => $l['monto'],
                    "tipo" => $l['tipo'] ?? 'DEBITO'
                ]);
            }

            DB::commit();
            return response()->json(["estado" => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["estado" => false, "msj" => $e->getMessage()]);
        }
    }

    public function reversar(Request $req) {
        $today = $this->today();

        try {
            DB::beginTransaction();

            cierres_puntos::where("fecha", $today)->delete();
            cierres::where("fecha", $today)->delete();

            $cats = catcajas::where("nombre", "LIKE", "%INGRESO DESDE CIERRE%")->get();
            foreach($cats as $cat) {
                cajas::where("fecha", $today)->where("categoria", $cat->id)->delete();
            }

            DB::commit();

            \Illuminate\Support\Facades\Artisan::call('optimize:clear');

            return response()->json(["estado" => true, "msj" => "Cierre reversado correctamente"]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["estado" => false, "msj" => "Error al reversar: " . $e->getMessage()]);
        }
    }
}
