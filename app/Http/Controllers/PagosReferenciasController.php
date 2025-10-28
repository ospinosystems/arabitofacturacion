<?php

namespace App\Http\Controllers;

use App\Models\pagos_referencias;
use App\Models\pedidos;
use App\Models\sucursal;
use App\Models\retenciones;
use Illuminate\Http\Request;
use Response;

class PagosReferenciasController extends Controller
{

    public function sendRefToMerchant(Request $req)
    {
        $id_ref = $req->id_ref;
        $ref = pagos_referencias::find($id_ref);

        if (!$ref) {
            return Response::json(["estado" => false, "msj" => "Referencia no encontrada"]);
        }

        // Obtener la sucursal y su modo de transferencia
        $sucursal = sucursal::first();
        if (!$sucursal) {
            return Response::json(["estado" => false, "msj" => "Sucursal no encontrada"]);
        }

        $modo_transferencia = $sucursal->modo_transferencia ?? 'codigo';

        // Lógica condicional basada en modo_transferencia

        // Si la sucursal es guacara y el usuario es caja5, pasa por procesarMegasoft
        if (
            isset($sucursal->codigo) && strtolower($sucursal->codigo) === 'guacara'
            && strtolower(session('usuario')) === 'caja5'
        ) {
            return $this->procesarMegasoft($ref);
        }
        switch ($modo_transferencia) {
            case 'megasoft':
                return $this->procesarMegasoft($ref);
            case 'central':
                // Validar que la referencia tenga descripción y banco para modo central
                if (empty($ref->descripcion) || empty($ref->banco)) {
                    return Response::json([
                        "estado" => false, 
                        "msj" => "Error: Para el modo central se requiere que la referencia tenga descripción y banco cargados"
                    ]);
                }
                return $this->createTranferenciaAprobacion($req);
            case 'codigo':
                return Response::json([
                    "estado" => "codigo_required",
                    "msj" => "Se requiere código de aprobación",
                    "codigo_generado" => $this->generarCodigoUnico()
                ]);
            default:
                return Response::json(["estado" => false, "msj" => "Modo de transferencia no válido"]);
        }

    }

    private function procesarMegasoft($ref)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $montoFormatted = number_format($ref->monto, 2, '', '');
            
            // Obtener la IP del cliente que realizó la petición (la caja)
            $clientIp = request()->ip();
            // Si la IP es 127.0.0.1, usar "localhost"
            if ($clientIp === '127.0.0.1') {
                $clientIp = 'localhost';
            }
            
            $response = $client->post('http://' . $clientIp . ':8085/vpos/metodo', [
                'json' => [
                    'accion' => "tarjeta",
                    'montoTransaccion' => $montoFormatted,
                    'cedula' => $ref->cedula,
                ]
            ]);

