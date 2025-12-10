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

            $descuento = floatval($req->descuento);
            if ($descuento>15) {
                // Verificar si existe cliente en el pedido
                $pedido = pedidos::with('cliente')->find($item->id_pedido);
                if (!$pedido || $pedido->id_cliente == 1) {
                    return Response::json(["msj"=>"Error: Debe registrar un cliente para solicitar descuentos superiores al 15%","estado"=>false]);
                }

                // Verificar si ya existe una solicitud para este pedido
                $solicitudExistente = (new sendCentral)->verificarSolicitudDescuento([
                    'id_sucursal' => (new sendCentral)->getOrigen(),
                    'id_pedido' => $item->id_pedido,
                    'tipo_descuento' => 'monto_porcentaje'
                ]);

                if ($solicitudExistente && isset($solicitudExistente['existe']) && $solicitudExistente['existe']) {
                    if ($solicitudExistente['data']['estado'] === 'enviado') {
                        return Response::json(["msj"=>"Ya existe una solicitud de descuento en espera de aprobación","estado"=>false]);
                    } elseif ($solicitudExistente['data']['estado'] === 'aprobado') {
                        // Validar que el porcentaje de descuento sea exactamente igual al aprobado
                        $porcentaje_aprobado = floatval($solicitudExistente['data']['porcentaje_descuento']);
                        if (abs($descuento - $porcentaje_aprobado) > 0.01) {
                            return Response::json([
                                "msj"=>"Error: El porcentaje de descuento ({$descuento}%) debe ser exactamente igual al aprobado ({$porcentaje_aprobado}%)",
                                "estado"=>false
                            ]);
                        }
                        // Continuar con el descuento aprobado
                        $item->descuento = $descuento;
                        $item->save();
                        return Response::json(["msj"=>"¡Éxito! Descuento aplicado con aprobación previa","estado"=>true]);
                    } elseif ($solicitudExistente['data']['estado'] === 'rechazado') {
                        return Response::json(["msj"=>"La solicitud de descuento fue rechazada","estado"=>false]);
                    }
                }

                // Crear nueva solicitud de descuento
                $items_pedido = items_pedidos::with('producto')->where('id_pedido', $item->id_pedido)->get();
                $monto_bruto = $items_pedido->sum('monto');
                
                // Calcular el descuento real item por item
                $monto_descuento_real = 0;
                foreach ($items_pedido as $item_pedido) {
                    $subtotal_item = $item_pedido->cantidad * ($item_pedido->producto ? $item_pedido->producto->precio : 0);
                    $descuento_item = $subtotal_item * ($descuento / 100);
                    $monto_descuento_real += $descuento_item;
                }
                
                $monto_con_descuento = $monto_bruto - $monto_descuento_real;

                $ids_productos = $items_pedido->map(function($item_pedido) use ($descuento) {
                    $subtotal_item = $item_pedido->cantidad * ($item_pedido->producto ? $item_pedido->producto->precio : 0);
                    $subtotal_con_descuento = $subtotal_item * (1 - ($descuento / 100));
                    
                    return [
                        'id_producto' => $item_pedido->id_producto,
                        'cantidad' => $item_pedido->cantidad,
                        'precio' => $item_pedido->producto ? $item_pedido->producto->precio : 0,
                        'precio_base' => $item_pedido->producto ? $item_pedido->producto->precio_base : 0,
                        'subtotal' => $subtotal_item,
                        'subtotal_con_descuento' => $subtotal_con_descuento,
                        'porcentaje_descuento' => $descuento
                    ];
                })->toArray();

                $resultado = (new sendCentral)->crearSolicitudDescuento([
                    'id_sucursal' => (new sendCentral)->getOrigen(),
                    'id_pedido' => $item->id_pedido,
                    'fecha' => now(),
                    'monto_bruto' => $monto_bruto,
                    'monto_con_descuento' => $monto_con_descuento,
                    'monto_descuento' => $monto_descuento_real,
                    'porcentaje_descuento' => $descuento,
                    'id_cliente' => $pedido->id_cliente,
                    'usuario_ensucursal' => session('usuario'),
                    'metodos_pago' => [],
                    'ids_productos' => $ids_productos,
                    'tipo_descuento' => 'monto_porcentaje',
                    'observaciones' => "Solicitud de descuento unitario: {$descuento}%"
                ]);

                if ($resultado && isset($resultado['estado']) && $resultado['estado']) {
                    return Response::json(["msj"=>"Solicitud de descuento enviada a central para aprobación","estado"=>false]);
                } else {
                    \Log::info("Error al enviar solicitud de descuento. setDescuentoUnitario",["resultado"=>$resultado]);
                    return $resultado;
                    return Response::json(["msj"=>"Error al enviar solicitud de descuento","estado"=>false]);
                }
            }
            if ($descuento<0) {
                return Response::json(["msj"=>"Error: No puede ser Negativo","estado"=>false]);
            }
            $checkItemsPedidoCondicionGarantiaCero = (new PedidosController)->checkItemsPedidoCondicionGarantiaCero($item->id_pedido);
            if ($checkItemsPedidoCondicionGarantiaCero!==true) {
                return Response::json(["msj"=>"Error: El pedido tiene garantias, no puede aplicar descuento unitario","estado"=>false]);
            }
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

            $descuento = floatval($req->descuento);
            if ($descuento>15) {
                // Verificar si existe cliente en el pedido
                $pedido = pedidos::with('cliente')->find($req->index);
                if (!$pedido || $pedido->id_cliente == 1) {
                    return Response::json(["msj"=>"Error: Debe registrar un cliente para solicitar descuentos superiores al 15%","estado"=>false]);
                }

                // Verificar si ya existe una solicitud para este pedido
                $solicitudExistente = (new sendCentral)->verificarSolicitudDescuento([
                    'id_sucursal' => (new sendCentral)->getOrigen(),
                    'id_pedido' => $req->index,
                    'tipo_descuento' => 'monto_porcentaje'
                ]);

                if ($solicitudExistente && isset($solicitudExistente['existe']) && $solicitudExistente['existe']) {
                    if ($solicitudExistente['data']['estado'] === 'enviado') {
                        return Response::json(["msj"=>"Ya existe una solicitud de descuento en espera de aprobación","estado"=>false]);
                    } elseif ($solicitudExistente['data']['estado'] === 'aprobado') {
                        // Validar que el porcentaje de descuento sea exactamente igual al aprobado
                        $porcentaje_aprobado = floatval($solicitudExistente['data']['porcentaje_descuento']);
                        if (abs($descuento - $porcentaje_aprobado) > 0.01) {
                            return Response::json([
                                "msj"=>"Error: El porcentaje de descuento ({$descuento}%) debe ser exactamente igual al aprobado ({$porcentaje_aprobado}%)",
                                "estado"=>false
                            ]);
                        }
                        // Continuar con el descuento aprobado
                        items_pedidos::where("id_pedido",$req->index)->update(["descuento"=>$descuento]);
                        return Response::json(["msj"=>"¡Éxito! Descuento aplicado con aprobación previa","estado"=>true]);
                    } elseif ($solicitudExistente['data']['estado'] === 'rechazado') {
                        return Response::json(["msj"=>"La solicitud de descuento fue rechazada","estado"=>false]);
                    }
                }

                // Crear nueva solicitud de descuento
                $items_pedido = items_pedidos::with('producto')->where('id_pedido', $req->index)->get();
                $monto_bruto = $items_pedido->sum('monto');
                
                // Calcular el descuento real item por item
                $monto_descuento_real = 0;
                foreach ($items_pedido as $item_pedido) {
                    $subtotal_item = $item_pedido->cantidad * ($item_pedido->producto ? $item_pedido->producto->precio : 0);
                    $descuento_item = $subtotal_item * ($descuento / 100);
                    $monto_descuento_real += $descuento_item;
                }
                
                $monto_con_descuento = $monto_bruto - $monto_descuento_real;

                $ids_productos = $items_pedido->map(function($item_pedido) use ($descuento) {
                    $subtotal_item = $item_pedido->cantidad * ($item_pedido->producto ? $item_pedido->producto->precio : 0);
                    $subtotal_con_descuento = $subtotal_item * (1 - ($descuento / 100));
                    
                    return [
                        'id_producto' => $item_pedido->id_producto,
                        'cantidad' => $item_pedido->cantidad,
                        'precio' => $item_pedido->producto ? $item_pedido->producto->precio : 0,
                        'precio_base' => $item_pedido->producto ? $item_pedido->producto->precio_base : 0,
                        'subtotal' => $subtotal_item,
                        'subtotal_con_descuento' => $subtotal_con_descuento,
                        'porcentaje_descuento' => $descuento
                    ];
                })->toArray();

                $resultado = (new sendCentral)->crearSolicitudDescuento([
                    'id_sucursal' => (new sendCentral)->getOrigen(),
                    'id_pedido' => $req->index,
                    'fecha' => now(),
                    'monto_bruto' => $monto_bruto,
                    'monto_con_descuento' => $monto_con_descuento,
                    'monto_descuento' => $monto_descuento_real,
                    'porcentaje_descuento' => $descuento,
                    'id_cliente' => $pedido->id_cliente,
                    'usuario_ensucursal' => session('usuario'),
                    'metodos_pago' => [],
                    'ids_productos' => $ids_productos,
                    'tipo_descuento' => 'monto_porcentaje',
                    'observaciones' => "Solicitud de descuento total: {$descuento}%"
                ]);

                if ($resultado && isset($resultado['estado']) && $resultado['estado']) {
                    return Response::json(["msj"=>"Solicitud de descuento enviada a central para aprobación","estado"=>false]);
                } else {
                    return $resultado;
                    return Response::json(["msj"=>"Error al enviar solicitud de descuento","estado"=>false]);
                }
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
