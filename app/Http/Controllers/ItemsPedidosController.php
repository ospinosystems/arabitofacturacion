<?php

namespace App\Http\Controllers;

use App\Models\items_pedidos;
use App\Models\tareaslocal;
use App\Models\pedidos;

use Illuminate\Http\Request;
use Response;

class ItemsPedidosController extends Controller
{
    
    function delitemduplicate() {

        $du = items_pedidos::selectRaw("id_producto, id_pedido, COUNT(*) as count")->groupByRaw("id_producto, id_pedido")->havingRaw("COUNT(*) > 1")->get();

        foreach ($du as $key => $val) {
            $id_pedido = $val["id_pedido"]; 
            $id_producto = $val["id_producto"]; 
            $count = $val["count"]-1;

            items_pedidos::where("id_pedido",$id_pedido)->where("id_producto",$id_producto)->limit($count)->delete();

            echo "$id_producto __ $id_pedido ____ $count veces <br>";
        }
    }
    public function changeEntregado(Request $req)
    {
       try {
            $id = $req->id;
            $item = items_pedidos::find($id);
            if ($item) {
                if ($item->entregado) {
                    $item->entregado = false;
                }else{
                    $item->entregado = true;
                }
                $item->save();
                

            }

            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        } 
    }
    public function delItemPedido(Request $req)
    {   
        return (new InventarioController)->hacer_pedido($req->index,null,99,"del");
    }
   
    public function setPrecioAlternoCarrito(Request $req)
    {

        try {
            $iditem = $req->iditem;
            $p = $req->p;

            (new PedidosController)->checkPedidoAuth($iditem,"item");
            $checkPedidoPago = (new PedidosController)->checkPedidoPago($iditem,"item");
            if ($checkPedidoPago!==true) {
                return $checkPedidoPago;
            }

            $item = items_pedidos::with("producto")->find($iditem);
            if ($p=="p1"||$p=="p2") {
                if ($item) {
                    if ($p=="p1") {
                        $p1 = $item->producto->precio1;
                    }
                    if ($p=="p2") {
                        $p1 = $item->producto->precio2;
                    }
                    $ct = $item->cantidad;
                    $monto = $item->monto;

                    $objetivo = $ct*$p1;

                    $porcentaje_objetivo = (100-((floatval($objetivo)*100)/$monto));

                    // let total = parseFloat(pedidoData.clean_subtotal)

                    // descuento = (100-((parseFloat(descuento)*100)/total))

                    if ($porcentaje_objetivo) {
                        $item->descuento = floatval($porcentaje_objetivo);
                        $item->save();
                    }
                    

                    // return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
                    return Response::json(["msj"=>"p1 $p1","estado"=>true]);
                }
            }

            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }
    }

    
    public function setDescuentoUnitario(Request $req)
    {
        try {

            $item = items_pedidos::find($req->index);
            if (!$item) {
                return Response::json(["msj"=>"Error: Ítem no encontrado","estado"=>false]);
            }

            // SOBRECOBRO 2026-06-13 (caso #426735) — NO permitir cambiar el descuento de un
            // pedido YA FACTURADO (estado=1): bajaría el total por debajo de lo ya cobrado y
            // dejaría un sobrecobro fantasma (efectivo > total). El cuadre solo valida al cerrar,
            // no cuando se toca un descuento después. Si hace falta, debe ir por devolución.
            $pedidoGuard = pedidos::find($item->id_pedido);
            if ($pedidoGuard && (int) $pedidoGuard->estado === 1) {
                return Response::json([
                    "msj" => "Error: El pedido #{$item->id_pedido} ya está facturado. No se puede aplicar o cambiar un descuento (generaría un sobrecobro). Use una devolución.",
                    "estado" => false
                ]);
            }

            $descuento = floatval($req->descuento);
            if ($descuento > 0) {
                // Verificar cliente (sigue siendo un requisito real; central exigirá id_cliente en la solicitud)
                $pedido = pedidos::with('cliente')->find($item->id_pedido);
                if (!$pedido || $pedido->id_cliente == 1) {
                    return Response::json(["msj"=>"Error: Debe registrar un cliente para aplicar descuentos","estado"=>false]);
                }

                // UNIFICACIÓN 2026-05-27 — Antes acá se hacía un round-trip a central para crear o
                // verificar una solicitud `monto_porcentaje`, y la aprobada por el admin era OTRA
                // distinta de la que se enviaba al facturar (`metodo_pago`). Resultado: 2 solicitudes
                // por pedido, 2 aprobaciones del admin.
                //
                // Ahora el descuento se guarda LOCALMENTE igual que en pedidos front-only. La única
                // solicitud sale en `setPagoPedido` (tipo `metodo_pago`) ya con descuento + métodos
                // de pago juntos. UNA solicitud por pedido, una aprobación.
                $item->descuento = $descuento;
                $item->save();
                return Response::json([
                    "msj" => "Descuento aplicado al ítem. Al guardar el pedido se enviará la solicitud a central junto con el método de pago.",
                    "estado" => true
                ]);
            }
            if ($descuento<0) {
                return Response::json(["msj"=>"Error: No puede ser Negativo","estado"=>false]);
            }
           /*  $checkItemsPedidoCondicionGarantiaCero = (new PedidosController)->checkItemsPedidoCondicionGarantiaCero($item->id_pedido);
            if ($checkItemsPedidoCondicionGarantiaCero!==true) {
                return Response::json(["msj"=>"Error: El pedido tiene garantias, no puede aplicar descuento unitario","estado"=>false]);
            } */
            $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                "id_pedido" => $item->id_pedido,
                "tipo" => "descuentoUnitario",
            ]);

