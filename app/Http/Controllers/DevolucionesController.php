<?php

namespace App\Http\Controllers;

use App\Models\devoluciones;
use App\Models\items_devoluciones;
use App\Models\inventario;
use App\Models\garantia;
use App\Http\Requests\StoredevolucionesRequest;
use App\Http\Requests\UpdatedevolucionesRequest;


use Illuminate\Http\Request;
use Response;



class DevolucionesController extends Controller
{
    public function createDevolucion(Request $req)
    {
        $idcliente = $req->idcliente;
        $idvendedor = session("id_usuario");

        $devolucion = new devoluciones;        
        $devolucion->id_vendedor = $idvendedor;
        $devolucion->id_cliente = $idcliente;

        if ($devolucion->save()) {
            return ["id_devolucion"=>$devolucion->id];
        }
    }
    
    public function setDevolucion(Request $req)
    {
        try {
            
            $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                "id_pedido" => null,
                "tipo" => "devolucion",
            ]);
            
            if ((new UsuariosController)->isAdmin()) {
            }elseif($isPermiso["permiso"]){
            }else{
                $nuevatarea = (new TareaslocalController)->createTareaLocal([
                    "id_pedido" => null,
                    "tipo" => "devolucion",
                    "valoraprobado" => 0,
                    "descripcion" => "Devolucion",
                ]);
                if ($nuevatarea) {
                    return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                }
            }

            $id_vendedor = session("id_usuario");
            $new_mov = new devoluciones;
            $new_mov->id_vendedor = $id_vendedor;
            $new_mov->id_cliente = $req->id_cliente;
            if ($new_mov->save()) {
                foreach ($req->productosselectdevolucion as $i => $e) {
                    $id_producto = $e["idproducto"];
                    $cantidad = $e["cantidad"];
                    $tipoMovMovimientos = $e["tipo"];
                    $tipoCatMovimientos = $e["categoria"];
                    $motivoMov = $e["motivo"];
        
                    $date = new \DateTime($req->fechaMovimientos);
                    $fechaMovimientos = $date->getTimestamp();
                   
                    $restar_cantidad_query = inventario::where('id', $id_producto)->lockForUpdate()->first();
                    $type_mov = "";
        
                    if ($tipoMovMovimientos==0) {
                        //Salida de Producto
                        $type_mov = "Salida";
                        
                        $restar_cantidad = $restar_cantidad_query->cantidad - $cantidad;
                        (new InventarioController)->descontarInventario($id_producto,$restar_cantidad, $restar_cantidad_query->cantidad, null, $type_mov."Devolucion");

                    }elseif ($tipoMovMovimientos==1) {
                        //Entrada de Producto
                        if ($tipoCatMovimientos==2) {
                            //Por Cambio
                            $type_mov = "Entrada";
                            $restar_cantidad = $restar_cantidad_query->cantidad + $cantidad;

                            (new InventarioController)->descontarInventario($id_producto,$restar_cantidad, $restar_cantidad_query->cantidad, null, $type_mov."Devolucion");
                            
                        }elseif ($tipoCatMovimientos==1) {
                            //Por Garantia
                            $exit_num = 0;
                            
                            $exi = garantia::where("id_producto",$id_producto)->first();
                            
                            if ($exi) {
                                $exit_num = $exi->cantidad;
                            }

                            garantia::updateOrCreate(
                                ["id_producto" => $id_producto],
                                ["cantidad" => $exit_num+$cantidad]
        
                            );
                        }
                    }

                    
                    $new_item_mov = new items_devoluciones;
                    $new_item_mov->id_producto = $id_producto;
                    $new_item_mov->cantidad = $cantidad;
                    $new_item_mov->tipo = $tipoMovMovimientos;
                    $new_item_mov->categoria = $tipoCatMovimientos;
                    $new_item_mov->motivo = $motivoMov?$motivoMov:"SALIDA";
                    $new_item_mov->id_devolucion = $new_mov->id;
                    $new_item_mov->save();

                }
            }   
            
            return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }


    }
    public function getBuscarDevolucionhistorico(Request $req)
    {
        $buscar = $req->q; 
        
        if ($buscar) {
            $dev =  devoluciones::with(["vendedor","cliente","items"=>function($q){
                $q->with("producto")->orderBy("tipo","desc");
            }])->whereIn("id_cliente",function($q) use ($buscar){
                $q->from('clientes')->where('identificacion',"LIKE","{$buscar}%")->select('id');

            })->orderBy($req->orderColumn,$req->orderBy)->limit($req->num)->get();

            $dev->where("fecha","LIKE","{$req->q}%");
        }else{
            $dev =  devoluciones::with(["vendedor","cliente","items"=>function($q){
                $q->with("producto")->orderBy("tipo","desc");
            }])->orderBy($req->orderColumn,$req->orderBy)->limit($req->num)->get();
        } 
        return $dev;
    }
    public function setpagoDevolucion(Request $req)
    {
        # code...
    }
}
