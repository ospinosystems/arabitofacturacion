<?php

namespace App\Http\Controllers;

use App\Models\sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    /**
     * Determina si la sucursal es 'fullfiscal' según el código
     * del primer registro de la tabla sucursal.
     *
     * @return bool
     */
    public static function isFullFiscal()
    {
        // Lista de códigos de sucursales tipo fullfiscal
        $fullfiscalCodes = [
            'cantaura',
            // Agrega aquí otros códigos de sucursales si es necesario, ej: 'otro_codigo'
        ];

        $firstSucursal = sucursal::orderBy('id', 'asc')->first();

        if (!$firstSucursal) {
            return false;
        }

        $codigo = strtolower($firstSucursal->codigo);

        return in_array($codigo, $fullfiscalCodes);
    }
    public function getSucursal()
    {
        return sucursal::all()->first();
        
    }
    public function index(Request $req)
    {
        $su = sucursal::find(1);
        return view("sucursal.crear",["sucursal"=>$su]);
    }

   
    public function setSucursal(Request $req)
    {
        if(sucursal::updateOrCreate(["id"=>1],[
                    "id"=>1,
                    "sucursal"=>$req->sucursal,
                    "codigo"=>$req->codigo,
                    "direccion_registro"=>$req->direccion_registro,
                    "direccion_sucursal"=>$req->direccion_sucursal,
                    "telefono1"=>$req->telefono1,
                    "telefono2"=>$req->telefono2,
                    "tickera"=> $req->tickera,
                    "fiscal"=> $req->fiscal,
                    
                    "correo"=> $req->correo,
                    "nombre_registro"=> $req->nombre_registro,
                    "rif"=> $req->rif,
                ])){
            return "¡Éxito!";
        }else{
            return "Error";

        }
    }

  
}