            if ((new UsuariosController)->isAdmin()) {
                (new PedidosController)->checkPedidoAuth($req->index,"item");
                $checkPedidoPago = (new PedidosController)->checkPedidoPago($req->index,"item");
                if ($checkPedidoPago!==true) {
                    return $checkPedidoPago;
                }
                $item->descuento = $descuento;

                $item->save();
                return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
                
            }elseif($isPermiso["permiso"]){
                // Comparar con tolerancia de 0.01 para evitar problemas de decimales
                $valorAprobado = floatval($isPermiso["valoraprobado"]);
                if (abs($valorAprobado - $descuento) <= 0.01) {
                    $item->descuento = $descuento;
                    $item->save();
                    return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
                }else{
                    return Response::json(["msj"=>"Error: Valor no aprobado. Aprobado: {$valorAprobado}%, Solicitado: {$descuento}%","estado"=>false]);

                }
            }else{

                $nuevatarea = (new TareaslocalController)->createTareaLocal([
                    "id_pedido" =>  $item->id_pedido,
                    "valoraprobado" => $descuento,
                    "tipo" => "descuentoUnitario",
                    "descripcion" => "Solicitud de descuento Unitario: ".$req->descuento."%",
                ]);
                if ($nuevatarea) {
                    return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                }

            }
            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }
    }

    public function setCantidad(Request $req)
    {
        // Verificar si el pedido tiene factura original asignada (es devolución)
        $item = items_pedidos::find($req->index);
        if ($item) {
            $pedido = pedidos::find($item->id_pedido);
            if ($pedido && $pedido->isdevolucionOriginalid) {
                return Response::json([
                    "msj" => "No se puede modificar la cantidad en un pedido de devolución vinculado a factura original",
                    "estado" => false
                ]);
            }
        }

        return (new InventarioController)->hacer_pedido($req->index,null,floatval($req->cantidad),"upd");
    }

    
    public function setDescuentoTotal(Request $req){
        try {
            // Verificar si el pedido tiene factura original asignada (es devolución)
            $pedido = pedidos::find($req->index);
            if ($pedido && $pedido->isdevolucionOriginalid) {
                return Response::json([
                    "msj" => "No se puede aplicar descuento en un pedido de devolución vinculado a factura original",
                    "estado" => false
                ]);
            }

            // SOBRECOBRO 2026-06-13 (caso #426735) — NO permitir cambiar el descuento de un
            // pedido YA FACTURADO (estado=1): bajaría el total por debajo de lo ya cobrado y
            // dejaría un sobrecobro fantasma (efectivo > total). Si hace falta, vía devolución.
            if ($pedido && (int) $pedido->estado === 1) {
                return Response::json([
                    "msj" => "Error: El pedido #{$req->index} ya está facturado. No se puede aplicar o cambiar un descuento (generaría un sobrecobro). Use una devolución.",
                    "estado" => false
                ]);
            }

            $descuento = floatval($req->descuento);
            if ($descuento > 0) {
                // Verificar cliente (sigue siendo un requisito real; central exigirá id_cliente en la solicitud)
                $pedido = pedidos::with('cliente')->find($req->index);
                if (!$pedido || $pedido->id_cliente == 1) {
                    return Response::json(["msj"=>"Error: Debe registrar un cliente para aplicar descuentos","estado"=>false]);
                }

                // UNIFICACIÓN 2026-05-27 — Ver explicación completa en setDescuentoUnitario.
                // Resumen: el descuento se guarda LOCALMENTE; la única solicitud sale en
                // setPagoPedido (tipo `metodo_pago`) con descuento + métodos de pago juntos.
                items_pedidos::where("id_pedido", $req->index)->update(["descuento" => $descuento]);
                return Response::json([
                    "msj" => "Descuento total aplicado. Al guardar el pedido se enviará la solicitud a central junto con el método de pago.",
                    "estado" => true
                ]);
            }
            if ($descuento<0) {
                return Response::json(["msj"=>"Error: No puede ser Negativo","estado"=>false]);
            }
            $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                "id_pedido" => $req->index,
                "tipo" => "descuentoTotal",
            ]);
            
            $checkPedidoPago = (new PedidosController)->checkPedidoPago($req->index);
            if ($checkPedidoPago!==true) {
                return $checkPedidoPago;
            }

            $checkItemsPedidoCondicionGarantiaCero = (new PedidosController)->checkItemsPedidoCondicionGarantiaCero($req->index);
            if ($checkItemsPedidoCondicionGarantiaCero!==true) {
                return Response::json(["msj"=>"Error: El pedido tiene garantias, no puede aplicar descuento total","estado"=>false]);
            }

            if ((new UsuariosController)->isAdmin()) {
                (new PedidosController)->checkPedidoAuth($req->index,"pedido");
                items_pedidos::where("id_pedido",$req->index)->update(["descuento"=>$descuento]);
                return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
            }elseif($isPermiso["permiso"]){
                (new PedidosController)->checkPedidoAuth($req->index,"pedido");
                if ($isPermiso["valoraprobado"]==round($descuento,0)) {

                    items_pedidos::where("id_pedido",$req->index)->update(["descuento"=>$descuento]);
                    return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
                }else{
                    return Response::json(["msj"=>"Error: Valor no aprobado","estado"=>false]);
                }
            }else{
                $nuevatarea = (new TareaslocalController)->createTareaLocal([
                    "id_pedido" =>  $req->index,
                    "valoraprobado" => round($descuento,0),
                    "tipo" => "descuentoTotal",
                    "descripcion" => "Solicitud de descuento Total: ".round($descuento,0)." %",
                ]);
                if ($nuevatarea) {
                    return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                }
            }
            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }
    }
}
