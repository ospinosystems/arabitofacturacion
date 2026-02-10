<?php

namespace App\Http\Controllers;

use App\Models\sucursal;
use App\Models\pedidos;
use App\Models\garantia;


use Illuminate\Http\Request;
use Mike42\Escpos;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

use Response;
use Http;

class tickera extends Controller
{
    public function resetPrintingState(Request $request)
    {
        try {
            $id = $request->id;
            $pedido = pedidos::find($id);
            if ($pedido) {
                $pedido->is_printing = false;
                $pedido->save();
                return Response::json([
                    "msj" => "Estado de impresión reseteado",
                    "estado" => true
                ]);
            }
            return Response::json([
                "msj" => "Pedido no encontrado",
                "estado" => false
            ]);
        } catch (\Exception $e) {
            return Response::json([
                "msj" => "Error: " . $e->getMessage(),
                "estado" => false
            ]);
        }
    }

    /**
     * Obtener cajas/impresoras disponibles
     * GET /api/get-cajas-disponibles
     */
    public function getCajasDisponibles(Request $request)
    {
        try {
            $sucursal = sucursal::all()->first();
            
            if (!$sucursal || !$sucursal->tickera) {
                return Response::json([
                    'success' => false,
                    'message' => 'No hay configuración de impresoras disponible'
                ], 404);
            }

            $arr_printers = explode(";", $sucursal->tickera);
            $cajas = [];

            foreach ($arr_printers as $index => $printer) {
                $cajas[] = [
                    'id' => $index + 1,
                    'nombre' => 'Caja ' . ($index + 1),
                    'impresora' => trim($printer),
                    'descripcion' => 'Impresora: ' . trim($printer)
                ];
            }

            return Response::json([
                'success' => true,
                'cajas' => $cajas,
                'total' => count($cajas)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error obteniendo cajas disponibles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Response::json([
                'success' => false,
                'message' => 'Error al obtener cajas disponibles: ' . $e->getMessage()
            ], 500);
        }
    }

    public function imprimir(Request $req)
    {
        try {
            \DB::beginTransaction();

            

            function addSpaces($string = '', $valid_string_length = 0) {
                if (strlen($string) < $valid_string_length) {
                    $spaces = $valid_string_length - strlen($string);
                    for ($index1 = 1; $index1 <= $spaces; $index1++) {
                        $string = $string . ' ';
                    }
                }
                return $string;
            }
            
            $get_moneda = (new PedidosController)->get_moneda();
            $moneda_req = $req->moneda;
            //$
            //bs
            //cop
            if ($moneda_req=="$") {
                $dolar = 1;
            }else if($moneda_req=="bs"){
                $dolar = $get_moneda["bs"];
            }else if($moneda_req=="cop"){
                $dolar = $get_moneda["cop"];
            }else{
                $dolar = $get_moneda["bs"];
            }

            $sucursal = sucursal::all()->first();

           
          

                $arr_printers = explode(";", $sucursal->tickera);
                $printer = 1;
                
                if ($req->printer) {
                    $printer = $req->printer-1;
                }

                if(count($arr_printers)==1){
                    $connector = new WindowsPrintConnector($arr_printers[0]);
                }else{
                    $connector = new WindowsPrintConnector($arr_printers[$printer]);
                }
                
                //smb://computer/printer
                $printer = new Printer($connector);
                $printer->setEmphasis(true);
                
                $pedido = (new PedidosController)->getPedido($req,floatval($dolar));

                if ((!isset($pedido->items) || $pedido->items->count()==0) && $req->id!="presupuesto") {
                    throw new \Exception("¡El pedido no tiene items, no se puede imprimir!", 1);
                }

                if (isset($pedido->fecha_factura) || isset($pedido->created_at)) {
                    $pedido_date = \Carbon\Carbon::parse($pedido->fecha_factura ?? $pedido->created_at)->toDateString();
                    $today_date = \Carbon\Carbon::now()->toDateString();
                    if(session("usuario") != "admin"){
                        if ($pedido_date !== $today_date) {
                            throw new \Exception("¡El pedido no es de hoy, no se puede imprimir!", 1);
                        }
                    }
                }

                // Verificar si algún item del pedido tiene 'condicion' diferente de 0
                if (isset($pedido->items)) {
                    foreach ($pedido->items as $item) {
                        if (isset($item['condicion']) && $item['condicion'] != 0) {
                            throw new \Exception("¡No se puede imprimir porque algún item tiene condición diferente de 0!", 1);
                        }
                    }
                }

                // Verificar si hay items con cantidad negativa para imprimir ticket de devolución
                $hasNegativeItems = false;
                $negativeItems = [];
                $positiveItems = [];
                $totalDevolucion = 0;
                $totalEntrada = 0;
                
                if (isset($pedido->items)) {
                    foreach ($pedido->items as $item) {
                        // Usar precio_unitario del item (precio al momento de la venta)
                        $precioItem = $item->precio_unitario ?? $item->producto->precio;
                        
                        if (floatval($item->cantidad) < 0) {
                            $hasNegativeItems = true;
                            $negativeItems[] = $item;
                            // Calcular total de devolución
                            $totalDevolucion += abs(floatval($item->cantidad)) * $precioItem;
                        } elseif (floatval($item->cantidad) > 0) {
                            $positiveItems[] = $item;
                            // Calcular total de entrada
                            $totalEntrada += floatval($item->cantidad) * $precioItem;
                        }
                    }
                }
                
                // Determinar el saldo final
                $saldoFinal = $totalEntrada - $totalDevolucion;
                $tieneProductosMixtos = count($negativeItems) > 0 && count($positiveItems) > 0;



                if (isset($pedido["estado"])) {
                    if ($pedido["estado"]==2) {
                        throw new \Exception("¡No puede imprimir un anulado!", 1);
                    }
                }
    
                if (isset($pedido["cliente"])) {
                    $nombres = $pedido["cliente"]["nombre"];
                    $identificacion = $pedido["cliente"]["identificacion"];
                }
                if (isset($req->nombres)) {
                    $nombres = $req->nombres;
                }
                if (isset($req->identificacion)) {
                    $identificacion = $req->identificacion;
                }
    
                if ($req->id==="presupuesto") {
                    
                    $printer -> setTextSize(1,1);
        
                    $printer->setEmphasis(true);
                    $printer->text("PRESUPUESTO");
                    $printer->setEmphasis(false);
    
                    $printer -> text("\n");
                    $printer -> text("\n");
        
                    if ($nombres!="") {
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer -> text("Nombre y Apellido: ".$nombres);
                        $printer -> text("\n");
                        $printer -> text("ID: ".$identificacion);
                        $printer -> text("\n");
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
    
                    }
                    $totalpresupuesto = 0;
                    foreach ($req->presupuestocarrito as $key => $e) {
    
                        $printer->text($e['descripcion']);
                        $printer->text("\n");
                        $printer->text($e['id']);
                        $printer->text("\n");
    
                        $printer->text(addSpaces("P/U. ",6).moneda($e['precio']*$dolar));
                        $printer->text("\n");
                        
                        $printer->setEmphasis(true);
                        $printer->text(addSpaces("Ct. ",6).$e['cantidad']);
                        $printer->setEmphasis(false);
                        $printer->text("\n");
    
                        $printer->text(addSpaces("SubTotal. ",6).moneda($e['subtotal']*$dolar));
                        $printer->text("\n");
                        $printer->feed();
    
                        $totalpresupuesto += $e['subtotal'];
                    }
    
                    $printer->text("Total: ".moneda($totalpresupuesto*$dolar));
                    $printer->text("\n");
                    $printer->text("\n");
                    $printer->text("\n");
    
                }else{

                    // Obtener el pedido y bloquearlo para actualización
                    $pedidoBlock = pedidos::where('id', $req->id)->lockForUpdate()->first();
                    
                    if (!$pedidoBlock) {
                        throw new \Exception("Pedido no encontrado", 1);
                    }

                    // Verificar si el pedidoBlock ya está siendo impreso
                    if ($pedidoBlock->is_printing) {
                        // Si ha pasado más de 2 minutos desde la última actualización, asumimos que hubo un error
                        $lastUpdate = strtotime($pedidoBlock->updated_at);
                        $now = time();
                        if (($now - $lastUpdate) > 120) { // 120 segundos = 2 minutos
                            $pedidoBlock->is_printing = false;
                            $pedidoBlock->save();
                        } else {
                            throw new \Exception("El pedido está siendo impreso en este momento", 1);
                        }
                    }

                    // Marcar el pedidoBlock como en proceso de impresión
                    $pedidoBlock->is_printing = true;
                    $pedidoBlock->save();
    
                    if (!(new PedidosController)->checksipedidoprocesado($req->id)) {
                        throw new \Exception("¡Debe procesar el pedido para imprimir!", 1);
                    }
                    $fecha_creada = date("Y-m-d",strtotime($pedido->fecha_factura ?? $pedido->created_at));
                    $today = (new PedidosController)->today();
    
                    if ($fecha_creada != $today || ($fecha_creada == $today && $pedido->ticked)) {
                        $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                            "id_pedido" => $req->id,
                            "tipo" => "tickera",
                        ]);
                        if ((new UsuariosController)->isAdmin()) {
                            // Avanza
                        }elseif($isPermiso["permiso"]){
                            if ($isPermiso["valoraprobado"]==1) {
                                // Avanza
                            }else{
                                throw new \Exception("Error: Valor no aprobado");
                            }
                        }else{
                            $nuevatarea = (new TareaslocalController)->createTareaLocal([
                                "id_pedido" =>  $req->id,
                                "valoraprobado" => 1,
                                "tipo" => "tickera",
                                "descripcion" => "Solicitud de Reimpresion COPIA",
                            ]);
                            if ($nuevatarea) {
                                \DB::commit();
                                return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                            }
                        }
                    }
                    
        
                    // Si hay items con cantidad negativa, imprimir ticket de devolución
                    if ($hasNegativeItems) {
                        $this->imprimirTicketDevolucion($printer, $pedido, $negativeItems, $positiveItems, $nombres, $identificacion, $sucursal, $dolar, $totalDevolucion, $totalEntrada, $saldoFinal, $tieneProductosMixtos);
                    } else {
                        // Imprimir ticket normal
                        $this->imprimirTicketNormal($printer, $pedido, $nombres, $identificacion, $sucursal, $dolar);
                    }
                    
                }
    
                $printer->cut();
                $printer->pulse();
                $printer->close();

                \DB::commit();

                return Response::json([
                    "msj"=>"Imprimiendo...",
                    "estado"=>true,
                    "printerpulse()" => $printer->pulse(),
                    "printerclose()" => $printer->close(),
                ]);

        } catch (\Exception $e) {
            // En caso de error, asegurarse de marcar el pedido como no imprimiendo
            $pedidoBlock = pedidos::where('id', $req->id)->lockForUpdate()->first();

            if (isset($pedidoBlock)) {
                $pedidoBlock->is_printing = false;
                $pedidoBlock->save();
            }
            \DB::rollback();
            return Response::json([
                "msj" => "Error: " . $e->getMessage(),
                "estado" => false
            ]);
        }
    }

