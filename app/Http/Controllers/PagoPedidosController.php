<?php

namespace App\Http\Controllers;

use App\Models\pagos_referencias;
use App\Models\retenciones;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;
use App\Models\clientes;
use App\Models\sucursal;
use App\Models\catcajas;



use Illuminate\Http\Request;

use App\Http\Controllers\PedidosController;
use App\Http\Controllers\sendCentral;
use Response;

class PagoPedidosController extends Controller
{   
    public function setconfigcredito(Request $req)
    {
        try {

            $fechainiciocredito = $req->fechainiciocredito;
            $fechavencecredito = $req->fechavencecredito;
            $formatopagocredito = $req->formatopagocredito;
            $id_pedido = $req->id_pedido;

            $ped = pedidos::find($id_pedido);
            if ($ped->id_cliente==1) {
                return Response::json(["msj"=>"Error: En caso de crédito, debe registrar los datos del cliente","estado"=>false]);
            }

            $ped->fecha_inicio = $fechainiciocredito;
            $ped->fecha_vence = $fechavencecredito;
            $ped->formato_pago = $formatopagocredito;

            $ped->save();
            
            return Response::json(["msj"=>"Configuracion de crédito registrada con éxito","estado"=>true]);

            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);

            
        }

    }
    public function entregarVuelto(Request $req)
    {
        try {
            $id_pedido = $req->id_pedido;
            $monto = floatval($req->monto);

            $mov = new movimientos_caja;

            if ($monto) {

                $total_acumulado = movimientos_caja::where("id_pedido",$id_pedido)
                ->sum("monto");

                $pendiente = pago_pedidos::where("tipo",6)->where("id_pedido",$id_pedido)
                ->sum("monto");

                if (($total_acumulado+$monto)<=$pendiente) {
                    $mov->id_pedido = $id_pedido;
                    $mov->categoria = 1;
                    $mov->descripcion = "VUELTO ENTREGADO Ped.".$id_pedido;
                    $mov->tipo = 1;
                    $mov->monto = $monto;
                    $mov->id_vendedor = session('id_usuario');


                    if ($mov->save()) {
                        return Response::json(["msj"=>"Éxito a entregar","estado"=>true]);
                    }
                }else{
                    $pen = $pendiente-$total_acumulado;

                    throw new \Exception("¡Solo quedan ".$pen." por entregar!", 1);
                }

            }else{
                throw new \Exception("¡Monto en cero!", 1);
                
            }

            
        } catch (\Exception $e) {
            
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }

        
    }
    function setPagoPedidoTrans(Request $req) {
        $id = $req->id;
        pago_pedidos::where("id_pedido",$id)->delete();
        pago_pedidos::updateOrCreate(["id_pedido"=>$id,"tipo"=>4],["cuenta"=>1,"monto"=>0.00001]);
        $pedido = pedidos::find($id);

        if ($pedido->estado==0) {
            $pedido->estado = 1;
            $pedido->save();
        }

        return ["estado"=>true, "msj" => "Guardado como transferencia de inventario"];
        
    }
    public function setPagoPedido(Request $req)
    {   
        // Iniciar transacción de base de datos
        \DB::beginTransaction();
        

        try {
            $metodos_pago = [];
        if ($req->efectivo && floatval($req->efectivo)) {
            $metodos_pago[] = ['tipo' => 'efectivo', 'monto' => floatval($req->efectivo)];
        }
        if ($req->debito && floatval($req->debito)) {
            $metodos_pago[] = ['tipo' => 'debito', 'monto' => floatval($req->debito)];
        }
        if ($req->transferencia && floatval($req->transferencia)) {
            $metodos_pago[] = ['tipo' => 'transferencia', 'monto' => floatval($req->transferencia)];
        }
        if ($req->biopago && floatval($req->biopago)) {
            $metodos_pago[] = ['tipo' => 'biopago', 'monto' => floatval($req->biopago)];
        }
        if ($req->credito && floatval($req->credito)) {
            $metodos_pago[] = ['tipo' => 'credito', 'monto' => floatval($req->credito)];
        }


        if (count($metodos_pago) > 1) {
            $montos = array_column($metodos_pago, 'monto');
            $tienePositivos = count(array_filter($montos, function($monto) { return $monto > 0; })) > 0;
            $tieneNegativos = count(array_filter($montos, function($monto) { return $monto < 0; })) > 0;

            
            if ($tienePositivos && $tieneNegativos) {
                \DB::rollback();
                return Response::json([
                    "msj" => "Error: No se pueden mezclar métodos de pago positivos y negativos en la misma transacción",
                    "estado" => false
                ]);
            }
        }
        // Si el usuario es admin, no permitir el pago
        if (session('tipo_usuario')==1) {
            \DB::rollback();
            return Response::json([
                "msj" => "Error: Los usuarios administradores no pueden procesar pagos de pedidos. Actualmente estás logueado como administrador.",
                "estado" => false
            ]);
        }

        // Validar que todos los métodos de pago tengan el mismo signo (todos positivos o todos negativos)

       



        
        $ped = (new PedidosController)->getPedido($req);

        if (!isset($ped->items) || $ped->items->count()==0) {
            \DB::rollback();
            return Response::json([
                "msj" => "Error: El pedido no tiene items, no se puede procesar el pago",
                "estado" => false
            ]);
        }

        // NUEVA LÓGICA: Verificar si hay items con cantidad negativa (garantías/devoluciones)
        $itemsConCantidadNegativa = items_pedidos::where("id_pedido", $req->id)
            ->where("cantidad", "<", 0)
            ->get();

        if ($itemsConCantidadNegativa->count() > 0) {
            // Hay items con cantidad negativa, buscar solicitudes de garantía en arabito central
            /* try {
                $solicitudesGarantia = (new sendCentral)->buscarSolicitudesGarantiaPorPedido($req->id);
                
                if (isset($solicitudesGarantia['success']) && $solicitudesGarantia['success']) {
                    $solicitudes = $solicitudesGarantia['solicitudes'] ?? [];
                    
                    if (count($solicitudes) > 0) {
                        // Encontrar la solicitud aprobada
                        $solicitudAprobada = collect($solicitudes)->firstWhere('estatus', 'FINALIZADA');
                        
                        if ($solicitudAprobada) {
                            $facturaOriginal = $solicitudAprobada['factura_venta_id'] ?? null;
                            $modo_traslado_interno = $solicitudAprobada['modo_traslado_interno'] ?? null;
                            
                            if ($facturaOriginal) {
                                // Verificar que algún pago de la factura original esté en la factura final
                                if (!$this->validarPagoGarantia($facturaOriginal, $req->id, $req)) {
                                    \DB::rollback();
                                    return Response::json([
                                        "msj" => "Error: Para procesar garantías/devoluciones, debe incluir al menos un método de pago de la factura original #{$facturaOriginal}",
                                        "estado" => false
                                    ]);
                                }
                                
                                \Log::info("Validación de pago de garantía exitosa", [
                                    'pedido_garantia' => $req->id,
                                    'factura_original' => $facturaOriginal,
                                    'solicitud_id' => $solicitudAprobada['id']
                                ]);
                            } else {
                                if ($modo_traslado_interno==0) {
                                    \DB::rollback();
                                    return Response::json([
                                        "msj" => "Error: No se encontró la factura original en la solicitud de garantía",
                                        "estado" => false
                                    ]);
                                }
                            }
                        } else {
                            \DB::rollback();
                            return Response::json([
                                "msj" => "Error: No se encontró una solicitud de garantía FINALIZADA para este pedido",
                                "estado" => false
                            ]);
                        }
                    } else {
                        \DB::rollback();
                        return Response::json([
                            "msj" => "Error: No se encontraron solicitudes de garantía para este pedido en arabito central",
                            "estado" => false
                        ]);
                    }
                } else {
                    $error = $solicitudesGarantia['error'] ?? 'Error desconocido';
                    \DB::rollback();
                    return Response::json([
                        "msj" => "Error al consultar solicitudes de garantía: {$error}",
                        "estado" => false
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error("Error al validar solicitudes de garantía", [
                    'pedido_id' => $req->id,
                    'error' => $e->getMessage()
                ]);
                
                \DB::rollback();
                return Response::json([
                    "msj" => "Error al validar solicitudes de garantía: " . $e->getMessage(),
                    "estado" => false
                ]);
            } */
        }

        // Verificar productos duplicados en el pedido
        $items = items_pedidos::where("id_pedido", $req->id)
            ->whereNotNull("id_producto")
            ->get();
        
        $productos = [];

        $todosCondicionCero = true;
        foreach ($items as $itm) {
            if ($itm->condicion != 0) {
                $todosCondicionCero = false;
                break;
            }
        }
        if ($todosCondicionCero) {
            foreach ($items as $item) {
                if (isset($productos[$item->id_producto])) {
                    \DB::rollback();
                    return Response::json([
                        "msj" => "Error: El producto ID " . $item->id_producto . " está duplicado en el pedido",
                        "estado" => false
                    ]);
                }
                $productos[$item->id_producto] = true;
            }
        } 



        $total_real = $ped->clean_total;
        
        // Obtener tasas del primer ítem del pedido (tasa histórica al momento de facturar)
        $primerItem = items_pedidos::where("id_pedido", $req->id)->first();
        $tasaDolar = $primerItem ? floatval($primerItem->tasa) : 1;
        $tasaPeso = $primerItem ? floatval($primerItem->tasa_cop) : 1;
        if ($tasaDolar <= 0) $tasaDolar = 1;
        if ($tasaPeso <= 0) $tasaPeso = 1;
        
        // Calcular total de métodos de pago principales
        // NOTA: debito viene en Bs, hay que convertirlo a USD
        $debitoUSD = $req->debito ? floatval($req->debito) / $tasaDolar : 0;
        $total_ins = $debitoUSD + floatval($req->efectivo) + floatval($req->transferencia) + floatval($req->biopago) + floatval($req->credito);
        
        // Agregar pagos adicionales de efectivo (Bs y Pesos) convertidos a dólares
        $totalAdicUsdBs = 0;
        $totalAdicUsdCop = 0;
        $totalAdicUsdDolar = 0;
        if ($req->pagosAdicionales && is_array($req->pagosAdicionales)) {
            foreach ($req->pagosAdicionales as $pagoAdicional) {
                if (isset($pagoAdicional['moneda']) && isset($pagoAdicional['monto_original'])) {
                    $monto = floatval($pagoAdicional['monto_original']);
                    
                    if ($pagoAdicional['moneda'] === 'bs' && $tasaDolar > 0) {
                        // Convertir Bs a dólares usando tasa del pedido
                        $total_ins += $monto / $tasaDolar;
                        $totalAdicUsdBs += $monto / $tasaDolar;
                    } elseif ($pagoAdicional['moneda'] === 'peso' && $tasaPeso > 0) {
                        // Convertir Pesos a dólares usando tasa del pedido
                        $total_ins += $monto / $tasaPeso;
                        $totalAdicUsdCop += $monto / $tasaPeso;
                    } elseif ($pagoAdicional['moneda'] === 'dolar') {
                        // Ya está en dólares
                        $total_ins += $monto;
                        $totalAdicUsdDolar += $monto;
                    }
                }
            }
        }

       
        
        /* if ($total_ins < 0 || itemsConCantidadNegativa->count() > 0) {
            $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                "id_pedido" => $req->id,
                "tipo" => "devolucion",
            ]);
            if($isPermiso["permiso"]){
                if ($isPermiso["valoraprobado"]==round($total_ins,0)) {
                    // Avanza
                }else{
                    \DB::rollback();
                    return Response::json(["msj"=>"Error: Valor no aprobado","estado"=>false]);
                }
            }else{
                $nuevatarea = (new TareaslocalController)->createTareaLocal([
                    "id_pedido" =>  $req->id,
                    "valoraprobado" => round($total_ins,0),
                    "tipo" => "devolucion",
                    "descripcion" => "Solicitud de Devolucion: ".round($total_ins,0)." $",
                ]);
                if ($nuevatarea) {
                    \DB::commit();
                    return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                }
            }
        } */

        if ($req->credito!=0) {
            $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                "id_pedido" => $req->id,
                "tipo" => "credito",
            ]);
            if ((new UsuariosController)->isAdmin()) {
                // Avanza
            }elseif($isPermiso["permiso"]){
                if ($isPermiso["valoraprobado"]==round($req->credito,0)) {
                    // Avanza
                }else{
                    \DB::rollback();
                    return Response::json(["msj"=>"Error: Valor no aprobado","estado"=>false]);
                }
            }else{
                $nuevatarea = (new TareaslocalController)->createTareaLocal([
                    "id_pedido" =>  $req->id,
                    "valoraprobado" => round($req->credito,0),
                    "tipo" => "credito",
                    "descripcion" => "Solicitud de Crédito: ".round($req->credito,0)." $",
                ]);
                if ($nuevatarea) {
                    \DB::commit();
                    return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                }
            }
        }
        if ($req->credito!=0&&$ped->id_cliente==1) {
            \DB::rollback();
            return Response::json(["msj"=>"Error: En caso de crédito, debe registrar los datos del cliente","estado"=>false]);
        }
       
        $res = $total_real-$total_ins;
        if ($res > -0.2 && $res < 0.2) {
               // 1 Transferencia
               // 2 Debito 
               // 3 Efectivo 
               // 4 Credito  
               // 5 Biopago
               // 6 vuelto
            try {
                (new PedidosController)->checkPedidoAuth($req->id);
                $checkPedidoPago = (new PedidosController)->checkPedidoPago($req->id);
                if ($checkPedidoPago!==true) {
                    return $checkPedidoPago;
                }
                // Validación de transferencias
                if ($req->transferencia) {
                    $monto_tra = floatval($req->transferencia);
                    $montodolares = 0.0;
                    $bs = (new PedidosController)->get_moneda()["bs"];
                    
                    // Obtener todas las referencias del pedido
                    $referencias = pagos_referencias::where("id_pedido", $req->id)->get();
                    
                    // Verificar que existan referencias si se está procesando una transferencia
                    if ($referencias->count()==0) {
                        throw new \Exception("Error: Para procesar una transferencia debe cargar al menos una referencia de pago.", 1);
                    }
                    
                    // Calcular el monto total en dólares de las referencias
                    foreach ($referencias as $referencia) {
                        $monto_ref = floatval($referencia->monto);
                        
                        if (
                            $referencia->banco == "ZELLE" ||
                            $referencia->banco == "BINANCE" ||
                            $referencia->banco == "AirTM"
                        ) {
                            // Bancos en dólares - usar monto directo
                            $montodolares += $monto_ref;
                        } else {
                            // Bancos en bolívares - convertir a dólares
                            if ($bs > 0) {
                                $montodolares += ($monto_ref / $bs);
                            } else {
                                throw new \Exception("Error: Tasa de cambio (BS) no válida para conversión.", 1);
                            }
                        }
                    }
                    
                    // Calcular diferencia con precisión de 6 decimales para mayor precisión
                    $diff = round($monto_tra - $montodolares, 6);
                    $tolerancia = 0.05; // Tolerancia de 5 centavos para casos de redondeo
                    
                    // Log para debugging
                    \Log::info("Validación transferencia - Pedido: {$req->id}", [
                        'monto_transferencia' => $monto_tra,
                        'monto_referencias_dolares' => $montodolares,
                        'diferencia' => $diff,
                        'tolerancia' => $tolerancia,
                        'referencias_count' => $referencias->count(),
                        'diferencia_absoluta' => abs($diff)
                    ]);
                    
                    // Validar que la diferencia esté dentro de la tolerancia
                    if (abs($diff) > $tolerancia) {
                        // Log adicional para casos que fallan
                        \Log::warning("Validación transferencia falló - Pedido: {$req->id}", [
                            'monto_transferencia' => $monto_tra,
                            'monto_referencias_dolares' => $montodolares,
                            'diferencia' => $diff,
                            'diferencia_absoluta' => abs($diff),
                            'tolerancia' => $tolerancia
                        ]);
                        $monto_tra = moneda($monto_tra);
                        $montodolares = moneda($montodolares);
                        $diff = moneda($diff);
                        throw new \Exception(
                            "Error: El monto de transferencia ($monto_tra) no coincide con las referencias cargadas ($montodolares). " .
                            "Diferencia: ($diff). " .
                            "Debe pagar exactamente lo que indica el sistema.",
                            1
                        );
                    }
                    
                    \Log::info("Transferencia validada correctamente - Pedido: {$req->id}");
                    
                } else {
                    // Si no es transferencia, verificar que no haya referencias cargadas
                    $check_ref = pagos_referencias::where("id_pedido", $req->id)->first();
                    if ($check_ref) {
                        throw new \Exception("Error: Tiene referencias de pago cargadas pero no está procesando una transferencia. " .
                                           "Debe seleccionar 'Transferencia' como método de pago.", 1);
                    }
                }


                $cuenta = 1;
                $checkIfAbono = items_pedidos::where("id_producto",NULL)->where("id_pedido",$req->id)->get()->count();
                if ($checkIfAbono && !$req->credito) {
                    //Es Abono
                    $cuenta = 0;
                }else{
                    //No es abono
                }
                pago_pedidos::where("id_pedido",$req->id)->delete();
                if($req->transferencia) {
                    // Validar descuentos antes de procesar transferencia
                    

                    $refs = pagos_referencias::where("id_pedido",$req->id)->get();
                    $retenciones = retenciones::where("id_pedido",$req->id)->get();
                    
                    $dataTransfe = [
                        "refs" => $refs,
                        "retenciones" => $retenciones,
                    ];

                    // Verificar que todas las referencias de este pedido tengan estatus "aprobada"
                    $todasAprobadas = true;
                    foreach ($refs as $ref) {
                        if (strtolower($ref->estatus) !== "aprobada") {
                            $todasAprobadas = false;
                            break;
                        }
                    }
                    if (!$todasAprobadas) {
                        return Response::json([
                            "msj" => "Error: Todas las referencias de pago deben estar en estatus 'aprobada' para procesar la transferencia.",
                            "estado" => false
                        ]);
                    }else{
                        $resultadoValidacion = $this->validarDescuentosPorMetodoPago($req->id, $req->transferencia, $metodos_pago, $cuenta, 1);
                        if ($resultadoValidacion !== true) {
                            return $resultadoValidacion;
                        }
                    }


                    /* try {
                        $transfResult = (new sendCentral)->createTranferenciaAprobacion($dataTransfe);
                        if (isset($transfResult["estado"])) {
                            if ($transfResult["estado"]==true && $transfResult["msj"]=="APROBADO") {
                            //if (true) {
                                
                            }else{
                                return $transfResult;
                            }
                        }else{
                            return $transfResult;
                        }
                    } catch (\Exception $e) {
                        return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
                    } */

                }
                // Obtener tasas del pedido (del primer item)
                $itemPedido = \App\Models\items_pedidos::where('id_pedido', $req->id)->first();
                $tasaDolar = $itemPedido ? floatval($itemPedido->tasa) : 1;
                $tasaPeso = $itemPedido ? floatval($itemPedido->tasa_cop) : 1;
                
                // Asegurar tasas válidas
                if ($tasaDolar <= 0) $tasaDolar = 1;
                if ($tasaPeso <= 0) $tasaPeso = 1;
                
                if($req->debito) {
                    $montoOriginalBs = floatval($req->debito); // Viene en Bs
                    $montoDebito = $montoOriginalBs / $tasaDolar; // Convertir a USD
                    
                    // Validar referencia obligatoria para débito SOLO si es positivo (no devolución)
                    if ($montoOriginalBs > 0 && !$req->debitoRef) {
                        \DB::rollback();
                        return Response::json([
                            "msj" => "Error: Debe ingresar la referencia del pago con débito",
                            "estado" => false
                        ]);
                    }
                    
                    // Validar que la referencia sea de exactamente 4 dígitos
                   /*  if ($req->debitoRef && strlen($req->debitoRef) != 4) {
                        \DB::rollback();
                        return Response::json([
                            "msj" => "Error: La referencia de débito debe ser de exactamente 4 dígitos",
                            "estado" => false
                        ]);
                    }
                    
                    // Validar que la referencia sea única para este usuario en el día actual
                    if ($req->debitoRef) {
                        $idUsuario = session('id_usuario');
                        $hoy = now()->toDateString();
                        
                        $referenciaExistente = pago_pedidos::where('referencia', $req->debitoRef)
                            ->where('tipo', 2) // tipo 2 = débito
                            ->where('id_pedido', '!=', $req->id) // excluir el pedido actual
                            ->whereDate('created_at', $hoy)
                            ->whereHas('pedido', function($query) use ($idUsuario) {
                                $query->where('id_vendedor', $idUsuario);
                            })
                            ->exists();
                        
                        if ($referenciaExistente) {
                            \DB::rollback();
                            return Response::json([
                                "msj" => "Error: La referencia de débito '{$req->debitoRef}' ya fue utilizada hoy. Por favor ingrese una referencia diferente.",
                                "estado" => false
                            ]);
                        }
                    } */
                    
                    $resultadoValidacion = $this->validarDescuentosPorMetodoPago($req->id, $montoDebito, $metodos_pago, $cuenta, 2);
                    if ($resultadoValidacion !== true) {
                        return $resultadoValidacion;
                    }
                    
                    // Guardar pago de débito con referencia
                    // monto = USD (convertido), monto_original = Bs, moneda = bs (siempre)
                    pago_pedidos::updateOrCreate(
                        ["id_pedido" => $req->id, "tipo" => 2],
                        [
                            "cuenta" => $cuenta, 
                            "monto" => $montoDebito, // Convertido a USD
                            "monto_original" => $montoOriginalBs, // Valor original en Bs
                            "moneda" => "bs", // Débito siempre es en Bs
                            "referencia" => $req->debitoRef ?? null
                        ]
                    );
                }
                // Función para recopilar todos los métodos de pago
               

                if($req->efectivo) {
                    // Efectivo en dólares: monto ya está en USD, moneda = dolar
                    pago_pedidos::updateOrCreate(
                        ["id_pedido"=>$req->id, "tipo"=>3, "moneda"=>"dolar"],
                        ["cuenta"=>$cuenta, "monto"=>floatval($req->efectivo), "moneda"=>"dolar"]
                    );
                }
                
                // Procesar pagos adicionales de efectivo por moneda
                // Recibe monto_original en moneda original, convierte a USD usando tasas del pedido
                if($req->pagosAdicionales && is_array($req->pagosAdicionales)) {
                    foreach($req->pagosAdicionales as $pagoAdicional) {
                        if(isset($pagoAdicional['moneda']) && isset($pagoAdicional['monto_original']) && floatval($pagoAdicional['monto_original']) > 0) {
                            $montoOriginal = floatval($pagoAdicional['monto_original']);
                            $moneda = $pagoAdicional['moneda'];
                            
                            // Convertir a USD según la moneda
                            $montoUSD = $montoOriginal;
                            if ($moneda === 'bs') {
                                $montoUSD = $montoOriginal / $tasaDolar;
                            } elseif ($moneda === 'peso') {
                                $montoUSD = $montoOriginal / $tasaPeso;
                            }
                            
                            pago_pedidos::create([
                                "id_pedido" => $req->id,
                                "tipo" => 3, // Tipo efectivo
                                "cuenta" => $cuenta,
                                "monto" => $montoUSD, // Convertido a USD
                                "moneda" => $moneda, // bs, peso
                                "monto_original" => $montoOriginal // Valor en moneda original
                            ]);
                        }
                    }
                }
                
                if($req->credito) {
                    $pedido = pedidos::with("cliente")->find($req->id);
                    if ($pedido->cliente) {
                        
                        $id_cliente = $pedido->cliente["id"];

                        $dataCredito = [
                            "cliente" => $pedido->cliente,
                            "idinsucursal" => $req->id,
                            "saldo" => floatval($req->credito),
                            "deuda" => $this->getDeudaFun(false,$id_cliente),
                            "fecha_ultimopago" => $this->getDeudaFechaPago($id_cliente)
                        ];

                        $creditoResult = (new sendCentral)->createCreditoAprobacion($dataCredito);
    
                        if ($creditoResult === "APROBADO") {
                            pago_pedidos::updateOrCreate(["id_pedido"=>$req->id,"tipo"=>4],["cuenta"=>$cuenta,"monto"=>floatval($req->credito)]);
                        }else{
                            return $creditoResult;
                        }
                    }else{
                        return Response::json(["msj"=>"Error: Sin Cliente","estado"=>false]);
                    }

                }
                if($req->biopago) {
                    $resultadoValidacion = $this->validarDescuentosPorMetodoPago($req->id, $req->biopago, $metodos_pago, $cuenta, 5);
                    if ($resultadoValidacion !== true) {
                        return $resultadoValidacion;
                    }
                }
                
                $pedido = pedidos::find($req->id);

                if ($pedido->estado==0) {
                    $pedido->estado = 1;
                    $pedido->save();

                    // Commit de transacción solo cuando cambie el estado del pedido
                    \DB::commit();
                    $isFullFiscal = (new SucursalController)->isFullFiscal();

                    if ($isFullFiscal) {
                        try {
                            (new tickera)->sendReciboFiscalFun($req->id);
                        } catch (\Exception $e) {
                            \Log::error('Error al enviar recibo fiscal: ' . $e->getMessage());
                        }
                    } else {
                        if ($req->debito && !$req->efectivo && !$req->transferencia) {
                            try {
                                (new tickera)->sendReciboFiscalFun($req->id);
                            } catch (\Exception $e) {
                                \Log::error('Error al enviar recibo fiscal: ' . $e->getMessage());
                            }
                        }
                    }
                }

                return Response::json(["msj"=>"Éxito","estado"=>true]);
            } catch (\Exception $e) {
                \DB::rollback();
                return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
            }

        }else{
            \DB::rollback();

            // Detalle explícito de los montos para ayudar al usuario a corregir
            $detalle = [];
            $detalle[] = "Pedido: ".round($total_real,4)." $";
            $detalle[] = "Pagos principales en $: Debito=".round($debitoUSD,4)." (Bs ".round(floatval($req->debito),2)."), Efectivo=".round(floatval($req->efectivo),4).", Transf=".round(floatval($req->transferencia),4).", Biopago=".round(floatval($req->biopago),4).", Credito=".round(floatval($req->credito),4);

            if ($totalAdicUsdBs || $totalAdicUsdCop || $totalAdicUsdDolar) {
                $detalleAdic = [];
                if ($totalAdicUsdBs)   $detalleAdic[] = "Bs->".round($totalAdicUsdBs,4)." $";
                if ($totalAdicUsdCop)  $detalleAdic[] = "COP->".round($totalAdicUsdCop,4)." $";
                if ($totalAdicUsdDolar)$detalleAdic[] = "USD Adic->".round($totalAdicUsdDolar,4)." $";
                $detalle[] = "Pagos adicionales en $: ".implode(" | ", $detalleAdic);
            }

            $detalle[] = "Total pagos: ".round($total_ins,4)." $";
            $detalle[] = "Diferencia: ".round($res,4)." $ (Pedido - Pagos)";

            $msj = "Error. Montos no coinciden. ".implode(" || ", $detalle);

            return Response::json(["msj"=>$msj,"estado"=>false]);
        }
        } catch (\Exception $e) {
            \DB::rollback();
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }
    }

    function setGastoOperativo(Request $req) {
        $id = $req->id;

        $itemsGasto = items_pedidos::where("id_pedido",$id);
        $monto_gasto = $itemsGasto->sum("monto");

        pago_pedidos::where("id_pedido",$id)->delete();
        pago_pedidos::updateOrCreate(["id_pedido"=>$id,"tipo"=>3],["cuenta"=>1,"monto"=>$monto_gasto]);
        $pedido = pedidos::find($id);
        if ($pedido->estado==0) {
            $pedido->estado = 1;
            $pedido->save();

            $itemsCount_gasto = $itemsGasto->count();

            $gastoCat = catcajas::where("nombre","LIKE","%REPARACIONES Y MANTENIMIENTO DE SUCURSAL%")->where("tipo",0)->first();
            if ($gastoCat) {
                $cajas = (new CajasController)->setCajaFun([
                    "id" => null,
                    "concepto" => "GASTO CON MERCANCIA DE SUCURSAL PED.".$id,
                    "categoria" => $gastoCat->id,
                    "montodolar" => $monto_gasto*-1,
                    "montopeso" => 0,
                    "montobs" => 0,
                    "montoeuro" => 0,
                    "tipo" => 0,
                    "estatus" => 1,
                ]);
    
                if ($cajas) {
                    return [
                        "msj" => "Éxito al registrar Gasto",
                        "estado" => true,
                    ];
                }
            }else{
                return [
                    "msj" => "ERROR, no se encontro categoría de gasto",
                    "estado" => false,
                ];
            }
        }
    }

    function getDeudaFechaPago($id_cliente) {
        $getfecha = pedidos::with(["pagos"=>function($q){
            $q->orderBy("tipo","desc");
        },
        // Cargar items para poder mostrar cantidad de productos por pedido en la interfaz de créditos
        "items"])
        ->where(function($q){
            $q->whereIn("id",pago_pedidos::orWhere(function($q){
                $q->where("cuenta",0); //Abono
            })->where("monto","<>",0)->select("id_pedido"));

        })
        ->where("id_cliente",$id_cliente)
        ->orderBy("created_at","desc")
        ->first();

        if ($getfecha) {
            return $getfecha->created_at;
        }
        return "";

    }

    public function getDeudaFun($onlyVueltos,$id_cliente)
    {
        $pedidos = pedidos::with([
            "pagos"=>function($q) use ($onlyVueltos){
                if ($onlyVueltos) {
                    $q->where("tipo",6)->where("monto","<>",0);
                }
                $q->orderBy("tipo","desc");
            },
            // Cargar items para que el frontend pueda mostrar la cantidad de productos por pedido
            "items"])
        ->where(function($q) use ($onlyVueltos){
            if ($onlyVueltos) {
                $q->whereIn("id",pago_pedidos::where("tipo",6)->where("monto","<>",0)->select("id_pedido"));
            }else{
                $q->whereIn("id",pago_pedidos::orWhere(function($q){
                    $q->orWhere("tipo",4); //Credito
                    $q->orWhere("cuenta",0); //Abono
                })->where("monto","<>",0)->select("id_pedido"));

            }
        })
        ->where("id_cliente",$id_cliente)
        ->orderBy("created_at","desc")
        ->get()
        ->map(function($q){
            
            $q->saldoDebe = $q->pagos->where("tipo",4)->sum("monto");
            $q->saldoAbono = $q->pagos->where("cuenta",0)->sum("monto");

            $q->entregado = [];

            return $q;
        });
        $pedido_total[0] = $pedidos->sum("saldoAbono");
        $pedido_total[1] = $pedidos->sum("saldoDebe");

        // $diferencia = 0;

        // if ($pedido_total[1] && $pedido_total[0]) {
        // $diferencia = ;
        // }
        $d = $pedido_total[0] - $pedido_total[1];

        $check = true;

        if ($d<0) {
            $check = false;
        }
        $pedido_total["diferencia"] = number_format($d,2);
        $pedido_total["diferencia_clean"] = $d;
        $pedido_total["check"] = $check;
        return [
            "pedido" => $pedidos,
            "pedido_total" => $pedido_total,
        ]; 
    }
    public function getDeudor(Request $req)
    {
        $onlyVueltos = $req->onlyVueltos;
        $id_cliente = $req->id;

        return $this->getDeudaFun($onlyVueltos,$id_cliente);
        

    }

    public function checkDeuda(Request $req)
    {
        $id_cliente = $req->id_cliente;
        return $this->getDeudaFun(false,$id_cliente);

    }
    public function verCreditos(Request $req)
    {
        $sucursal = sucursal::all()->first();
        $today = (new PedidosController)->today();


        $qDeudores = $req->qDeudores;
        $orderbycolumdeudores = $req->orderbycolumdeudores;
        $orderbyorderdeudores = $req->orderbyorderdeudores;

        $data = $this->getDeudoresFun($qDeudores,$orderbycolumdeudores,$orderbyorderdeudores,$today,200);
        
        return view("reportes.creditos",["data" => $data,"sucursal" => $sucursal,"today"=>$today]);
    }
    public function reporteCreditosCliente(Request $req)
    {
        $id_cliente = $req->id_cliente;
        $ids = $req->ids;

        if (!$id_cliente || !$ids) {
            abort(404);
        }

        $idsArray = explode(",", $ids);

        $cliente = clientes::findOrFail($id_cliente);

        $pedidos = pedidos::with([
                'cliente',
                'pagos',
                'items' => function ($q) {
                    $q->with('producto');
                }
            ])
            ->where('id_cliente', $id_cliente)
            ->whereIn('id', $idsArray)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($q) {
                $q->saldoDebe = $q->pagos->where('tipo', 4)->sum('monto');
                $q->saldoAbono = $q->pagos->where('cuenta', 0)->sum('monto');
                return $q;
            });

        $totalCreditos = $pedidos->sum('saldoDebe');
        $totalAbonos = $pedidos->sum('saldoAbono');
        $saldo = $totalAbonos - $totalCreditos;

        // Cantidad de movimientos de crédito (tipo 4) y de abono (cuenta 0)
        $cantidadMovCreditos = 0;
        $cantidadMovAbonos = 0;

        foreach ($pedidos as $p) {
            if (isset($p->pagos)) {
                $cantidadMovCreditos += $p->pagos->where('tipo', 4)->where('monto', '<>', 0)->count();
                $cantidadMovAbonos += $p->pagos->where('cuenta', 0)->where('monto', '<>', 0)->count();
            }
        }

        $resumen = [
            'cantidad_pedidos' => $pedidos->count(),
            'total_creditos'   => $totalCreditos,
            'total_abonos'     => $totalAbonos,
            'saldo'            => $saldo,
            'cantidad_mov_creditos' => $cantidadMovCreditos,
            'cantidad_mov_abonos'   => $cantidadMovAbonos,
        ];

        return view('reportes.creditos_detalle', [
            'cliente' => $cliente,
            'pedidos' => $pedidos,
            'resumen' => $resumen,
        ]);
    }
    public function getDeudores(Request $req)
    {   
        $today = (new PedidosController)->today();
        $busqueda = $req->qDeudores;
        $view = $req->view;

        $orderbycolumdeudores = $req->orderbycolumdeudores;
        $orderbyorderdeudores = $req->orderbyorderdeudores;
        $limitdeudores = $req->limitdeudores;


        if ($view==="vueltos") {
            return clientes::with(["pedidos"=>function($q){
                $q->with(["pagos"]);
                $q->orderBy("created_at","desc");
            }])
            ->where("id","<>",1)->where(function($q) use ($busqueda){
                $q->orWhere("identificacion","LIKE","%".$busqueda."%")
                ->orWhere("nombre","LIKE","%".$busqueda."%");
            })

            ->limit($limitdeudores)
            ->get()
            ->map(function($q) use ($view,$today) {

                $q->totalVuelto = 0; 
                $q->saldo = 0;
                if ($view==="vueltos") {
                    // code...
                    $q->totalVuelto = $q->pedidos->map(function($q){

                        $check_vuelto_entregado = 0;
                        $sum_entregado = 0;
                        if ($check_vuelto_entregado) {
                            
                            $sum_entregado = $check_vuelto_entregado;
                        }

                        return $q->pagos->where("tipo",6)->sum("monto")-$sum_entregado;
                    })->sum();
                }
                return $q;
            });
        }else{
            return $this->getDeudoresFun($busqueda,$orderbycolumdeudores,$orderbyorderdeudores,$today,$limitdeudores);
        }


    }
    public function getDeudoresFun($busqueda,$orderbycolumdeudores,$orderbyorderdeudores,$today,$limitdeudores)
    {   
        return clientes::with(["pedidos"=>function($q){
                // $q->with(["pagos"]);
                $q->orderBy("created_at","desc");
            }])
            ->selectRaw("*,@credito := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND tipo=4) as credito, @abono := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND cuenta=0) as abono, (@abono-@credito) as saldo, @vence := (SELECT fecha_vence FROM pedidos WHERE id_cliente=clientes.id AND fecha_vence > $today ORDER BY pedidos.fecha_vence ASC LIMIT 1) as vence , (COALESCE(DATEDIFF(@vence,'$today 00:00:00'),0)) as dias")
            // ->where("saldo","<",0)
            ->where("id","<>",1)->where(function($q) use ($busqueda){
                $q->orWhere("identificacion","LIKE","%".$busqueda."%")
                ->orWhere("nombre","LIKE","%".$busqueda."%");
            })
            // ->having("saldo","<",0)
            ->orderBy($orderbycolumdeudores,$orderbyorderdeudores)
            ->limit($limitdeudores)
            ->get();
    }

    public function setPagoCredito(Request $req)
    {
        try {
            $monto_pago_deudor = $req->monto_pago_deudor;
    
            if (session()->has("id_usuario")) {
    
                if ($monto_pago_deudor<0) {
                    $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                        "id_pedido" => null,
                        "tipo" => "devolucionPago",
                    ]);
                    
                    if ((new UsuariosController)->isAdmin()) {
                    }elseif($isPermiso["permiso"]){
                    }else{
                        $nuevatarea = (new TareaslocalController)->createTareaLocal([
                            "id_pedido" => null,
                            "tipo" => "devolucionPago",
                            "valoraprobado" => $monto_pago_deudor,
                            "descripcion" => "Devolver dinero $ ".$monto_pago_deudor,
                        ]);
                        if ($nuevatarea) {
                            return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                        }
                    }
                }
                $id_cliente = $req->id_cliente;
                $pedido = new pedidos;
    
                $pedido->estado = 0;
                $pedido->id_cliente = $id_cliente;
                $pedido->id_vendedor = session("id_usuario");
    
                if ($pedido->save()) {
                    $tipo_pago_deudor = $req->tipo_pago_deudor;
                    $monto_pago_deudor = $req->monto_pago_deudor;
    
                    $tipo = $monto_pago_deudor<0?"DEVOLUCION":"PAGO";
    
                    $cliente = clientes::find($id_cliente);
    
                    $producto_pago_desc = $tipo." ".$cliente->nombre;
                    
    
                    $items_pedidos = new items_pedidos;
                    $items_pedidos->id_producto = null;
                    $items_pedidos->abono = $producto_pago_desc;
                    $items_pedidos->id_pedido = $pedido->id;
                    $items_pedidos->cantidad = 1;
                    $items_pedidos->descuento = 0;
                    $items_pedidos->monto = $monto_pago_deudor;
                    $items_pedidos->save();
                    
                    $pago_pedidos = new pago_pedidos;
                    $pago_pedidos->tipo = $tipo_pago_deudor;
                    $pago_pedidos->monto = $monto_pago_deudor;
                    $pago_pedidos->id_pedido = $pedido->id;
                    $pago_pedidos->cuenta = 0;
                    $pago_pedidos->save();
    
                    return Response::json(["msj"=>"Pago registrado con éxito","estado"=>true,"id_pedido"=>$pedido->id]);
                    
    
                }
            }
        } catch (\Exception $e) {
            return Response::json(["msj"=>$e->getMessage(),"estado"=>false]);

        }
    }

    /**
     * Validar que los métodos de pago sean exactos a los aprobados
     */
    private function validarMetodosPago($metodos_actuales, $metodos_aprobados)
    {
        // Si no hay métodos aprobados, no hay validación
        if (empty($metodos_aprobados)) {
            return true;
        }

        // Si no hay métodos actuales pero sí aprobados, es inválido
        if (empty($metodos_actuales)) {
            return false;
        }

        // Normalizar los arrays para comparación
        $normalizarMetodos = function($metodos) {
            $normalizados = [];
            foreach ($metodos as $metodo) {
                $tipo = strtolower(trim($metodo['tipo']));
                $monto = round(floatval($metodo['monto']), 2);
                $normalizados[] = ['tipo' => $tipo, 'monto' => $monto];
            }
            // Ordenar por tipo para comparación consistente
            usort($normalizados, function($a, $b) {
                return strcmp($a['tipo'], $b['tipo']);
            });
            return $normalizados;
        };

        $actuales_normalizados = $normalizarMetodos($metodos_actuales);
        $aprobados_normalizados = $normalizarMetodos($metodos_aprobados);

        // Comparar arrays
        if (count($actuales_normalizados) !== count($aprobados_normalizados)) {
            return false;
        }

        foreach ($actuales_normalizados as $index => $metodo_actual) {
            if (!isset($aprobados_normalizados[$index])) {
                return false;
            }
            
            $metodo_aprobado = $aprobados_normalizados[$index];
            
            if ($metodo_actual['tipo'] !== $metodo_aprobado['tipo'] || 
                $metodo_actual['monto'] !== $metodo_aprobado['monto']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida que haya al menos un método de pago común entre la factura original y la factura de garantía
     * Y que no se permita devolución en efectivo si el pago original no fue en efectivo
     */
    private function validarPagoGarantia($facturaOriginal, $facturaGarantia, $request)
    {
        try {
            // Obtener pagos de la factura original
            $pagosFacturaOriginal = pago_pedidos::where("id_pedido", $facturaOriginal)->get();

            
            // Obtener pagos de la factura de garantía desde la request (aún no registrados en BD)
            $pagosFacturaGarantia = [];
            
            if ($request->efectivo) {
                $pagosFacturaGarantia[] = ['tipo' => 3, 'monto' => floatval($request->efectivo)]; // 3 = Efectivo
            }
            if ($request->debito) {
                $pagosFacturaGarantia[] = ['tipo' => 2, 'monto' => floatval($request->debito)]; // 2 = Débito
            }
            if ($request->transferencia) {
                $pagosFacturaGarantia[] = ['tipo' => 1, 'monto' => floatval($request->transferencia)]; // 1 = Transferencia
            }
            if ($request->biopago) {
                $pagosFacturaGarantia[] = ['tipo' => 5, 'monto' => floatval($request->biopago)]; // 5 = Biopago
            }
            if ($request->credito) {
                $pagosFacturaGarantia[] = ['tipo' => 4, 'monto' => floatval($request->credito)]; // 4 = Crédito
            }

            $sumaPagosGarantia = array_sum(array_column($pagosFacturaGarantia, 'monto'));

            if ($sumaPagosGarantia > 0) {
                return true;
            }

            if ($sumaPagosGarantia == 0) {
                \Log::info("La suma de los montos de pagosFacturaGarantia es cero, se permite la garantía sin pago.");
                return true;
            }


            // NUEVA VALIDACIÓN: No permitir devolución en efectivo si el pago original no fue en efectivo
            if ($request->efectivo) {
                $pagoOriginalEnEfectivo = false;
                foreach ($pagosFacturaOriginal as $pagoOriginal) {
                    if ($pagoOriginal->tipo == 3) { // 3 = Efectivo
                        $pagoOriginalEnEfectivo = true;
                        break;
                    }
                }
                
                if (!$pagoOriginalEnEfectivo) {
                    \Log::warning("Intento de devolución en efectivo con pago original no en efectivo", [
                        'factura_original' => $facturaOriginal,
                        'factura_garantia' => $facturaGarantia,
                        'pagos_original' => $pagosFacturaOriginal->toArray(),
                        'monto_efectivo_garantia' => $request->efectivo
                    ]);
                    
                    return false;
                }
            }

           
            // Si la suma es positiva, también permite
           

            
           /*  if ($pagosFacturaOriginal->isEmpty()) {
                \Log::warning("No se encontraron pagos en la factura original", [
                    'factura_original' => $facturaOriginal
                ]);
                return false;
            }
            
            if (empty($pagosFacturaGarantia)) {
                \Log::warning("No se encontraron pagos en la factura de garantía", [
                    'factura_garantia' => $facturaGarantia,
                    'request_data' => [
                        'efectivo' => $request->efectivo,
                        'debito' => $request->debito,
                        'transferencia' => $request->transferencia,
                        'biopago' => $request->biopago,
                        'credito' => $request->credito
                    ]
                ]);
                return false;
            } */
            
            // Buscar coincidencias en tipos de pago (solo tipo, no importa el monto)

            // Si la suma de los montos de pagosFacturaGarantia es cero, retorna true
           

            /* foreach ($pagosFacturaOriginal as $pagoOriginal) {
                foreach ($pagosFacturaGarantia as $pagoGarantia) {
                    // Solo comparar tipo de pago, no el monto
                    \Log::info("Pago original: " . $pagoOriginal->tipo);
                    \Log::info("Pago garantia: " . $pagoGarantia['tipo']);
                    if ($pagoOriginal->tipo == $pagoGarantia['tipo']) {
                        
                        \Log::info("Tipo de pago común encontrado", [
                            'factura_original' => $facturaOriginal,
                            'factura_garantia' => $facturaGarantia,
                            'tipo_pago' => $pagoOriginal->tipo,
                            'monto_original' => $pagoOriginal->monto,
                            'monto_garantia' => $pagoGarantia['monto']
                        ]);
                        
                        return true;
                    }
                }
            }
            
            \Log::warning("No se encontraron pagos comunes", [
                'factura_original' => $facturaOriginal,
                'factura_garantia' => $facturaGarantia,
                'pagos_original' => $pagosFacturaOriginal->toArray(),
                'pagos_garantia' => $pagosFacturaGarantia
            ]); */
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error("Error al validar pagos de garantía", [
                'factura_original' => $facturaOriginal,
                'factura_garantia' => $facturaGarantia,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Valida descuentos por método de pago y maneja solicitudes de aprobación
     * 
     * @param int $id_pedido ID del pedido
     * @param float $monto_pago Monto del método de pago
     * @param array $metodos_pago Array de métodos de pago
     * @param int $cuenta Tipo de cuenta
     * @param int $tipo_pago Tipo de pago (1=transferencia, 2=débito, 3=efectivo, 4=crédito, 5=biopago)
     * @return mixed true si la validación es exitosa, Response::json si hay error
     */
    private function validarDescuentosPorMetodoPago($id_pedido, $monto_pago, $metodos_pago, $cuenta, $tipo_pago = 3)
    {
        // Si es una devolución (monto negativo), no aplicar validación de descuentos
        if (floatval($monto_pago) < 0) {
            pago_pedidos::updateOrCreate(["id_pedido"=>$id_pedido,"tipo"=>$tipo_pago],["cuenta"=>$cuenta,"monto"=>floatval($monto_pago)]);
            return true;
        }
        
        // Verificar si hay descuentos aplicados en los items del pedido
        $items_pedido = items_pedidos::with('producto')->where('id_pedido', $id_pedido)->get();
        $items_con_descuento = $items_pedido->where('descuento', '>', 0);
        
        // Si hay descuentos aplicados, verificar que exista cliente
        if ($items_con_descuento->count() > 0) {
            $pedido = pedidos::with('cliente')->find($id_pedido);
            if (!$pedido || $pedido->id_cliente == 1) {
                return Response::json(["msj"=>"Error: Debe registrar un cliente para aplicar descuentos en efectivo","estado"=>false]);
            }
        
            // Hay descuento y hay cliente, proceder con la solicitud
            $monto_bruto = $items_pedido->sum('monto');
            $monto_pago = floatval($monto_pago);
            
            // Calcular el descuento real item por item
            $monto_descuento_real = 0;
            foreach ($items_pedido as $item) {
                if ($item->descuento > 0) {
                    $subtotal_item = $item->cantidad * $item->producto->precio;
                    $descuento_item = $subtotal_item * ($item->descuento / 100);
                    $monto_descuento_real += $descuento_item;
                }
            }
            
            $porcentaje_descuento = ($monto_descuento_real / $monto_bruto) * 100;
            
            // Verificar si ya existe una solicitud para este pedido
            $solicitudExistente = (new sendCentral)->verificarSolicitudDescuento([
                'id_sucursal' => (new sendCentral)->getOrigen(),
                'id_pedido' => $id_pedido,
                'tipo_descuento' => 'metodo_pago'
            ]);

            if ($solicitudExistente && isset($solicitudExistente['existe']) && $solicitudExistente['existe']) {
                if ($solicitudExistente['data']['estado'] === 'enviado') {
                    return Response::json(["msj"=>"Ya existe una solicitud de descuento por método de pago en espera de aprobación","estado"=>false]);
                } elseif ($solicitudExistente['data']['estado'] === 'aprobado') {
                    // Validar que el porcentaje de descuento sea exactamente igual al aprobado
                    $porcentaje_aprobado = floatval($solicitudExistente['data']['porcentaje_descuento']);
                    if (abs($porcentaje_descuento - $porcentaje_aprobado) > 0.01) {
                        return Response::json([
                            "msj"=>"Error: El porcentaje de descuento calculado ({$porcentaje_descuento}%) debe ser exactamente igual al aprobado ({$porcentaje_aprobado}%)",
                            "estado"=>false
                        ]);
                    }
                    // Validar que los métodos de pago sean exactos a los aprobados
                    $metodos_aprobados = $solicitudExistente['data']['metodos_pago'];
                    if (!$this->validarMetodosPago($metodos_pago, $metodos_aprobados)) {
                        return Response::json(["msj"=>"Error: Los métodos de pago deben ser exactos a los aprobados en la solicitud","estado"=>false]);
                    }
                    // Continuar con el pago aprobado
                    pago_pedidos::updateOrCreate(["id_pedido"=>$id_pedido,"tipo"=>$tipo_pago],["cuenta"=>$cuenta,"monto"=>floatval($monto_pago)]);
                } elseif ($solicitudExistente['data']['estado'] === 'rechazado') {
                    return Response::json(["msj"=>"La solicitud de descuento por método de pago fue rechazada","estado"=>false]);
                }
            } else {
                // Crear nueva solicitud de descuento por método de pago
                $items_pedido = items_pedidos::with('producto')->where('id_pedido', $id_pedido)->get();

                $ids_productos = $items_pedido->map(function($item) {
                    $subtotal_item = $item->cantidad * ($item->producto ? $item->producto->precio : 0);
                    $subtotal_con_descuento = $subtotal_item * (1 - ($item->descuento / 100));
                    
                    return [
                        'id_producto' => $item->id_producto,
                        'cantidad' => $item->cantidad,
                        'precio' => $item->producto ? $item->producto->precio : 0,
                        'precio_base' => $item->producto ? $item->producto->precio_base : 0,
                        'subtotal' => $subtotal_item,
                        'subtotal_con_descuento' => $subtotal_con_descuento,
                        'porcentaje_descuento' => $item->descuento
                    ];
                })->toArray();

                $resultado = (new sendCentral)->crearSolicitudDescuento([
                    'id_sucursal' => (new sendCentral)->getOrigen(),
                    'id_pedido' => $id_pedido,
                    'fecha' => now(),
                    'monto_bruto' => $monto_bruto,
                    'monto_con_descuento' => $monto_bruto - $monto_descuento_real,
                    'monto_descuento' => $monto_descuento_real,
                    'porcentaje_descuento' => $porcentaje_descuento,
                    'id_cliente' => $pedido->id_cliente,
                    'usuario_ensucursal' => session('usuario'),
                    'metodos_pago' => $metodos_pago,
                    'ids_productos' => $ids_productos,
                    'tipo_descuento' => 'metodo_pago',
                    'observaciones' => "Solicitud de descuento por método de pago: {$porcentaje_descuento}%"
                ]);

                if ($resultado && isset($resultado['estado']) && $resultado['estado']) {
                    return Response::json(["msj"=>"Solicitud de descuento por método de pago enviada a central para aprobación","estado"=>false]);
                } else {
                    \Log::info("Error al enviar solicitud de descuento por método de pago. setPagoPedido",["resultado"=>$resultado]);
                    return $resultado;
                }
            }
        } else {
            // No hay items con descuento, proceder normalmente con el pago
            pago_pedidos::updateOrCreate(["id_pedido"=>$id_pedido,"tipo"=>$tipo_pago],["cuenta"=>$cuenta,"monto"=>floatval($monto_pago)]);
        }
        
        return true;
    }

    /**
     * Actualiza los pedidos de crédito de un cliente específico
     * Recalcula precios de items y actualiza montos de pago
     */
    public function updateCreditOrders(Request $req)
    {
        try {
            $id_cliente = $req->id_cliente;
            
            if (!$id_cliente) {
                return Response::json([
                    "msj" => "Error: ID de cliente requerido",
                    "estado" => false
                ]);
            }

            // Obtener la deuda actual del cliente usando la misma lógica que getDeudores
            $today = (new PedidosController)->today();
            $cliente = clientes::selectRaw("*,@credito := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND tipo=4) as credito, @abono := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND cuenta=0) as abono, (@abono-@credito) as saldo")
                ->where('id', $id_cliente)
                ->first();

            if (!$cliente) {
                return Response::json([
                    "msj" => "Cliente no encontrado",
                    "estado" => false
                ]);
            }

            $deudaActual = abs($cliente->saldo); // Valor absoluto de la deuda
            
            if ($deudaActual <= 0) {
                return Response::json([
                    "msj" => "El cliente no tiene deuda pendiente",
                    "estado" => false
                ]);
            }

            // Obtener todos los pedidos del cliente con tipo de pago 4 (crédito) ordenados por fecha desc
            $pedidosCredito = pedidos::with(['items.producto', 'pagos'])
                ->where('id_cliente', $id_cliente)
                ->whereHas('pagos', function($query) {
                    $query->where('tipo', 4); // 4 = Crédito
                })
                ->orderBy('created_at', 'desc')
                ->get();

            if ($pedidosCredito->isEmpty()) {
                return Response::json([
                    "msj" => "No se encontraron pedidos de crédito para este cliente",
                    "estado" => false
                ]);
            }

            // Seleccionar pedidos cuyo valor acumulado sea igual o aproximado a la deuda actual
            $pedidosAActualizar = [];
            $acumulado = 0;
            $tolerancia = $deudaActual * 0.05; // 5% de tolerancia

            foreach ($pedidosCredito as $pedido) {
                $pagoCredito = $pedido->pagos->where('tipo', 4)->first();
                if ($pagoCredito) {
                    $acumulado += $pagoCredito->monto;
                    $pedidosAActualizar[] = $pedido;
                    
                    // Si el acumulado está dentro del rango de la deuda (con tolerancia), parar
                    if ($acumulado >= ($deudaActual - $tolerancia) && $acumulado <= ($deudaActual + $tolerancia)) {
                        break;
                    }
                    
                    // Si ya superamos significativamente la deuda, parar
                    if ($acumulado > ($deudaActual + $tolerancia)) {
                        break;
                    }
                }
            }

            if (empty($pedidosAActualizar)) {
                return Response::json([
                    "msj" => "No se encontraron pedidos que coincidan con la deuda actual de $" . number_format($deudaActual, 2),
                    "estado" => false
                ]);
            }

            $pedidosActualizados = 0;
            $errores = [];

            // Procesar solo los pedidos seleccionados
            foreach ($pedidosAActualizar as $pedido) {
                try {
                    // Obtener el pago de crédito para este pedido
                    $pagoCredito = $pedido->pagos->where('tipo', 4)->first();
                    
                    if (!$pagoCredito) {
                        continue;
                    }

                    // Calcular el total actual del pedido
                    $totalActual = 0;
                    foreach ($pedido->items as $item) {
                        if ($item->producto) {
                            $totalActual += $item->cantidad * $item->producto->precio;
                        }
                    }

                    // Calcular el porcentaje que representa el crédito del total original
                    $totalOriginal = $pedido->pagos->sum('monto');
                    $porcentajeCredito = $totalOriginal > 0 ? ($pagoCredito->monto / $totalOriginal) * 100 : 0;

                    // Actualizar cada item con el precio actual
                    foreach ($pedido->items as $item) {
                        if ($item->producto) {
                            $nuevoMonto = $item->cantidad * $item->producto->precio;
                            $item->monto = $nuevoMonto;
                            $item->save();
                        }
                    }

                    // Calcular el nuevo monto de crédito basado en el porcentaje
                    $nuevoTotal = $pedido->items->sum('monto');
                    $nuevoMontoCredito = ($nuevoTotal * $porcentajeCredito) / 100;

                    // Actualizar el pago de crédito
                    $pagoCredito->monto = $nuevoMontoCredito;
                    $pagoCredito->save();

                    $pedidosActualizados++;

                } catch (\Exception $e) {
                    $errores[] = "Error en pedido {$pedido->id}: " . $e->getMessage();
                }
            }

            if (count($errores) > 0) {
                return Response::json([
                    "msj" => "Se actualizaron {$pedidosActualizados} pedidos (deuda: $" . number_format($deudaActual, 2) . "), pero hubo errores en algunos",
                    "estado" => true,
                    "pedidos_actualizados" => $pedidosActualizados,
                    "deuda_actual" => $deudaActual,
                    "pedidos_seleccionados" => count($pedidosAActualizar),
                    "errores" => $errores
                ]);
            }

            return Response::json([
                "msj" => "Se actualizaron exitosamente {$pedidosActualizados} pedidos de crédito (deuda: $" . number_format($deudaActual, 2) . ")",
                "estado" => true,
                "pedidos_actualizados" => $pedidosActualizados,
                "deuda_actual" => $deudaActual,
                "pedidos_seleccionados" => count($pedidosAActualizar)
            ]);

        } catch (\Exception $e) {
            return Response::json([
                "msj" => "Error al actualizar pedidos de crédito: " . $e->getMessage(),
                "estado" => false
            ]);
        }
    }
}
