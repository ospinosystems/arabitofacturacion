<?php

namespace App\Http\Controllers;

use App\Models\cajas;
use App\Models\cierres;
use App\Models\catcajas;
use App\Models\cierres_puntos;



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
        $c = cierres::where("tipo_cierre",0)->where("fecha",$today)->get();
        $lotes = [];
        $biopagos = [];
        foreach ($c as $key => $e) {

           

            if ($e->puntolote1montobs&&$e->puntolote1) {
                array_push($lotes,[
                    "monto" => $e->puntolote1montobs,
                    "lote" => $e->puntolote1,
                    "banco" => $e->puntolote1banco,
                ]);
            }
            if ($e->puntolote2montobs&&$e->puntolote2) {
                array_push($lotes,[
                    "monto" => $e->puntolote2montobs,
                    "lote" => $e->puntolote2,
                    "banco" => $e->puntolote2banco,
                ]);
            }
            if ($e->biopagoserial&&$e->biopagoserialmontobs) {
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

        cierres_puntos::where("fecha",$today)->delete();
        cierres::where("fecha",$today)->delete();
        catcajas::where("nombre","LIKE","%INGRESO DESDE CIERRE%")
        ->get()
        ->map(function($q) use ($today) {
            cajas::where("fecha",$today)->where("categoria",$q->id)->delete();
        });
        \Illuminate\Support\Facades\Artisan::call('optimize');
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    }
}