    /**
     * Imprimir ticket de devolución (ORIGINAL y COPIA)
     */
    private function imprimirTicketDevolucion($printer, $pedido, $negativeItems, $positiveItems, $nombres, $identificacion, $sucursal, $dolar, $totalDevolucion, $totalEntrada, $saldoFinal, $tieneProductosMixtos)
    {
        // Imprimir ORIGINAL (para el cliente)
        $this->imprimirTicketDevolucionFormato($printer, $pedido, $negativeItems, $positiveItems, $nombres, $identificacion, $sucursal, $dolar, $totalDevolucion, $totalEntrada, $saldoFinal, $tieneProductosMixtos);
        
        // Cortar papel
        $printer->cut();
        $printer->feed(3);
        
        // Imprimir COPIA (para la empresa)
      //  $this->imprimirTicketDevolucionFormato($printer, $pedido, $negativeItems, $positiveItems, $nombres, $identificacion, $sucursal, $dolar, $totalDevolucion, $totalEntrada, $saldoFinal, $tieneProductosMixtos);
    }

    /**
     * Formato específico para ticket de devolución
     */
    private function imprimirTicketDevolucionFormato($printer, $pedido, $negativeItems, $positiveItems, $nombres, $identificacion, $sucursal, $dolar, $totalDevolucion, $totalEntrada, $saldoFinal, $tieneProductosMixtos)
    {
        // Membrete de la empresa
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("\n");
        $printer->text($sucursal->nombre_registro);
        $printer->text("\n");
        $printer->text($sucursal->rif);
        $printer->text("\n");
        $printer->text($sucursal->telefono1." | ".$sucursal->telefono2);
        $printer->text("\n");

        $printer->setTextSize(1,1);
        $printer->setEmphasis(true);
        $printer->text("\n");
        
        // Tipo de ticket basado en número de impresión
        if (!$pedido->ticked) {
            $printer->setTextSize(2,2);
            $printer->setEmphasis(true);
            $printer->text("ORIGINAL");
        } else {
            $printer->setTextSize(1,1);
            $printer->setEmphasis(true); 
            $printer->text("COPIA " . $pedido->ticked);
        }
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("\n");
        
        // Título del ticket
        $printer->setTextSize(2,2);
        $printer->setEmphasis(true);
        $printer->text("TICKET DE");
        $printer->text("\n");
        $printer->text("DEVOLUCION");
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("\n");
        
        $printer->text($sucursal->sucursal);
        $printer->text("\n");
        $printer->text("#".$pedido->id);
        $printer->setEmphasis(false);
        $printer->text("\n");

        // Información del cliente
        if ($nombres!="") {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Nombre y Apellido: ".$nombres);
            $printer->text("\n");
            $printer->text("ID: ".$identificacion);
            $printer->text("\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->feed();
        $printer->setPrintLeftMargin(0);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        
        // Si hay productos mixtos, mostrar ambos tipos
        if ($tieneProductosMixtos) {
            // PRODUCTOS DEVUELTOS
            $printer->setEmphasis(true);
            $printer->text("PRODUCTOS DEVUELTOS:");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("---------------------------");
            $printer->text("\n");

            $cantidadTotalDevolucion = 0;

            // Imprimir items con cantidad negativa
            foreach ($negativeItems as $item) {
                if ($item->producto) {
                    // Descripción del producto
                    $printer->text($item->producto->descripcion);
                    $printer->text("\n");
                    $printer->text($item->producto->codigo_barras);
                    $printer->text("\n");

                    // Precio unitario y total (usar precio del item, no del producto actual)
                    $printer->setTextSize(1, 1);
                    $puDolares = $item->precio_unitario ?? $item->producto->precio;
                    $cantidadAbs = abs(floatval($item->cantidad)); // Convertir a positivo
                    $totDolares = $puDolares * $cantidadAbs;
                    $puBs = $puDolares * $dolar;
                    $totBs = $totDolares * $dolar;
                    // Línea 1: P/U y TOT en dólares (REF)
                    $printer->text("P/U:" . number_format($puDolares, 2) . " TOT:" . number_format($totDolares, 2) . " REF");
                    $printer->text("\n");
                    // Línea 2: P/U y TOT en bolívares
                    $printer->text("P/U:Bs" . number_format($puBs, 2) . " TOT:Bs" . number_format($totBs, 2));
                    $printer->text("\n");
                    
                    // Cantidad (en negativo para mostrar que es devolución)
                    $printer->setTextSize(1, 1);
                    $printer->text("Ct:");
                    $printer->setTextSize(2, 1);
                    $printer->text("-" . $this->formato_numero_dos_decimales($cantidadAbs));
                    $printer->text("\n");
                    $printer->setTextSize(1, 1);

                    $printer->feed();
                    
                    $cantidadTotalDevolucion += $cantidadAbs;
                }
            }

            $printer->text("---------------------------");
            $printer->text("\n");
            $printer->setEmphasis(true);
            $printer->text("PRODUCTOS SALIENTES:");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("---------------------------");
            $printer->text("\n");

            $cantidadTotalEntrada = 0;

            // Imprimir items con cantidad positiva
            foreach ($positiveItems as $item) {
                if ($item->producto) {
                    // Descripción del producto
                    $printer->text($item->producto->descripcion);
                    $printer->text("\n");
                    $printer->text($item->producto->codigo_barras);
                    $printer->text("\n");

                    // Precio unitario y total (usar precio del item, no del producto actual)
                    $printer->setTextSize(1, 1);
                    $puDolares = $item->precio_unitario ?? $item->producto->precio;
                    $cantidadItem = floatval($item->cantidad);
                    $totDolares = $puDolares * $cantidadItem;
                    $puBs = $puDolares * $dolar;
                    $totBs = $totDolares * $dolar;
                    // Línea 1: P/U y TOT en dólares (REF)
                    $printer->text("P/U:" . number_format($puDolares, 2) . " TOT:" . number_format($totDolares, 2) . " REF");
                    $printer->text("\n");
                    // Línea 2: P/U y TOT en bolívares
                    $printer->text("P/U:Bs" . number_format($puBs, 2) . " TOT:Bs" . number_format($totBs, 2));
                    $printer->text("\n");
                    
                    // Cantidad
                    $printer->setTextSize(1, 1);
                    $printer->text("Ct:");
                    $printer->setTextSize(2, 1);
                    $printer->text("+" . $this->formato_numero_dos_decimales($cantidadItem));
                    $printer->text("\n");
                    $printer->setTextSize(1, 1);

                    $printer->feed();
                    
                    $cantidadTotalEntrada += $cantidadItem;
                }
            }

            // RESUMEN FINAL
            $printer->text("---------------------------");
            $printer->text("\n");
            $printer->setEmphasis(true);
            $printer->text("RESUMEN FINAL:");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("Total devuelto: " . number_format($totalDevolucion, 2) . " REF");
            $printer->text("\n");
            $printer->text("Total devuelto: Bs" . number_format($totalDevolucion * $dolar, 2));
            $printer->text("\n");
            $printer->text("Total saliente: " . number_format($totalEntrada, 2) . " REF");
            $printer->text("\n");
            $printer->text("Total saliente: Bs" . number_format($totalEntrada * $dolar, 2));
            $printer->text("\n");
            $printer->text("---------------------------");
            $printer->text("\n");
            
            // Determinar el saldo y método de pago
            if ($saldoFinal > 0) {
                $printer->setEmphasis(true);
                $printer->text("DINERO A FAVOR:");
                $printer->setEmphasis(false);
                $printer->text("\n");
                $printer->text("Monto: " . number_format($saldoFinal, 2));
                $printer->text("\n");
                $printer->setEmphasis(true);
                $printer->text("Método de Pago:");
                $printer->setEmphasis(false);
                $printer->text("\n");
                $this->imprimirPagosDetallados($printer, $pedido, $dolar);
            } elseif ($saldoFinal < 0) {
                $printer->setEmphasis(true);
                $printer->text("DINERO A DEVOLVER:");
                $printer->setEmphasis(false);
                $printer->text("\n");
                $printer->text("Monto: " . number_format(abs($saldoFinal), 2));
                $printer->text("\n");
                $printer->setEmphasis(true);
                $printer->text("Método de Pago:");
                $printer->setEmphasis(false);
                $printer->text("\n");
                $this->imprimirPagosDetallados($printer, $pedido, $dolar);
            } else {
                $printer->setEmphasis(true);
                $printer->text("SALDO CERO:");
                $printer->setEmphasis(false);
                $printer->text("\n");
                $printer->text("No hay diferencia monetaria");
                $printer->text("\n");
            }
            
        } else {
            // Solo productos devueltos (lógica original)
            $printer->setEmphasis(true);
            $printer->text("PRODUCTOS DEVUELTOS:");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("---------------------------");
            $printer->text("\n");

            $cantidadTotalDevolucion = 0;

            // Imprimir solo los items con cantidad negativa
            foreach ($negativeItems as $item) {
                if ($item->producto) {
                    // Descripción del producto
                    $printer->text($item->producto->descripcion);
                    $printer->text("\n");
                    $printer->text($item->producto->codigo_barras);
                    $printer->text("\n");

                    // Precio unitario y total (usar precio del item, no del producto actual)
                    $printer->setTextSize(1, 1);
                    $puDolares = $item->precio_unitario ?? $item->producto->precio;
                    $cantidadAbs = abs(floatval($item->cantidad)); // Convertir a positivo
                    $totDolares = $puDolares * $cantidadAbs;
                    $puBs = $puDolares * $dolar;
                    $totBs = $totDolares * $dolar;
                    // Línea 1: P/U y TOT en dólares (REF)
                    $printer->text("P/U:" . number_format($puDolares, 2) . " TOT:" . number_format($totDolares, 2) . " REF");
                    $printer->text("\n");
                    // Línea 2: P/U y TOT en bolívares
                    $printer->text("P/U:Bs" . number_format($puBs, 2) . " TOT:Bs" . number_format($totBs, 2));
                    $printer->text("\n");
                    
                    // Cantidad (en negativo para mostrar que es devolución)
                    $printer->setTextSize(1, 1);
                    $printer->text("Ct:");
                    $printer->setTextSize(2, 1);
                    $printer->text("-" . $this->formato_numero_dos_decimales($cantidadAbs));
                    $printer->text("\n");
                    $printer->setTextSize(1, 1);

                    $printer->feed();
                    
                    $cantidadTotalDevolucion += $cantidadAbs;
                }
            }

            // Totales
            $printer->text("---------------------------");
            $printer->text("\n");
            $printer->setEmphasis(true);
            $printer->text("RESUMEN DE DEVOLUCION:");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("Cantidad total devuelta: " . $cantidadTotalDevolucion);
            $printer->text("\n");
            $printer->text("Total a devolver: " . number_format($totalDevolucion, 2) . " REF");
            $printer->text("\n");
            $printer->text("Total a devolver: Bs" . number_format($totalDevolucion * $dolar, 2));
            $printer->text("\n");
            $printer->setEmphasis(true);
            $printer->text("Método de Pago:");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $this->imprimirPagosDetallados($printer, $pedido, $dolar);
        }
        
        $printer->text("---------------------------");
        $printer->text("\n");

        // Información adicional
        $printer->text("\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Fecha: ".($pedido->fecha_factura ?? $pedido->created_at));
        $printer->text("\n");
        $printer->text("Por: ".session("usuario") ?? $pedido->vendedor->usuario);
        $printer->text("\n");
        
        // Pie del ticket
        $printer->text("\n");
        $printer->text("*ESTE RECIBO ES SOLO PARA");
        $printer->text("\n");
        $printer->text("VERIFICAR; EXIJA FACTURA FISCAL*");
        $printer->text("\n");
        $printer->text("\n");
        $printer->text("\n");

        $updateprint = pedidos::find($pedido->id);
        $updateprint->ticked = !$updateprint->ticked ? 1 : $updateprint->ticked + 1;
        $updateprint->is_printing = false; // Marcar como no imprimiendo
        $updateprint->save();
    }

    /**
     * Determinar el método de pago basado en el pedido
     */
    private function determinarMetodoPago($pedido)
    {
        // Obtener los métodos de pago del pedido
        $pagos = \App\Models\pago_pedidos::where('id_pedido', $pedido->id)->get();
        
        if ($pagos->isEmpty()) {
            return "No especificado";
        }
        
        $metodos = [];
        foreach ($pagos as $pago) {
            // Basado en el enum 'tipo' de la tabla pago_pedidos:
            // 1 Transferencia, 2 Debito, 3 Efectivo, 4 Credito, 5 Otros, 6 Vuelto
            switch ($pago->tipo) {
                case '1':
                    $metodos[] = "Transferencia";
                    break;
                case '2':
                    $metodos[] = "Débito";
                    break;
                case '3':
                    $metodos[] = "Efectivo";
                    break;
                case '4':
                    $metodos[] = "Crédito";
                    break;
                case '5':
                    $metodos[] = "Otros";
                    break;
                case '6':
                    $metodos[] = "Vuelto";
                    break;
                default:
                    $metodos[] = "Desconocido";
                    break;
            }
        }
        
        return implode(", ", array_unique($metodos));
    }

    /**
     * Imprimir detalle de pagos con monto original, referencia y equivalente en Bs
     */
    private function imprimirPagosDetallados($printer, $pedido, $dolar)
    {
        $pagos = \App\Models\pago_pedidos::where('id_pedido', $pedido->id)->get();
        
        if ($pagos->isEmpty()) {
            $printer->text("No especificado");
            $printer->text("\n");
            return;
        }
        
        foreach ($pagos as $pago) {
            $tipoPago = $this->getTipoPagoDescripcion($pago->tipo);
            $montoOriginal = floatval($pago->monto_original ?? $pago->monto);
            $montoBs = floatval($pago->monto);
            $moneda = $pago->moneda ?? '$';
            $referencia = $pago->referencia ?? '';
            
            // Determinar si es monto negativo
            $esNegativo = $montoOriginal < 0;
            $signo = $esNegativo ? "-" : "";
            $montoOriginalAbs = abs($montoOriginal);
            $montoBsAbs = abs($montoBs);
            
            // Calcular monto en dólares
            $montoDolares = ($moneda == 'bs' && $dolar > 0) ? ($montoOriginalAbs / $dolar) : $montoOriginalAbs;
            if ($moneda == '$') {
                $montoDolares = $montoOriginalAbs;
            }
            
            // Línea 1: Tipo de pago
            $printer->setEmphasis(true);
            $printer->text($tipoPago);
            $printer->setEmphasis(false);
            $printer->text("\n");
            
            // Línea 2: Monto original (con signo si es negativo)
            if ($moneda == '$') {
                $printer->text(" " . $signo . number_format($montoOriginalAbs, 2));
            } elseif ($moneda == 'bs') {
                $printer->text(" " . $signo . "Bs" . number_format($montoOriginalAbs, 2));
            } elseif ($moneda == 'cop') {
                $printer->text(" " . $signo . "COP" . number_format($montoOriginalAbs, 0));
            } else {
                $printer->text(" " . $signo . number_format($montoDolares, 2));
            }
            
            // Agregar referencia si es transferencia o débito
            if (($pago->tipo == 1 || $pago->tipo == 2) && !empty($referencia)) {
                $printer->text(" REF:" . $referencia);
            }
            $printer->text("\n");
            
            // Línea 3: Equivalente en Bs (si la moneda original no es Bs)
            if ($moneda != 'bs') {
                $printer->text(" =" . $signo . "Bs" . number_format($montoBsAbs, 2));
                $printer->text("\n");
            }
        }
    }

    /**
     * Imprimir ticket normal (código original)
     */
    private function imprimirTicketNormal($printer, $pedido, $nombres, $identificacion, $sucursal, $dolar)
    {
        ////TICKET DE GARANTIA
        $isFullFiscal = (new SucursalController)->isFullFiscal();
        
        foreach ($pedido->items as $val) {
            if (!$val->producto) {
               
            }else{
                if ($val->condicion==1) {
                    $printer -> text("-----------------------------");
                    $printer -> text("\n");
                    $ga = garantia::where("id_pedido",$val->id_pedido)->where("id_producto",$val->id_producto)->first();
                    
                    $printer->setEmphasis(true);
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $printer->text("TICKED DE GARANTIA");
                    $printer->setEmphasis(false);
                    $printer -> text("\n");
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer -> text("#FACTURA ORIGINAL: ".$ga->numfactoriginal);
                    $printer -> text("\n");
                    $printer -> text("#TICKET DE GARANTIA: ".$ga->id_pedido);
                    $printer -> text("\n");
                    $printer -> text("CLIENTE: ".$ga->nombre_cliente." (".$ga->ci_cliente.")" );
                    $printer -> text("\n");
                    $printer -> text("TELÉFONO CLIENTE: ".$ga->telefono_cliente );
                    $printer -> text("\n");
                    $printer -> text("AUTORIZO: ".$ga->nombre_autorizo." (".$ga->ci_autorizo.")" );
                    $printer -> text("\n");
                    $printer -> text("CAJERO: ".$ga->nombre_cajero." (".$ga->ci_cajero.")" );
                    $printer -> text("\n");
                    $printer -> text("\n");

                    $printer->text("PRODUCTO");
                    $printer->text("\n");
                    $printer->text($val["producto"]['descripcion']);
                    $printer->text("\n");
                    $printer->text($val["producto"]['codigo_barras']);
                    $printer->text("\n");
                    
                    if (!$isFullFiscal) {
                        $printer->text(addSpaces("P/U. ",6).$val["producto"]['pu']);
                        $printer->text("\n");
                    }
                    
                    $printer->setEmphasis(true);
                    $printer->text(addSpaces("Ct. ",6).$val['cantidad']);
                    $printer->setEmphasis(false);
                    $printer->text("\n");
                    $printer->text("\n");
                    $printer->text("MOTIVO: ".$ga->motivo);
                    $printer->text("\n");
                    $printer->text("DIAS DESDE COMPRA: ".$ga->dias_desdecompra);
                    $printer -> text("\n");
                    
                    $printer->setEmphasis(true);
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $printer->text("FECHA DE CREACION");
                    $printer -> text("\n");
                    $printer->text(date("Y-m-d H:i:s"));
                    $printer->setEmphasis(false);

                    $printer -> text("\n");
                    $printer -> text("-----------------------------");
                    $printer -> text("\n");
                    $printer -> text("\n");
                }
            }
        }
        ////NOTA DE GARANTIA

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        
        $printer -> text("\n");
        $printer -> text($sucursal->nombre_registro);
        $printer -> text("\n");
        $printer -> text($sucursal->rif);
        $printer -> text("\n");
        $printer -> text($sucursal->telefono1." | ".$sucursal->telefono2);
        $printer -> text("\n");

        $printer -> setTextSize(1,1);
        $printer->setEmphasis(true);
        $printer -> text("\n");
        if (!$pedido->ticked) {
            $printer->setTextSize(2,2);
            $printer->setEmphasis(true);
            $printer->text("ORIGINAL");
        } else {
            $printer->setTextSize(1,1);
            $printer->setEmphasis(true); 
            $printer->text("COPIA " . $pedido->ticked);
        }
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer -> text("\n");
        $printer->text("ORDEN DE DESPACHO");
        $printer -> text("\n");
        $printer->text($sucursal->sucursal);
        $printer -> text("\n");
        $printer -> text("#".$pedido->id);
        $printer->setEmphasis(false);

        $printer -> text("\n");

        if ($nombres!="") {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer -> text("Nombre y Apellido: ".$nombres);
            $printer -> text("\n");
            $printer -> text("ID: ".$identificacion);
            $printer -> text("\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->feed();
        $printer->setPrintLeftMargin(0);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(true);
        $printer->setEmphasis(false);
        $items = [];
        $monto_total = 0;

        if($pedido->items){
            foreach ($pedido->items as $val) {
                if (!$val->producto) {
                    $items[] = [
                        'descripcion' => $val->abono,
                        'codigo_barras' => 0,
                        'cantidad' => $val->cantidad,
                        'pu' => $val->monto,
                        'totalprecio' => $val->total,
                        "pu_bs" => $val->monto*$val->tasa,
                        "totalprecio_bs" => $val->total*$val->tasa,
                    ];
                }else{
                    // Usar precio_unitario del item (precio al momento de la venta)
                    $precioItem = $val->precio_unitario ?? $val->producto->precio;
                    $items[] = [
                        'descripcion' => $val->producto->descripcion,
                        'codigo_barras' => $val->producto->codigo_barras,
                        'cantidad' => $val->cantidad,
                        'totalprecio' => $val->total,
                        'pu' => ($val->descuento<0)?$precioItem-$val->des_unitario:$precioItem,
                        "pu_bs" => ($val->descuento<0)?($precioItem-$val->des_unitario)*$val->tasa:$precioItem*$val->tasa,
                        "totalprecio_bs" => $val->total*$val->tasa,
                    ];
                }
            }
        }
       $total_bs = 0;
       $total_dolares = 0;
        foreach ($items as $item) {
            //Current item ROW 1
           $printer->text($item['descripcion']);
           $printer->text("\n");
           $printer->text($item['codigo_barras']);
           $printer->text("\n");

           // Configurar ancho de columnas para ticket de 58mm
           $printer->setTextSize(1, 1);
           if (!$isFullFiscal) {
               // Precio unitario en dólares
               $puDolares = floatval($item['pu']);
               // Calcular total en dólares (P/U * cantidad)
               $cantidad = floatval($item['cantidad']);
               $totDolares = $puDolares * $cantidad;
               // Calcular equivalentes en bolívares
               // Línea 1: P/U y TOT en dólares (REF)
               $printer->text("P/U:" . number_format($item['pu'], 2) . " TOT:" . number_format($item['pu']*$item['cantidad'], 2) . " REF");
               $printer->text("\n");
               // Línea 2: P/U y TOT en bolívares
               $printer->text("P/U:Bs" . number_format($item['pu_bs'], 2) . " TOT:Bs" . number_format($item['pu_bs']*$item['cantidad'], 2));
               $printer->text("\n");
               $total_bs += $item['pu_bs']*$item['cantidad'];
               $total_dolares += $item['pu']*$item['cantidad'];
           }
           
           // Imprimir Ct pequeño y cantidad grande
           $printer->setTextSize(1, 1);
           $printer->text("Ct:");
           $printer->setTextSize(2, 1);
           // Formatear cantidad: si tiene decimales, mostrar 2 decimales; si no, solo entero
           $cantidad = floatval($item['cantidad']);
           if (is_numeric($cantidad) && floor($cantidad) != $cantidad) {
               $printer->text(number_format($cantidad, 2));
           } else {
               $printer->text((int)$cantidad);
           }
           $printer->text("\n");
           $printer->setTextSize(1, 1);

           $printer->feed();
        }

        // Método de pago y totales - No mostrar si isFullFiscal es true
        if (!$isFullFiscal) {
            if (isset($pedido->pagos) && !empty($pedido->pagos)) {
                $printer->setEmphasis(true);
                $printer->text("Método de Pago: ");
                $printer->setEmphasis(false);
                $printer->text("\n");
                foreach ($pedido->pagos as $pago) { 
                    $tipoPago = (new tickera)->getTipoPagoDescripcion($pago->tipo);
                    $montoOriginal = floatval($pago->monto_original ?? $pago->monto);
                    $montoBs = floatval($pago->monto);
                    $moneda = $pago->moneda ?? '$';
                    $referencia = $pago->referencia ?? '';
                    
                    // Determinar si es monto negativo
                    $esNegativo = $montoOriginal < 0;
                    $signo = $esNegativo ? "-" : "";
                    $montoOriginalAbs = abs($montoOriginal);
                    $montoBsAbs = abs($montoBs);
                    
                    // Calcular monto en dólares
                    $montoDolares = ($moneda == 'bs' && $dolar > 0) ? ($montoOriginalAbs / $dolar) : $montoOriginalAbs;
                    if ($moneda == '$') {
                        $montoDolares = $montoOriginalAbs;
                    }
                    
                    // Línea 1: Tipo de pago
                    $printer->setEmphasis(true);
                    $printer->text($tipoPago);
                    $printer->setEmphasis(false);
                    $printer->text("\n");
                    
                    // Línea 2: Monto original (con signo si es negativo)
                    if ($moneda == '$') {
                        $printer->text(" " . $signo . number_format($montoOriginalAbs, 2));
                    } elseif ($moneda == 'bs') {
                        $printer->text(" " . $signo . "Bs" . number_format($montoOriginalAbs, 2));
                    } elseif ($moneda == 'cop') {
                        $printer->text(" " . $signo . "COP" . number_format($montoOriginalAbs, 0));
                    } else {
                        $printer->text(" " . $signo . number_format($montoDolares, 2));
                    }
                    
                    // Agregar referencia si es transferencia o débito
                    if (($pago->tipo == 1 || $pago->tipo == 2) && !empty($referencia)) {
                        $printer->text(" REF:" . $referencia);
                    }
                    $printer->text("\n");
                    
                    // Línea 3: Equivalente en Bs (si la moneda original no es Bs)
                    if ($moneda != 'bs') {
                        $printer->text(" =" . $signo . "Bs" . number_format($montoBsAbs, 2));
                        $printer->text("\n");
                    }
                }
            }
            $printer->text("\n");
            $printer->setEmphasis(true);
            
            $printer->text("Desc: ".$pedido->total_des);
            $printer->text("\n");
            // Agregar detalle de montos y formas de pago al log
            
            $printer->text("Sub-Total: ". number_format($total_bs,2) );
            $printer->text("\n");
            $printer->text("Total: ". number_format($total_bs,2) );
            $printer->text("\n");
            
            $printer->text("\n");
            $printer->text("\n");
        } else {
            // Si es fullFiscal, solo agregar espacio adicional
            $printer->text("\n");
            $printer->text("\n");
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        
        $printer->text("Fecha: ".($pedido->fecha_factura ?? $pedido->created_at));
        $printer->text("\n");
        $printer->text("Por: ".session("usuario") ?? $pedido->vendedor->usuario);

        $printer->text("\n");
        $printer->text("*ESTE RECIBO ES SOLO PARA");
        $printer->text("\n");
        $printer->text("VERIFICAR; EXIJA FACTURA FISCAL*");
        $printer->text("\n");

        $printer->text("\n");
        $printer->text("\n");

        $updateprint = pedidos::find($pedido->id);
        $updateprint->ticked = !$updateprint->ticked ? 1 : $updateprint->ticked + 1;
        $updateprint->is_printing = false; // Marcar como no imprimiendo
        $updateprint->save();
    }

    function sendFiscalTerminal($parametros,$type,$file,$caja_force=null) {
        $codigo_origen = (new sendCentral)->getOrigen();
        $caja = $caja_force!==null?$caja_force:session("usuario");
        
        //$path = "C:/IntTFHKA/IntTFHKA.exe";
        $parametros = [
            'parametros' => $parametros,
            'type' => $type,
            'file' => $file,
        ];
        $response = null;
        $ipReal = null;


        if ($caja_force) {
            $ipReal = gethostbyname($caja_force);
        }

        if($codigo_origen=="sancarlos"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja3";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }

        if($codigo_origen=="cantaura"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja2";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }

        if($codigo_origen=="pariaguan"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja3";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }

        if($codigo_origen=="puntademata"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja3";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }

        if($codigo_origen=="araguadebarcelona"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja2";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }

        if($codigo_origen=="carora"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja1";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }
        if($codigo_origen=="altagraciadeorituco"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja2";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }
        
        if($codigo_origen=="elsombrero"){
            //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja1";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
            //}
        }
        if($codigo_origen=="sansebastian"){
                //if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja2";
                $ipReal = gethostbyname($nombre_equipo);
                $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
                \Log::info('Respuesta de Fiscal Terminal', [
                    'response' => $response->body()
                ]);
                \Log::info('Respuesta de Fiscal Terminal', $parametros);
            //}
        }
        if($codigo_origen=="turen"){
            if ($caja=="caja3"||$caja=="caja4") {
                $nombre_equipo = "caja3";
                $ipReal = gethostbyname($nombre_equipo);
            }
            if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja2";
                $ipReal = gethostbyname($nombre_equipo);
            }
            $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
        }
        if($codigo_origen=="anaco"){
            if ($caja=="caja3"||$caja=="caja4") {
                $nombre_equipo = "caja3";
                $ipReal = gethostbyname($nombre_equipo);
            }
            if ($caja=="caja1"||$caja=="caja2") {
                $nombre_equipo = "caja2";
                //$nombre_equipo = "ospino";
                $ipReal = gethostbyname($nombre_equipo);
            }
            
            $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
        }


        if($codigo_origen=="guacara"){
            if ($caja=="autopago1") {
                $nombre_equipo = "GUACARA-AUTOPAGO1";
                //$nombre_equipo = "ospino";
               // $ipReal = gethostbyname($nombre_equipo);
               $ipReal = "192.168.0.107";
            }
            
            $response = Http::timeout(3)->post("http://$ipReal:3000/fiscal", $parametros);
        }
        
        //shell_exec("C:/IntTFHKA/IntTFHKA.exe ".$parametros);
       
        /* $ipCliente = request()->ip();
        $ipReal = request()->header('X-Forwarded-For') ?? $ipCliente; */


        

        if ($response!==null) {
            if ($response->successful()) {
                return response()->json(['status' => 'ok '.$ipReal]);
            } else {
                return response()->json(['status' => 'error', 'message' => 'No se pudo contactar al cliente'], 500);
            }
        }


    }

    function reportefiscal(Request $req) {
        $type = $req->type;
        $numReporteZ = $req->numReporteZ;
        $caja = $req->caja;

        if (!$numReporteZ) {
            if ($type=="x") {
                $cmd = "I0X";
            }else if($type=="z"){
                $cmd = "I0Z";
            }
        }else{
            $cmd = "I3A".str_pad($numReporteZ, 6, '0', STR_PAD_LEFT).str_pad($numReporteZ, 6, '0', STR_PAD_LEFT);
        }

        $sentencia = $cmd;

        $this->sendFiscalTerminal($sentencia,"reportefiscal","",$caja);

        $rep = ""; 
        /* $repuesta = file('C:/IntTFHKA/Retorno.txt');
        $lineas = count($repuesta);
        for($i=0; $i < $lineas; $i++)
        {
            $rep = $repuesta[$i];
        }  */
        return $rep;
    }

    function sendReciboFiscal(Request $req) {
        $id = $req->pedido;

        return $this->sendReciboFiscalFun($id);
        
    }

    function sendNotaCredito(Request $req) {
        $id = $req->pedido;
        $numfact = $req->numfact;
        $serial = $req->serial;

        if (!(new PedidosController)->checksipedidoprocesado($id)) {
            throw new \Exception("¡Debe procesar el pedido para imprimir!", 1);
        }
        $get_moneda = (new PedidosController)->get_moneda();
        $cop = $get_moneda["cop"];
        $bs = $get_moneda["bs"];
        $pedido = (new PedidosController)->getPedidoFun($id, "todos", $cop, $bs, $bs);

        $devolucion = true;
        if ($pedido->fiscal) {
    
           
            
    
    
                /*  $factura = array(
                0 => "!000000100000001000Harina\n",
                1 => "!000000150000001500Jamon\n",
                2 => '"000000205000003000Patilla\n',
                3 => "#000005000000001000Caja de Whisky\n",
                4 => "101"); */
    
                $factura = [];
                $nombre = $pedido->cliente->nombre;
                $identificacion = $pedido->cliente->identificacion;
                $direccion = $pedido->cliente->direccion;
                $telefono = $pedido->cliente->telefono;
                $fecha = date("d-m-Y", strtotime(substr($pedido->fecha_factura ?? $pedido->created_at,0,10)));

                
                	

              
                    array_push($factura,("iS*".$nombre."\n"));
                    array_push($factura,("iR*".$identificacion."\n"));
                    array_push($factura,("i03Direccion: ".$direccion."\n"));
                    array_push($factura,("i04Telefono: ".$telefono."\n"));
                    
                    
                    
                    
                    /*  iF*00000000001
                    iD*14-07-2015
                    iI*ZPA2000343
                    ACOMENTARIO NOTA DE CREDITO */
                    
               
                array_push($factura,("iF*". str_pad($numfact, 11, '0', STR_PAD_LEFT) ."\n"));
                array_push($factura,("iD*".$fecha."\n"));
                array_push($factura,("iI*".$serial."\n"));
                array_push($factura,"i05Caja: ".($pedido->vendedor->usuario)." - ".$id."\n" );



    
                //iS*Dany Mendez
                //iR*14.547.292
                //i03Direccion: Ppal de la Urbina
                //i04Telefono: (0212) 555-55-55
    
                foreach ($pedido->items as $val) {
    
                    $items[] = [
                        'descripcion' => str_replace("\\"," ",$val->producto->descripcion),
                        'codigo_barras' => $val->producto->codigo_barras,
                        'pu' => $val->producto->precio,
                        'cantidad' => $val->cantidad,
                        'totalprecio' => $val->total,
                    
                    ];
    
                    $precioFull = $val->producto->iva!=0?($val->producto->precio/1.16):$val->producto->precio;
                    if ($val->descuento) {
                        $precioFull = $precioFull * (1 - ($val->descuento/100));
                    }
                    if ($devolucion) {
                        //Es devolucion
                        $exentogravable = floatval($val->producto->iva)?"d1":"d0";
                        
                    }else{
                        $exentogravable = floatval($val->producto->iva)?"!":" ";
                    }
                    // 000000100 000001000
                    
                    $precio = str_pad(number_format($precioFull, 2, '', ''), 10, '0', STR_PAD_LEFT);
                    $ct = str_pad(number_format($val->cantidad, 3, '', ''), 8, '0', STR_PAD_LEFT);
                    // Esta línea elimina todos los caracteres que no sean letras, números o espacios de la descripción del producto.
                    $desc = $this->soloLetrasNumerosEspacios($val->producto->descripcion);
                    
                    array_push($factura,$exentogravable.$precio."$ct".$desc."\n");
                    /* if (floatval($val->descuento)) {
                        array_push($factura, number_format($val->descuento, 2, '', '')."\n");
                    } */
                }
                array_push($factura,"101");
                
                if ($devolucion) {
                    $file = "C:/IntTFHKA/CREDITO.txt";	
                }else{
                    $file = "C:/IntTFHKA/Factura.txt";	
                }
               
    
                $this->sendFiscalTerminal(json_encode($factura),"notacredito",$file);
    
                $rep = ""; 
                /* $repuesta = file('C:/IntTFHKA/Retorno.txt');
                $lineas = count($repuesta);
                for($i=0; $i < $lineas; $i++){
                    $rep = $repuesta[$i];
                }  */
                
                $updateprint = pedidos::find($id);
                $updateprint->fiscal = 1;
                $updateprint->save();
                
                return Response::json([
                    "msj"=>"Imprimiendo Nota de Crédito...".$rep,
                    "estado"=>true,
                ]);
        } 
    }

    function sendReciboFiscalFun($id) {
        if (!(new PedidosController)->checksipedidoprocesado($id)) {
            throw new \Exception("¡Debe procesar el pedido para imprimir!", 1);
        }
        $get_moneda = (new PedidosController)->get_moneda();
        $cop = $get_moneda["cop"];
        $bs = $get_moneda["bs"];
        $pedido = (new PedidosController)->getPedidoFun($id, "todos", $cop, $bs, $bs);

        // Verificar si hay items con cantidad negativa
        $tieneCantidadNegativa = false;
        foreach ($pedido->items as $item) {
            if ($item->cantidad < 0) {
                $tieneCantidadNegativa = true;
                break;
            }
        }

        // Si hay items con cantidad negativa, no procesar
        if ($tieneCantidadNegativa) {
            return Response::json([
                "msj" => "Error: No se puede emitir factura fiscal con cantidades negativas (devoluciones)",
                "estado" => false,
            ]);
        }

        $devolucion = false;
        if (!$pedido->fiscal) {
    
           
            
    
    
                /*  $factura = array(
                0 => "!000000100000001000Harina\n",
                1 => "!000000150000001500Jamon\n",
                2 => '"000000205000003000Patilla\n',
                3 => "#000005000000001000Caja de Whisky\n",
                4 => "101"); */
    
                $factura = [];
                $nombre = $pedido->cliente->nombre;
                $identificacion = $pedido->cliente->identificacion;
                $direccion = $pedido->cliente->direccion;
                $telefono = $pedido->cliente->telefono;

                if ($nombre!="CF") {
                    array_push($factura,("iS*".$nombre."\n"));
                    array_push($factura,("iR*".$identificacion."\n"));
                    array_push($factura,("i03Direccion: ".$direccion."\n"));
                    array_push($factura,("i04Telefono: ".$telefono."\n"));
                }else{
                   /*  return Response::json([
                        "msj"=>"Error: Debe personalizar la factura",
                        "estado"=>false,
                    ]); */
                }
                array_push($factura,"i05Caja: ".($pedido->vendedor->usuario)." - ".$id."\n" );



    
                //iS*Dany Mendez
                //iR*14.547.292
                //i03Direccion: Ppal de la Urbina
                //i04Telefono: (0212) 555-55-55
    
                foreach ($pedido->items as $val) {
    
                    $items[] = [
                        'descripcion' => str_replace("\\"," ",$val->producto->descripcion),
                        'codigo_barras' => $val->producto->codigo_barras,
                        'pu' => $val->producto->precio,
                        'cantidad' => $val->cantidad,
                        'totalprecio' => $val->total,
                    
                    ];
    
                    $precioFull = $val->producto->iva!=0?($val->producto->precio/1.16):$val->producto->precio;
                    if ($val->descuento) {
                        $precioFull = $precioFull * (1 - ($val->descuento/100));
                    }
                    if ($devolucion) {
                        //Es devolucion
                        $exentogravable = floatval($val->producto->iva)?"d1":"d0";
                        
                    }else{
                        $exentogravable = floatval($val->producto->iva)?"!":" ";
                    }
                    // 000000100 000001000
                    
                    $precio = str_pad(number_format($precioFull, 2, '', ''), 10, '0', STR_PAD_LEFT);
                    $ct = str_pad(number_format($val->cantidad, 3, '', ''), 8, '0', STR_PAD_LEFT);
                    $desc = $this->soloLetrasNumerosEspacios($val->producto->descripcion);
                    
                    
                    array_push($factura,$exentogravable.$precio."$ct".$desc."\n");
                  /*   if (floatval($val->descuento)) {
                        array_push($factura, number_format($val->descuento, 2, '', '')."\n");
                    } */
                }
                array_push($factura,"101");
    
                $file = "C:/IntTFHKA/Factura.txt";	
                
    
                $this->sendFiscalTerminal(json_encode($factura),"factura",$file);
    
                $rep = ""; 
               /*  $repuesta = file('C:/IntTFHKA/Retorno.txt');
                $lineas = count($repuesta);
                for($i=0; $i < $lineas; $i++){
                    $rep = $repuesta[$i];
                }  */
                
                $updateprint = pedidos::find($id);
                $updateprint->fiscal = 1;
                $updateprint->save();
                
                return Response::json([
                    "msj"=>"Imprimiendo Factura Fiscal...".$rep,
                    "estado"=>true,
                ]);
        }else{
            return Response::json([
                "msj"=> "Error: Ya se ha impreso Factura Fiscal",
                "estado"=>false,
            ]);
        }
    }
    
    /**
     * Imprimir ticket de garantía
     * POST /api/imprimir-ticket-garantia
     */
    public function imprimirTicketGarantia(Request $request)
    {
        try {
            \DB::beginTransaction();

            // Validar datos requeridos
            $validator = \Validator::make($request->all(), [
                'solicitud_id' => 'required|integer',
                'printer' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Datos inválidos: ' . $validator->errors()->first()
                ], 422);
            }

            $solicitudId = $request->solicitud_id;
            $printerIndex = $request->printer ?? 1;

            // Obtener datos de la solicitud desde central
            $sendCentral = new \App\Http\Controllers\sendCentral();
            $consultaResult = $sendCentral->getGarantiaFromCentral($solicitudId);

            if (!$consultaResult['success']) {
                return Response::json([
                    'success' => false,
                    'message' => 'Error al consultar solicitud: ' . $consultaResult['message']
                ], 404);
            }

            $solicitud = $consultaResult['data'];

            // Verificar que la solicitud esté finalizada
            if ($solicitud['estatus'] !== 'FINALIZADA') {
                return Response::json([
                    'success' => false,
                    'message' => 'Solo se pueden imprimir tickets de solicitudes finalizadas'
                ], 422);
            }

            // Verificar que el pedido de insucursal esté procesado (estado 1)
            if (!empty($solicitud['id_pedido_insucursal'])) {
                $pedidoInsucursal = \App\Models\pedidos::find($solicitud['id_pedido_insucursal']);
                if (!$pedidoInsucursal || $pedidoInsucursal->estado != 1) {
                    return Response::json([
                        'success' => false,
                        'message' => 'Solo se pueden imprimir tickets cuando el pedido esté procesado (estado 1)'
                    ], 422);
                }
            }

            // Obtener número de impresión y tipo
            $numeroImpresion = \App\Models\GarantiaTicketImpresion::getNextPrintNumber($solicitudId);
            $tipoImpresion = \App\Models\GarantiaTicketImpresion::getTipoImpresion($numeroImpresion);

            // Obtener datos de pago si existe id_pedido_insucursal
            $datosPago = [];
            if (!empty($solicitud['id_pedido_insucursal'])) {
                $datosPago = $this->obtenerDatosPago($solicitud['id_pedido_insucursal']);
            }

            // Obtener detalles de la factura original
            $datosFacturaOriginal = [];
            \Log::info('Verificando factura_venta_id', [
                'factura_venta_id' => $solicitud['factura_venta_id'] ?? 'NO EXISTE',
                'solicitud_keys' => array_keys($solicitud)
            ]);
            
            if (!empty($solicitud['factura_venta_id'])) {
                $datosFacturaOriginal = $this->obtenerDatosFacturaOriginal($solicitud['factura_venta_id']);
                \Log::info('Resultado obtención factura', [
                    'datos_obtenidos' => !empty($datosFacturaOriginal),
                    'cantidad_productos' => count($datosFacturaOriginal['productos'] ?? [])
                ]);
            } else {
                \Log::warning('No se encontró factura_venta_id en la solicitud');
            }

            // Configurar impresora
            $sucursal = \App\Models\sucursal::all()->first();
            $arr_printers = explode(";", $sucursal->tickera);
            
            if (count($arr_printers) == 1) {
                $connector = new WindowsPrintConnector($arr_printers[0]);
            } else {
                $printerIndex = min($printerIndex - 1, count($arr_printers) - 1);
                $connector = new WindowsPrintConnector($arr_printers[$printerIndex]);
            }

            $printer = new Printer($connector);
            // Imprimir ticket
            $this->imprimirTicketGarantia58mm($printer, $solicitud, $datosPago, $datosFacturaOriginal, $tipoImpresion, $numeroImpresion, $sucursal);

            // Registrar impresión en base de datos
            \App\Models\GarantiaTicketImpresion::create([
                'solicitud_garantia_id' => $solicitudId,
                'usuario_id' => session('id_usuario'),
                'tipo_impresion' => $tipoImpresion,
                'numero_impresion' => $numeroImpresion,
                'datos_impresion' => [
                    'solicitud' => $solicitud,
                    'datos_pago' => $datosPago,
                    'sucursal' => [
                        'nombre_registro' => $sucursal->nombre_registro,
                        'rif' => $sucursal->rif,
                        'telefono1' => $sucursal->telefono1,
                        'telefono2' => $sucursal->telefono2,
                        'sucursal' => $sucursal->sucursal
                    ],
                    'usuario_impresion' => [
                        'id' => session('id_usuario'),
                        'nombre' => session('nombre_usuario'),
                        'ip' => $request->ip()
                    ]
                ],
                'fecha_impresion' => now(),
                'ip_impresion' => $request->ip(),
                'observaciones' => 'Ticket impreso desde GarantiaList - Solicitud Finalizada'
            ]);

            \DB::commit();

            return Response::json([
                'success' => true,
                'message' => 'Ticket de garantía impreso exitosamente',
                'tipo_impresion' => $tipoImpresion,
                'numero_impresion' => $numeroImpresion
            ]);

        } catch (\Exception $e) {
            \DB::rollback();
            
            \Log::error('Error imprimiendo ticket de garantía', [
                'solicitud_id' => $request->solicitud_id ?? 'N/A',
                'error' => $e->getMessage().", ".$e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return Response::json([
                'success' => false,
                'message' => 'Error al imprimir ticket: ' . $e->getMessage(). " - ".$e->getLine()
            ], 500);
        }
    }

    /**
     * Imprimir ticket de garantía en formato 58mm
     */

    public function formato_numero_dos_decimales($numero) {
        // Convertir a float para asegurar el formato
        $float = floatval($numero);

        // Si es entero, mostrar sin decimales
        if (fmod($float, 1) == 0.0) {
            return (string)intval($float);
        } else {
            // Si tiene decimales, mostrar con dos decimales
            return number_format($float, 2, '.', '');
        }
    }
    /**
     * Deja solo letras (sin ñ/Ñ), números y espacios en el texto.
     * Elimina cualquier otro carácter, incluyendo la ñ y Ñ.
     */
    public function soloLetrasNumerosEspacios($texto) {
        // Quita todo excepto letras a-zA-Z (sin ñ/Ñ), números y espacios
        // Esta línea significa: "Devuelve el texto original, pero eliminando cualquier carácter que NO sea una letra (a-z o A-Z), un número (0-9) o un espacio".
        // Es decir, solo deja letras, números y espacios; elimina signos de puntuación, tildes, la ñ/Ñ y cualquier otro símbolo especial.
        return preg_replace('/[^a-zA-Z0-9\s]/', '', $texto);
    }
    private function imprimirTicketGarantia58mm($printer, $solicitud, $datosPago, $datosFacturaOriginal, $tipoImpresion, $numeroImpresion, $sucursal)
    {
        // Función helper para formatear texto en 58mm con encoding UTF-8
        function formatText58mm($text, $maxLength = 32) {
            // Asegurar que el texto esté en UTF-8
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
            }
            
            // Limpiar caracteres problemáticos
            $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text); // Remover caracteres de control
            $text = str_replace(['[', ']', '{', '}', '\\'], '', $text); // Remover caracteres problemáticos
            
            // Truncar si es necesario
            if (mb_strlen($text, 'UTF-8') <= $maxLength) {
                return $text;
            }
            return mb_substr($text, 0, $maxLength - 3, 'UTF-8') . '...';
        }

        // Función helper para formatear montos compactos
        function formatMonto($monto) {
            return number_format($monto, 2);
        }

        // Función helper para formatear fechas compactas
        function formatFecha($fecha) {
            return date('d/m/Y H:i', strtotime($fecha));
        }



        // Configurar formato para 58mm
        $printer->setTextSize(1, 1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // Membrete de la empresa (igual que en función imprimir)
        $printer->text("\n");
        $printer->text($this->cleanTextForPrinter($sucursal->nombre_registro));
        $printer->text("\n");
        $printer->text($this->cleanTextForPrinter($sucursal->rif));
        $printer->text("\n");
        $printer->text($this->cleanTextForPrinter($sucursal->telefono1 . " | " . $sucursal->telefono2));
        $printer->text("\n");

        $printer->setTextSize(1, 1);
        $printer->setEmphasis(true);

        // Encabezado con tipo de impresión
        $printer->text("\n");
        if ($tipoImpresion === 'ORIGINAL') {
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(true);
            $printer->text("ORIGINAL");
        } else {
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(true);
            $printer->text("COPIA " . ($numeroImpresion - 1));
        }
        $printer->setEmphasis(false);
        $printer->setTextSize(1, 1);
        $printer->text("\n");
        $printer->setTextSize(2, 2);
        $printer->setEmphasis(true);
        
        $printer->text("TICKET DE");
        $printer->text("\n");
        $printer->text("GARANTIA");
        $printer->setEmphasis(false);
        $printer->setTextSize(1, 1);
        $printer->text("\n");
        
        // Información de sucursal más prominente
        $printer->setEmphasis(true);
        $printer->text("SUCURSAL: " . $this->cleanTextForPrinter($sucursal->sucursal));
        $printer->setEmphasis(false);
        $printer->text("\n");
        $printer->text("Solicitud #" . $solicitud['id']);
        $printer->text("\n");
        if (!empty($solicitud['id_pedido_insucursal'])) {
            $printer->text("Pedido #" . $solicitud['id_pedido_insucursal']);
            $printer->text("\n");
        }

        // Información del cliente (formato similar a función imprimir)
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Nombre y Apellido: " . $this->cleanTextForPrinter($solicitud['cliente']['nombre'] . " " . $solicitud['cliente']['apellido']));
        $printer->text("\n");
        $printer->text("ID: " . $this->cleanTextForPrinter($solicitud['cliente']['cedula']));
        $printer->text("\n");
        $printer->text("Telefono: " . $this->cleanTextForPrinter($solicitud['garantia_data']['cliente']['telefono'] ?? 'N/A'));
        $printer->text("\n");
        $printer->text("Direccion: " . $this->cleanTextForPrinter($solicitud['garantia_data']['cliente']['direccion'] ?? 'N/A'));
        $printer->text("\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("\n");

        // Información de la factura original
        if (!empty($datosFacturaOriginal)) {
            $printer->setEmphasis(true);
            $printer->text("FACTURA ORIGINAL VALIDADA:");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("Factura #" . $datosFacturaOriginal['numero_factura']);
            $printer->text("\n");
            $printer->text("Fecha: " . formatFecha($datosFacturaOriginal['fecha_factura']));
            $printer->text("\n");
            $printer->text("Total: $" . formatMonto($datosFacturaOriginal['total_factura']));
            $printer->text("\n");
            
            // Productos de la factura original (mostrar todos)
            if (!empty($datosFacturaOriginal['productos'])) {
                $printer->text("Productos originales:");
                $printer->text("\n");
                foreach ($datosFacturaOriginal['productos'] as $producto) {
                    $printer->text("• " . formatText58mm($this->cleanTextForPrinter($producto['descripcion']), 20));
                    $printer->text("\n");
                    $printer->text("  x" . $this->formato_numero_dos_decimales($producto['cantidad']) . " $" . formatMonto($producto['precio_unitario']) . " = $" . formatMonto($producto['subtotal']));
                    $printer->text("\n");
                }
            }

            // Datos de pago de la factura original
            if (!empty($datosFacturaOriginal['pagos'])) {
                $printer->text("Pagos originales:");
                $printer->text("\n");
                foreach ($datosFacturaOriginal['pagos'] as $pago) {
                    $printer->text("• " . $this->cleanTextForPrinter($pago['tipo_descripcion']) . " $" . formatMonto($pago['monto']) . " " . $this->cleanTextForPrinter($pago['moneda']));
                    if (!empty($pago['referencia'])) {
                        $printer->text(" (Ref:" . formatText58mm($this->cleanTextForPrinter($pago['referencia']), 8) . ")");
                    }
                    $printer->text("\n");
                }
            }
            $printer->text("---------------------------\n");
        }

        // Métodos de pago de la devolución (se mostrarán en la sección de totales)

        // Caso de uso y sucursal
        $printer->setEmphasis(true);
        $casoUsoDesc = $this->getCasoUsoDescription($solicitud['caso_uso']);
        $printer->text("CASO DE USO: " . $this->cleanTextForPrinter($casoUsoDesc));
        $printer->setEmphasis(false);
        $printer->text("\n");
        $printer->text("Sucursal: " . $this->cleanTextForPrinter($sucursal->sucursal));
        $printer->text("\n");
        $printer->text("\n");

        // Información de responsables
        $printer->setEmphasis(true);
        $printer->text("RESPONSABLES:");
        $printer->setEmphasis(false);
        $printer->text("\n");
        $printer->text("Cajero: " . $this->cleanTextForPrinter(@$solicitud['cajero']['nombre'] . " " . @$solicitud['cajero']['apellido']));
        $printer->text("\n");
        $printer->text("(" . $this->cleanTextForPrinter(@$solicitud['cajero']['cedula']) . ")");
        $printer->text("\n");
        $printer->text("GERENTE: " . $this->cleanTextForPrinter(@$solicitud['supervisor']['nombre'] . " " . @$solicitud['supervisor']['apellido']));
        $printer->text("\n");
        $printer->text("(" . $this->cleanTextForPrinter(@$solicitud['supervisor']['cedula']) . ")");
        $printer->text("\n");
        $printer->text("DICI: " . $this->cleanTextForPrinter(@$solicitud['dici']['nombre'] . " " . @$solicitud['dici']['apellido']));
        $printer->text("\n");
        $printer->text("(" . $this->cleanTextForPrinter(@$solicitud['dici']['cedula']) . ")");
        $printer->text("\n");
       /*  if (isset($solicitud['gerente'])) {
            $printer->text("Gerente: " . $solicitud['gerente']['nombre'] . " " . $solicitud['gerente']['apellido']);
            $printer->text("\n");
            $printer->text("(" . $solicitud['gerente']['cedula'] . ")");
            $printer->text("\n");
        } */
        $printer->text("---------------------------\n");

        // Motivo y detalles
        $printer->setEmphasis(true);
        $printer->text("MOTIVO Y DETALLES:");
        $printer->setEmphasis(false);
        $printer->text("\n");
        $printer->text("Motivo: " . $solicitud['motivo_devolucion']);
        $printer->text("\n");
        if (!empty($solicitud['detalles_adicionales'])) {
            $printer->text("Detalles: " . $solicitud['detalles_adicionales']);
            $printer->text("\n");
        }
        $printer->text("Dias desde compra: " . $solicitud['dias_transcurridos_compra']);
        $printer->text("\n");
        $printer->text("Trajo factura: " . ($solicitud['trajo_factura'] ? 'Si' : 'No'));
        $printer->text("\n");
        $printer->text("---------------------------\n");

        // Productos (formato similar a función imprimir)
        $printer->feed();
        $printer->setPrintLeftMargin(0);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        
        $productosData = json_decode($solicitud['productos_data'], true);
        $productosConDatos = $solicitud['productos_con_datos'] ?? [];

        // Separar productos entrantes y salientes
        $productosEntrantes = array_filter($productosData, function($p) { return $p['tipo'] === 'entrada'; });
        $productosSalientes = array_filter($productosData, function($p) { return $p['tipo'] === 'salida'; });

        // Productos ENTRANTES (que devuelve el cliente)
        if (!empty($productosEntrantes)) {
            $printer->setEmphasis(true);
            $printer->text("PRODUCTOS ENTRANTES");
            $printer->text("\n");
            $printer->text("(DEVUELTOS):");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("---------------------------");
            $printer->text("\n");

            foreach ($productosEntrantes as $index => $producto) {
                $productoDetalle = null;
                foreach ($productosConDatos as $prodDetalle) {
                    if ($prodDetalle['id_producto'] == $producto['id_producto']) {
                        $productoDetalle = $prodDetalle;
                        break;
                    }
                }

                if ($productoDetalle && isset($productoDetalle['producto'])) {
                    // Descripción del producto
                    $printer->text($this->soloLetrasNumerosEspacios($productoDetalle['producto']['descripcion']));
                    $printer->text("\n");
                    
                    // Código de barras
                    $printer->text($productoDetalle['producto']['codigo_barras']);
                    $printer->text("\n");

                    // Precio unitario y total
                    $printer->setTextSize(1, 1);
                    $precio = $productoDetalle['producto']['precio'];
                    $total = $precio * $producto['cantidad'];
                    $printer->text("P/U:" . number_format($precio, 2) . "  Tot:" . number_format($total, 2));
                    $printer->text("\n");

                    // Cantidad
                    $printer->setTextSize(1, 1);
                    $printer->text("Ct:");
                    $printer->setTextSize(2, 1);
                    $printer->text($producto['cantidad']);
                    $printer->text("\n");
                    $printer->setTextSize(1, 1);

                    // Estado (DAÑADO/BUENO)
                    $printer->setEmphasis(true);
                    $printer->text("ESTADO: " . $producto['estado']);
                    $printer->setEmphasis(false);
                    $printer->text("\n");

                    $printer->feed();
                }
            }
        }

        // Productos SALIENTES (que recibe el cliente)
        if (!empty($productosSalientes)) {
            $printer->setEmphasis(true);
            $printer->text("PRODUCTOS SALIENTES");
            $printer->text("\n");
            $printer->text("(ENTREGADOS):");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("---------------------------");
            $printer->text("\n");

            foreach ($productosSalientes as $index => $producto) {
                $productoDetalle = null;
                foreach ($productosConDatos as $prodDetalle) {
                    if ($prodDetalle['id_producto'] == $producto['id_producto']) {
                        $productoDetalle = $prodDetalle;
                        break;
                    }
                }

                if ($productoDetalle && isset($productoDetalle['producto'])) {
                    // Descripción del producto
                    $printer->text($this->soloLetrasNumerosEspacios($productoDetalle['producto']['descripcion']));
                    $printer->text("\n");
                    
                    // Código de barras
                    $printer->text($productoDetalle['producto']['codigo_barras']);
                    $printer->text("\n");

                    // Precio unitario y total
                    $printer->setTextSize(1, 1);
                    $precio = $productoDetalle['producto']['precio'];
                    $total = $precio * $producto['cantidad'];
                    $printer->text("P/U:" . number_format($precio, 2) . "  Tot:" . number_format($total, 2));
                    $printer->text("\n");

                    // Cantidad
                    $printer->setTextSize(1, 1);
                    $printer->text("Ct:");
                    $printer->setTextSize(2, 1);
                    $printer->text($producto['cantidad']);
                    $printer->text("\n");
                    $printer->setTextSize(1, 1);

                    // Estado (DAÑADO/BUENO)
                    $printer->setEmphasis(true);
                    $printer->text("ESTADO: " . $producto['estado']);
                    $printer->setEmphasis(false);
                    $printer->text("\n");

                    $printer->feed();
                }
            }
        }

        // Métodos de pago (ya se muestran arriba en la sección específica)

        // Datos de pago adicionales si existen
        

        // Totales y resumen
        $printer->text("---------------------------\n");
        $printer->setEmphasis(true);
        $printer->text("RESUMEN Y TOTALES:");
        $printer->setEmphasis(false);
        $printer->text("\n");
        
        // Calcular totales
        $totalEntrantes = 0;
        $totalSalientes = 0;
        $cantidadEntrantes = 0;
        $cantidadSalientes = 0;

        foreach ($productosEntrantes as $producto) {
            $productoDetalle = null;
            foreach ($productosConDatos as $prodDetalle) {
                if ($prodDetalle['id_producto'] == $producto['id_producto']) {
                    $productoDetalle = $prodDetalle;
                    break;
                }
            }
            if ($productoDetalle && isset($productoDetalle['producto'])) {
                $totalEntrantes += $productoDetalle['producto']['precio'] * $producto['cantidad'];
                $cantidadEntrantes += $producto['cantidad'];
            }
        }

        foreach ($productosSalientes as $producto) {
            $productoDetalle = null;
            foreach ($productosConDatos as $prodDetalle) {
                if ($prodDetalle['id_producto'] == $producto['id_producto']) {
                    $productoDetalle = $prodDetalle;
                    break;
                }
            }
            if ($productoDetalle && isset($productoDetalle['producto'])) {
                $totalSalientes += $productoDetalle['producto']['precio'] * $producto['cantidad'];
                $cantidadSalientes += $producto['cantidad'];
            }
        }

        $printer->text("Entrantes:" . $cantidadEntrantes . " $" . formatMonto($totalEntrantes));
        $printer->text("\n");
        $printer->text("Salientes:" . $cantidadSalientes . " $" . formatMonto($totalSalientes));
        $printer->text("\n");
        
        // Calcular diferencia
        $diferencia = $totalSalientes - $totalEntrantes;
        if ($diferencia < 0) {
            /* $printer->setEmphasis(true);
            $printer->text("DIFERENCIA A FAVOR CLIENTE");
            $printer->setEmphasis(false);
            $printer->text("\n"); */
            $printer->setEmphasis(true);
            $printer->text("¡SE DEBE DEVOLVER AL CLIENTE!");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("$" . formatMonto($diferencia));
            $printer->text("\n");
            
        } elseif ($diferencia > 0) {
            /* $printer->setEmphasis(true);
            $printer->text("DIFERENCIA A FAVOR EMPRESA");
            $printer->setEmphasis(false);
            $printer->text("\n"); */
            $printer->setEmphasis(true);
            $printer->text("CLIENTE DEBE PAGAR DIFERENCIA");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("$" . formatMonto(abs($diferencia)));
            $printer->text("\n");
           
        } else {
            $printer->setEmphasis(true);
            $printer->text("SIN DIFERENCIA");
            $printer->setEmphasis(false);
            $printer->text("\n");
            $printer->text("NO HAY DEVOLUCION NI PAGO");
            $printer->text("\n");
        }
        
        if (!empty($datosPago)) {
            $printer->text("---------------------------\n");
            $printer->setEmphasis(true);
            $printer->text("INFORMACION DE PAGO\n");
            $printer->setEmphasis(false);
            $printer->text("Pedido:" . $solicitud['id_pedido_insucursal'] . "\n");
            foreach ($datosPago as $pago) {
                $printer->text(formatText58mm($this->getTipoPagoDescripcion($pago['tipo']), 15) . " $" . formatMonto($pago['monto']));
                $printer->text("\n");
            }
        }
        
        $printer->text("Total Prod:" . count($productosData));
        $printer->text("\n");

        $printer->text("\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // Fechas y usuario
        $printer->setEmphasis(true);
        $printer->text("FECHAS:");
        $printer->setEmphasis(false);
        $printer->text("\n");
        $printer->text("Creada:" . formatFecha($solicitud['fecha_solicitud']));
        $printer->text("\n");
        if ($solicitud['fecha_aprobacion']) {
            $printer->text("Aprobada:" . formatFecha($solicitud['fecha_aprobacion']));
            $printer->text("\n");
        }
        if ($solicitud['fecha_ejecucion']) {
            $printer->text("Finalizada:" . formatFecha($solicitud['fecha_ejecucion']));
            $printer->text("\n");
        }
        $printer->text("Impreso:" . formatFecha(now()));
        $printer->text("\n");
        $printer->text("Por:" . formatText58mm(session('nombre_usuario') ?? 'Sistema', 20));
        $printer->text("\n");

        // Pie del ticket
        $printer->text("\n");
        $printer->text("*ESTE TICKET ES UN COMPROBANTE");
        $printer->text("\n");
        $printer->text("OFICIAL DE GARANTIA/DEVOLUCION*");
        $printer->text("\n");

        $printer->text("\n");
        $printer->text("\n");
        $printer->text("\n");

        $printer->cut();
        $printer->pulse();
        $printer->close();
    }

    /**
     * Obtener datos de pago por id_pedido_insucursal
     */
    private function obtenerDatosPago($pedidoId)
    {
        try {
            $pagos = \App\Models\pago_pedidos::where('id_pedido', $pedidoId)->get();
            return $pagos->toArray();
        } catch (\Exception $e) {
            \Log::warning('Error obteniendo datos de pago', [
                'pedido_id' => $pedidoId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener datos de la factura original
     */
    private function obtenerDatosFacturaOriginal($facturaId)
    {
        try {
            \Log::info('Obteniendo datos de factura original', ['factura_id' => $facturaId]);

            // Buscar en base de datos local primero
            $factura = \App\Models\pedidos::with('cliente')->where('id', $facturaId)->first();
            
            /* if (!$factura) {
                \Log::warning('Factura no encontrada en BD local', ['factura_id' => $facturaId]);
                return [];
            }

            \Log::info('Factura encontrada', [
                'factura_id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'total' => $factura->total,
                'fecha' => $factura->created_at
            ]); */

            // Obtener productos de la factura
            $productos = \App\Models\items_pedidos::where('id_pedido', $factura->id)
                ->with('producto')
                ->get();

            \Log::info('Productos encontrados', ['cantidad' => $productos->count()]);

            $productosFactura = [];
            foreach ($productos as $item) {
                $productosFactura[] = [
                    'descripcion' => $item->producto->descripcion ?? 'Producto no encontrado',
                    'codigo_barras' => $item->producto->codigo_barras ?? 'N/A',
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => $item->precio_unitario,
                    'subtotal' => $item->monto
                ];
            }

            // Obtener datos de pago del cliente
            $pagosCliente = \App\Models\pago_pedidos::where('id_pedido', $factura->id)->get();
            $pagosFactura = [];
            foreach ($pagosCliente as $pago) {
                $pagosFactura[] = [
                    'tipo' => $pago->tipo,
                    'tipo_descripcion' => $this->getTipoPagoDescripcion($pago->tipo),
                    'monto' => $pago->monto,
                    'moneda' => $pago->moneda ?? 'USD',
                    'referencia' => $pago->referencia ?? ''
                ];
            }

            $resultado = [
                'numero_factura' => $factura->numero_factura ?? $factura->id,
                'fecha_factura' => $factura->created_at,
                'total_factura' => $factura->total,
                'productos' => $productosFactura,
                'pagos' => $pagosFactura
            ];

            // Intentar obtener datos del cliente si existe la relación
            if (isset($factura->cliente)) {
                $resultado['cliente'] = [
                    'nombre' => $factura->cliente->nombre ?? 'N/A',
                    'apellido' => $factura->cliente->apellido ?? 'N/A',
                    'cedula' => $factura->cliente->cedula ?? 'N/A'
                ];
            }

            \Log::info('Datos de factura procesados exitosamente', [
                'factura_id' => $facturaId,
                'productos_count' => count($productosFactura)
            ]);

            return $resultado;

        } catch (\Exception $e) {
            \Log::error('Error obteniendo datos de factura original', [
                'factura_id' => $facturaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Obtener descripción del tipo de pago
     */
    private function getTipoPagoDescripcion($tipo)
    {
        $tipos = [
            1 => 'TRANSFERENCIA',
            2 => 'DÉBITO',
            3 => 'EFECTIVO',
            4 => 'CRÉDITO',
            5 => 'BIOPAGO',
            6 => 'VUELTO'
        ];

        return $tipos[$tipo] ?? 'DESCONOCIDO';
    }

    /**
     * Obtener descripción del caso de uso
     */
    private function getCasoUsoDescription($casoUso)
    {
        $casos = [
            1 => 'Producto por producto',
            2 => 'Producto por dinero',
            3 => 'Dinero por producto',
            4 => 'Dinero por dinero',
            5 => 'Transferencia entre sucursales'
        ];
        return $casos[$casoUso] ?? "Caso de uso $casoUso";
    }

    /**
     * Limpiar texto para impresora asegurando encoding UTF-8
     */
    private function cleanTextForPrinter($text)
    {
        if (empty($text)) return '';
        
        // Asegurar UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        
        // Remover caracteres problemáticos
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        $text = str_replace(['[', ']', '{', '}', '\\'], '', $text);
        
        return $text;
    }
    

    
}
