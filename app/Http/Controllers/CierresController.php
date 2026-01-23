<?php

namespace App\Http\Controllers;

use App\Models\cajas;
use App\Models\cierres;
use App\Models\catcajas;
use App\Models\CierresMetodosPago;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CierresController extends Controller
{
    function getLastCierre() {
        $seconds = 3600;
        
        if (Cache::has('lastcierres')) {
            return Cache::get('lastcierres');
        }else{
            return Cache::remember('lastcierres', $seconds, function () {
                return cierres::orderBy("fecha","desc")->first();
            });
        }
    }
    public function getStatusCierre(Request $req)
    {
        $today = (new PedidosController)->today();

        $tipo_accion = cierres::where("fecha",$today)->where("id_usuario",session("id_usuario"))->first();
        if ($tipo_accion) {
            $tipo_accion = "editar"; 
        }else{
            $tipo_accion = "guardar"; 

        }

        return ["tipo_accionCierre"=>$tipo_accion];
    }
    public function getTotalizarCierre(Request $req)
    {   
        $bs = (new PedidosController)->get_moneda()["bs"];

        $today = (new PedidosController)->today();
        $c = cierres::where("tipo_cierre",0)
            ->where("fecha",$today)
            ->with('puntos')
            ->get();
        
        // Obtener lotes desde la relación puntos
        $lotes = $c->pluck('puntos')
            ->flatten()
            ->map(function($q) {
                return [
                    "monto" => $q->monto,
                    "lote" => $q->lote ?? $q->descripcion,
                    "banco" => $q->banco,
                    "tipo" => $q->tipo ?? $q->categoria,
                ];
            })
            ->toArray();
        
        // Obtener biopagos desde cierres (aún se guarda ahí)
        $biopagos = [];
        foreach ($c as $key => $e) {
            if ($e->biopagoserial && $e->biopagoserialmontobs) {
                array_push($biopagos,[
                    "monto" => $e->biopagoserialmontobs,
                    "serial" => $e->biopagoserial,
                ]);
            }
        }

        return [
            "caja_usd" => $c->sum("efectivo_actual"),
            "caja_cop" => $c->sum("efectivo_actual_cop"),
            "caja_bs" => $c->sum("efectivo_actual_bs"),
            "caja_punto" => $c->sum("puntodeventa_actual_bs"),
            "dejar_dolar" => $c->sum("dejar_dolar"),
            "dejar_peso" => $c->sum("dejar_peso"),
            "dejar_bss" => $c->sum("dejar_bss"),
            "caja_biopago" => $c->sum("caja_biopago")*$bs,
            "lotes" => $lotes,
            "biopagos" => $biopagos,
        ];
    }

    function reversarCierre() {
        $today = (new PedidosController)->today();

        // Obtener cierres del día para eliminar sus relaciones primero
        $cierres_del_dia = cierres::where("fecha",$today)->get();
        
        foreach ($cierres_del_dia as $cierre) {
            // Eliminar relaciones usando id_cierre
            CierresMetodosPago::where("id_cierre", $cierre->id)->delete();
        }
        
        // Ahora eliminar los cierres
        $cierres_eliminados = cierres::where("fecha",$today)->get();
        cierres::where("fecha",$today)->delete();
        
        // Invalidar caché de addNewPedido para todos los usuarios afectados
        foreach ($cierres_eliminados as $cierre) {
            $cache_key = "cierre_guardado_usuario_{$cierre->id_usuario}_fecha_{$today}";
            Cache::forget($cache_key);
        }
        
        // Eliminar registros de caja
        catcajas::where("nombre","LIKE","%INGRESO DESDE CIERRE%")
        ->get()
        ->map(function($q) use ($today) {
            cajas::where("fecha",$today)->where("categoria",$q->id)->delete();
        });
        
        \Illuminate\Support\Facades\Artisan::call('optimize');
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    }

    function eliminarCierreUsuario(Request $req) {
        try {
            $today = (new PedidosController)->today();
            $id_usuario = session("id_usuario");

            // Verificar que no existe cierre de administrador (tipo_cierre = 1)
            $cierre_admin = cierres::where("fecha", $today)
                ->where("tipo_cierre", 1)
                ->first();

            if ($cierre_admin) {
                return response()->json([
                    "msj" => "No se puede eliminar el cierre. Existe un cierre de administrador que debe ser reversado primero.",
                    "estado" => false
                ], 400);
            }

            // Obtener cierre del usuario
            $cierre_usuario = cierres::where("fecha", $today)
                ->where("id_usuario", $id_usuario)
                ->first();

            if (!$cierre_usuario) {
                return response()->json([
                    "msj" => "No se encontró un cierre guardado para eliminar.",
                    "estado" => false
                ], 404);
            }

            // Eliminar relaciones en CierresMetodosPago
            CierresMetodosPago::where("id_cierre", $cierre_usuario->id)->delete();

            // Eliminar el cierre
            $cierre_usuario->delete();

            // Limpiar caché
            Cache::forget('lastcierres');
            
            // Invalidar caché de addNewPedido para este usuario y fecha
            $cache_key = "cierre_guardado_usuario_{$id_usuario}_fecha_{$today}";
            Cache::forget($cache_key);

            return response()->json([
                "msj" => "Cierre eliminado exitosamente. Ahora puede recalcular.",
                "estado" => true
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error eliminando cierre usuario: " . $e->getMessage());
            return response()->json([
                "msj" => "Error al eliminar el cierre: " . $e->getMessage(),
                "estado" => false
            ], 500);
        }
    }
}
