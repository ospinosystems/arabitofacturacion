<?php

namespace App\Http\Controllers;

use App\Models\garantia;
use App\Models\inventario;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;
use App\Services\GarantiaCentralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Response;
use DB;

class GarantiaController extends Controller
{
    function getGarantias(Request $req) {
        // Solo consultar solicitudes de garantía desde central, no datos locales
        try {
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            // Crear parámetros para la consulta
            $params = [
                'codigo_origen' => $sendCentral->getOrigen(),
                'busqueda' => $req->qgarantia,
                'campo_orden' => $req->garantiaorderCampo ?? 'fecha_solicitud',
                'orden' => $req->garantiaorder ?? 'desc',
                'estado' => $req->garantiaEstado,
                'per_page' => 100 // Obtener más registros para mostrar
            ];
            
            // Hacer petición a central para obtener solicitudes de garantía
            $response = \Http::timeout(30)->get($sendCentral->path() . "/api/garantias/solicitudes", $params);
            
            if ($response->successful()) {
                $data = $response->json();
                $solicitudes = $data['solicitudes'] ?? [];
                
                // Transformar datos para que coincidan con el formato esperado por el frontend
                $arr = [];
                foreach ($solicitudes as $solicitud) {
                    $arr[] = [
                        "id" => $solicitud['id'],
                        "fecha_solicitud" => $solicitud['fecha_solicitud'],
                        "factura_venta_id" => $solicitud['factura_venta_id'],
                        "tipo_solicitud" => $solicitud['tipo_solicitud'],
                        "estatus" => $solicitud['estatus'],
                        "motivo_devolucion" => $solicitud['motivo_devolucion'] ?? '',
                        "monto_devolucion_dinero" => $solicitud['monto_devolucion_dinero'] ?? 0,
                        "cliente" => $solicitud['cliente'] ?? '',
                        "cajero" => $solicitud['cajero'] ?? '',
                        "supervisor" => $solicitud['supervisor'] ?? '',
                        "requiere_aprobacion" => $solicitud['requiere_aprobacion'] ?? false,
                        "fecha_aprobacion" => $solicitud['fecha_aprobacion'] ?? null,
                        "fecha_ejecucion" => $solicitud['fecha_ejecucion'] ?? null,
                        "detalles_ejecucion" => $solicitud['detalles_ejecucion'] ?? null,
                    ];
                }
                
                return $arr;
            } else {
                // Si hay error con central, devolver array vacío
                return [];
            }
            
        } catch (\Exception $e) {
            // En caso de error, devolver array vacío
            return [];
        }
    }
    function setSalidaGarantias(Request $req) {

        try {
            $id = $req->id;
            $cantidad = floatval($req->cantidad);
            $motivo = $req->motivo;

            $g = garantia::where("id_producto",$id);
            $sumpendiente = $g->sum("cantidad");

            if ($cantidad > $sumpendiente) {
                return ["estado"=>false,"msj"=>"ERROR: La cantidad a transferir es mayor a la pendiente"];
            }

            $pedido_garantia = garantia::where("id_producto",$id)->orderBy("id","desc")->first();
            $id_pedido = $pedido_garantia?$pedido_garantia->id_pedido:null;
            garantia::updateOrCreate([
                "id" => null
            ],[
                "id_producto" => $id,
                "id_pedido" => $id_pedido,
                "cantidad" => ($cantidad*-1),
                "motivo" => $motivo,
                "numfactgarantia" => $id_pedido,
            ]);
            return ["estado"=>true,"msj"=>"Éxito al SACAR GARANTIA"];
        } catch (\Exception $e) {
            return ["estado"=>false,"msj"=>"ERROR: ".$e->getMessage()];
        }
    }

