<?php

namespace App\Http\Controllers;

use App\Models\factura;
use App\Models\proveedores;
use App\Models\sucursal;
use Illuminate\Http\Request;
use Response;
class FacturaController extends Controller
{      
    public function verFactura(Request $req)
    {
        $id = $req->id;
        $factura = factura::with(["proveedor","items"=>function($q){
            $q->with("producto");
        }])
        ->find($id);

        $factura->items->map(function($q)
        {   
            $q->subtotal = number_format($q->producto->precio*$q->cantidad,2);
            return $q;
        });
        $factura->monto = number_format($factura->monto,2);

        $sucursal = sucursal::all()->first();

        return view("reportes.factura",["factura"=>$factura,"sucursal"=>$sucursal]);
    }
    public function verDetallesImagenFactura(Request $req)
    {
        $id = $req->id;
        $fact = factura::find($id);

        return asset('facturas')."/".$fact->descripcion;
    }
    
    public function getFacturas(Request $req)
    {
        $factqBuscar = $req->factqBuscar;
        $factqBuscarDate = $req->factqBuscarDate;
        $factOrderBy = $req->factOrderBy;
        $factOrderDescAsc = $req->factOrderDescAsc;
       
        $fa = factura::with(["proveedor","items"=>function($q){
            // inventario no tiene relaciones categoria/proveedor → cargar solo el producto.
            $q->with(["producto"])->orderBy("id","asc");
        }])
        ->where(function($q) use ($factqBuscar) {
            $q->orWhere("descripcion","LIKE","%$factqBuscar%")
            ->orWhere("numfact","LIKE","%$factqBuscar%");
        })
        ->when($factqBuscarDate,function($q) use ($factqBuscarDate) {
            $q->where("created_at","LIKE","%$factqBuscarDate%");
        })
        ->orderBy("id","desc")
        ->limit(100)
        ->get()
        ->map(function($q){
            $sub = $q->items->map(function($q){
                // Guardar contra producto nulo (producto borrado): antes esto lanzaba 500 y el
                // front recibía una respuesta no-array → "facturas.map is not a function".
                if (!$q->producto) {
                    $q->basefact = 0;
                    $q->subtotal_base_clean = 0;
                    $q->subtotal_clean = 0;
                    return $q;
                }
                $q->producto->cantidadfact = $q->cantidad;
                $q->basefact = ($q->producto->precio3 ?? 0) * $q->cantidad;
                $q->subtotal_base_clean = ($q->producto->precio_base ?? 0) * $q->cantidad;
                $q->subtotal_clean = ($q->producto->precio ?? 0) * $q->cantidad;
                return $q;
            });

            $q->basefact = $sub->sum("basefact");
            $q->summonto_base_clean = $sub->sum("subtotal_base_clean"); 
            $q->summonto_clean = $sub->sum("subtotal_clean"); 
            return $q;
        });

        return $fa;
    }
    public function saveMontoFactura(Request $req)
    {
        $fact = factura::find($req->id);
        $fact->monto = $req->monto;
        $fact->save();
    }
    public function setFactura(Request $req)
    {
        try {
            $id = $req->id=="null"||!$req->id? 0:$req->id;
            $factInpid_proveedor = $req->factInpid_proveedor;
            $factInpnumfact = $req->factInpnumfact;
            $factInpdescripcion = $req->factInpdescripcion;
            $factInpmonto = $req->factInpmonto;
            $factInpfechavencimiento = $req->factInpfechavencimiento;
            $factInpImagen = $req->factInpImagen;

            $provcentral = [
                "id" => $req->proveedorCentraid,
                "rif" => $req->proveedorCentrarif,
                "descripcion" => $req->proveedorCentradescripcion,
                "direccion" => $req->proveedorCentradireccion,
                "telefono" => $req->proveedorCentratelefono,
            ];

            
            $id_usuario = session("id_usuario");
            
            
            $id_proveedor = proveedores::updateOrCreate([
                "rif" => $provcentral["rif"]
            ],[
                "descripcion" => $provcentral["descripcion"],
                "rif" => $provcentral["rif"],
                "direccion" => $provcentral["direccion"],
                "telefono" => $provcentral["telefono"],
            ]);  
            
            if ($id_proveedor) {
                $filename = $id_proveedor->id . "-$factInpnumfact." . $factInpImagen->getClientOriginalExtension();
                factura::updateOrCreate(
                    ["id" => $id],
                    [
                        "id_proveedor" => $id_proveedor->id,
                        "numfact" => $factInpnumfact,
                        "descripcion" => $filename,
                        "monto" => $factInpmonto,
                        "fechavencimiento" => $factInpfechavencimiento,
                        
                        "numnota" => $req->factInpnumnota,
                        "subtotal" => $req->factInpsubtotal,
                        "descuento" => $req->factInpdescuento,
                        "monto_gravable" => $req->factInpmonto_gravable,
                        "monto_exento" => $req->factInpmonto_exento,
                        "iva" => $req->factInpiva,
                        "fechaemision" => $req->factInpfechaemision,
                        "fecharecepcion" => $req->factInpfecharecepcion,
                        "nota" => $req->factInpnota,
                        "id_usuario" => $id_usuario,
                        "estatus" => 0,
                    ]
    
                );
                $factInpImagen->move(public_path('facturas'), $filename);
                return Response::json(["msj"=>"Éxito","estado"=>true]);
            }
                
            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage()." ".$e->getLine(),"estado"=>false]);
        }
      
    }

    public function delFactura(Request $req)
    {
        try {
            $id = $req->id;
            factura::find($id)->delete();
            return Response::json(["msj"=>"Éxito al eliminar","estado"=>true]);

            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
            
        }
    }

    
    

}
