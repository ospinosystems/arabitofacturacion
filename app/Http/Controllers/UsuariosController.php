<?php

namespace App\Http\Controllers;

use App\Models\usuarios;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;

use Response;


class UsuariosController extends Controller
{
    public function isAdmin()
    {
        $tipo = session("tipo_usuario");
        if ($tipo==1) {
            return true;
        }else{
            return false;
        }
    }
    public function setUsuario(Request $req)
    {
        try {
            $arr = [
                "nombre" => $req->nombres,
                "usuario" => $req->usuario,
                "tipo_usuario" => $req->role,
                "ip_pinpad" => $req->ip_pinpad,
            ];
            if ($req->clave) {
                $arr["clave"] = Hash::make( preg_replace( '/[^a-z0-9 ]/i', '', strtolower($req->clave)) );
            }


            usuarios::updateOrCreate(
                ["id"=>$req->id],
            $arr);

            return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }
    }
    public function delUsuario(Request $req)
    {
        try {
            $id = $req->id;
            if ($id) {
                usuarios::find($id)->delete();
            }
            return Response::json(["msj"=>"Éxito al eliminar usuario.","estado"=>true]);
            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
            
        }
    }

    public function getUsuarios(Request $req)
    {
        $qBuscarUsuario = $req->q;
        return usuarios::orwhere("usuario","LIKE",$qBuscarUsuario."%")->orwhere("nombre","LIKE",$qBuscarUsuario."%")->get(["id","nombre","usuario","tipo_usuario","ip_pinpad"]);
    }
    
}