    /**
     * Crea un pedido automáticamente cuando se carga una garantía o devolución
     * para los primeros 4 casos de uso, siguiendo la lógica del método 
     * del InventarioController
     */
    public function crearPedidoGarantia(Request $req)
    {
        DB::beginTransaction();
        try {
            // Datos del formulario
            $caso_uso = $req->caso_uso;
            $productos = $req->productos; // Array de productos del carrito
            $usuario_id = session("id_usuario");
            $cliente_id = 1; // Cliente por defecto
            $factura_original = $req->factura_venta_id;
            $garantia_data = $req->garantia_data; // Datos adicionales de la garantía
            
            // Validar que sea uno de los 4 primeros casos de uso
            if (!in_array($caso_uso, [1, 2, 3, 4])) {
                throw new \Exception("Este caso de uso no requiere creación de pedido automático", 1);
            }
            
            // VALIDACIÓN CRÍTICA: Verificar restricciones de devolución de dinero
            $permiteDevolucionDinero = in_array($caso_uso, [2, 4]);
            
            // Validar productos del carrito según las restricciones
            foreach ($productos as $producto_item) {
                $producto = inventario::find($producto_item['id_producto']);
                if (!$producto) {
                    throw new \Exception("Producto con ID {$producto_item['id_producto']} no encontrado");
                }
                
                $precio_original = $producto->precio;
                
                // Validar monto de devolución si está presente
                if (isset($producto_item['monto_devolucion_unitario'])) {
                    $monto_devolucion_unitario = $producto_item['monto_devolucion_unitario'];
                    
                    if (!$permiteDevolucionDinero && $monto_devolucion_unitario > 0) {
                        throw new \Exception("ERROR: El caso de uso $caso_uso NO permite devolución de dinero para el producto {$producto->descripcion}. Solo se permiten diferencias a favor de la empresa.");
                    }
                    
                    if ($permiteDevolucionDinero && $monto_devolucion_unitario > $precio_original) {
                        throw new \Exception("ERROR: El monto de devolución unitario ($monto_devolucion_unitario) no puede ser mayor al precio original ($precio_original) para el producto {$producto->descripcion}");
                    }
                }
            }
            
            // Determinar el tipo de condición según el caso de uso
            // 1=garantía, 2=cambio/devolución
            $condicion = in_array($caso_uso, [1, 2]) ? 1 : 2;
            
            // Crear el pedido principal
            $new_pedido = new pedidos;
            $new_pedido->estado = 0; // Pendiente
            $new_pedido->id_cliente = $cliente_id;
            $new_pedido->id_vendedor = $usuario_id;
            $new_pedido->fecha_factura = now();
            $new_pedido->created_at = now();
            $new_pedido->updated_at = now();
            $new_pedido->save();
            
            $id_pedido = $new_pedido->id;
            
            // Variable para calcular monto total a devolver
            $monto_total_devolucion = 0;
            
            // Procesar cada producto del carrito
            foreach ($productos as $producto_item) {
                $id_producto = $producto_item['id_producto'];
                $cantidad = abs($producto_item['cantidad']); // Asegurar que sea positivo para luego volverlo negativo
                
                // Obtener datos del producto
                $producto = inventario::select(["cantidad", "precio", "descripcion"])->find($id_producto);
                
                if (!$producto) {
                    throw new \Exception("Producto con ID {$id_producto} no encontrado", 1);
                }
                
                $precio = $producto->precio;
                
                // Las cantidades DEBEN estar en negativo para garantías/devoluciones
                $cantidad_negativa = $cantidad * -1;
                $monto = $precio * $cantidad; // Monto positivo para el registro
                
                // Obtener tasas actuales
                $tasa_bs = \App\Models\moneda::where("tipo", 1)->orderBy("id", "desc")->first();
                $tasa_cop = \App\Models\moneda::where("tipo", 2)->orderBy("id", "desc")->first();
                
                // Crear item del pedido
                items_pedidos::create([
                    "id_producto" => $id_producto,
                    "id_pedido" => $id_pedido,
                    "cantidad" => $cantidad_negativa, // ¡MUY IMPORTANTE: cantidad negativa!
                    "monto" => $monto,
                    "condicion" => $condicion, // 1=garantía, 2=cambio/devolución
                    "tasa" => $tasa_bs ? $tasa_bs->valor : 1,
                    "tasa_cop" => $tasa_cop ? $tasa_cop->valor : 1,
                    "precio_unitario" => $precio,
                    "created_at" => now(),
                    "updated_at" => now()
                ]);
                
                // Para casos 2 y 4 (producto por dinero), acumular monto a devolver
                if (in_array($caso_uso, [2, 4])) {
                    $monto_total_devolucion += $monto;
                }
                
                // Crear registro de garantía con todos los datos
                garantia::create([
                    "id_producto" => $id_producto,
                    "id_pedido" => $id_pedido,
                    "cantidad" => $cantidad_negativa, // Cantidad negativa
                    "motivo" => $garantia_data['motivo'] ?? '',
                    "cantidad_salida" => $garantia_data['cantidad_salida'] ?? 0,
                    "motivo_salida" => $garantia_data['motivo_salida'] ?? '',
                    "ci_cajero" => $garantia_data['ci_cajero'] ?? '',
                    "ci_autorizo" => $garantia_data['ci_autorizo'] ?? '',
                    "dias_desdecompra" => $garantia_data['dias_desdecompra'] ?? 0,
                    "ci_cliente" => $garantia_data['ci_cliente'] ?? '',
                    "telefono_cliente" => $garantia_data['telefono_cliente'] ?? '',
                    "nombre_cliente" => $garantia_data['nombre_cliente'] ?? '',
                    "nombre_cajero" => $garantia_data['nombre_cajero'] ?? '',
                    "nombre_autorizo" => $garantia_data['nombre_autorizo'] ?? '',
                    "trajo_factura" => "1", // String "1" para indicar que sí trajo factura
                    "motivonotrajofact" => "", // Vacío ya que siempre trae factura
                    "numfactoriginal" => $factura_original,
                    "numfactgarantia" => $id_pedido,
                ]);
            }
            
            // *** CREAR PAGO NEGATIVO PARA DEVOLUCIONES DE DINERO ***
            // Solo para casos 2 y 4 (producto por dinero)
            if (in_array($caso_uso, [2, 4]) && $monto_total_devolucion > 0) {
                // Usar el monto específico si está definido, sino usar el monto total calculado
                $monto_devolucion_final = $garantia_data['monto_devolucion_dinero'] ?? $monto_total_devolucion;
                
                // Crear pago negativo (tipo 3 = efectivo, monto negativo = devolución)
                pago_pedidos::create([
                    "id_pedido" => $id_pedido,
                    "tipo" => 3, // Efectivo
                    "cuenta" => 1, // Cuenta normal
                    "monto" => $monto_devolucion_final * -1, // ¡MONTO NEGATIVO = DEVOLUCIÓN!
                    "created_at" => now(),
                    "updated_at" => now()
                ]);
                
                // Actualizar el estado del pedido a procesado ya que incluye pago
                $new_pedido->estado = 1;
                $new_pedido->save();
            }
            
            DB::commit();
            
            return response()->json([
                "success" => true,
                "mensaje" => "Pedido de garantía/devolución creado exitosamente",
                "id_pedido" => $id_pedido,
                "tipo" => $condicion == 1 ? "GARANTIA" : "DEVOLUCION",
                "caso_uso" => $caso_uso,
                "productos_procesados" => count($productos),
                "monto_devolucion" => in_array($caso_uso, [2, 4]) ? ($garantia_data['monto_devolucion_dinero'] ?? $monto_total_devolucion) : 0
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                "mensaje" => "Error al crear pedido: " . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Endpoint para manejar la creación completa de garantías/devoluciones
     * que incluye la creación automática de pedidos para los casos 1-4
     */
    public function crearGarantiaCompleta(Request $req)
    {
        DB::beginTransaction();
        try {
            $caso_uso = $req->caso_uso;
            
            // Para casos 1-4, crear pedido automáticamente
            if (in_array($caso_uso, [1, 2, 3, 4])) {
                $response = $this->crearPedidoGarantia($req);
                $response_data = $response->getData(true);
                
                if (!$response_data['success']) {
                    throw new \Exception($response_data['mensaje'], 1);
                }
                
                DB::commit();
                return response()->json([
                    "success" => true,
                    "mensaje" => "Garantía/devolución creada exitosamente con pedido automático",
                    "pedido_id" => $response_data['id_pedido'],
                    "tipo" => $response_data['tipo'],
                    "caso_uso" => $caso_uso,
                    "monto_devolucion" => $response_data['monto_devolucion'] ?? 0
                ]);
            } else {
                // Para caso 5, manejar por separado (producto dañado interno)
                DB::commit();
                return response()->json([
                    "success" => true,
                    "mensaje" => "Caso de uso 5 procesado (sin pedido automático)",
                    "caso_uso" => $caso_uso
                ]);
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                "mensaje" => "Error al crear garantía: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar nueva solicitud de garantía/devolución CON métodos de devolución
     * Este método reemplaza al flujo anterior donde se creaba el pedido inmediatamente
     */
    public function registrarSolicitudGarantia(Request $request)
    {
        DB::beginTransaction();
        try {
            // Decodificar JSON strings que vienen de FormData
            $productos = json_decode($request->productos, true);
            $garantiaData = json_decode($request->garantia_data, true);
            $metodosDevolucion = json_decode($request->metodos_devolucion, true);

            // Obtener datos del carrito dinámico si existen
            $carritoData = null;
            $resumenCarrito = null;
            if ($request->has('entradas') && $request->has('salidas') && $request->has('resumen_carrito')) {
                $carritoData = [
                    'entradas' => json_decode($request->entradas, true),
                    'salidas' => json_decode($request->salidas, true)
                ];
                $resumenCarrito = json_decode($request->resumen_carrito, true);
            }

            // Validar datos principales
            $validator = \Validator::make([
                'caso_uso' => $request->caso_uso,
                'productos' => $productos,
                'garantia_data' => $garantiaData,
                'metodos_devolucion' => $metodosDevolucion,
                'factura_venta_id' => $request->factura_venta_id,
                'foto_factura' => $request->file('foto_factura'),
                'fotos_productos' => $request->file('fotos_productos'),
                'fotos_descripciones' => $request->fotos_descripciones,
                'modo_traslado_interno' => $request->modo_traslado_interno, // Nuevo campo
            ], [
                'caso_uso' => 'required|integer|in:1,2,3,4,5',
                'productos' => 'required|array',
                'productos.*.id_producto' => 'required|integer|exists:inventarios,id',
                'productos.*.cantidad' => 'required|numeric|min:0',
                'productos.*.monto_devolucion_unitario' => 'nullable|numeric|min:0',
                'garantia_data' => 'required|array',
               /*  'garantia_data.motivo' => 'required|string|max:255', */
                'garantia_data.cliente' => 'required|array',
                'garantia_data.cliente.cedula' => 'required|string|max:20',
                'garantia_data.cliente.nombre' => 'required|string|max:100',
                'garantia_data.cliente.apellido' => 'required|string|max:100',
              /*   'garantia_data.cajero' => 'required|array',
                'garantia_data.cajero.cedula' => 'required|string|max:20',
                'garantia_data.cajero.nombre' => 'required|string|max:100',
                'garantia_data.cajero.apellido' => 'required|string|max:100',
                'garantia_data.supervisor' => 'required|array',
                'garantia_data.supervisor.cedula' => 'required|string|max:20',
                'garantia_data.supervisor.nombre' => 'required|string|max:100',
                'garantia_data.supervisor.apellido' => 'required|string|max:100', */
                'garantia_data.dias_desdecompra' => 'required|integer|min:0',
                'garantia_data.cantidad_salida' => 'nullable|integer|min:0',
                'garantia_data.motivo_salida' => 'nullable|string|max:255',
                'garantia_data.detalles_adicionales' => 'nullable|string|max:500',
                'factura_venta_id' => ($request->modo_traslado_interno == 'true' || $request->modo_traslado_interno === true || $request->modo_traslado_interno === '1') ? 'nullable|integer' : 'required|integer', // Condicional según modo traslado interno
                'modo_traslado_interno' => 'nullable|in:true,false,1,0', // Nuevo campo - acepta strings y números
                
                // Fotos
                'foto_factura' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
                'fotos_productos' => 'nullable|array',
                'fotos_productos.*' => 'file|mimes:jpeg,png,jpg|max:5120',
                'fotos_descripciones' => 'nullable|array',
                'fotos_descripciones.*' => 'string|max:255',
                
                // VALIDACIÓN CONDICIONAL: Métodos de devolución solo para casos 2 y 4
                'metodos_devolucion' => 'array',
              /*   'metodos_devolucion.*.tipo' => 'required_with:metodos_devolucion|string|in:efectivo,debito,biopago,transferencia',
                'metodos_devolucion.*.monto' => 'required_with:metodos_devolucion|numeric|min:0.01',
                'metodos_devolucion.*.moneda' => 'required_with:metodos_devolucion|string|in:USD,BS', */
                
                // Campos específicos para transferencias
                'metodos_devolucion.*.banco' => 'required_if:metodos_devolucion.*.tipo,transferencia|string|max:100',
                'metodos_devolucion.*.referencia' => 'required_if:metodos_devolucion.*.tipo,transferencia|string|max:50',
                'metodos_devolucion.*.telefono' => 'nullable|string|max:20',
                
                // Campos opcionales
                'metodos_devolucion.*.cuenta' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                $errores = [];
                foreach ($validator->errors()->messages() as $campo => $mensajes) {
                    foreach ($mensajes as $mensaje) {
                        $errores[] = "$campo: $mensaje";
                    }
                }
                throw new \Exception("Datos de validación incorrectos: " . implode(', ', $errores));
            }

            // Validación especial para traslados internos
            $modoTrasladoInterno = ($request->modo_traslado_interno == 'true' || $request->modo_traslado_interno === true || $request->modo_traslado_interno === '1');
            
            // Log para debug
            \Log::info('Modo traslado interno', [
                'valor_original' => $request->modo_traslado_interno,
                'tipo' => gettype($request->modo_traslado_interno),
                'modo_traslado_interno' => $modoTrasladoInterno,
                'factura_venta_id' => $request->factura_venta_id
            ]);
            
            if ($modoTrasladoInterno) {
                // En traslados internos, validar que el producto que entra sea el mismo que sale
                if ($carritoData && !empty($carritoData['entradas']) && !empty($carritoData['salidas'])) {
                    $productosEntrada = collect($carritoData['entradas'])->pluck('id')->sort()->values();
                    $productosSalida = collect($carritoData['salidas'])->pluck('id')->sort()->values();
                    
                    // Verificar que sean los mismos productos
                    if ($productosEntrada->toArray() !== $productosSalida->toArray()) {
                        throw new \Exception("En traslados internos, el producto que entra debe ser el mismo que sale");
                    }
                    
                    // Verificar que las cantidades sean iguales
                    $totalEntrada = collect($carritoData['entradas'])->sum('cantidad');
                    $totalSalida = collect($carritoData['salidas'])->sum('cantidad');
                    
                    if ($totalEntrada !== $totalSalida) {
                        throw new \Exception("En traslados internos, la cantidad que entra debe ser igual a la que sale");
                    }
                }
            }

            $caso_uso = $request->caso_uso;
            // $productos, $garantia_data, $metodos_devolucion ya están decodificados arriba
            $factura_original = $request->factura_venta_id;

            // Obtener tasas de cambio actuales
            $tasas = $this->obtenerTasasCambio();
            
            // USAR DATOS DEL CARRITO DINÁMICO SI ESTÁN DISPONIBLES
            if ($resumenCarrito) {
                // Usar los datos ya calculados del carrito dinámico
                $diferencia_pago = $resumenCarrito['diferencia_pago']; // Ya calculado correctamente en el frontend
                $balance_tipo = $resumenCarrito['balance_tipo']; // 'favor_cliente', 'favor_empresa', 'equilibrio'
                $total_entradas = $resumenCarrito['total_entradas'];
                $total_salidas = $resumenCarrito['total_salidas'];
                
                $hayDiferenciaPositiva = $diferencia_pago > 0.01; // Cliente debe recibir dinero
                $hayDiferenciaNegativa = $diferencia_pago < -0.01; // Empresa recibe más valor
                $monto_total_devolucion_usd = max(0, $diferencia_pago); // Solo si es positivo
                
                // Para el servicio, usar los totales del carrito dinámico
                $monto_total_productos_devueltos = $total_entradas;
                $monto_total_productos_entregados = $total_salidas;
            } else {
                // Fallback: Calcular usando el método anterior (menos preciso)
            $monto_total_productos_devueltos = 0; // Lo que ENTRA a la empresa
            $monto_total_productos_entregados = 0; // Lo que SALE de la empresa
            
            foreach ($productos as $producto_item) {
                $producto = inventario::find($producto_item['id_producto']);
                if (!$producto) {
                    throw new \Exception("Producto con ID {$producto_item['id_producto']} no encontrado");
                }
                
                $precio_original = $producto->precio;
                $cantidad = abs($producto_item['cantidad']);
                
                    // Solo calcular productos que ENTRAN (tipo = 'entrada' o no especificado)
                    if (!isset($producto_item['tipo']) || $producto_item['tipo'] === 'entrada') {
                if (isset($producto_item['monto_devolucion_unitario'])) {
                    $monto_devolucion_unitario = $producto_item['monto_devolucion_unitario'];
                    if ($monto_devolucion_unitario > $precio_original) {
                        throw new \Exception("ERROR: El monto de devolución unitario ($monto_devolucion_unitario) no puede ser mayor al precio original ($precio_original) para el producto {$producto->descripcion}");
                    }
                    $monto_total_productos_devueltos += $monto_devolucion_unitario * $cantidad;
                } else {
                    $monto_total_productos_devueltos += $precio_original * $cantidad;
                }
                    }
                
                    // Calcular productos que SALEN (tipo = 'salida')
                    if (isset($producto_item['tipo']) && $producto_item['tipo'] === 'salida') {
                        $monto_total_productos_entregados += $precio_original * $cantidad;
                }
            }
            
                $diferencia_pago = $monto_total_productos_devueltos - $monto_total_productos_entregados;
                $hayDiferenciaPositiva = $diferencia_pago > 0.01;
                $hayDiferenciaNegativa = $diferencia_pago < -0.01;
                $monto_total_devolucion_usd = max(0, $diferencia_pago);
            }

            // VALIDAR BASADO EN LA DIFERENCIA REAL DE DINERO
            $permiteDevolucionDinero = in_array($caso_uso, [2, 4]);
            
            // Validar que si hay métodos de devolución, sea para casos que lo permiten Y que haya diferencia positiva
            if (!empty($metodosDevolucion) && !$permiteDevolucionDinero) {
                //throw new \Exception("ERROR: Los casos de uso 1, 3 y 5 NO permiten devolución de dinero. Solo se permiten diferencias a favor de la empresa.");
            }
            
            // Si hay diferencia negativa en casos 1, 3, 5 - está bien (cliente debe a empresa)
            if ($hayDiferenciaNegativa && !$permiteDevolucionDinero) {
                // Esto está permitido - la diferencia es a favor de la empresa
            }
            
            // VALIDACIÓN PRINCIPAL: Se requieren métodos cuando hay diferencia positiva real
           /*  if ($hayDiferenciaPositiva && $permiteDevolucionDinero && empty($metodosDevolucion)) {
                throw new \Exception("ERROR: Se requieren métodos de devolución porque hay una diferencia de $" . number_format($diferencia_pago, 2) . " USD a favor del cliente.");
            } */
            
            // Si hay métodos pero no hay diferencia positiva, advertir
          /*   if (!$hayDiferenciaPositiva && !empty($metodosDevolucion)) {
                if ($diferencia_pago == 0) {
                    throw new \Exception("INFO: No se requieren métodos de devolución porque no hay diferencia de dinero (intercambio equilibrado).");
                } else {
                    throw new \Exception("INFO: No se requieren métodos de devolución porque la diferencia es a favor de la empresa.");
                }
            } */
            
            // Si la diferencia es positiva, la empresa debe dinero al cliente
            // Si es negativa, el cliente debe dinero a la empresa
            // Si es cero, no hay diferencia de dinero
            $monto_total_devolucion_usd = max(0, $diferencia_pago);

            // Validar y procesar métodos de devolución (solo cuando hay diferencia positiva real)
            $metodos_procesados = [];
            $monto_total_metodos_usd = 0;
            $transferencias_validadas = [];
            
            if ($hayDiferenciaPositiva && !empty($metodosDevolucion)) {
                foreach ($metodosDevolucion as $metodo) {
                    $metodo_procesado = $this->procesarMetodoDevolucion($metodo, $tasas);
                    $metodos_procesados[] = $metodo_procesado;
                    $monto_total_metodos_usd += $metodo_procesado['monto_usd'];
                }

                // Validar que el monto total coincida (con tolerancia de $0.01)
                if (abs($monto_total_devolucion_usd - $monto_total_metodos_usd) > 0.01) {
                    throw new \Exception("El monto total de los métodos de devolución ($monto_total_metodos_usd USD) debe coincidir con el monto total de la devolución ($monto_total_devolucion_usd USD)");
                }

                // Validar transferencias con central
                foreach ($metodos_procesados as $metodo) {
                    if ($metodo['tipo'] === 'transferencia') {
                        $validacion = $this->validarTransferenciaConCentral($metodo);
                        if (!$validacion['success']) {
                            throw new \Exception("Error al validar transferencia: " . $validacion['message']);
                        }
                        $transferencias_validadas[] = $validacion['data'];
                    }
                }
            }

            // Enviar solicitud directamente a central (sin almacenamiento temporal)



            $service = new GarantiaCentralService();
            $response = $service->enviarSolicitudGarantiaConMetodos([
                'caso_uso' => $caso_uso,
                'productos' => $productos,
                'garantia_data' => $garantiaData,
                'metodos_devolucion' => $metodos_procesados,
                'transferencias_validadas' => $transferencias_validadas,
                'factura_venta_id' => $factura_original,
                'monto_total_devolucion' => $monto_total_devolucion_usd,
                'tasas_cambio' => $tasas,
                
                // Información detallada de la diferencia
                'monto_productos_devueltos' => $monto_total_productos_devueltos,
                'monto_productos_entregados' => $monto_total_productos_entregados,
                'diferencia_neta' => $diferencia_pago,
                'hay_diferencia_positiva' => $hayDiferenciaPositiva,
                'hay_diferencia_negativa' => $hayDiferenciaNegativa,
                'modo_traslado_interno' => $modoTrasladoInterno,
                
                // Archivos de fotos
                'foto_factura' => $request->file('foto_factura'),
                'fotos_productos' => $request->file('fotos_productos'),
                'fotos_descripciones' => $request->fotos_descripciones,
            ]);

            if (!$response['success']) {
                throw new \Exception("Error al enviar solicitud a central: " . $response['message'].' '.@$response['error']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de garantía/devolución enviada exitosamente a central para aprobación',
                'solicitud_central_id' => $response['solicitud_id'],
                'estado' => 'PENDIENTE_APROBACION',
                
                // Información detallada de la diferencia
                'diferencia_calculada' => [
                    'productos_devueltos_usd' => number_format($monto_total_productos_devueltos, 2),
                    'productos_entregados_usd' => number_format($monto_total_productos_entregados, 2),
                    'diferencia_neta_usd' => number_format($diferencia_pago, 2),
                    'empresa_debe_al_cliente' => $hayDiferenciaPositiva,
                    'cliente_debe_a_empresa' => $hayDiferenciaNegativa,
                    'intercambio_sin_diferencia' => ($diferencia_pago == 0),
                ],
                
                'monto_total_devolucion_usd' => $monto_total_devolucion_usd,
                'metodos_devolucion' => $metodos_procesados,
                'tasas_aplicadas' => $tasas
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tasas de cambio actuales del sistema
     */
    private function obtenerTasasCambio()
    {
        // Usar el mismo método que usa PedidosController
        $cop = 1; // Default para COP
        $bs = 1;  // Default para BS

        try {
            if (\Cache::has('bs')) {
                $bs = \Cache::get('bs');
            } else {
                $tasa_bs = \App\Models\moneda::where("tipo", 1)->orderBy("id", "desc")->first();
                $bs = $tasa_bs ? $tasa_bs->valor : 1;
                \Cache::put('bs', $bs);
            }

            if (\Cache::has('cop')) {
                $cop = \Cache::get('cop');
            } else {
                $tasa_cop = \App\Models\moneda::where("tipo", 2)->orderBy("id", "desc")->first();
                $cop = $tasa_cop ? $tasa_cop->valor : 1;
                \Cache::put('cop', $cop);
            }
        } catch (\Exception $e) {
            \Log::warning("Error al obtener tasas de cambio: " . $e->getMessage());
        }

        return [
            'bs_to_usd' => $bs,
            'cop_to_usd' => $cop,
            'timestamp' => now()->toDateTimeString()
        ];
    }

    /**
     * Obtener tasas de cambio actuales
     */
    public function getTasasCambio()
    {
        try {
            $tasas = $this->obtenerTasasCambio();
            
            return response()->json([
                'success' => true,
                'tasas' => $tasas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tasas de cambio: ' . $e->getMessage(),
                'tasas' => [
                    'bs_to_usd' => 37, // Tasa por defecto
                    'cop_to_usd' => 1,
                    'timestamp' => now()->toDateTimeString()
                ]
            ]);
        }
    }

    /**
     * Procesar y validar un método de devolución específico
     */
    private function procesarMetodoDevolucion($metodo, $tasas)
    {
        // Validar tipo de pago
        $tipos_validos = ['efectivo', 'debito', 'biopago', 'transferencia'];
        if (!in_array($metodo['tipo'], $tipos_validos)) {
            throw new \Exception("Tipo de método de devolución inválido: {$metodo['tipo']}");
        }

        // Procesar moneda y convertir a USD si es necesario
        $monto_original = floatval($metodo['monto']);
        $moneda = strtoupper($metodo['moneda']);
        $monto_usd = $monto_original;

        if ($moneda === 'BS') {
            // Convertir de BS a USD
            $monto_usd = $monto_original / $tasas['bs_to_usd'];
        } elseif ($moneda !== 'USD') {
            throw new \Exception("Moneda no soportada: {$moneda}. Solo se permiten USD y BS");
        }

        // Validaciones específicas por tipo
        $metodo_procesado = [
            'tipo' => $metodo['tipo'],
            'monto_original' => $monto_original,
            'moneda_original' => $moneda,
            'monto_usd' => round($monto_usd, 2),
            'tasa_aplicada' => $moneda === 'BS' ? $tasas['bs_to_usd'] : 1,
        ];

        // Agregar campos específicos según el tipo
        switch ($metodo['tipo']) {
            case 'transferencia':
                if (empty($metodo['banco']) || empty($metodo['referencia'])) {
                    throw new \Exception("Las transferencias requieren banco y referencia");
                }
                
                // Validar que la referencia no esté duplicada
                $this->validarReferenciaUnica($metodo['referencia'], $metodo['banco']);
                
                $metodo_procesado['banco'] = $metodo['banco'];
                $metodo_procesado['referencia'] = $metodo['referencia'];
                $metodo_procesado['telefono'] = $metodo['telefono'] ?? '';
                
                // Para transferencias, determinar si es USD o BS basado en el banco
                $metodo_procesado['es_banco_usd'] = $this->esBancoUSD($metodo['banco']);
                break;
                
            case 'debito':
            case 'biopago':
                $metodo_procesado['cuenta'] = $metodo['cuenta'] ?? '1';
                break;
                
            case 'efectivo':
                // Efectivo no requiere campos adicionales
                break;
        }

        return $metodo_procesado;
    }

    /**
     * Validar que una referencia bancaria no esté duplicada
     */
    private function validarReferenciaUnica($referencia, $banco)
    {
        $existe = \App\Models\pagos_referencias::where('descripcion', $referencia)
                                              ->where('banco', $banco)
                                              ->exists();
        
        if ($existe) {
            throw new \Exception("La referencia {$referencia} ya existe para el banco {$banco}");
        }
    }

    /**
     * Determinar si un banco maneja USD (como ZELLE, BINANCE, AirTM)
     */
    private function esBancoUSD($banco)
    {
        $bancos_usd = ['ZELLE', 'BINANCE', 'AIRTM'];
        
        foreach ($bancos_usd as $banco_usd) {
            if (stripos($banco, $banco_usd) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validar transferencia con central usando createTranferenciaAprobacion
     */
    private function validarTransferenciaConCentral($metodo_transferencia)
    {
        try {
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            // Preparar datos para validación con central
            $dataTransfe = [
                'tipo' => 'DEVOLUCION_GARANTIA',
                'monto' => $metodo_transferencia['monto_original'], // Monto en moneda original
                'moneda' => $metodo_transferencia['moneda_original'],
                'monto_usd' => $metodo_transferencia['monto_usd'],
                'tasa_aplicada' => $metodo_transferencia['tasa_aplicada'],
                'banco' => $metodo_transferencia['banco'],
                'referencia' => $metodo_transferencia['referencia'],
                'telefono' => $metodo_transferencia['telefono'] ?? '',
                'motivo' => 'Devolución por garantía/devolución',
                'timestamp' => now()->toDateTimeString(),
                'es_banco_usd' => $metodo_transferencia['es_banco_usd']
            ];

            $response = $sendCentral->createTranferenciaAprobacion($dataTransfe);

            if (is_array($response) && isset($response['estado']) && $response['estado']) {
                return [
                    'success' => true,
                    'data' => [
                        'aprobacion_id' => $response['id'] ?? null,
                        'codigo_aprobacion' => $response['codigo'] ?? null,
                        'estado' => $response['estado_texto'] ?? 'APROBADA',
                        'monto_aprobado' => $response['monto'] ?? $metodo_transferencia['monto_original'],
                        'moneda_aprobada' => $response['moneda'] ?? $metodo_transferencia['moneda_original']
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'Transferencia no aprobada por central: ' . ($response['msj'] ?? 'Error desconocido')
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al validar transferencia: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ejecutar solicitud de garantía APROBADA por central
     * Este método crea los pedidos, items y pagos SOLO después de aprobación
     */
    public function ejecutarSolicitudAprobada(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validar que vengan todos los datos necesarios de la solicitud aprobada
            $validator = \Validator::make($request->all(), [
                'solicitud_central_id' => 'required|integer',
                'caso_uso' => 'required|integer|in:1,2,3,4,5',
                'productos' => 'required|array',
                'garantia_data' => 'required|array',
                'metodos_devolucion' => 'required|array',
                'transferencias_validadas' => 'nullable|array',
                'tasas_cambio' => 'required|array',
                'factura_venta_id' => 'required|integer',
                'monto_total_devolucion' => 'required|numeric',
                'usuario_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                throw new \Exception('Datos de solicitud incompletos: ' . $validator->errors()->first());
            }
            
            // Crear pedido con los datos exactos aprobados
            $id_pedido = $this->crearPedidoGarantiaAprobada(
                $request->caso_uso,
                $request->productos,
                $request->garantia_data,
                $request->metodos_devolucion,
                $request->factura_venta_id,
                $request->usuario_id,
                [
                    'aprobacion_id' => $request->solicitud_central_id,
                    'transferencias_validadas' => $request->transferencias_validadas,
                    'tasas_aplicadas' => $request->tasas_cambio
                ]
            );
            
            // Notificar a central que se ejecutó la solicitud
            $service = new GarantiaCentralService();
            $response = $service->finalizarSolicitud($request->solicitud_central_id, [
                'id_pedido_creado' => $id_pedido,
                'fecha_ejecucion' => now()->toDateTimeString(),
                'estado' => 'EJECUTADA'
            ]);
            
            if (!$response['success']) {
                \Log::warning('Error al notificar ejecución a central: ' . $response['message']);
                // No fallar la ejecución local por error de notificación
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Solicitud de garantía ejecutada exitosamente',
                'id_pedido' => $id_pedido,
                'monto_total_devolucion' => $request->monto_total_devolucion,
                'metodos_aplicados' => $request->metodos_devolucion,
                'tasas_aplicadas' => $request->tasas_cambio
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear pedido de garantía/devolución DESPUÉS de aprobación
     */
    private function crearPedidoGarantiaAprobada($caso_uso, $productos, $garantia_data, $metodos_devolucion, $factura_original, $usuario_id, $datos_aprobacion)
    {
        // VALIDACIÓN CRÍTICA: Verificar restricciones de devolución de dinero
        $permiteDevolucionDinero = in_array($caso_uso, [2, 4]);
        
        // Validar productos según las restricciones
        foreach ($productos as $producto_item) {
            $producto = inventario::find($producto_item['id_producto']);
            if (!$producto) {
                throw new \Exception("Producto con ID {$producto_item['id_producto']} no encontrado");
            }
            
            $precio_original = $producto->precio;
            
            // Validar monto de devolución si está presente
            if (isset($producto_item['monto_devolucion_unitario'])) {
                $monto_devolucion_unitario = $producto_item['monto_devolucion_unitario'];
                
                if (!$permiteDevolucionDinero && $monto_devolucion_unitario > 0) {
                    throw new \Exception("ERROR: El caso de uso $caso_uso NO permite devolución de dinero para el producto {$producto->descripcion}. Solo se permiten diferencias a favor de la empresa.");
                }
                
                if ($permiteDevolucionDinero && $monto_devolucion_unitario > $precio_original) {
                    throw new \Exception("ERROR: El monto de devolución unitario ($monto_devolucion_unitario) no puede ser mayor al precio original ($precio_original) para el producto {$producto->descripcion}");
                }
            }
        }
        
        // Determinar el tipo de condición según el caso de uso
        $condicion = in_array($caso_uso, [1, 2]) ? 1 : 2;

        // Crear el pedido principal
        $new_pedido = new pedidos;
        $new_pedido->estado = 0; // Pendiente
        $new_pedido->id_cliente = 1; // Cliente por defecto
        $new_pedido->id_vendedor = $usuario_id;
        $new_pedido->observaciones = 'Garantía/Devolución - Aprobada por Central ID: ' . ($datos_aprobacion['aprobacion_id'] ?? 'N/A');
        $new_pedido->fecha_factura = now();
        $new_pedido->created_at = now();
        $new_pedido->updated_at = now();
        $new_pedido->save();

        $id_pedido = $new_pedido->id;

        // Procesar cada producto
        foreach ($productos as $producto_item) {
            $id_producto = $producto_item['id_producto'];
            $cantidad = abs($producto_item['cantidad']);

            $producto = inventario::find($id_producto);
            $precio = $producto->precio;
            $cantidad_negativa = $cantidad * -1;
            $monto = $precio * $cantidad;
            
            // Obtener tasas actuales
            $tasa_bs = \App\Models\moneda::where("tipo", 1)->orderBy("id", "desc")->first();
            $tasa_cop = \App\Models\moneda::where("tipo", 2)->orderBy("id", "desc")->first();

            // Crear item del pedido
            items_pedidos::create([
                "id_producto" => $id_producto,
                "id_pedido" => $id_pedido,
                "cantidad" => $cantidad_negativa,
                "monto" => $monto,
                "condicion" => $condicion,
                "tasa" => $tasa_bs ? $tasa_bs->valor : 1,
                "tasa_cop" => $tasa_cop ? $tasa_cop->valor : 1,
                "precio_unitario" => $precio,
                "created_at" => now(),
                "updated_at" => now()
            ]);

            // Crear registro de garantía
            garantia::create([
                "id_producto" => $id_producto,
                "id_pedido" => $id_pedido,
                "cantidad" => $cantidad_negativa,
                "motivo" => $garantia_data['motivo'],
                "cantidad_salida" => $garantia_data['cantidad_salida'] ?? 0,
                "motivo_salida" => $garantia_data['motivo_salida'] ?? '',
                "ci_cajero" => $garantia_data['ci_cajero'],
                "ci_autorizo" => $garantia_data['ci_autorizo'],
                "dias_desdecompra" => $garantia_data['dias_desdecompra'],
                "ci_cliente" => $garantia_data['ci_cliente'],
                "telefono_cliente" => $garantia_data['telefono_cliente'] ?? '',
                "nombre_cliente" => $garantia_data['nombre_cliente'],
                "nombre_cajero" => $garantia_data['nombre_cajero'],
                "nombre_autorizo" => $garantia_data['nombre_autorizo'],
                "trajo_factura" => "1",
                "motivonotrajofact" => "",
                "numfactoriginal" => $factura_original,
                "numfactgarantia" => $id_pedido,
            ]);
        }

        // Crear pagos de devolución según los métodos aprobados (solo para casos con devolución de dinero)
        if (in_array($caso_uso, [2, 4]) && !empty($metodos_devolucion)) {
            foreach ($metodos_devolucion as $metodo) {
                $tipo_pago = $this->convertirTipoPago($metodo['tipo']);
                
                // Crear pago principal
                $pago = pago_pedidos::create([
                    "id_pedido" => $id_pedido,
                    "tipo" => $tipo_pago,
                    "cuenta" => $metodo['cuenta'] ?? 1,
                    "monto" => $metodo['monto_usd'] * -1, // ¡MONTO NEGATIVO = DEVOLUCIÓN en USD!
                    "created_at" => now(),
                    "updated_at" => now()
                ]);

                // Para transferencias, crear registro adicional en pagos_referencias
                if ($metodo['tipo'] === 'transferencia') {
                    \App\Models\pagos_referencias::create([
                        'tipo' => $tipo_pago,
                        'descripcion' => $metodo['referencia'],
                        'monto' => $metodo['monto_original'], // Monto en moneda original (BS o USD)
                        'banco' => $metodo['banco'],
                        'id_pedido' => $id_pedido,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Actualizar estado del pedido a procesado
            $new_pedido->estado = 1;
            $new_pedido->save();
        } else {
            // Para casos sin devolución de dinero (1, 3, 5), el pedido se queda en estado 0 (pendiente)
            // Se procesará cuando central confirme la recepción de los productos
        }

        return $id_pedido;
    }

    /**
     * Convertir tipo de método de devolución a tipo de pago del sistema
     */
    private function convertirTipoPago($tipo_metodo)
    {
        switch ($tipo_metodo) {
            case 'efectivo':
                return 3;
            case 'debito':
                return 2;
            case 'transferencia':
                return 1;
            case 'biopago':
                return 5;
            default:
                return 3; // Efectivo por defecto
        }
    }

    /**
     * Reversar garantía cuando se anula un pedido
     */
    public function reversarGarantia($id_pedido, $solicitud_central_id = null)
    {
        DB::beginTransaction();
        try {
            
            // Verificar que el pedido existe y tiene garantías
            $pedido = pedidos::find($id_pedido);
            if (!$pedido) {
                throw new \Exception("Pedido no encontrado: $id_pedido");
            }
            
            // Obtener garantías asociadas
            $garantias = garantia::where('id_pedido', $id_pedido)->get();
            if ($garantias->isEmpty()) {
                throw new \Exception("No se encontraron garantías para el pedido $id_pedido");
            }
            
            // Obtener el ID de central desde la primera garantía si no se proporciona
            if (!$solicitud_central_id) {
                $primera_garantia = $garantias->first();
                // Extraer el ID de central de las observaciones del pedido
                if (preg_match('/Central ID: (\d+)/', $pedido->observaciones, $matches)) {
                    $solicitud_central_id = $matches[1];
                } else {
                    throw new \Exception("No se pudo determinar el ID de la solicitud en central");
                }
            }
            
            // 1. Reversar en central usando el servicio
            $service = new GarantiaCentralService();
            $response = $service->reversarGarantia($solicitud_central_id, [
                'id_pedido_local' => $id_pedido,
                'motivo_reversa' => 'Anulación de pedido de garantía/devolución',
                'fecha_reversa' => now()->toDateTimeString()
            ]);
            
            if (!$response['success']) {
                throw new \Exception("Error al reversar en central: " . $response['message']);
            }
            
            // 2. Actualizar inventario local (revertir movimientos)
            $items_pedido = items_pedidos::where('id_pedido', $id_pedido)->get();
            foreach ($items_pedido as $item) {
                if ($item->id_producto) {
                    $inventario = inventario::find($item->id_producto);
                    if ($inventario) {
                        // Revertir el movimiento: si el item era negativo, ahora sumamos
                        $inventario->cantidad += abs($item->cantidad);
                        $inventario->save();
                    }
                }
            }
            
            // 3. Reversar referencias bancarias (eliminar transferencias registradas)
            $referencias_eliminadas = [];
            $referencias = \App\Models\pagos_referencias::where('id_pedido', $id_pedido)->get();
            foreach ($referencias as $referencia) {
                $referencias_eliminadas[] = [
                    'banco' => $referencia->banco,
                    'referencia' => $referencia->descripcion,
                    'monto' => $referencia->monto
                ];
                $referencia->delete();
            }
            
            // 4. Marcar garantías como reversadas
            foreach ($garantias as $garantia) {
                $garantia->motivo_salida = 'REVERSADA - Anulación de pedido';
                $garantia->save();
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Garantía reversada exitosamente',
                'id_pedido' => $id_pedido,
                'id_central_reversa' => $response['reversa_id'] ?? null,
                'referencias_eliminadas' => $referencias_eliminadas,
                'inventario_revertido' => count($items_pedido),
                'fecha_reversa' => now()->toDateTimeString()
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error al reversar garantía: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al reversar garantía: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener solicitudes de garantía pendientes de aprobación
     */
    public function getSolicitudesPendientes()
    {
        try {
            // Consultar solicitudes pendientes directamente desde central
            $service = new GarantiaCentralService();
            $response = $service->consultarSolicitudesPendientes();
            
            if (!$response['success']) {
                throw new \Exception('Error al consultar solicitudes en central: ' . $response['message']);
            }
            
            return response()->json([
                'success' => true,
                'solicitudes' => $response['solicitudes'] ?? [],
                'total' => count($response['solicitudes'] ?? [])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener solicitudes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las solicitudes de garantía de esta sucursal desde central
     */
    public function getSolicitudesGarantia(Request $request)
    {
        try {
            // Consultar solicitudes desde central usando el servicio sendCentral
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            // Crear parámetros para la consulta usando codigo_origen
            $params = [
                'codigo_origen' => $sendCentral->getOrigen(),
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'estatus' => $request->estatus,
                'tipo_solicitud' => $request->tipo_solicitud,
                'page' => $request->page ?? 1,
                'per_page' => $request->per_page ?? 15
            ];
            
            // Hacer petición a central para obtener solicitudes de garantía
            $response = \Http::timeout(30)->get($sendCentral->path() . "/api/garantias/solicitudes", $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'solicitudes' => $data['solicitudes'] ?? [],
                    'total' => $data['total'] ?? 0,
                    'current_page' => $data['current_page'] ?? 1,
                    'last_page' => $data['last_page'] ?? 1,
                    'per_page' => $data['per_page'] ?? 15
                ]);
            } else {
                throw new \Exception('Error al comunicarse con central: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener solicitudes de garantía: ' . $e->getMessage(),
                'solicitudes' => [],
                'total' => 0
            ], 500);
        }
    }

    /**
     * Ejecutar solicitud de garantía usando el nuevo flujo separado
     * Este método reemplaza la ejecución legacy que usaba el modelo garantia
     * 
     * FLUJO CRÍTICO: Primero valida y actualiza estatus en central, 
     * solo si central confirma la actualización se ejecuta localmente
     */
    public function ejecutarSolicitudGarantiaModerna(Request $request)
    {
        try {
            // Validar que venga el ID de la solicitud y la caja
            $validator = \Validator::make($request->all(), [
                'solicitud_id' => 'required|integer',
                'id_caja' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de solicitud requerido: ' . $validator->errors()->first()
                ], 422);
            }

            // 1. Consultar la solicitud desde central
            $sendCentral = new \App\Http\Controllers\sendCentral();
            $consultaResult = $sendCentral->getGarantiaFromCentral($request->solicitud_id);

            if (!$consultaResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al consultar solicitud: ' . $consultaResult['message']
                ], 404);
            }

            $solicitudData = $consultaResult['data'];

            // 2. Validar que la solicitud está aprobada
            if ($solicitudData['estatus'] !== 'APROBADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'La solicitud debe estar aprobada para ejecutarse. Estado actual: ' . $solicitudData['estatus']
                ], 422);
            }

            // 3. CRÍTICO: Primero intentar actualizar estatus en central
            $codigoOrigen = $sendCentral->getOrigen();
            $actualizarCentralResult = $this->actualizarEstatusEnCentral(
                $request->solicitud_id,
                $codigoOrigen,
                'EJECUTANDO'
            );

            if (!$actualizarCentralResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede ejecutar la solicitud. Error al actualizar estatus en central: ' . $actualizarCentralResult['message'],
                    'solicitud_id' => $request->solicitud_id
                ], 500);
            }

            // 4. Solo si central confirma la actualización, ejecutar localmente
            DB::beginTransaction();
            try {
                $ejecutorLocal = new \App\Services\GarantiaEjecucionLocalService();
                $resultadoLocal = $ejecutorLocal->ejecutarSolicitudLocalmente($solicitudData, $request->id_caja);

                if (!$resultadoLocal['success']) {
                    DB::rollBack();
                    
                    // Revertir estatus en central
                    $this->revertirEstatusEnCentral($request->solicitud_id, $codigoOrigen, 'APROBADA');
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Error en ejecución local: ' . $resultadoLocal['message'],
                        'solicitud_id' => $request->solicitud_id
                    ], 500);
                }

                // 5. Confirmar finalización en central
                $finalizarCentralResult = $this->finalizarEjecucionEnCentral(
                    $request->solicitud_id,
                    $codigoOrigen,
                    $resultadoLocal
                );

                if (!$finalizarCentralResult['success']) {
                    DB::rollBack();
                    
                    // Revertir estatus en central
                    $this->revertirEstatusEnCentral($request->solicitud_id, $codigoOrigen, 'APROBADA');
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al finalizar en central: ' . $finalizarCentralResult['message'],
                        'solicitud_id' => $request->solicitud_id
                    ], 500);
                }

                DB::commit();

                // 6. Imprimir ticket automáticamente después de finalizar
                try {
                    $this->imprimirTicketAutomatico($request->solicitud_id);
                } catch (\Exception $e) {
                    \Log::warning('Error al imprimir ticket automático', [
                        'solicitud_id' => $request->solicitud_id,
                        'error' => $e->getMessage()
                    ]);
                    // No fallar la ejecución si falla la impresión
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Solicitud de garantía ejecutada exitosamente con validación de central',
                    'resultado_local' => $resultadoLocal,
                    'resultado_central' => $finalizarCentralResult,
                    'solicitud_id' => $request->solicitud_id
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                
                // Revertir estatus en central
                $this->revertirEstatusEnCentral($request->solicitud_id, $codigoOrigen, 'APROBADA');
                
                throw $e;
            }

        } catch (\Exception $e) {
            \Log::error('Error ejecutando solicitud de garantía moderna', [
                'solicitud_id' => $request->solicitud_id ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar la solicitud de garantía: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estatus en central ANTES de ejecutar localmente
     * Esto asegura que central esté sincronizado antes de proceder
     */
    private function actualizarEstatusEnCentral($solicitudId, $codigoOrigen, $nuevoEstatus)
    {
        try {
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            $response = \Http::timeout(30)->put(
                $sendCentral->path() . "/api/garantias/solicitudes/{$solicitudId}/estatus",
                [
                    'codigo_origen' => $codigoOrigen,
                    'nuevo_estatus' => $nuevoEstatus,
                    'timestamp' => now()->toDateTimeString()
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success']) {
                    \Log::info('Estatus actualizado correctamente en central', [
                        'solicitud_id' => $solicitudId,
                        'estatus' => $nuevoEstatus
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => 'Estatus actualizado en central',
                        'data' => $data
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Central rechazó la actualización: ' . ($data['message'] ?? 'Error desconocido')
                    ];
                }
            } else {
                \Log::error('Error HTTP al actualizar estatus en central', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Error HTTP: ' . $response->status() . ' - ' . $response->body()
                ];
            }

        } catch (\Exception $e) {
            \Log::error('Excepción al actualizar estatus en central', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Finalizar ejecución en central (cambiar a FINALIZADA)
     */
    private function finalizarEjecucionEnCentral($solicitudId, $codigoOrigen, $resultadoLocal)
    {
        try {
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            // Preparar datos en la estructura que espera central
            $detallesEjecucion = [
                'solicitud_id' => $solicitudId,
                'caso_uso' => $resultadoLocal['caso_uso'] ?? 1,
                'resultados' => [
                    'success' => $resultadoLocal['success'] ?? true,
                    'message' => $resultadoLocal['message'] ?? 'Ejecución completada exitosamente',
                    'inventario' => $resultadoLocal['inventario'] ?? [],
                    'pagos' => $resultadoLocal['pagos'] ?? [],
                    'movimientos' => $resultadoLocal['movimientos'] ?? [],
                    'pedidos_creados' => $resultadoLocal['pedidos_creados'] ?? [],
                    'totales' => [
                        'productos_procesados' => count($resultadoLocal['inventario'] ?? []),
                        'pagos_procesados' => count($resultadoLocal['pagos'] ?? []),
                        'movimientos_creados' => count($resultadoLocal['movimientos'] ?? [])
                    ],
                    'timestamp_ejecucion' => now()->toDateTimeString(),
                    'codigo_origen' => $codigoOrigen
                ],
                'entradas' => $this->extraerEntradas($resultadoLocal),
                'salidas' => $this->extraerSalidas($resultadoLocal)
            ];
            
            $response = \Http::timeout(30)->post(
                $sendCentral->path() . "/api/garantias/solicitudes/{$solicitudId}/ejecutar",
                [
                    'codigo_origen' => $codigoOrigen,
                    'detalles_ejecucion' => $detallesEjecucion,
                    'timestamp' => now()->toDateTimeString()
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success']) {
                    \Log::info('Ejecución finalizada correctamente en central', [
                        'solicitud_id' => $solicitudId
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => 'Ejecución finalizada en central',
                        'data' => $data
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Central rechazó la finalización: ' . ($data['message'] ?? 'Error desconocido')
                    ];
                }
            } else {
                \Log::error('Error HTTP al finalizar ejecución en central', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Error HTTP: ' . $response->status() . ' - ' . $response->body()
                ];
            }

        } catch (\Exception $e) {
            \Log::error('Excepción al finalizar ejecución en central', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extraer información de productos que entran (para inventario de garantía)
     */
    private function extraerEntradas($resultadoLocal)
    {
        $entradas = [];
        
        if (isset($resultadoLocal['inventario'])) {
            foreach ($resultadoLocal['inventario'] as $movimiento) {
                if (isset($movimiento['tipo']) && $movimiento['tipo'] === 'ENTRADA') {
                    $entradas[] = [
                        'tipo' => 'PRODUCTO',
                        'producto_id' => $movimiento['producto_id'],
                        'cantidad' => $movimiento['cantidad'],
                        'descripcion' => $movimiento['descripcion'] ?? 'Producto devuelto por garantía'
                    ];
                }
            }
        }
        
        return $entradas;
    }

    /**
     * Extraer información de productos que salen y pagos
     */
    private function extraerSalidas($resultadoLocal)
    {
        $salidas = [];
        
        // Productos que salen
        if (isset($resultadoLocal['inventario'])) {
            foreach ($resultadoLocal['inventario'] as $movimiento) {
                if (isset($movimiento['tipo']) && $movimiento['tipo'] === 'SALIDA') {
                    $salidas[] = [
                        'tipo' => 'PRODUCTO',
                        'producto_id' => $movimiento['producto_id'],
                        'cantidad' => $movimiento['cantidad'],
                        'descripcion' => $movimiento['descripcion'] ?? 'Producto entregado por garantía'
                    ];
                }
            }
        }
        
        // Pagos realizados
        if (isset($resultadoLocal['pagos'])) {
            foreach ($resultadoLocal['pagos'] as $pago) {
                if (isset($pago['monto']) && $pago['monto'] != 0) {
                    $salidas[] = [
                        'tipo' => 'DINERO',
                        'monto' => abs($pago['monto']),
                        'tipo_pago' => $pago['tipo_pago'] ?? 'efectivo',
                        'descripcion' => $pago['descripcion'] ?? 'Pago por garantía'
                    ];
                }
            }
        }
        
        return $salidas;
    }

    /**
     * Revertir estatus en central si algo falla
     */
    private function revertirEstatusEnCentral($solicitudId, $codigoOrigen, $estatusAnterior)
    {
        try {
            \Log::warning('Revirtiendo estatus en central', [
                'solicitud_id' => $solicitudId,
                'estatus_anterior' => $estatusAnterior
            ]);
            
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            $response = \Http::timeout(30)->put(
                $sendCentral->path() . "/api/garantias/solicitudes/{$solicitudId}/estatus",
                [
                    'codigo_origen' => $codigoOrigen,
                    'nuevo_estatus' => $estatusAnterior,
                    'timestamp' => now()->toDateTimeString(),
                    'motivo' => 'Revertir por error en ejecución local'
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success']) {
                    \Log::info('Estatus revertido correctamente en central', [
                        'solicitud_id' => $solicitudId,
                        'estatus_revertido' => $estatusAnterior
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => 'Estatus revertido en central'
                    ];
                }
            }
            
            // Si falla la reversión, solo logear el error
            \Log::error('No se pudo revertir estatus en central', [
                'solicitud_id' => $solicitudId,
                'estatus_anterior' => $estatusAnterior,
                'response' => $response->body()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error al revertir estatus en central'
            ];

        } catch (\Exception $e) {
            \Log::error('Excepción al revertir estatus en central', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error de conexión al revertir: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Notificar a central que se ejecutó la solicitud
     * @deprecated Usar finalizarEjecucionEnCentral en su lugar
     */
    private function notificarEjecucionACentral($solicitudId, $codigoOrigen, $resultadoLocal)
    {
        try {
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            $response = \Http::timeout(30)->post(
                $sendCentral->path() . "/api/garantias/solicitudes/{$solicitudId}/ejecutar",
                [
                    'codigo_origen' => $codigoOrigen,
                    'detalles_ejecucion' => $resultadoLocal
                ]
            );

            if ($response->successful()) {
                return $response->json();
            } else {
                \Log::error('Error HTTP al notificar ejecución a central', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Error HTTP: ' . $response->status(),
                    'body' => $response->body()
                ];
            }

        } catch (\Exception $e) {
            \Log::error('Excepción al notificar ejecución a central', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Imprimir ticket automáticamente después de finalizar una solicitud
     */
    private function imprimirTicketAutomatico($solicitudId)
    {
        try {
            // Crear una request simulada para la función de impresión
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'solicitud_id' => $solicitudId,
                'printer' => 1
            ]);

            // Llamar a la función de impresión
            $tickera = new \App\Http\Controllers\tickera();
            $resultado = $tickera->imprimirTicketGarantia($request);

            if ($resultado->getStatusCode() === 200) {
                $data = json_decode($resultado->getContent(), true);
                if ($data['success']) {
                    \Log::info('Ticket impreso automáticamente', [
                        'solicitud_id' => $solicitudId,
                        'tipo_impresion' => $data['tipo_impresion'],
                        'numero_impresion' => $data['numero_impresion']
                    ]);
                } else {
                    \Log::warning('Error al imprimir ticket automático', [
                        'solicitud_id' => $solicitudId,
                        'error' => $data['message']
                    ]);
                }
            } else {
                \Log::warning('Error HTTP al imprimir ticket automático', [
                    'solicitud_id' => $solicitudId,
                    'status' => $resultado->getStatusCode()
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Excepción al imprimir ticket automático', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
