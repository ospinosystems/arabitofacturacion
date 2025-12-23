<?php

namespace App\Http\Controllers;

use App\Models\tareaslocal;
use Illuminate\Http\Request;
use Response;


class TareaslocalController extends Controller
{
    public function createTareaLocal($arr)
    {
        $id_usuario = session("id_usuario");
        return tareaslocal::updateOrCreate([
            "id_usuario"=> $id_usuario,
            "id_pedido"=> $arr["id_pedido"],
            "tipo"=> $arr["tipo"],
        ],[
            "valoraprobado" => $arr["valoraprobado"],
            "estado" => 0,
            "descripcion"=> $arr["descripcion"],
            "created_at"=> date("Y-m-d H:i:s"),
        ]);
    }

    public function getTareasLocal(Request $req)
    {
        $fecha = $req->fecha;
        return tareaslocal::with("usuario")
        ->when($fecha,function($q) use ($fecha){
            $q->where("created_at","LIKE",$fecha."%");
        })
        ->orderBy("id","desc")
        ->get();
    }
    
    public function checkIsResolveTarea($arr)
    {
        $id_usuario = session("id_usuario");

        $t = tareaslocal::where("id_usuario",$id_usuario)
        ->where("id_pedido",$arr["id_pedido"])
        ->where("tipo",$arr["tipo"])
        ->first();
        $permiso = false;
        $valoraprobado = 0;
        if ($t) {
            if($t->estado){
                $permiso = true;
                $valoraprobado = $t->valoraprobado;
                $t->delete();
            };
        }
        

        return [
            "permiso" => $permiso, 
            "valoraprobado" => $valoraprobado, 
        ];
    }

    public function resolverTareaLocal(Request $req)
    {
        $id_usuario = session("id_usuario");
        $tipo_usuario = session("tipo_usuario");

        if ($req->tipo=="aprobar") {
            $obj = tareaslocal::find($req->id);
            
            // Verificar permisos por tipo de usuario
            $permiso = false;
            
            // Tipo 7 (DICI) puede aprobar: devolucion, eliminarPedido, modped, devolucionPago
            if ($tipo_usuario == "7") {
                if (in_array($obj->tipo, ["exportarPedido","aprobarPedido","devolucion", "eliminarPedido", "modped", "devolucionPago"])) {
                    $permiso = true;
                }
            }
            
            // Tipo 1 (GERENTE) y Tipo 6 (SUPERADMIN) pueden aprobar: credito, transferirPedido, descuentoTotal, descuentoUnitario
            if ($tipo_usuario == "1" || $tipo_usuario == "6") {
                if (in_array($obj->tipo, ["credito", "transferirPedido", "descuentoTotal", "descuentoUnitario"])) {
                    $permiso = true;
                }
            }
            
            // Tipo 5 (SUPERVISOR DE CAJA) puede aprobar: tickera
            if ($tipo_usuario == "5") {
                if ($obj->tipo == "tickera") {
                    $permiso = true;
                }
            }
            
            if ($permiso) {
                $obj->estado = 1;
                $obj->save();
                return Response::json(["msj"=>"Tarea aprobada con éxito","estado"=>true]);
            } else {
                return Response::json(["msj"=>"No tiene permisos para aprobar esta tarea","estado"=>false]);
            }
        } else {
            // Para rechazar, solo verificar si es admin o gerente
            if ($tipo_usuario == "1" || $tipo_usuario == "6" || $tipo_usuario == "7" || $tipo_usuario == "5") {
                $obj = tareaslocal::find($req->id);
                $obj->delete();
                return Response::json(["msj"=>"Tarea rechazada con éxito","estado"=>true]);
            } else {
                return Response::json(["msj"=>"No tiene permisos para rechazar esta tarea","estado"=>false]);
            }
        }
    }

    public function getPermisoCierre(Request $req)
    {
        $id_usuario = session("id_usuario");

        $t = tareaslocal::where("id_usuario",$id_usuario)
        ->whereNull("id_pedido")
        ->where("tipo","cierre")
        ->first();

        if ($t) {
            if ($t->estado) {
                $t->delete();
                return Response::json(["msj"=>"APROBADO","estado"=>true]);
            }
            return Response::json(["id_tarea"=>$t->id, "msj"=>"RECHAZADO","estado"=>false]);
        }else{
            $nuevatarea = $this->createTareaLocal([
                "id_pedido" =>  null,
                "valoraprobado" => 0,
                "tipo" => "cierre",
                "descripcion" => "Cerrar caja",
            ]);
            if ($nuevatarea) {
                return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
            }
        }
    }
}