            if ($response->getStatusCode() == 200) {
                // Obtener la respuesta del servicio externo
                $responseBody = json_decode($response->getBody()->getContents(), true);
                
                // La respuesta real está dentro del campo "response"
                $responseData = $responseBody['response'] ?? $responseBody;
                
                // Log para debug
                \Log::info("DEBUG - Validación referencia:", [
                    'responseBody_completo' => $responseBody,
                    'responseData_extraido' => $responseData,
                    'codRespuesta' => $responseData['codRespuesta'] ?? 'NO_EXISTE',
                    'numeroReferencia' => $responseData['numeroReferencia'] ?? 'NO_EXISTE'
                ]);
                
                // Determinar el estado basado en codRespuesta
                $estatus = 'pendiente';
                $mensaje = $responseData['mensajeRespuesta'] ?? 'Sin mensaje de respuesta';

                if (isset($responseData['codRespuesta'])) {
                    if ($responseData['codRespuesta'] === '00') {
                        $estatus = 'aprobada';
                        
                        // Guardar la numeroReferencia en el campo descripcion
                        if (isset($responseData['numeroReferencia'])) {
                            $ref->descripcion = $responseData['numeroReferencia'];
                        }
                        
                        // Actualizar información del banco si está disponible
                        if (isset($responseData['nombreAutorizador'])) {
                            $ref->banco = $responseData['nombreAutorizador'];
                        }
                        
                    } elseif ($responseData['codRespuesta'] === 'VE') {
                        $estatus = 'rechazada';
                    } elseif ($responseData['codRespuesta'] === 'VP') {
                        $estatus = 'pendiente';
                    } else {
                        $estatus = 'error';
                    }
                } else {
                    // Si no hay codRespuesta, tratar como error
                    $estatus = 'error';
                    $mensaje = 'Respuesta inválida del servidor de pagos';
                }

                // Actualizar la referencia con la respuesta completa y el estado
                $ref->response = json_encode($responseBody);
                $ref->estatus = $estatus;
                $ref->save();
                
                // Log para confirmar qué se guardó
                \Log::info("DEBUG - Referencia guardada:", [
                    'id_referencia' => $ref->id,
                    'estatus_guardado' => $ref->estatus,
                    'descripcion_guardada' => $ref->descripcion,
                    'banco_guardado' => $ref->banco
                ]);
                
                return Response::json([
                    "estado" => true,
                    "msj" => $mensaje,
                    "estatus" => $estatus
                ]);
            } else {
                return Response::json(["estado" => false, "msj" => "Error: " . $response->getBody()]);
            }
        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error: " . $e->getMessage()]);
        }
    }

    public function createTranferenciaAprobacion(Request $req)
    {
        try {
            $refs = pagos_referencias::where("id", $req->id_ref)->first();
            $retenciones = retenciones::where("id_pedido",$refs->id_pedido)->get();

            
            $dataTransfe = [
                "refs" => [$refs],
                "retenciones" => $retenciones,
            ];

            try {
                $transfResult = (new sendCentral)->createTranferenciaAprobacion($dataTransfe);
            
            // Log para debugging - ver qué devuelve createTranferenciaAprobacion
            \Log::info("Respuesta de createTranferenciaAprobacion:", [
                'respuesta_completa' => $transfResult,
                'tipo_respuesta' => gettype($transfResult),
                'es_null' => is_null($transfResult),
                'es_false' => $transfResult === false,
                'es_string' => is_string($transfResult),
                'es_array' => is_array($transfResult),
                'tiene_estado' => is_array($transfResult) && isset($transfResult["estado"]),
                'data_enviada' => $dataTransfe
            ]);

                // Si la respuesta es un objeto Response HTTP (error del servidor central)
                if (is_object($transfResult)) {
                    // Si es una respuesta HTTP de Laravel/Guzzle
                    if (method_exists($transfResult, 'status') && method_exists($transfResult, 'body')) {
                        $status = $transfResult->status();
                        $body = $transfResult->body();
                        
                        // Intentar decodificar el body como JSON para obtener más información
                        $bodyDecoded = null;
                        try {
                            $bodyDecoded = json_decode($body, true);
                        } catch (\Exception $e) {
                            // Si no se puede decodificar, usar el body tal como está
                        }
                        
                        $errorMessage = "Error del servidor central (HTTP {$status})";
                        
                        // Si hay un mensaje específico en la respuesta JSON
                        if ($bodyDecoded && isset($bodyDecoded['message'])) {
                            $errorMessage .= ": " . $bodyDecoded['message'];
                        } elseif ($bodyDecoded && isset($bodyDecoded['msj'])) {
                            $errorMessage .= ": " . $bodyDecoded['msj'];
                        } elseif ($status == 404) {
                            $errorMessage .= ": Endpoint no encontrado en el servidor central";
                        } elseif ($status == 500) {
                            $errorMessage .= ": Error interno del servidor central";
                        } elseif ($status >= 400 && $status < 500) {
                            $errorMessage .= ": Error de solicitud";
                        } elseif ($status >= 500) {
                            $errorMessage .= ": Error del servidor";
                        }
                        
                        return Response::json([
                            "estado" => false,
                            "msj" => $errorMessage,
                            "data" => [
                                "status_code" => $status,
                                "response_body" => $body,
                                "response_decoded" => $bodyDecoded,
                                "error_type" => "HTTP_SERVER_ERROR"
                            ]
                        ]);
                    }
                    
                    // Si es un objeto Response de Laravel
                    if (method_exists($transfResult, 'getStatusCode')) {
                        return $transfResult; // Ya es una respuesta JSON válida
                    }
                    
                    // Otro tipo de objeto
                    return Response::json([
                        "estado" => false,
                        "msj" => "Respuesta de objeto desconocido del servidor central",
                        "data" => [
                            "object_class" => get_class($transfResult),
                            "object_methods" => get_class_methods($transfResult),
                            "error_type" => "UNKNOWN_OBJECT"
                        ]
                    ]);
                }

                // Si la respuesta es null o false, probablemente hubo un error de conexión real
                if ($transfResult === null || $transfResult === false) {
                    return Response::json([
                        "estado" => false,
                        "msj" => "Error de conexión: No se pudo conectar con el servidor central. Verifique su conexión a internet.",
                        "error_type" => "CONNECTION_ERROR"
                    ]);
                }

                // Si la respuesta es un string, es un error del servidor central
                if (is_string($transfResult)) {
                    return Response::json([
                        "estado" => false,
                        "msj" => "Error del servidor central: " . $transfResult,
                        "error_type" => "SERVER_EXCEPTION",
                        "data" => [
                            "error_message" => $transfResult
                        ]
                    ]);
                }

                // Si la respuesta es un array, procesar normalmente
                if (isset($transfResult["estado"])) {
                    if ($transfResult["estado"] === true && $transfResult["msj"] == "APROBADO") {
                        $refs->estatus = 'aprobada';
                        $refs->save();
                        return Response::json([
                            "estado" => true,
                            "msj" => "Transferencia aprobada y referencia actualizada.",
                            "data" => $transfResult
                        ]);
                    } else {
                        return Response::json([
                            "estado" => false,
                            "msj" => isset($transfResult["msj"]) ? $transfResult["msj"] : "Error en la aprobación de la transferencia.",
                            "data" => $transfResult
                        ]);
                    }
                } else {
                    // Respuesta inesperada - Log para debugging
                    \Log::warning("Respuesta inesperada del servidor central:", [
                        'respuesta_completa' => $transfResult,
                        'tipo_respuesta' => gettype($transfResult),
                        'es_array' => is_array($transfResult),
                        'keys_disponibles' => is_array($transfResult) ? array_keys($transfResult) : 'N/A'
                    ]);
                    
                    return Response::json([
                        "estado" => false,
                        "msj" => "Respuesta inesperada del servidor central. Tipo: " . gettype($transfResult) . 
                                (is_array($transfResult) ? " | Keys: " . implode(', ', array_keys($transfResult)) : ""),
                        "data" => $transfResult,
                        "debug_info" => [
                            "tipo" => gettype($transfResult),
                            "es_array" => is_array($transfResult),
                            "keys" => is_array($transfResult) ? array_keys($transfResult) : null,
                            "respuesta_raw" => $transfResult
                        ]
                    ]);
                }
            } catch (\Exception $e) {
                return Response::json([
                    "estado" => false,
                    "msj" => "Error al procesar la transferencia: " . $e->getMessage()
                ]);
            }

            // Aquí puedes agregar la lógica específica para procesar la transferencia central
            // Por ejemplo, enviar a un servicio central, guardar en base de datos, etc.
            
            return Response::json([
                "estado" => true,
                "msj" => "Transferencia procesada por central",
                "data" => $dataTransfe
            ]);
            
        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error: " . $e->getMessage()]);
        }
    }

    private function generarCodigoUnico()
    {
        // Generar un código único basado en timestamp y random
        $timestamp = time();
        $random = rand(1000, 9999);
        $codigo = substr($timestamp, -4) . $random;
        
        // Almacenar el código en sesión para validación posterior
        session(['codigo_aprobacion' => $codigo, 'codigo_timestamp' => $timestamp]);
        
        return $codigo;
    }

    public function validarCodigoAprobacion(Request $req)
    {
        try {
            $codigoIngresado = $req->codigo;
            $codigoSesion = session('codigo_aprobacion');
            $timestamp = session('codigo_timestamp');
            
            // Verificar que el código no haya expirado (5 minutos)
            if (!$timestamp || (time() - $timestamp) > 300) {
                return Response::json(["estado" => false, "msj" => "Código expirado"]);
            }
            
            // Generar la clave esperada basada en el código
            $claveEsperada = $this->generarClaveDesdecodigo($codigoSesion);
            
            if ($codigoIngresado === $claveEsperada) {
                // Limpiar la sesión
                session()->forget(['codigo_aprobacion', 'codigo_timestamp']);
                
                // Procesar la referencia original
                $id_ref = $req->id_ref;
                $ref = pagos_referencias::find($id_ref);
                
                if ($ref) {
                    $ref->estatus = 'aprobada';
                    $ref->save();
                    
                    return Response::json([
                        "estado" => true,
                        "msj" => "Código validado correctamente",
                        "estatus" => "aprobada"
                    ]);
                }
            }
            
            return Response::json(["estado" => false, "msj" => "Código incorrecto"]);
            
        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error: " . $e->getMessage()]);
        }
    }
    
    private function generarClaveDesdecodigo($codigo)
    {
        // Algoritmo simple para generar clave desde código
        // Tomar los últimos 4 dígitos, sumar 1227 y tomar los últimos 4 dígitos del resultado
        $ultimosCuatro = substr($codigo, -4);
        $suma = intval($ultimosCuatro) + 1227;
        return substr(strval($suma), -4);
    }
    public function addRefPago(Request $req)
    {
         try {
            $check = true;
            (new PedidosController)->checkPedidoAuth($req->id_pedido);
            
            
            if (isset($req->check)) {
                if ($req->check==false) {
                    $check = false;
                }
            }
            if ($check) {
                $checkPedidoPago = (new PedidosController)->checkPedidoPago($req->id_pedido);
                if ($checkPedidoPago!==true) {
                    return $checkPedidoPago;
                }
            }
            
            $check_exist = pagos_referencias::where("descripcion",$req->descripcion)->where("banco",$req->banco)->first();

          /*   if ($check_exist) {
                return Response::json(["msj"=>"Error: Ya existe Referencia en Banco. ".$req->descripcion." ".$req->banco." PEDIDO ".$req->id_pedido,"estado"=>false]);

            } */
            if (floatval($req->monto) == 0) {
                return Response::json(["msj" => "Error: El monto no puede ser cero", "estado" => false]);
            }
            // Validar que el monto sea un número con hasta dos decimales (formato xxx.xx)
            // Permitir también montos negativos con hasta dos decimales
            
             // Redondear el monto a dos decimales antes de guardar/validar
             $req->monto = number_format(floatval($req->monto), 2, '.', '');
             if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $req->monto)) {
                 return Response::json([
                     "msj" => "Error: El monto debe ser un número (positivo o negativo) con hasta dos decimales (ejemplo: 123.45 o -123.45)",
                     "estado" => false
                 ]);
             }

             // Validar descripción y banco para modo central
             $sucursal = sucursal::first();
             if ($sucursal && $sucursal->modo_transferencia === 'central') {
                 if (empty($req->descripcion)) {
                     return Response::json([
                         "msj" => "Error: En modo central es obligatorio ingresar la descripción/referencia de la transferencia",
                         "estado" => false
                     ]);
                 }
                 
                 if (empty($req->banco)) {
                     return Response::json([
                         "msj" => "Error: En modo central es obligatorio seleccionar el banco de la transferencia",
                         "estado" => false
                     ]);
                 }
             }

             $item = new pagos_referencias;
          
            $item->tipo = $req->tipo;
            $item->monto = $req->monto;
            $item->id_pedido = $req->id_pedido;
            $item->cedula = $req->cedula;
            $item->telefono = $req->telefono;
            $item->descripcion = $req->descripcion ?? null;
            $item->banco = $req->banco ?? null;
            $item->estatus = 'pendiente'; // Estado inicial
            $item->save();

            return Response::json(["msj"=>"¡Éxito!","estado"=>true]);
            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
        }
    }
    public function delRefPago(Request $req)
    {
         try {
            $id = $req->id;
            $pagos_referencias = pagos_referencias::find($id);

            (new PedidosController)->checkPedidoAuth($pagos_referencias->id_pedido);
            $checkPedidoPago = (new PedidosController)->checkPedidoPago($pagos_referencias->id_pedido);
            if ($checkPedidoPago!==true) {
                return $checkPedidoPago;
            }

            if ($pagos_referencias) {
                
                $pagos_referencias->delete();
                return Response::json(["msj"=>"Éxito al eliminar","estado"=>true]);
            }


            
        } catch (\Exception $e) {
            return Response::json(["msj"=>"Error: ".$e->getMessage(),"estado"=>false]);
            
        }
    }


    function getReferenciasElec(Request $req) {
        $fecha1pedido = $req->fecha1pedido;
        $fecha2pedido = $req->fecha2pedido;

        $id_vendedor =  session("id_usuario");
        $tipo_usuario =  session("tipo_usuario");


        
        $gets = pagos_referencias::when(($tipo_usuario!=1),function($q) use($id_vendedor) {
            $q->whereIn("id_pedido",function ($q) use ($id_vendedor) {
                $q->from("pedidos")->where("id_vendedor",$id_vendedor)->select("id");
            });
        })->whereBetween("created_at", ["$fecha1pedido 00:00:00", "$fecha2pedido 23:59:59"]);

        $arr = [
            "refs" => $gets->get()->map(function($q){
                $ped = pedidos::with("vendedor")->where("id",$q->id_pedido)->first();
                $q->vendedorUser = $ped->vendedor->usuario;
                return $q;
            }),
            "total" => $gets->sum("monto")
         ];
         return $arr;
     }

     public function mostrarCambioModoTransferencia()
     {
         // Verificar que sea usuario tipo 1 (admin)
         if (session('tipo_usuario') != 1) {
             abort(403, 'Acceso denegado. Solo administradores pueden acceder a esta función.');
         }

         $sucursal = sucursal::first();
         $modoActual = $sucursal ? $sucursal->modo_transferencia : 'codigo';

         return view('admin.cambiar-modo-transferencia', [
             'modo_actual' => $modoActual,
             'sucursal' => $sucursal
         ]);
     }

     public function procesarCambioModoTransferencia(Request $req)
     {
         try {
             // Verificar que sea usuario tipo 1 (admin)
             if (session('tipo_usuario') != 1) {
                 return Response::json([
                     "estado" => false, 
                     "msj" => "Acceso denegado. Solo administradores pueden cambiar el modo de transferencia."
                 ]);
             }

             // Validar que se proporcione el código
             if (empty($req->codigo_secreto)) {
                 return Response::json([
                     "estado" => false, 
                     "msj" => "Debe ingresar el código secreto para realizar el cambio."
                 ]);
             }

             // Validar que se proporcione el nuevo modo
             if (empty($req->nuevo_modo)) {
                 return Response::json([
                     "estado" => false, 
                     "msj" => "Debe seleccionar el nuevo modo de transferencia."
                 ]);
             }

             // Validar que el nuevo modo sea válido
             $modosValidos = ['central', 'codigo', 'megasoft'];
             if (!in_array($req->nuevo_modo, $modosValidos)) {
                 return Response::json([
                     "estado" => false, 
                     "msj" => "Modo de transferencia no válido. Debe ser: central, codigo o megasoft."
                 ]);
             }

             // Obtener el código de sesión y validar
             $codigoSesion = session('codigo_cambio_modo');
             $timestamp = session('codigo_cambio_timestamp');
             
             // Verificar que el código no haya expirado (5 minutos)
             if (!$timestamp || (time() - $timestamp) > 300) {
                 return Response::json([
                     "estado" => false, 
                     "msj" => "Código expirado. Genere un nuevo código."
                 ]);
             }

             // Generar la clave esperada basada en el código
             $claveEsperada = $this->generarClaveDesdecodigo($codigoSesion);
             
             if ($req->codigo_secreto !== $claveEsperada) {
                 return Response::json([
                     "estado" => false, 
                     "msj" => "Código secreto incorrecto."
                 ]);
             }

             // Obtener o crear la sucursal
             $sucursal = sucursal::first();
             if (!$sucursal) {
                 return Response::json([
                     "estado" => false, 
                     "msj" => "No se encontró información de la sucursal."
                 ]);
             }

             $modoAnterior = $sucursal->modo_transferencia;

             // Actualizar el modo de transferencia
             $sucursal->modo_transferencia = $req->nuevo_modo;
             $sucursal->save();

             // Limpiar la sesión
             session()->forget(['codigo_cambio_modo', 'codigo_cambio_timestamp']);

             // Log del cambio
             \Log::info("Cambio de modo de transferencia:", [
                 'usuario' => session('usuario'),
                 'modo_anterior' => $modoAnterior,
                 'modo_nuevo' => $req->nuevo_modo,
                 'timestamp' => now()
             ]);

             return Response::json([
                 "estado" => true,
                 "msj" => "Modo de transferencia cambiado exitosamente de '{$modoAnterior}' a '{$req->nuevo_modo}'."
             ]);

         } catch (\Exception $e) {
             return Response::json([
                 "estado" => false, 
                 "msj" => "Error: " . $e->getMessage()
             ]);
         }
     }

     public function generarCodigoSecreto()
     {
         // Verificar que sea usuario tipo 1 (admin)
         if (session('tipo_usuario') != 1) {
             return Response::json([
                 "estado" => false, 
                 "msj" => "Acceso denegado."
             ]);
         }

         // Generar un código único basado en timestamp y random
         $timestamp = time();
         $random = rand(1000, 9999);
         $codigo = substr($timestamp, -4) . $random;
         
         // Almacenar el código en sesión para validación posterior
         session(['codigo_cambio_modo' => $codigo, 'codigo_cambio_timestamp' => $timestamp]);
         
         return Response::json([
             "estado" => true,
             "codigo_generado" => $codigo,
             "msj" => "Código generado exitosamente. Válido por 5 minutos."
         ]);
     }
 }
