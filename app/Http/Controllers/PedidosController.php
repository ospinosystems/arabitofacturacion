<?php
namespace App\Http\Controllers;

use App\Models\cajas;
use App\Models\catcajas;
use App\Models\cierres_puntos;
use App\Models\cierres;
use App\Models\pedidos;
use App\Models\moneda;
use App\Models\inventario;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;
use App\Models\clientes;
use App\Models\usuarios;
use App\Models\garantia;



use App\Models\sucursal;
use App\Models\movimientos;
use App\Models\items_movimiento;
use App\Models\pagos_referencias;
use App\Models\movimientosInventario;



use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Mail\enviarCuentaspagar;




use Response;

class PedidosController extends Controller
{



    protected $letras = [
        1 => "L",
        2 => "R",
        3 => "E",
        4 => "A",
        5 => "S",
        6 => "G",
        7 => "F",
        8 => "B",
        9 => "P",
        0 => "X",
    ];
    public function sends()
    {
        return (new sendCentral)->sends();
    }
    public function changepedidouser(Request $req)
    {

       /* el */
    }

    function showcajas() {
        $ene = pedidos::selectRaw('id_vendedor, (SELECT nombre FROM usuarios WHERE id=id_vendedor and usuario LIKE "caja%") as vendedor, COUNT(*) as ventas')->where("created_at","LIKE", "2025-01%")->groupBy("id_vendedor")->get();
        
        $feb = pedidos::selectRaw('id_vendedor, (SELECT nombre FROM usuarios WHERE id=id_vendedor and usuario LIKE "caja%") as vendedor, COUNT(*) as ventas')->where("created_at","LIKE", "2025-02%")->groupBy("id_vendedor")->get();

        $mar = pedidos::selectRaw('id_vendedor, (SELECT nombre FROM usuarios WHERE id=id_vendedor and usuario LIKE "caja%") as vendedor, COUNT(*) as ventas')->where("created_at","LIKE", "2025-03%")->groupBy("id_vendedor")->get();
        return view("reportes.showcajas",[
            "sucursal" => sucursal::first()->codigo,
            "enero" => $ene,
            "febrero" => $feb,
            "marzo" => $mar,
        ]);
    }
    public function setexportpedido(Request $req)
    {
        $id = $req->id;

        $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
            "id_pedido" => $id,
            "tipo" => "exportarPedido",
        ]);
        if ($isPermiso["permiso"]) {
        } else {

            $nuevatarea = (new TareaslocalController)->createTareaLocal([
                "id_pedido" => $id,
                "valoraprobado" => 0,
                "tipo" => "exportarPedido",
                "descripcion" => "Solicitud de Exportacion de pedido: #" . $id,
            ]);
            if ($nuevatarea) {
                return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del DICI", "estado" => false]);
            }
        }

        $p = pedidos::find($id);
        $central = null;
        if ($p) {
            if ($p->export) {
                $central = (new sendCentral)->setPedidoInCentralFromMaster($id, $req->transferirpedidoa, "delete");
            } else {
                $central = (new sendCentral)->setPedidoInCentralFromMaster($id, $req->transferirpedidoa, "add" );
            }
            return $central;
        }
    }
    public function getPedidosFast(Request $req)
    {

        $lastComovamos = cache('last_send_comovamos');
        $now = now();
        if (!$lastComovamos || $now->diffInMinutes(\Carbon\Carbon::parse($lastComovamos)) >= 30) {
            try {
                \Artisan::call('comovamos:send');
            } catch (\Exception $e) {
                \Log::error("Error ejecutando sendComovamos: " . $e->getMessage());
            }
            cache(['last_send_comovamos' => $now], 31 * 60); // cache por 31 minutos
        }

        
        $fecha = $req->fecha1pedido??$this->today();

        if (isset($req->vendedor)) {
            // code...
            $vendedor = $req->vendedor;
        } else {

            $vendedor = [];
        }

        $ret = pedidos::whereBetween("fecha_factura", ["$fecha 00:00:00", "$fecha 23:59:59"]);

        if (count($vendedor)) {
            $ret->whereIn("id_vendedor", $vendedor);
        }

        return $ret->limit(12)
            ->orderBy("id", "desc")
            ->get(["id", "estado"]);
    }
    public function get_moneda()
    {
        if (Cache::has('cop')) {
            $cop = Cache::get('cop');
        } else {
            $cop = moneda::where("tipo", 2)->orderBy("id", "desc")->first()["valor"];
            Cache::put('cop', $cop);
        }


        if (Cache::has('bs')) {
            $bs = Cache::get('bs');
            //
        } else {
            $bs = moneda::where("tipo", 1)->orderBy("id", "desc")->first()["valor"];
            Cache::put('bs', $bs);
        }

        return ["cop" => $cop, "bs" => $bs];
    }
    public function today()
    {
        if (!Cache::has('today')) {
            date_default_timezone_set("America/Caracas");
            $today = date("Y-m-d");

            $fechafixedsql = (new CierresController)->getLastCierre();

            if ($fechafixedsql) {
                $Date1 = $fechafixedsql->fecha;
                $fechafixedsqlmas5 = date('Y-m-d', strtotime($Date1 . " + 3 day"));

                
                    Cache::put('today', $today, 10800);
                    return $today;
                
            } else {
                Cache::put('today', $today, 10800);
                return $today;
            }
        } else {
            return Cache::get('today');
        }




    }

    function printBultos(Request $req) {
        $id = $req->id;
        $bultos = $req->bultos;
        $ped = pedidos::with("cliente","items")->find($id);
        $origen = sucursal::all()->first()->codigo;
        $fecha = $ped->created_at;

        $count_ped = $ped->items->count();
        $cliente = $ped->cliente->nombre;
        $porbulto = [];
        
        for ($i=1; $i <= $bultos ; $i++) { 
            $porbulto[$i] = $i;
        }

        $suc = explode("SUC ",$cliente);
        if (isset($suc[1])) {

            $total_bultos = count($porbulto);

            return view("reportes.bultos",[
                "bultos"=>$porbulto,
                "id"=>$id,
                "total" => count($porbulto),
                "fecha" => $fecha,
                "origen" => $origen,
                "sucursal" => strtoupper($suc[1])
            ]);
        }
        



        
    }
    public function sumpedidos(Request $req)
    {
        $cop = $this->get_moneda()["cop"];
        $bs = $this->get_moneda()["bs"];


        $ped = pedidos::with([
            "cliente",
            "pagos",
            "items" => function ($q) {

                $q->with("producto");
                $q->orderBy("id", "desc");

            }
        ])->whereIn("id", explode(",", $req->id))
            ->get();

        $items = [];

        $cliente = [];

        $subtotal = 0;
        $total_porciento = 0;
        $total_des = 0;
        $total = 0;
        foreach ($ped as $key => $val) {
            if ($key == 0) {
                $cliente = $val->cliente;
            }
            foreach ($val["items"] as $item) {
                if (!$item->id_producto) {
                    return "No puede seleccioar un pago: #" . $item->id_pedido;

                }
                $ct = 0;
                if (isset($items[$item->id_producto])) {
                    $ct = $items[$item->id_producto]->ct + $item->cantidad;
                } else {
                    $ct = $item->cantidad;
                }
                $item->ct = $ct;
                $items[$item->id_producto] = $item;

                $des = ($item->descuento / 100) * $item->monto;
                $total += $item->monto - $des;
                $subtotal += $item->monto;
                $total_porciento += $item->descuento;
                $item->total_des = round($des, 2);
                $total_des += $des;
            }
        }



        // return $items;

        $sucursal = sucursal::all()->first();

        return view("reportes.sumpedidos", [
            "sucursal" => $sucursal,
            "pedido" => $items,
            "cliente" => $cliente,

            "created_at" => $this->today(),
            "id" => time(),

            "subtotal" => ($subtotal * $bs),
            "total_porciento" => $total_porciento,
            "total_des" => ($total_des * $bs),
            "total" => ($total * $bs)
        ]);

    }
    public function getDiaVentaFun($fechaventas, $applyObfuscation = true)
    {

        \Artisan::call('comovamos:send');


        $arr = $this->cerrarFun($fechaventas, 0, 0, 0, [], true, (session("tipo_usuario") == 1 ? true : false), false);
        if ($fechaventas && $applyObfuscation) {
            // foreach ($this->letras as $key => $value) {
            if (isset($arr["total"])) {
                $arr["total"] = toLetras(number_format(floatval($arr["total"]), 2));
            }
            if (isset($arr["5"])) {
                $arr["5"] = toLetras(number_format(floatval($arr["5"]), 2));
            }
            if (isset($arr["3"])) {
                $arr["3"] = toLetras(number_format(floatval($arr["3"]), 2));
            }
            if (isset($arr["2"])) {
                $arr["2"] = toLetras(number_format(floatval($arr["2"]), 2));
            }

            if (isset($arr["1"])) {
                $arr["1"] = toLetras(number_format(floatval($arr["1"]), 2));
            }
            // }
        }


        return $arr;
    }
    public function getVentas(Request $req)
    {
        $fechaventas = $req->fechaventas;
        
        if (!$fechaventas) {
            return response()->json([
                "msj" => "Error: Fecha invalida", 
                "estado" => false
            ]);
        }

        // Obtener usuarios para totalizar
        $id_vendedor = $this->selectUsersTotalizar(false, $fechaventas);
        
        // Consulta optimizada para ventas del día
        $ventas = DB::table('pedidos as p')
            ->join('pago_pedidos as pp', 'p.id', '=', 'pp.id_pedido')
            ->where('p.created_at', 'LIKE', $fechaventas . '%')
            ->where('p.estado', 1)
            //->whereIn('p.id_vendedor', $id_vendedor)
            ->where('pp.monto', '<>', 0)
            ->whereNotIn('pp.tipo', [4, 6]) // Excluir créditos y vueltos
            ->select([
                'p.id as id_pedido',
                'pp.monto',
                'pp.tipo',
                'pp.updated_at',
                DB::raw('DATE_FORMAT(pp.updated_at, "%H:%i") as hora')
            ])
            ->orderBy('pp.updated_at', 'asc')
            ->get();

        // Agrupar ventas por pedido para evitar duplicados
        $ventasAgrupadas = [];
        $totalPorTipo = [1 => 0, 2 => 0, 3 => 0, 5 => 0]; // Transferencia, Débito, Efectivo, Biopago
        $totalGeneral = 0;
        $numVentas = 0;

        foreach ($ventas as $venta) {
            if (!isset($ventasAgrupadas[$venta->id_pedido])) {
                $ventasAgrupadas[$venta->id_pedido] = [
                    'id_pedido' => $venta->id_pedido,
                    'monto' => 0,
                    'hora' => $venta->hora
                ];
                $numVentas++;
            }
            
            $ventasAgrupadas[$venta->id_pedido]['monto'] += $venta->monto;
            $totalPorTipo[$venta->tipo] += $venta->monto;
            $totalGeneral += $venta->monto;
        }

        // Preparar datos para gráfica (agrupar por hora)
        $grafica = [];
        foreach ($ventasAgrupadas as $venta) {
            $hora = $venta['hora'];
            if (!isset($grafica[$hora])) {
                $grafica[$hora] = ['hora' => $hora, 'monto' => 0];
            }
            $grafica[$hora]['monto'] += $venta['monto'];
        }

        // Ordenar gráfica por hora
        ksort($grafica);

        return response()->json([
            'total' => number_format($totalGeneral, 2),
            'numventas' => $numVentas,
            'ventas' => array_values($ventasAgrupadas),
            'grafica' => array_values($grafica),
            'resumen' => [
                'transferencia' => number_format($totalPorTipo[1] ?? 0, 2),
                'debito' => number_format($totalPorTipo[2] ?? 0, 2),
                'efectivo' => number_format($totalPorTipo[3] ?? 0, 2),
                'biopago' => number_format($totalPorTipo[5] ?? 0, 2)
            ],
            'fecha' => $fechaventas,
            'estado' => true
        ]);
    }

    /**
     * Función rápida para reportes de ventas - Optimizada para consultas rápidas
     */
    public function getVentasRapido(Request $req)
    {

        \Artisan::call('comovamos:send');


        $fechaventas = $req->fechaventas;
        
        if (!$fechaventas) {
            return response()->json([
                "msj" => "Error: Fecha invalida", 
                "estado" => false
            ]);
        }

        // Obtener usuarios para totalizar
        $id_vendedor = $this->selectUsersTotalizar(false, $fechaventas);
        
        // Consulta optimizada para ventas del día
        $ventas = DB::table('pedidos as p')
            ->join('pago_pedidos as pp', 'p.id', '=', 'pp.id_pedido')
            ->where('p.created_at', 'LIKE', $fechaventas . '%')
            ->where('p.estado', 1)
            //->whereIn('p.id_vendedor', $id_vendedor)
            ->where('pp.monto', '<>', 0)
            ->whereNotIn('pp.tipo', [4, 6]) // Excluir créditos y vueltos
            ->select([
                'p.id as id_pedido',
                'pp.monto',
                'pp.tipo',
                'pp.updated_at',
                DB::raw('DATE_FORMAT(pp.updated_at, "%H:%i") as hora')
            ])
            ->orderBy('pp.updated_at', 'asc')
            ->get();

        // Agrupar ventas por pedido para evitar duplicados
        $ventasAgrupadas = [];
        $totalPorTipo = [1 => 0, 2 => 0, 3 => 0, 5 => 0]; // Transferencia, Débito, Efectivo, Biopago
        $totalGeneral = 0;
        $numVentas = 0;

        foreach ($ventas as $venta) {
            if (!isset($ventasAgrupadas[$venta->id_pedido])) {
                $ventasAgrupadas[$venta->id_pedido] = [
                    'id_pedido' => $venta->id_pedido,
                    'monto' => 0,
                    'hora' => $venta->hora
                ];
                $numVentas++;
            }
            
            $ventasAgrupadas[$venta->id_pedido]['monto'] += $venta->monto;
            $totalPorTipo[$venta->tipo] += $venta->monto;
            $totalGeneral += $venta->monto;
        }

        // Preparar datos para gráfica (agrupar por hora)
        $grafica = [];
        foreach ($ventasAgrupadas as $venta) {
            $hora = $venta['hora'];
            if (!isset($grafica[$hora])) {
                $grafica[$hora] = ['hora' => $hora, 'monto' => 0];
            }
            $grafica[$hora]['monto'] += $venta['monto'];
        }

        // Ordenar gráfica por hora
        ksort($grafica);

        return response()->json([
            'total' => number_format($totalGeneral, 2),
            'numventas' => $numVentas,
            'ventas' => array_values($ventasAgrupadas),
            'grafica' => array_values($grafica),
            'resumen' => [
                'transferencia' => number_format($totalPorTipo[1] ?? 0, 2),
                'debito' => number_format($totalPorTipo[2] ?? 0, 2),
                'efectivo' => number_format($totalPorTipo[3] ?? 0, 2),
                'biopago' => number_format($totalPorTipo[5] ?? 0, 2)
            ],
            'fecha' => $fechaventas,
            'estado' => true
        ]);
    }
    public function getPedidos(Request $req)
    {
        $fact = [];
        $prod = [];
        $vendedor = $req->vendedor ?? [];
        $tipobusquedapedido = $req->tipobusquedapedido;
        $busquedaPedido = $req->busquedaPedido;
        $fecha1pedido = $req->fecha1pedido ?: "0000-00-00";
        $fecha2pedido = $req->fecha2pedido ?: "9999-12-31";
        $filterMetodoPagoToggle = $req->filterMetodoPagoToggle;
        $tipoestadopedido = $req->tipoestadopedido;
        $orderbycolumpedidos = $req->orderbycolumpedidos;
        $orderbyorderpedidos = $req->orderbyorderpedidos;

        // Determinar el límite basado en las fechas
        $limit = ($fecha1pedido == "0000-00-00" || $fecha2pedido == "9999-12-31") ? 25 : 1000;

        if ($tipobusquedapedido == "prod") {
            // Optimización para búsqueda de productos - Sin cache
            $prod = inventario::with([
                "proveedor:id,descripcion",
                "categoria:id,descripcion",
                "marca:id,descripcion",
                "deposito:id,descripcion",
            ])
            ->where(function ($q) use ($busquedaPedido) {
                $q->where("descripcion", "LIKE", "%$busquedaPedido%")
                  ->orWhere("codigo_proveedor", "LIKE", "%$busquedaPedido%")
                  ->orWhere("codigo_barras", "LIKE", "%$busquedaPedido%");
            })
            ->whereIn("id", function ($q) use ($vendedor, $fecha1pedido, $fecha2pedido, $tipoestadopedido) {
                $q->select("id_producto")
                  ->from("items_pedidos")
                  ->whereIn("id_pedido", function ($q) use ($vendedor, $fecha1pedido, $fecha2pedido, $tipoestadopedido) {
                      $q->select("id")
                        ->from("pedidos")
                        ->whereBetween("fecha_factura", ["$fecha1pedido 00:00:00", "$fecha2pedido 23:59:59"])
                        ->when($tipoestadopedido != "todos", function($q) use ($tipoestadopedido) {
                            $q->where("estado", $tipoestadopedido);
                        })
                        ->when(count($vendedor), function($q) use ($vendedor) {
                            $q->whereIn("id_vendedor", $vendedor);
                        });
                  });
            })
            ->selectRaw("
                *,
                (SELECT COALESCE(SUM(cantidad), 0) 
                 FROM items_pedidos 
                 WHERE id_producto = inventarios.id 
                 AND created_at BETWEEN ? AND ?) as cantidadtotal,
                (SELECT COALESCE(SUM(cantidad), 0) * inventarios.precio 
                 FROM items_pedidos 
                 WHERE id_producto = inventarios.id 
                 AND created_at BETWEEN ? AND ?) as totalventa
            ", [$fecha1pedido . " 00:00:00", $fecha2pedido . " 23:59:59", $fecha1pedido . " 00:00:00", $fecha2pedido . " 23:59:59"])
            ->orderBy("cantidadtotal", "desc")
            ->get()
            ->map(function ($q) use ($fecha1pedido, $fecha2pedido, $vendedor) {
                $q->items = items_pedidos::with(['pedido.vendedor:id,nombre'])
                    ->whereBetween("created_at", ["$fecha1pedido 00:00:00", "$fecha2pedido 23:59:59"])
                    ->where("id_producto", $q->id)
                    ->when(count($vendedor), function($q) use ($vendedor) {
                        $q->whereIn("id_pedido", function($q) use ($vendedor) {
                            $q->select("id")
                              ->from("pedidos")
                              ->whereIn("id_vendedor", $vendedor);
                        });
                    })
                    ->get();
                return $q;
            });
        } else if ($tipobusquedapedido == "fact" || $tipobusquedapedido == "cliente") {
            // Optimización para búsqueda de facturas y clientes - Sin cache
            $fact = pedidos::with([
                "pagos:id,id_pedido,tipo,monto,monto_original,moneda,referencia",
                "items:id,id_pedido,monto,descuento",
                "vendedor:id,nombre",
                "cliente:id,nombre,identificacion"
            ])
            ->whereBetween("fecha_factura", ["$fecha1pedido 00:00:00", "$fecha2pedido 23:59:59"])
            ->when($tipoestadopedido != "todos", function($q) use ($tipoestadopedido) {
                $q->where("estado", $tipoestadopedido);
            })
            ->when(count($vendedor), function($q) use ($vendedor) {
                $q->whereIn("id_vendedor", $vendedor);
            })
            ->when($tipobusquedapedido == "fact" && $busquedaPedido, function($q) use ($busquedaPedido) {
                $q->where("id", "LIKE", "$busquedaPedido%");
            })
            ->when($tipobusquedapedido == "cliente", function($q) use ($busquedaPedido) {
                $q->whereIn("id_cliente", function ($q) use ($busquedaPedido) {
                    $q->select("id")
                      ->from("clientes")
                      ->where(function($q) use ($busquedaPedido) {
                          $q->where("nombre", "LIKE", "%$busquedaPedido%")
                            ->orWhere("identificacion", "LIKE", "%$busquedaPedido%");
                      });
                });
            })
            ->when($filterMetodoPagoToggle != "todos", function($q) use ($filterMetodoPagoToggle) {
                $q->whereIn("id", function ($q) use ($filterMetodoPagoToggle) {
                    $q->select('id_pedido')
                      ->from("pago_pedidos")
                      ->where("tipo", $filterMetodoPagoToggle)
                      ->where("monto", "<>", 0);
                });
            })
            ->selectRaw("
                *,
                (SELECT ROUND(COALESCE(SUM(monto-(monto*(descuento/100))), 0), 2) 
                 FROM items_pedidos 
                 WHERE id_pedido = pedidos.id) as totales
            ")
            ->orderBy($orderbycolumpedidos, $orderbyorderpedidos)
            ->limit($limit)
            ->get();

            // Agrega monto en bs para cada pago de cada pedido
            $bs_rate = $this->get_moneda()['bs'] ?? 1;
            foreach ($fact as $pedido) {
                if (isset($pedido->pagos)) {
                    foreach ($pedido->pagos as $pago) {
                        $pago->bs = number_format($pago->monto * $bs_rate, 2, ".", ",");
                    }
                }
            }

            $totaltotal = $fact->sum("totales");
        }

        return [
            "fact" => $fact,
            "prod" => $prod,
            "subtotal" => toLetras(number_format($subtotal ?? 0, 2, ".", ",")),
            "desctotal" => $desctotal ?? 0,
            "totaltotal" => toLetras(number_format($totaltotal ?? 0, 2, ".", ",")),
            "itemstotal" => $itemstotal ?? 0,
            "totalventas" => $totalventas ?? 0,
        ];
    }
    public function getPedidosUser(Request $req)
    {
        $vendedor = $req->vendedor;
        return pedidos::where("estado", 0)->where("id_vendedor", $vendedor)->orderBy("id", "desc")->limit(1)->get(["id", "estado"]);
    }
    public function checkItemsPedidoCondicionGarantiaCero($id_pedido)
    {
        $itemsPedido = items_pedidos::where('id_pedido', $id_pedido)->get();
        foreach ($itemsPedido as $item) {
            if ($item->condicion != 0) {
                return false;
            }
        }
        return true;
    }
    public function pedidoAuth($id, $tipo = "pedido")
    {
        $today = $this->today();
        if ($id === null) {
            $fecha_creada = $tipo;
            $estado = true;
        } else {

            $pedido = $tipo == "pedido" ? pedidos::select(["id","estado", "created_at","export"])->find($id) : pedidos::select(["id","estado", "created_at","export"])->find(items_pedidos::find($id)->id_pedido);
            if ($pedido) {
                $fecha_creada = date("Y-m-d", strtotime($pedido->created_at));
                //$checkifcredito = pago_pedidos::where("id_pedido",$pedido->id)->where("tipo",4)->first();
                $estado = $pedido->estado;
                if ($estado==2 || $pedido->export==1) {
                    return false;
                }
                
            } else {
                return false;
            }
        }
        //si el pedido no es de hoy, no se puede hacer nada
        if ($fecha_creada != $today) {
            return false;
        }
        //Si no se ha pagado
        //si la fecha de entrada no existe en los cierres
        //si la fecha del ultimo cierre es igual la fecha de entrada


        if (!Cache::has('cierreCount')) {
            $cierreCount = cierres::where("fecha", $fecha_creada)->get()->count();
            Cache::put('cierreCount', $cierreCount, 7200);
        } else {
            $cierreCount = Cache::get('cierreCount');
        }



        if ((!$estado and $today === $fecha_creada) || !$cierreCount) {
            return true;
        } else {
            return false;
        }
    }
    public function checkPedidoPago($id, $tipo = "pedido")
    {
        $pedidomodify = $tipo == "pedido" ? pedidos::find($id) : pedidos::find(items_pedidos::find($id)->id_pedido);
        if ($pedidomodify->estado==1) {
            return Response::json(["id_tarea"=>null,"msj"=>"No puede modificar un pedido procesado","estado"=>false]);

            /* $checkifcredito = pago_pedidos::where("id_pedido",$pedidomodify->id)->where("tipo",4)->first();

            if ($checkifcredito) {
                return Response::json(["id_tarea"=>null,"msj"=>"No puede modificar un crédito","estado"=>false]);
            }

            $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                "id_pedido" => $pedidomodify->id,
                "tipo" => "modped",
            ]);

            if ((new UsuariosController)->isAdmin()) {
            } elseif ($isPermiso["permiso"]) {
            } else {
                $nuevatarea = (new TareaslocalController)->createTareaLocal([
                    "id_pedido" => $pedidomodify->id,
                    "tipo" => "modped",
                    "valoraprobado" => 0,
                    "descripcion" => "Modificar pedido",
                ]);
                if ($nuevatarea) {
                    return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador","estado"=>false]);
                }
            }
            $pedidomodify->estado = 0;
            if ($pedidomodify->save()) {
                pago_pedidos::where("id_pedido", $pedidomodify->id)->delete();
            } */
        }
        return true;
    }
    public function checkPedidoAuth($id, $tipo = "pedido")
    {
        if (!$this->pedidoAuth($id, $tipo)) {
            throw new \Exception("No se puede hacer movimientos en esta fecha. ¡Cierre Procesado!", 1);
        }


    }
    public function checksipedidoprocesado($id, $tipo = "pedido")
    {
        $pedidomodify = $tipo == "pedido" ? pedidos::find($id) : pedidos::find(items_pedidos::find($id)->id_pedido);
        return $pedidomodify->estado;
    }
    function delpedidoForce(Request $req)
    {
        //$this->checkPedidoAuth($req->id);
        $this->delPedidoFun($req->id, $req->motivo);
    }
    public function delpedido(Request $req)
    {
        try {

            $id = $req->id;
            $motivo = $req->motivo;

            $checkItemsPedidoCondicionGarantiaCero = (new PedidosController)->checkItemsPedidoCondicionGarantiaCero($id);
            if ($checkItemsPedidoCondicionGarantiaCero!==true) {
                throw new \Exception("Error: El pedido tiene garantias, no puede eliminar el item", 1);
            }
            $pedidoConItems = pedidos::with("items")->find($id);
            if ($pedidoConItems && $pedidoConItems->items && $pedidoConItems->items->count() > 0) {

                $isPermiso = (new TareaslocalController)->checkIsResolveTarea([
                    "id_pedido" => $id,
                    "tipo" => "eliminarPedido",
                ]);
                if ($isPermiso["permiso"]) {
                } else {

                    $nuevatarea = (new TareaslocalController)->createTareaLocal([
                        "id_pedido" => $id,
                        "valoraprobado" => 0,
                        "tipo" => "eliminarPedido",
                        "descripcion" => "Solicitud de eliminacion de pedido: #" . $id,
                    ]);
                    if ($nuevatarea) {
                        return Response::json(["id_tarea"=>$nuevatarea->id,"msj"=>"Debe esperar aprobacion del Administrador", "estado" => false]);
                    }

                }
            }



            $pedido = pedidos::find($id);
            if ($pedido) {
                if ($pedido->estado!=0) {
                    $this->checkPedidoAuth($id);
                }
            }
            if ($id) {
                return $this->delPedidoFun($id, $motivo);
            }

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage()." LINEA ".$e->getLine()." FILE ".$e->getFile(), "estado" => false]);

        }
    }
    public function delPedidoFun($id, $motivo)
    {
        $items = items_pedidos::with("producto")->where("id_pedido", $id)->get();
        $monto_pedido = pago_pedidos::where("id_pedido", $id)->where("monto", "<>", 0)->get();
        $pedido = pedidos::with("cliente")->find($id);

        $monto = 0;
        $pagos = "";
        foreach ($monto_pedido as $k => $v) {
            $monto += $v->monto;
            if ($v->tipo == 1) {
                $pagos .= "Transferencia ";
            }
            if ($v->tipo == 2) {
                $pagos .= "Debito ";
            }
            if ($v->tipo == 3) {
                $pagos .= "Efectivo ";
            }
            if ($v->tipo == 4) {
                $pagos .= "Credito ";
            }
            if ($v->tipo == 5) {
                $pagos .= "Biopago ";
            }
            if ($v->tipo == 6) {
                $pagos .= "vuelto ";
            }
        }

        $mov = new movimientos;
        $mov->id_usuario = session("id_usuario");
        $mov->motivo = $motivo;
        $mov->tipo_pago = $pagos;
        $mov->monto = $monto;
        $mov->tipo = ($pedido->estado==0?"Eliminacion de Pedido #":"Anulacion de Factura #") . $id;
        $mov->save();

        $cliente = $pedido->cliente;
        $ifaprobadoanulacionpedido = false;


        /////////////////////////
        if ($pedido->estado==1) {
            
            $items_json = [];
            $monto_pedido_json = [];

            foreach ($items as $i => $item) {
                array_push($items_json,[
                    "id" => $item->id,
                    "ct" => $item->cantidad,
                    "m" => $item->monto,
                    "desc" => $item->producto?$item->producto->descripcion:"PAGO", 
                    "barras" => $item->producto?$item->producto->codigo_barras:"PAGO", 
                    "alterno" => $item->producto?$item->producto->codigo_proveedor:"PAGO", 
                ]);
            }

            foreach ($monto_pedido as $i => $pago) {
                array_push($monto_pedido_json,[
                    "id" => $pago->id,
                    "tipo" => $pago->tipo,
                    "m" => $pago->monto,
                ]);
            }
            
            $result = (new sendCentral)->createAnulacionPedidoAprobacion([
                "id" => $pedido->id,
                "monto" => $items->sum("monto"),
                "items" => json_encode($items_json),
                "pagos" => json_encode($monto_pedido_json),
                "cliente" => json_encode($cliente),
                "motivo" => $motivo,
            ]);
    
            if($result["estado"]==true && $result["msj"]=="APROBADO"){
                $ifaprobadoanulacionpedido = true;
            }else{
                $ifaprobadoanulacionpedido = false;
                return $result;
            }
        }
        /////////////////////////


        if ($pedido->estado==0 || $pedido->estado==1) {
            foreach ($items as $key => $value) {
                $id = $value->id;
                
                $item = items_pedidos::find($id);
                $old_ct = $item->cantidad;
                $id_producto = $item->id_producto;
                $pedido_id = $item->id_pedido;
                $condicion = $item->condicion;

                $items_mov = new items_movimiento;
                $items_mov->id_producto = $item->id_producto;
                $items_mov->cantidad = $item->cantidad;
                $items_mov->tipo = 2;

                $producto = inventario::select(["cantidad"])->find($id_producto);
                
                if ($pedido->estado==0) {
                
                    if($item->delete()){
                        
                        $ctSeter = $producto->cantidad + $old_ct;
        
                        if ($condicion!=1) {
                            //Si no es garantia
                            (new InventarioController)->descontarInventario($id_producto,$ctSeter, $producto->cantidad, $pedido_id, "ELI.VENTA");
                            (new InventarioController)->checkFalla($id_producto,$ctSeter);
                        }else{
                            //Si es garantia - NUEVO FLUJO con reversión en central
                            $garantiaController = new \App\Http\Controllers\GarantiaController();
                            $reverseResult = $garantiaController->reversarGarantia($pedido_id);
                            
                            if (!$reverseResult['success']) {
                                \Log::warning("Error al reversar garantía en central para pedido {$pedido_id}: " . $reverseResult['message']);
                                // Continuar con la eliminación local aunque falle la reversión
                            }
                            
                            garantia::where("id_pedido", $pedido_id)->where("id_producto", $id_producto)->delete();
                        }
                    }

                    $items_mov->id_movimiento = $mov->id;
                    $items_mov->categoria = "Eliminacion de pedido - Item";
                    $items_mov->save();

                }else if($pedido->estado==1){

                    if ($ifaprobadoanulacionpedido) {
                    
                        (new PedidosController)->checkPedidoAuth($id,"item");
                        /* $checkPedidoPago = (new PedidosController)->checkPedidoPago($id,"item");
                        if ($checkPedidoPago!==true) {
                            return $checkPedidoPago;
                        } */
            
                        //if($item->delete()){
                            
                            $ctSeter = ($producto? $producto->cantidad:0) + ($old_ct);
            
                            if ($condicion!=1) {
                                //Si no es garantia
                                (new InventarioController)->descontarInventario($id_producto,$ctSeter, ($producto? $producto->cantidad:0), $pedido_id, "ELI.VENTA");
                                (new InventarioController)->checkFalla($id_producto,$ctSeter);
                            }else{
                                //Si es garantia - NUEVO FLUJO con reversión en central
                                $garantiaController = new \App\Http\Controllers\GarantiaController();
                                $reverseResult = $garantiaController->reversarGarantia($pedido_id);
                                
                                if (!$reverseResult['success']) {
                                    \Log::warning("Error al reversar garantía en central para pedido {$pedido_id}: " . $reverseResult['message']);
                                    // Continuar con la eliminación local aunque falle la reversión
                                }
                                
                                garantia::where("id_pedido", $pedido_id)->where("id_producto", $id_producto)->delete();
                            }
                            
                        //}
                        $items_mov->id_movimiento = $mov->id;
                        $items_mov->categoria = "Anulacion de Factura - Item";
                        $items_mov->save();
                    }
                }
            }
        }
        
        
        if ($pedido->estado==0) {
            $pedido->delete();
            pagos_referencias::where("id_pedido", $pedido->id)->delete();
            return Response::json(["msj" => "Pedido eliminado correctamente", "estado" => true]);
        }else if($pedido->estado==1){
            if ($ifaprobadoanulacionpedido) {
                $pedido->estado = 2;
                $pedido->save();
                pago_pedidos::where("id_pedido", $pedido->id)->delete();
                pagos_referencias::where("id_pedido", $pedido->id)->delete();
                return Response::json(["msj" => "Pedido anulado correctamente", "estado" => true]);
            }
        }   

    }
    public function getPedidoFun($id_pedido, $filterMetodoPagoToggle = "todos", $cop = 1, $bs = 1, $factor = 1, $clean = false)
    {
        // Optimize eager loading with specific columns
        $pedido = pedidos::with([
            'retenciones',
            'referencias:id,tipo,descripcion,monto,id_pedido,banco,cedula,telefono,estatus,banco_origen,fecha_pago,categoria,response',
            'vendedor:id,usuario,tipo_usuario,nombre',
            'cliente:id,identificacion,nombre,direccion,telefono',
            'pagos' => function ($q) {
                $q->select('id', 'tipo', 'monto', 'monto_original', 'moneda', 'referencia', 'cuenta', 'id_pedido')
                  ->orderBy('tipo', 'desc');
            },
            'items' => function ($q) {
                $q->select([
                    'id', 'lote', 'id_producto', 'id_pedido', 'abono',
                    'cantidad', 'descuento', 'monto', 'entregado', 'condicion',
                    'tasa', 'precio_unitario'
                ])
                ->with([
                    'producto' => function ($q) {
                        $q->select([
                            'id', 'codigo_barras', 'codigo_proveedor', 'descripcion',
                            'precio', 'precio_base', 'iva', 'precio1', 'bulto'
                        ]);
                    }
                ])
                ->orderBy('id', 'asc');
            }
        ])
        ->where('id', $id_pedido)
        ->first();

        if (!$pedido) {
            return null;
        }

        // Initialize totals
        $totals = [
            'base' => 0,
            'des_ped' => 0,
            'subtotal_ped' => 0,
            'total_ped' => 0,
            'exento' => 0,
            'gravable' => 0,
            'monto_iva' => 0,
            
        ];
        
        $ivas = [];
        $retencion = $pedido->retencion;

        // REGLA: Redondear cada línea a 2 decimales PRIMERO, luego sumar
        // Así el total siempre coincide con la suma de las líneas mostradas al cliente
        // NOTA: Precios pueden tener hasta 3 decimales, descuentos pueden tener decimales

        // Process items and calculate totals
        $pedido->items->transform(function ($item) use (&$totals, $factor, $retencion, &$ivas, $bs) {
            if (!$item->producto) {
                // Handle non-product items (like payments)
                $item->monto = round($item->monto * $factor, 4);
                $subtotal = round($item->monto * $item->cantidad, 2);
                $iva_val = "0";
                $iva_m = 0;
            } else {
                // Usar precio_unitario del item si existe, sino usar precio actual del producto
                // Mantener precisión original del precio (hasta 4 decimales)
                $precioUnitario = round($item->precio_unitario ?? $item->producto->precio, 4);
                $precioBase = round($item->producto->precio_base ?? 0, 4);
                
                // Handle product items - descuento unitario (mantener precisión para mostrar)
                $des_unitario = $item->descuento < 0 ? 
                    round(($item->descuento / 100) * $precioUnitario, 4) : 0;
                
                $item->des_unitario = $des_unitario;
                
                // Precio para mostrar al cliente (4 decimales para precios de 3 decimales)
                $item->producto->precio = round($precioUnitario * $factor, 4);
                $item->producto->precio_base = round($precioBase * $factor, 4);
                
                // Guardar la tasa del momento de la venta en el item
                $item->tasa_venta = $item->tasa ?? $bs;
                
                // Calcular subtotal de línea: precio exacto * cantidad, resultado a 2 decimales
                // Esto es el monto que el cliente paga por esta línea
                $subtotal = round($precioUnitario * $factor * $item->cantidad, 2);
                $totals['base'] += round($item->producto->precio_base * $item->cantidad, 2);
                
                $iva_val = $item->producto->iva;
                $iva_m = $iva_val / 100;
            }

            // Calcular descuento: porcentaje exacto (puede tener decimales) sobre subtotal
            // El resultado en dinero se redondea a 2 decimales
            $total_des = round(($item->descuento / 100) * $subtotal, 2);
            $subtotal_c_desc = round($subtotal - $total_des, 2);

            // Sumar a totales (ya están redondeados a 2 decimales)
            $totals['des_ped'] += $total_des;
            $totals['subtotal_ped'] += $subtotal;

            if (!$iva_m) {
                $totals['exento'] += $subtotal_c_desc;
            } else {
                $totals['gravable'] += $subtotal_c_desc;
            }

            if (!in_array($iva_val, $ivas)) {
                $ivas[] = $iva_val;
            }

            // Total de línea con IVA (si aplica retención)
            $totalLineaConIva = round($subtotal_c_desc * ($retencion ? 1.16 : 1), 2);
            $totals['total_ped'] += $totalLineaConIva;
            
            // Calcular total en BS con tasa histórica del item (2 decimales)
            $tasaItem = $item->tasa ?? $bs;
            $totalBsItem = round($totalLineaConIva * $tasaItem, 2);
           
            $item->total_des = $total_des;
            $item->subtotal = $subtotal;
            $item->total = $subtotal_c_desc;

            return $item;
        });

        // Los totales ya son la suma exacta de líneas redondeadas a 2 decimales
        // Solo redondear a 2 decimales por seguridad (evitar 0.0000001 por float)
        $pedido->tot_items = $pedido->items->count();
        $pedido->total_des = round($totals['des_ped'], 2);
        $pedido->subtotal = round($totals['subtotal_ped'], 2);
        $pedido->total = round($totals['total_ped'], 2);

        // Set tax information
        $pedido->exento = round($totals['exento'], 2);
        $pedido->gravable = round($totals['gravable'], 2);
        $pedido->ivas = implode(',', $ivas);
        $pedido->monto_iva = round($totals['monto_iva'], 2);

        // Clean totals = mismo valor (ya está en 2 decimales exactos)
        $pedido->clean_total_des = round($totals['des_ped'], 2);
        $pedido->clean_subtotal = round($totals['subtotal_ped'], 2);
        $pedido->clean_total = round($totals['total_ped'], 2);
        $pedido->total_base = round($totals['base'], 2);

        // Set order status and metadata
        $pedido->editable = $this->pedidoAuth($id_pedido);
        $pedido->vuelto_entregado = [];

        // Calculate discount percentage (hasta 4 decimales para porcentajes precisos)
        $pedido->total_porciento = $totals['subtotal_ped'] == 0 ? 0 : 
            round(($totals['des_ped'] * 100) / $totals['subtotal_ped'], 4);
        $pedido->clean_total_porciento = $totals['subtotal_ped'] == 0 ? 0 : 
            round(($totals['des_ped'] * 100) / $totals['subtotal_ped'], 4);

        // Obtener tasas de los items (tasa histórica del momento de la venta)
        $primerItem = $pedido->items->first();
        $tasaBsItems = $primerItem && $primerItem->tasa ? floatval($primerItem->tasa) : $bs;
        $tasaCopItems = $primerItem && $primerItem->tasa_cop ? floatval($primerItem->tasa_cop) : $cop;

        // Conversión a otras monedas usando tasas de los items
        $pedido->cop = round($totals['total_ped'] * $tasaCopItems, 2);
        $pedido->bs = round($totals['total_ped'] * $tasaBsItems, 2);
        $pedido->cop_clean = round($totals['total_ped'] * $tasaCopItems, 2);
        $pedido->bs_clean = round($totals['total_ped'] * $tasaBsItems, 2);
        
       

        return $clean ? $pedido->makeHidden('items') : $pedido;
    }
    public function getPedido(Request $req, $factor = 1)
    {
        $cop = $this->get_moneda()["cop"];
        $bs = $this->get_moneda()["bs"];


        if ($req->id == "ultimo") {
            $vendedor = session("id_usuario");

            $check = pedidos::where("estado", 0)->where("id_vendedor", $vendedor)->orderBy("id", "desc")->first();
            

            if (!$check) {
                return [];
            } else {
                $id = $check->id;
            }
        } else {
            $id = $req->id;
        }
        return $this->getPedidoFun($id, "todos", $cop, $bs, $factor);
    }

    public function notaentregapedido(Request $req)
    {
        $sucursal = sucursal::all()->first();
        $cop = $this->get_moneda()["cop"];
        $bs = $this->get_moneda()["bs"];
        $factor = 1;

        $ids = explode("-",$req->id);

        $pedidos = [];
        if (count($ids) == 1) {
            array_push(
                $pedidos,
                $this->getPedidoFun($ids[0], "todos", $cop, $bs, $factor)
            ); 
        }

        if (count($ids) == 2) {
            $pedidosGet = pedidos::whereBetween("id", [$ids[0],$ids[1]])->get();
            foreach ($pedidosGet as $i => $pedido) {
    
                array_push(
                    $pedidos,
                    $this->getPedidoFun($pedido->id, "todos", $cop, $bs, $factor)
                );
            }
        }

        if (sucursal::first()->codigo == "galponvalencia1") {
            return view("reportes.notaentrega_enblanco", [
                "sucursal" => $sucursal,
                "pedidos" => $pedidos,
                "bs" => $bs,
                "ids" => $ids,
            ]);
        }
        return view("reportes.notaentrega", [
            "sucursal" => $sucursal,
            "pedidos" => $pedidos,
            "bs" => $bs,
            "ids"=>$ids,
        ]);
       



        

    }

    public function setpersonacarrito(Request $req)
    {
        try {
            $this->checkPedidoAuth($req->numero_factura);

            $pedido_select = pedidos::find($req->numero_factura);
            $pedido_select->id_cliente = $req->id_cliente;
            $pedido_select->save();
            return Response::json(["msj" => "¡Éxito al agregar cliente!", "estado" => true]);


        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);

        }
    }

    
    public function ultimoCierre($fecha, $id_vendedor)
    {
        $fecha_pasadaquery = cierres::where("fecha", "<", $fecha)->whereIn("id_usuario", $id_vendedor)->orderBy("fecha", "desc")->first();
        if ($fecha_pasadaquery) {
            $fecha_pasada = $fecha_pasadaquery->fecha;
        } else {
            $fecha_pasada = "2023-01-01";
            $id_vendedor = [1];
        }
        //toma a todos los cierres tipo cajero

        return cierres::where("fecha", $fecha_pasada)->whereIn("id_usuario", $id_vendedor)->where("tipo_cierre", 0)->orderBy("fecha", "desc");
    }

    public function selectUsersTotalizar($totalizarcierre,$fecha="")
    {
        if ($totalizarcierre) {
            return pedidos::select('id_vendedor')->distinct()->get()->map(function ($e) {
                return $e->id_vendedor;
            });

        } else {
            return [session("id_usuario")];
        }
    }

    public function cerrarFun($fecha, $total_caja_neto, $total_punto, $total_biopago, $dejar = [], $grafica = false, $totalizarcierre = false, $check_pendiente = true, $usuario = null)
    {
        if (!$fecha) {
            return Response::json(["msj" => "Error: Fecha invalida", "estado" => false]);
        }

        if ($check_pendiente) {
            $pedido_pendientes_check = pedidos::where("estado", 0)->get();
            if (count($pedido_pendientes_check)) {
                return Response::json([
                    "msj" => "Error: Hay pedidos pendientes " . $pedido_pendientes_check->map(function ($q) {
                        return $q->id;
                    }),
                    "estado" => false
                ]);
            }
        }


        $id_vendedor = $usuario ? [$usuario] : $this->selectUsersTotalizar($totalizarcierre,$fecha);

        $usuariosget = usuarios::whereIn("id", $id_vendedor)->get(["id", "usuario", "tipo_usuario", "nombre"]);
        
        $ultimo_cierre = $this->ultimoCierre($fecha, $id_vendedor);

        $cop = $this->get_moneda()["cop"];
        $bs = $this->get_moneda()["bs"];

        $caja_inicial = 0;
        $caja_inicialpeso = 0;
        $caja_inicialbs = 0;
        if ($ultimo_cierre) {
            $caja_inicial = round($ultimo_cierre->sum("dejar_dolar") + ($ultimo_cierre->sum("dejar_peso") / $cop) + ($ultimo_cierre->sum("dejar_bss") / $bs), 3);
            $caja_inicialpeso = $ultimo_cierre->sum("dejar_peso");
            $caja_inicialbs = $ultimo_cierre->sum("dejar_bss");
        }
        $pedido = pedidos::where("fecha_factura", "LIKE", $fecha . "%")->where("estado",1)->whereIn("id_vendedor", $id_vendedor);
        

        /////Montos de ganancias
        //Var vueltos_des
        //Var precio
        //Var precio_base
        //Var desc_total
        //Var ganancia
        //Var porcentaje
        $vueltos_des = pago_pedidos::where("tipo", 6)->where("monto", "<>", 0)
            ->whereIn("id_pedido", pedidos::where("fecha_factura", "LIKE", $fecha . "%")->whereIn("id_vendedor", $id_vendedor)->select("id"))
            ->get()
            ->map(function ($q) {
                $q->cliente = pedidos::with("cliente")->find($q->id_pedido);
                return $q;
            });

        $puntosAdicional = cierres_puntos::whereIn("id_usuario", $id_vendedor)->where("fecha",$fecha)->get();


        $inv = items_pedidos::with([
            "producto",
            "pedido" => function ($q) {
                $q->with("cliente");
            }
        ])
            ->whereIn(
                "id_pedido",
                pedidos::where("fecha_factura", "LIKE", $fecha . "%")
                    ->whereIn("id_vendedor", $id_vendedor)
                    ->where("estado", 1)
                    ->select("id")
            )
            ->get()
            ->map(function ($q) {
                $q->monto_abono = 0;
                if (isset($q->producto)) {

                    $base_total = $q->producto->precio_base * $q->cantidad;
                    $venta_total = $q->producto->precio * $q->cantidad;

                    $descuentopromedio = ($q->descuento / 100);

                    $q->base_total = $base_total - ($base_total * $descuentopromedio);
                    $q->venta_total = $venta_total - ($venta_total * $descuentopromedio);

                    $q->sin_base_total = $base_total;
                    $q->sin_venta_total = $venta_total;

                } else {
                    $q->monto_abono = $q->monto;
                }
                return $q;
            });
            
        $total_export = items_pedidos::whereIn("id_pedido",pedidos::where("fecha_factura", "LIKE", $fecha . "%")->where("export",1)->select("id") )->sum("monto");
        $total_credito = pago_pedidos::whereIn("id_pedido", pedidos::where("fecha_factura", "LIKE", $fecha . "%")->whereIn("id_vendedor", $id_vendedor)->where("tipo", 4)->select("id"))
            ->get()
            ->sum("monto")+$total_export;

        

        $base_total = $inv->sum("base_total");
        //print_r($base_total);
        $venta_total = $inv->sum("venta_total");

        $sin_base_total = $inv->sum("sin_base_total");
        $sin_venta_total = $inv->sum("sin_venta_total");

        $monto_abono = $inv->sum("monto_abono");

        $ganancia_bruta = ($venta_total - $base_total);
        $porcentaje = $base_total == 0 ? 100 : round((($ganancia_bruta * 100) / $base_total), 2);

        $divisor = ($porcentaje / 100) + 1;

        if ($divisor == 0 || $total_credito == 0) {
            $base_credito = 0;
        } else {
            $base_credito = round($total_credito / $divisor, 2);
        }
        $venta_credito = $total_credito;

        $base_abono = $divisor == 0 ? 0 : round($monto_abono / $divisor, 2);
        $venta_abono = $monto_abono;


        $sin_precio_base = ($sin_base_total + $base_abono) - $base_credito;
        $sin_precio = ($sin_venta_total + $venta_abono) - $venta_credito;


        $precio_base = ($base_total + $base_abono) - $base_credito;
        $precio = $sin_precio;



        $desc_total = ($venta_total + $venta_abono) - $venta_credito;

        $ganancia = $desc_total - $precio_base;





        /////End Montos de ganancias
        $tipo_accion = cierres::where("fecha", $fecha)->where("id_usuario", session("id_usuario"))->first();
        if ($tipo_accion) {
            $tipo_accion = "editar";
        } else {
            $tipo_accion = "guardar";
        }

        ////Monto Inventario
        $total_inventario = DB::table("inventarios")
            ->select(DB::raw("sum(precio*cantidad) as suma"))->first()->suma;
        $total_inventario_base = DB::table("inventarios")
            ->select(DB::raw("sum(precio_base*cantidad) as suma"))->first()->suma;

        ////Creditos totales
        $cred_total = clientes::selectRaw("*,@credito := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND tipo=4) as credito,
        @abono := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND cuenta=0) as abono,
        (@credito-@abono) as saldo")
            ->get(["saldo"])
            ->sum("saldo");


        ////Vueltos totales
        $vuelto_entregado = 0;

        $vueltos_pendiente = pago_pedidos::where("tipo", 6)
            ->where("monto", "<>", 0)
            ->whereIn('id_pedido', function ($q) use ($id_vendedor) {
                $q->from('pedidos')->whereIn('id_vendedor', $id_vendedor)->select('id');
            })
            ->sum("monto");

        $vueltos_totales = $vueltos_pendiente - $vuelto_entregado;


        ///Abonos del Dia
        $abonosdeldia = 0;
        $pedidos_abonos = pedidos::with(["pagos", "cliente"])
            ->where(function ($q) {
                $q
                    ->whereIn("id", pago_pedidos::orWhere(function ($q) {
                        $q->orWhere("cuenta", 0); //Abono
                    })
                        ->where("monto", "<>", 0)
                        ->select("id_pedido"));
            })
            ->where("created_at", "LIKE", $fecha . "%")
            ->get()
            ->map(function ($q) use (&$abonosdeldia) {
                $saldoDebe = $q->pagos->where("tipo", 4)->sum("monto");
                $saldoAbono = $q->pagos->where("cuenta", 0)->sum("monto");

                $q->saldoDebe = $saldoDebe;
                $q->saldoAbono = $saldoAbono;

                $abonosdeldia += $q->pagos->sum("monto");
                return $q;
            });

        $arr_pagos = [
            "puntosAdicional" => $puntosAdicional,
            "total_inventario" => $total_inventario,
            "total_inventario_base" => $total_inventario_base,

            "cred_total" => $cred_total,
            "vueltos_totales" => $vueltos_totales,
            "pedidos_abonos" => $pedidos_abonos,
            "abonosdeldia" => $abonosdeldia,

            "total" => 0,
            "fecha" => $fecha,
            "caja_inicial" => $caja_inicial,
            "caja_inicialpeso" => $caja_inicialpeso,
            "caja_inicialbs" => $caja_inicialbs,

            "numventas" => 0,
            "grafica" => [],
            "ventas" => [],

            "entregadomenospend" => 0,
            "entregado" => 0,
            "pendiente" => 0,
            "entre_pend_get" => 0,

            "total_caja" => 0,
            "total_punto" => 0,
            "total_biopago" => 0,


            "estado_efec" => 0,
            "msj_efec" => "",

            "estado_punto" => 0,
            "msj_punto" => "",
            //Montos de ganancias
            "vueltos_des" => $vueltos_des,
            "precio" => $precio,
            "precio_base" => $precio_base,
            "desc_total" => $desc_total,
            "ganancia" => $ganancia,
            "porcentaje" => $porcentaje,
            //
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,

            "usuariosget" => $usuariosget,
            "tipo_accion" => $tipo_accion,

        ];
        $numventas_arr = [];
        pago_pedidos::whereIn("id_pedido", $pedido->select("id"))
            ->where("monto", "<>", 0)
            ->orderBy("id", "desc")
            ->get()
            ->map(function ($q) use (&$arr_pagos, &$numventas_arr) {
                if (array_key_exists($q->tipo, $arr_pagos)) {
                    $arr_pagos[$q->tipo] += $q->monto;
                } else {
                    $arr_pagos[$q->tipo] = $q->monto;
                }
                if ($q->tipo != 4 && $q->tipo != 6) {
                    $hora = date("h:i", strtotime($q->updated_at));
                    if (!array_key_exists($q->id_pedido, $numventas_arr)) {
                        $numventas_arr[$q->id_pedido] = ["hora" => $hora, "monto" => $q->monto, "id_pedido" => $q->id_pedido];
                    } else {
                        $numventas_arr[$q->id_pedido]["monto"] = $numventas_arr[$q->id_pedido]["monto"] + $q->monto;
                    }
                    $arr_pagos["total"] += $q->monto;
                }
            });
        $arr_pagos["numventas"] = count($numventas_arr);
        $arr_pagos["ventas"] = array_values($numventas_arr);
        if ($grafica) {
            $arr_pagos["grafica"] = array_values($numventas_arr);
        }
        if (isset($arr_pagos[6])) {
            // Sumar vuelto a pendientes
            $arr_pagos["pendiente"] += $arr_pagos[6];
        }
        $entregadomenospend = $arr_pagos["entregado"] - $arr_pagos["pendiente"];
        $arr_pagos["entregadomenospend"] = $entregadomenospend;

        $diff_debito = 0;
        $diff_biopago = 0;
        $diff_efectivo = 0;
        if (isset($arr_pagos[2])) {
            $this->msj_cuadre($total_punto, $arr_pagos[2], "punto", $arr_pagos);
            $arr_pagos["total_punto"] = round($total_punto, 3);
            $diff_debito = $total_punto - $arr_pagos[2];
        }
        if (isset($arr_pagos[5])) {
            $this->msj_cuadre($total_biopago, $arr_pagos[5], "biopago", $arr_pagos);
            $arr_pagos["total_biopago"] = round($total_biopago, 3);
            $diff_biopago = $total_biopago - $arr_pagos[5];
        }
        if (isset($arr_pagos[3])) {
            $total_caja = ($total_caja_neto - $caja_inicial) + $entregadomenospend;
            $arr_pagos["total_caja"] = round($total_caja, 3);

            $this->msj_cuadre($total_caja, $arr_pagos[3], "efec", $arr_pagos);
            $diff_efectivo = $total_caja - $arr_pagos[3];
        }
        $arr_pagos["descuadre"] = $diff_debito + $diff_biopago + $diff_efectivo; 

        $arr_pagos["transferencia_digital"] = $arr_pagos[1];
        $arr_pagos["debito_digital"] = $arr_pagos[2];
        $arr_pagos["efectivo_digital"] = $arr_pagos[3];
        $arr_pagos["biopago_digital"] = $arr_pagos[5];
        



        $dejar_usd = 0;
        $dejar_cop = 0;
        $dejar_bs = 0;

        if ($dejar) {
            $dejar_usd = floatval($dejar["dejar_usd"]);
            $dejar_cop = floatval($dejar["dejar_cop"]);
            $dejar_bs = floatval($dejar["dejar_bs"]);
        }
        $c = cierres::where("tipo_cierre",0)->where("fecha",$fecha)->get();
        $lotes = [];
        $biopagos = [];
        foreach ($c as $key => $e) {
            if ($e->puntolote1montobs&&$e->puntolote1) {
                array_push($lotes,[
                    "monto" => $e->puntolote1montobs,
                    "lote" => $e->puntolote1,
                    "banco" => $e->puntolote1banco,
                ]);
            }
            if ($e->puntolote2montobs&&$e->puntolote2) {
                array_push($lotes,[
                    "monto" => $e->puntolote2montobs,
                    "lote" => $e->puntolote2,
                    "banco" => $e->puntolote2banco,
                ]);
            }
            if ($e->biopagoserial&&$e->biopagoserialmontobs) {
                array_push($biopagos,[
                    "monto" => $e->biopagoserialmontobs,
                    "serial" => $e->biopagoserial,
                ]);
            }
        }


        $efectivo_guardado = floatval($arr_pagos["total_caja"]) + floatval($caja_inicial) - ($entregadomenospend) - floatval($dejar_usd + ($dejar_cop / $cop) + ($dejar_bs / $bs));

        
        $arr_pagos["efectivo_guardado"] = round($efectivo_guardado, 2);

        $arr_pagos["lotes"] = $lotes;
        $arr_pagos["biopagos"] = $biopagos;

        return $arr_pagos;
    }
    public function getCierres(Request $req)
    {

        $fechaGetCierre = $req->fechaGetCierre;
        $fechaGetCierre2 = $req->fechaGetCierre2;
        $tipoUsuarioCierre = $req->tipoUsuarioCierre;

        if (!$fechaGetCierre && !$fechaGetCierre2) {
            $cierres = cierres::with("usuario")
                ->when($tipoUsuarioCierre != "", function ($q) use ($tipoUsuarioCierre) {
                    $q->where("tipo_cierre", $tipoUsuarioCierre);
                })
                ->orderBy("fecha", "desc");
        } else {
            $cierres = cierres::with("usuario")
                ->whereBetween("fecha", [$fechaGetCierre, $fechaGetCierre2])
                ->when($tipoUsuarioCierre != "", function ($q) use ($tipoUsuarioCierre) {
                    $q->where("tipo_cierre", $tipoUsuarioCierre);
                })
                ->orderBy("fecha", "desc");
        }


        return [
            "cierres" => $cierres->get(),
            "numventas" => $cierres->sum("numventas"),

            "debito" => number_format($cierres->sum("debito"), 2),
            "efectivo" => number_format($cierres->sum("efectivo"), 2),
            "transferencia" => number_format($cierres->sum("transferencia"), 2),
            "caja_biopago" => number_format($cierres->sum("caja_biopago"), 2),


            "precio" => number_format($cierres->sum("precio"), 2),
            "precio_base" => number_format($cierres->sum("precio_base"), 2),

            "ganancia" => number_format($cierres->sum("ganancia"), 2),
            "porcentaje" => number_format($cierres->avg("porcentaje"), 2),




            "dejar_dolar" => number_format($cierres->sum("dejar_dolar"), 2),
            "dejar_peso" => number_format($cierres->sum("dejar_peso"), 2),
            "dejar_bss" => number_format($cierres->sum("dejar_bss"), 2),
            "efectivo_guardado" => number_format($cierres->sum("efectivo_guardado"), 2),
            "efectivo_guardado_cop" => number_format($cierres->sum("efectivo_guardado_cop"), 2),
            "efectivo_guardado_bs" => number_format($cierres->sum("efectivo_guardado_bs"), 2),
            "efectivo_actual" => number_format($cierres->sum("efectivo_actual"), 2),
            "efectivo_actual_cop" => number_format($cierres->sum("efectivo_actual_cop"), 2),
            "efectivo_actual_bs" => number_format($cierres->sum("efectivo_actual_bs"), 2),
            "puntodeventa_actual_bs" => number_format($cierres->sum("puntodeventa_actual_bs"), 2),

        ];

    }
    public function cerrar(Request $req)
    {
        $today = (new PedidosController)->today();

        return $this->cerrarFun(
            $today,
            $req->total_caja_neto,
            $req->total_punto,
            $req->total_biopago,

            ["dejar_bs" => $req->dejar_bs, "dejar_usd" => $req->dejar_usd, "dejar_cop" => $req->dejar_cop],
            false,
            filter_var($req->totalizarcierre, FILTER_VALIDATE_BOOLEAN),

        );
    }

    public function msj_cuadre($total_entregado, $monto_facturado, $clave, &$arr_pagos, $tolerancia = 5)
    {
        if ($monto_facturado or $monto_facturado === 0) {
            $diff = round($total_entregado - $monto_facturado, 3);
            if (($diff >= 0) && ($diff <= $tolerancia)) {
                $arr_pagos["msj_" . $clave] = "Cuadrado. Sobran " . $diff;
                $arr_pagos["estado_" . $clave] = 1;
            } elseif ($diff >= $tolerancia) {
                $arr_pagos["msj_" . $clave] = "Err. Sobran " . $diff;
                $arr_pagos["estado_" . $clave] = 0;
            } elseif (($diff <= 0)) {
                $arr_pagos["msj_" . $clave] = "Faltan " . $diff;
                $arr_pagos["estado_" . $clave] = 0;
            }
        }
    }
    
    
    
    public function guardarCierre(Request $req)
    {
        try {
            
            $id_usuario = session("id_usuario");
            $today = (new PedidosController)->today();

            $cop = $this->get_moneda()["cop"];
            $bs = $this->get_moneda()["bs"];

            $totalizarcierre = filter_var($req->totalizarcierre, FILTER_VALIDATE_BOOLEAN);
            $dataPuntosAdicionales = $req->dataPuntosAdicionales;

            $id_vendedor = $this->selectUsersTotalizar($totalizarcierre,$today);
            $tipo_cierre = $totalizarcierre ? 1 : 0;

            if ($tipo_cierre==0 && session("tipo_usuario")==1) {
                return "Siendo Administrador, solo puede hacer cierre totalizado";
            }

           /*  $check = cajas::where("estatus",0);
            
            if ($check->count()) {
                return "Hay movimientos de Caja PENDIENTES";
            } */

            if ($tipo_cierre==0) {
                cierres_puntos::where("fecha", $today)->whereIn("id_usuario",$id_vendedor)->delete();
                foreach ($dataPuntosAdicionales as $e) {
                    if(
                    !$e["banco"] ||
                    !$e["monto"] ||
                    !$e["descripcion"] ||
                    !$e["categoria"]){
                        return "Lotes adicional con campo Vacío";
                    }
                }

                // Validar que la suma de los lotes coincida con el total de bolívares en punto
                $sumaMontosLotes = 0;
                foreach ($dataPuntosAdicionales as $e) {
                    $sumaMontosLotes += floatval($e["monto"]);
                }
                $cajaPuntoTotal = floatval($req->caja_punto);
                
                // Permitir una diferencia de hasta 0.01 por redondeos
                if (abs($sumaMontosLotes - $cajaPuntoTotal) > 0.01) {
                    // Si hay discrepancia, eliminar el cierre y cierres_puntos existentes del día
                    cierres::where("fecha", $today)->whereIn("id_usuario", $id_vendedor)->delete();
                    cierres_puntos::where("fecha", $today)->whereIn("id_usuario", $id_vendedor)->delete();
                    return "Error: La suma de los lotes (Bs. " . number_format($sumaMontosLotes, 2, ',', '.') . ") no coincide con el total de bolívares en punto (Bs. " . number_format($cajaPuntoTotal, 2, ',', '.') . "). El cierre ha sido eliminado.";
                }

                foreach ($dataPuntosAdicionales as $e) {
                    $newpunadi = new cierres_puntos;
                    $newpunadi->fecha = $today;
                    $newpunadi->categoria = $e["categoria"];
                    $newpunadi->descripcion = $e["descripcion"];
                    $newpunadi->banco = $e["banco"];
                    $newpunadi->monto = $e["monto"];
                    $newpunadi->id_usuario = $id_usuario;
                    $newpunadi->save();
                }
            }

            $last_cierre = cierres::whereIn("id_usuario", $id_vendedor)->orderBy("fecha", "desc")->first();
            $check = cierres::whereIn("id_usuario", $id_vendedor)->where("fecha", $today)->first();

            $fecha_ultimo_cierre = "0";
            if ($last_cierre) {
                $fecha_ultimo_cierre = $last_cierre["fecha"];
            }


            if ($check === null || $fecha_ultimo_cierre == $today) {
                Cache::forget('lastcierres');
                Cache::forget('cierreCount');
                Cache::forget('today');

                

                if ($req->montolote1punto || $req->lote1punto || $req->puntolote1banco) {
                    if (!$req->lote1punto || !$req->puntolote1banco || !$req->montolote1punto) {
                        return "Error: Monto PUNTO 1 es valido. LOTE PUNTO 1 o BANCO PUNTO 1 NO es Valido";
                    }
                }
                if ($req->montolote2punto || $req->lote2punto || $req->puntolote2banco) {
                    if (!$req->lote2punto || !$req->puntolote2banco || !$req->montolote2punto) {
                        return "Error: Monto PUNTO 2 es valido. LOTE PUNTO 2 o BANCO PUNTO 2 NO es Valido";
                    }
                }

                if (floatval($req->guardar_usd)<0) {
                    return Response::json(["msj" => "Error: Esta guardando mas efectivo de lo disponible", "estado" => false]);
        
                }
                
                if ($req->tipo_accionCierre == "guardar") {
                    $objcierres = new cierres;

                    $objcierres->caja_biopago = floatval($req->total_biopago);
                    $objcierres->debito = floatval($req->total_punto);
                    $objcierres->efectivo = floatval($req->efectivo);
                    $objcierres->transferencia = floatval($req->transferencia);
                    
                    
                    $objcierres->debito_digital = floatval($req->debito_digital); 
                    $objcierres->efectivo_digital = floatval($req->efectivo_digital); 
                    $objcierres->transferencia_digital = floatval($req->transferencia_digital); 
                    $objcierres->biopago_digital = floatval($req->biopago_digital); 
                    $objcierres->descuadre = floatval($req->descuadre); 

                    $objcierres->dejar_dolar = floatval($req->dejar_usd);
                    $objcierres->dejar_peso = floatval($req->dejar_cop);
                    $objcierres->dejar_bss = floatval($req->dejar_bs);

                    $objcierres->efectivo_guardado = floatval($req->guardar_usd);
                    $objcierres->efectivo_guardado_cop = floatval($req->guardar_cop);
                    $objcierres->efectivo_guardado_bs = floatval($req->guardar_bs);
                    $objcierres->tasa = $bs;
                    $objcierres->nota = $req->notaCierre;
                    $objcierres->id_usuario = $id_usuario;
                    $objcierres->fecha = $today;
                    
                    $objcierres->precio = floatval($req->precio);
                    $objcierres->precio_base = floatval($req->precio_base);
                    $objcierres->ganancia = floatval($req->ganancia);
                    $objcierres->porcentaje = floatval($req->porcentaje);
                    $objcierres->numventas = intval($req->numventas);
                    $objcierres->desc_total = floatval($req->desc_total);

                    $objcierres->efectivo_actual = floatval($req->caja_usd);
                    $objcierres->efectivo_actual_cop = floatval($req->caja_cop);
                    $objcierres->efectivo_actual_bs = floatval($req->caja_bs);
                    $objcierres->puntodeventa_actual_bs = floatval($req->caja_punto);

                    $objcierres->tipo_cierre = $tipo_cierre;

                    $objcierres->tasacop = $cop;

                    $objcierres->numreportez = $req->numreportez;
                    $objcierres->ventaexcento = floatval($req->ventaexcento);
                    $objcierres->ventagravadas = floatval($req->ventagravadas);
                    $objcierres->ivaventa = floatval($req->ivaventa);
                    $objcierres->totalventa = floatval($req->totalventa);
                    $objcierres->ultimafactura = $req->ultimafactura;

                    $objcierres->efecadiccajafbs = floatval($req->efecadiccajafbs);
                    $objcierres->efecadiccajafcop = floatval($req->efecadiccajafcop);
                    $objcierres->efecadiccajafdolar = floatval($req->efecadiccajafdolar);
                    $objcierres->efecadiccajafeuro = floatval($req->efecadiccajafeuro);


                    $objcierres->inventariobase = floatval($req->inventariobase);
                    $objcierres->inventarioventa = floatval($req->inventarioventa);

                    $objcierres->credito = floatval($req->credito);
                    $objcierres->creditoporcobrartotal = floatval($req->creditoporcobrartotal);
                    $objcierres->vueltostotales = floatval($req->vueltostotales);
                    $objcierres->abonosdeldia = floatval($req->abonosdeldia);

                    $objcierres->puntolote1montobs = floatval($req->montolote1punto);
                    $objcierres->puntolote2montobs = floatval($req->montolote2punto);
                    $objcierres->puntolote1 = $req->lote1punto;
                    $objcierres->puntolote2 = $req->lote2punto;
                    $objcierres->biopagoserial = $req->serialbiopago;
                    $objcierres->biopagoserialmontobs = $req->caja_biopago;


                    $objcierres->puntolote1banco = $req->puntolote1banco;
                    $objcierres->puntolote2banco = $req->puntolote2banco;

                    $objcierres->save();

                } else if ($req->tipo_accionCierre == "editar") {


                    cierres::updateOrCreate(
                        ["fecha" => $today, "id_usuario" => $id_usuario],
                        [
                            "caja_biopago" => floatval($req->total_biopago),
                            "debito" => floatval($req->total_punto),
                            "efectivo" => floatval($req->efectivo),
                            "transferencia" => floatval($req->transferencia),

                            "debito_digital" => floatval($req->debito_digital), 
                            "efectivo_digital" => floatval($req->efectivo_digital), 
                            "transferencia_digital" => floatval($req->transferencia_digital), 
                            "biopago_digital" => floatval($req->biopago_digital), 
                            "descuadre" => floatval($req->descuadre), 


                            "dejar_dolar" => floatval($req->dejar_usd),
                            "dejar_peso" => floatval($req->dejar_cop),
                            "dejar_bss" => floatval($req->dejar_bs),

                            "efectivo_guardado" => floatval($req->guardar_usd),
                            "efectivo_guardado_cop" => floatval($req->guardar_cop),
                            "efectivo_guardado_bs" => floatval($req->guardar_bs),
                            "tasa" => $bs,
                            "nota" => $req->notaCierre,
                            "id_usuario" => $id_usuario,

                            "precio" => floatval($req->precio),
                            "precio_base" => floatval($req->precio_base),
                            "ganancia" => floatval($req->ganancia),
                            "porcentaje" => floatval($req->porcentaje),
                            "numventas" => intval($req->numventas),
                            "desc_total" => floatval($req->desc_total),

                            "efectivo_actual" => floatval($req->caja_usd),
                            "efectivo_actual_cop" => floatval($req->caja_cop),
                            "efectivo_actual_bs" => floatval($req->caja_bs),
                            "puntodeventa_actual_bs" => floatval($req->caja_punto),

                            "tasacop" => $cop,
                            "inventariobase" => floatval($req->inventariobase),
                            "inventarioventa" => floatval($req->inventarioventa),
                            "numreportez" => $req->numreportez,
                            "ventaexcento" => floatval($req->ventaexcento),
                            "ventagravadas" => floatval($req->ventagravadas),
                            "ivaventa" => floatval($req->ivaventa),
                            "totalventa" => floatval($req->totalventa),
                            "ultimafactura" => $req->ultimafactura,
                            "credito" => floatval($req->credito),
                            "creditoporcobrartotal" => floatval($req->creditoporcobrartotal),
                            "vueltostotales" => floatval($req->vueltostotales),
                            "abonosdeldia" => floatval($req->abonosdeldia),
                            "efecadiccajafbs" => floatval($req->efecadiccajafbs),
                            "efecadiccajafcop" => floatval($req->efecadiccajafcop),
                            "efecadiccajafdolar" => floatval($req->efecadiccajafdolar),
                            "efecadiccajafeuro" => floatval($req->efecadiccajafeuro),

                            "puntolote1montobs" => floatval($req->montolote1punto),
                            "puntolote2montobs" => floatval($req->montolote2punto),
                            "puntolote1" => $req->lote1punto,
                            "puntolote2" => $req->lote2punto,
                            "biopagoserial" => $req->serialbiopago,
                            "biopagoserialmontobs" => $req->caja_biopago,
                            "puntolote1banco" => $req->puntolote1banco,
                            "puntolote2banco" => $req->puntolote2banco,
                        ]

                    );

                }


                $CajaFuerteEntradaCierreDolar = floatval($req->CajaFuerteEntradaCierreDolar);
                $CajaFuerteEntradaCierreCop = floatval($req->CajaFuerteEntradaCierreCop);
                $CajaFuerteEntradaCierreBs = floatval($req->CajaFuerteEntradaCierreBs);

                if ($tipo_cierre==1) {
                    cajas::where("concepto","INGRESO DESDE CIERRE")->where("fecha",$today)->delete();
                    cajas::where("concepto","FALTANTE DE CAJA $today")->where("fecha",$today)->delete();

                    if (floatval($req->descuadre)<-5) {
                        cajas::updateOrCreate([
                            "fecha"=>$today,
                            "concepto" => "FALTANTE DE CAJA $today",
                        ],[
                            "concepto" => "FALTANTE DE CAJA $today",
                            "categoria" => 27,
                            "id_departamento" => null,
                            "tipo" => 1,
                            "fecha" => $today,

                            "montodolar" => abs(floatval($req->descuadre)),
                            "montopeso" => 0,
                            "montobs" => 0,
                            "montoeuro" => 0,
                            
                            "dolarbalance" => 0,
                            "pesobalance" => 0,
                            "bsbalance" => 0,
                            "eurobalance" => 0,

                            "estatus" => 1,
                        ]);
                    }

                    cajas::updateOrCreate([
                        "fecha"=>$today,
                        "concepto" => "INGRESO DESDE CIERRE",
                    ],[
                        "concepto" => "INGRESO DESDE CIERRE",
                        "categoria" => 26,
                        "id_departamento" => null,
                        "tipo" => 1,
                        "fecha" => $today,
                        "montodolar" => $CajaFuerteEntradaCierreDolar,
                        "montopeso" => $CajaFuerteEntradaCierreCop,
                        "montobs" => $CajaFuerteEntradaCierreBs,
                        "montoeuro" => 0,
                        "dolarbalance" => 0,
                        "pesobalance" => 0,
                        "bsbalance" => 0,
                        "eurobalance" => 0,
                        "estatus" => 1,
                    ]);
                }
                
            } else {
                throw new \Exception("Cierre de la fecha: " . $fecha_ultimo_cierre . " procesado. No se pueden hacer cambios.", 1);
            }

            return Response::json(["msj" => "¡Cierre guardado exitosamente!", "estado" => true]);

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getCode() . " " . $e->getMessage()." LINEA ".$e->getLine(), "estado" => false]);

        }
    }
    public function addNewPedido()
    {
        $usuario = session("id_usuario");

        $new_pedido = new pedidos;
        $new_pedido->estado = 0;
        $new_pedido->id_cliente = 1;
        $new_pedido->id_vendedor = $usuario;
        $new_pedido->fecha_factura = now();
        $new_pedido->save();
        return $new_pedido->id;
    }
    public function verCierre(Request $req)
    {
        $fechareq = $req->fecha;
        $type = $req->type;
        $usuario = isset($req->usuario) ? $req->usuario : null;

        $usuarioLogin = $usuario ? $usuario : session("id_usuario");

        $sucursal = sucursal::all()->first();
        

        $totalizarcierre = filter_var($req->totalizarcierre, FILTER_VALIDATE_BOOLEAN);
        if ($totalizarcierre) {
            $id_vendedor = pedidos::select('id_vendedor')->distinct()->get()->map(function ($e) {
                return $e->id_vendedor;
            });

        } else {
            $id_vendedor = [$usuarioLogin];
        }

        $cierre = cierres::with("usuario")->where("fecha", $fechareq)->where('id_usuario', $usuarioLogin)->first();
        if (!$cierre) {
            return "No hay cierre guardado para esta fecha";
        }

        $pagos_referencias = pagos_referencias::where("created_at", "LIKE", $fechareq . "%")
            ->whereIn('id_pedido', function ($q) use ($id_vendedor) {
                $q->from('pedidos')->whereIn('id_vendedor', $id_vendedor)->select('id');
            })
            ->orderBy("tipo", "desc")->get();

        $movimientos = movimientos::with([
            "usuario" => function ($q) {
                $q->select(["id", "nombre", "usuario"]);
            },
            "items" => function ($q) {
                $q->with("producto");
            }
        ])->where("created_at", "LIKE", $fechareq . "%")->get();


        $fechareqmenos1 = date('Y-m-d', strtotime($fechareq . " - 1 day"));

        $movimientosInventario = movimientosInventario::with([
            "usuario" => function ($q) {
                $q->select(["id", "nombre", "usuario"]);
            }
        ])->whereBetween("created_at",[$fechareqmenos1." 00:00:00",$fechareq." 23:59:59"])

            ->get()
            ->map(function ($q) {
                if ($q->antes) {
                    $q->antes = json_decode($q->antes);
                }
                if ($q->despues) {
                    $q->despues = json_decode($q->despues);
                }
                return $q;
            });

        $facturado = $this->cerrarFun($fechareq, 0, 0, 0, [], false, $totalizarcierre, true, $usuario ? $usuario : null);
        if (is_array($facturado)) {
            $total_inventario = $facturado["total_inventario"];
            $total_inventario_base = $facturado["total_inventario_base"];
            $cred_total = $facturado["cred_total"];
            $vueltos_totales = $facturado["vueltos_totales"];
            $pedidos_abonos = $facturado["pedidos_abonos"];
            $abonosdeldia = $facturado["abonosdeldia"];
        } else {
            return $facturado;
        }


        if (is_object($facturado)) {
            return $facturado;
        }
        $arr_send = [
            "referencias" => $pagos_referencias,
            "cierre" => $cierre,
            "cierre_tot" => moneda($cierre->debito + $cierre->efectivo + $cierre->transferencia + $cierre->caja_biopago),

            "total_inventario" => ($total_inventario),
            "total_inventario_format" => moneda($total_inventario),
            "total_inventario_base" => ($total_inventario_base),
            "total_inventario_base_format" => moneda($total_inventario_base),

            "vueltos_totales" => moneda($vueltos_totales),
            "vueltos_des" => $facturado["vueltos_des"],

            "precio" => moneda($facturado["precio"]),
            "precio_base" => moneda($facturado["precio_base"]),
            "ganancia" => moneda(round($facturado["ganancia"], 2)),
            "porcentaje" => moneda($facturado["porcentaje"]),
            "desc_total" => moneda(round($facturado["desc_total"], 2)),
            "facturado" => $facturado,
            "facturado_tot" => moneda($facturado[2] + $facturado[3] + $facturado[1] + $facturado[5]),
            "sucursal" => $sucursal,
            "movimientos" => $movimientos,
            "movimientosInventario" => $movimientosInventario,
        ];

        
    
        
        $arr_send["cajas"]["balance"]["chica"]["dolarbalance"] = (new CajasController)->getBalance(0, "dolarbalance");
        $arr_send["cajas"]["balance"]["chica"]["pesobalance"] = (new CajasController)->getBalance(0, "pesobalance");
        $arr_send["cajas"]["balance"]["chica"]["bsbalance"] = (new CajasController)->getBalance(0, "bsbalance");

        $arr_send["cajas"]["balance"]["fuerte"]["dolarbalance"] = (new CajasController)->getBalance(1, "dolarbalance");
        $arr_send["cajas"]["balance"]["fuerte"]["pesobalance"] = (new CajasController)->getBalance(1, "pesobalance");
        $arr_send["cajas"]["balance"]["fuerte"]["bsbalance"] = (new CajasController)->getBalance(1, "bsbalance");

        $detalles_fuerte = cajas::with("cat")->whereBetween("created_at",[$fechareq." 00:00:00",$fechareq." 23:59:59"])
        ->where("tipo","1")
        ->orderBy("id","desc")
        ->get();
        
        $arr_send["cajas"]["detalles"]["fuerte"] = $detalles_fuerte;
        
        $detalles_chica = cajas::with("cat")->whereBetween("created_at",[$fechareq." 00:00:00",$fechareq." 23:59:59"])
        ->where("tipo","0")
        ->orderBy("id","desc")
        ->get();

        $arr_send["cajas"]["detalles"]["chica"] = $detalles_chica;



        $caja_montodolar = cajas::where("created_at", "LIKE", $fechareq."%")->whereIn("categoria",[10,11])->sum("montodolar");

        $caja_montopeso = cajas::where("created_at", "LIKE", $fechareq."%")->whereIn("categoria",[10,11])->sum("montopeso");
        $caja_montobs = cajas::where("created_at", "LIKE", $fechareq."%")->whereIn("categoria",[10,11])->sum("montobs");



        $arr_send["cierre"]["debito"] = moneda($arr_send["cierre"]["debito"]);
        $arr_send["cierre"]["efectivo"] = moneda($arr_send["cierre"]["efectivo"]);
        $arr_send["cierre"]["transferencia"] = moneda($arr_send["cierre"]["transferencia"]);
        $arr_send["cierre"]["caja_biopago"] = moneda($arr_send["cierre"]["caja_biopago"]);

        $arr_send["cierre"]["dejar_dolar"] = moneda($arr_send["cierre"]["dejar_dolar"]);
        $arr_send["cierre"]["dejar_peso"] = moneda($arr_send["cierre"]["dejar_peso"]);
        $arr_send["cierre"]["dejar_bss"] = moneda($arr_send["cierre"]["dejar_bss"]);
        $arr_send["cierre"]["tasa"] = moneda($arr_send["cierre"]["tasa"]);
        $arr_send["cierre"]["tasacop"] = moneda($arr_send["cierre"]["tasacop"]);
        
        $arr_send["cierre"]["numreportez"] = moneda($arr_send["cierre"]["numreportez"]);
        $arr_send["cierre"]["ventaexcento"] = moneda($arr_send["cierre"]["ventaexcento"]);
        $arr_send["cierre"]["ventagravadas"] = moneda($arr_send["cierre"]["ventagravadas"]);
        $arr_send["cierre"]["ivaventa"] = moneda($arr_send["cierre"]["ivaventa"]);
        $arr_send["cierre"]["totalventa"] = moneda($arr_send["cierre"]["totalventa"]);
        $arr_send["cierre"]["ultimafactura"] = moneda($arr_send["cierre"]["ultimafactura"]);

        $arr_send["cajas"]["caja_montodolar"] = moneda($caja_montodolar);
        $arr_send["cajas"]["caja_montopeso"] = moneda($caja_montopeso);
        $arr_send["cajas"]["caja_montobs"] = moneda($caja_montobs);


        $arr_send["cierre"]["efectivo_guardado"] = moneda($arr_send["cierre"]["efectivo_guardado"]);
        $arr_send["cierre"]["efectivo_guardado_cop"] = moneda($arr_send["cierre"]["efectivo_guardado_cop"]);
        $arr_send["cierre"]["efectivo_guardado_bs"] = moneda($arr_send["cierre"]["efectivo_guardado_bs"]);
        $arr_send["facturado"]["total"] = moneda($arr_send["facturado"]["total"]);
        $arr_send["facturado"]["caja_inicial"] = moneda($arr_send["facturado"]["caja_inicial"]);
        $arr_send["facturado"]["caja_inicialpeso"] = moneda($arr_send["facturado"]["caja_inicialpeso"]);
        $arr_send["facturado"]["caja_inicialbs"] = moneda($arr_send["facturado"]["caja_inicialbs"]);

        $arr_send["facturado"]["entregadomenospend"] = moneda($arr_send["facturado"]["entregadomenospend"]);
        $arr_send["facturado"]["entregado"] = moneda($arr_send["facturado"]["entregado"]);
        $arr_send["facturado"]["pendiente"] = moneda($arr_send["facturado"]["pendiente"]);
        $arr_send["facturado"]["total_caja"] = moneda($arr_send["facturado"]["total_caja"]);
        $arr_send["facturado"]["total_punto"] = moneda($arr_send["facturado"]["total_punto"]);
        $arr_send["facturado"]["total_biopago"] = moneda($arr_send["facturado"]["total_biopago"]);

        $arr_send["facturado"]["1"] = moneda($arr_send["facturado"]["1"]);
        $arr_send["facturado"]["2"] = moneda($arr_send["facturado"]["2"]);
        $arr_send["facturado"]["3"] = moneda($arr_send["facturado"]["3"]);
        $arr_send["facturado"]["4"] = moneda($arr_send["facturado"]["4"]);
        $arr_send["facturado"]["5"] = moneda($arr_send["facturado"]["5"]);
        $arr_send["facturado"]["6"] = moneda($arr_send["facturado"]["6"]);

        $arr_send["total_inventario_format"] = toLetras($arr_send["total_inventario_format"]);
        $arr_send["total_inventario_base_format"] = toLetras($arr_send["total_inventario_base_format"]);
        $arr_send["vueltos_totales"] = toLetras($arr_send["vueltos_totales"]);
        $arr_send["precio"] = toLetras($arr_send["precio"]);
        $arr_send["precio_base"] = toLetras($arr_send["precio_base"]);
        $arr_send["ganancia"] = toLetras($arr_send["ganancia"]);
        $arr_send["porcentaje"] = toLetras($arr_send["porcentaje"]);
        $arr_send["desc_total"] = toLetras($arr_send["desc_total"]);
        $arr_send["cierre_tot"] = toLetras($arr_send["cierre_tot"]);
        $arr_send["facturado_tot"] = toLetras($arr_send["facturado_tot"]);

        $arr_send["cierre"]["debito"] = toLetras($arr_send["cierre"]["debito"]);
        $arr_send["cierre"]["efectivo"] = toLetras($arr_send["cierre"]["efectivo"]);
        $arr_send["cierre"]["transferencia"] = toLetras($arr_send["cierre"]["transferencia"]);
        $arr_send["cierre"]["caja_biopago"] = toLetras($arr_send["cierre"]["caja_biopago"]);

        $arr_send["cierre"]["dejar_dolar"] = toLetras($arr_send["cierre"]["dejar_dolar"]);
        $arr_send["cierre"]["dejar_peso"] = toLetras($arr_send["cierre"]["dejar_peso"]);
        $arr_send["cierre"]["dejar_bss"] = toLetras($arr_send["cierre"]["dejar_bss"]);
        $arr_send["cierre"]["tasa"] = toLetras($arr_send["cierre"]["tasa"]);
        $arr_send["cierre"]["tasacop"] = toLetras($arr_send["cierre"]["tasacop"]);

        $arr_send["cierre"]["efectivo_guardado"] = toLetras($arr_send["cierre"]["efectivo_guardado"]);
        $arr_send["cierre"]["efectivo_guardado_cop"] = toLetras($arr_send["cierre"]["efectivo_guardado_cop"]);
        $arr_send["cierre"]["efectivo_guardado_bs"] = toLetras($arr_send["cierre"]["efectivo_guardado_bs"]);

        $arr_send["facturado"]["numventas"] = toLetras($arr_send["facturado"]["numventas"]);
        $arr_send["facturado"]["total"] = toLetras($arr_send["facturado"]["total"]);
        $arr_send["facturado"]["caja_inicial"] = toLetras($arr_send["facturado"]["caja_inicial"]);
        $arr_send["facturado"]["caja_inicialpeso"] = toLetras($arr_send["facturado"]["caja_inicialpeso"]);
        $arr_send["facturado"]["caja_inicialbs"] = toLetras($arr_send["facturado"]["caja_inicialbs"]);

        
        $arr_send["facturado"]["entregadomenospend"] = toLetras($arr_send["facturado"]["entregadomenospend"]);
        $arr_send["facturado"]["entregado"] = toLetras($arr_send["facturado"]["entregado"]);
        $arr_send["facturado"]["pendiente"] = toLetras($arr_send["facturado"]["pendiente"]);
        $arr_send["facturado"]["total_caja"] = toLetras($arr_send["facturado"]["total_caja"]);
        $arr_send["facturado"]["total_punto"] = toLetras($arr_send["facturado"]["total_punto"]);
        $arr_send["facturado"]["total_biopago"] = toLetras($arr_send["facturado"]["total_biopago"]);

        $arr_send["facturado"]["1"] = toLetras($arr_send["facturado"]["1"]);
        $arr_send["facturado"]["2"] = toLetras($arr_send["facturado"]["2"]);
        $arr_send["facturado"]["3"] = toLetras($arr_send["facturado"]["3"]);
        $arr_send["facturado"]["4"] = toLetras($arr_send["facturado"]["4"]);
        $arr_send["facturado"]["5"] = toLetras($arr_send["facturado"]["5"]);
        $arr_send["facturado"]["6"] = toLetras($arr_send["facturado"]["6"]);

        $arr_send["cred_total"] = $cred_total;
        $arr_send["pedidos_abonos"] = $pedidos_abonos;
        $arr_send["abonosdeldia"] = $abonosdeldia;

        // Obtener devoluciones del día agrupadas por pedido
        // Paso 1: Identificar pedidos que tengan al menos un item con cantidad negativa
        $pedidos_con_devoluciones = \DB::table('items_pedidos as ip')
            ->join('pedidos as p', 'ip.id_pedido', '=', 'p.id')
            ->select('p.id')
            ->where('p.created_at', 'LIKE', $fechareq . '%')
            ->whereIn('p.id_vendedor', $id_vendedor)
            ->where('ip.cantidad', '<', 0)
            ->distinct()
            ->pluck('id');

        // Paso 2: Obtener TODOS los items de esos pedidos (positivos y negativos)
        $devoluciones_items = collect();
        if ($pedidos_con_devoluciones->isNotEmpty()) {
            $devoluciones_items = \DB::table('items_pedidos as ip')
                ->join('pedidos as p', 'ip.id_pedido', '=', 'p.id')
                ->join('inventarios as pr', 'ip.id_producto', '=', 'pr.id')
                ->leftJoin('usuarios as u', 'p.id_vendedor', '=', 'u.id')
                ->select(
                    'ip.id as item_id',
                    'ip.cantidad',
                    'pr.precio as precio_unitario',
                    \DB::raw('ip.cantidad * pr.precio as total_devolucion'),
                    'ip.condicion',
                    'pr.codigo_barras',
                    'pr.descripcion',
                    'p.id as pedido_id',
                    'p.created_at as fecha',
                    'u.usuario as vendedor',
                    'u.nombre as vendedor_nombre'
                )
                ->whereIn('p.id', $pedidos_con_devoluciones)
                ->orderBy('p.id', 'desc')
                ->orderBy('ip.id', 'asc')
                ->get();
        }

        // Paso 3: Obtener TODOS los pagos de esos pedidos
        $pagos_devueltos = collect();
        if ($pedidos_con_devoluciones->isNotEmpty()) {
            $pagos_devueltos = \DB::table('pago_pedidos as pp')
                ->select(
                    'pp.id_pedido',
                    'pp.tipo',
                    'pp.monto',
                    'pp.created_at as fecha_pago'
                )
                ->whereIn('pp.id_pedido', $pedidos_con_devoluciones)
                ->get()
                ->groupBy('id_pedido');
        }

        // Agrupar items por pedido
        $devoluciones_por_pedido = [];
        $total_devoluciones_buenos = 0;
        $total_devoluciones_malos = 0;
        $count_buenos = 0;
        $count_malos = 0;

        foreach ($devoluciones_items as $item) {
            $pedido_id = $item->pedido_id;
            
            // Inicializar pedido si no existe
            if (!isset($devoluciones_por_pedido[$pedido_id])) {
                $devoluciones_por_pedido[$pedido_id] = [
                    'pedido_id' => $pedido_id,
                    'fecha' => $item->fecha,
                    'vendedor' => $item->vendedor,
                    'vendedor_nombre' => $item->vendedor_nombre,
                    'items' => [],
                    'pagos_devueltos' => [],
                    'total_buenos' => 0,
                    'total_malos' => 0,
                    'total_pedido' => 0
                ];
                
                // Agregar pagos devueltos de este pedido
                if (isset($pagos_devueltos[$pedido_id])) {
                    foreach ($pagos_devueltos[$pedido_id] as $pago) {
                        $devoluciones_por_pedido[$pedido_id]['pagos_devueltos'][] = [
                            'tipo' => $pago->tipo,
                            'monto' => $pago->monto,
                            'fecha' => $pago->fecha_pago
                        ];
                    }
                }
            }
            
            // Clasificar item como bueno o malo
            $item->es_bueno = $item->condicion == 0 || $item->condicion == 2; // Cambio o devolución normal
            $item->es_malo = $item->condicion == 1; // Garantía
            
            // Agregar item al pedido
            $devoluciones_por_pedido[$pedido_id]['items'][] = $item;
            $devoluciones_por_pedido[$pedido_id]['total_pedido'] += $item->total_devolucion;
            
            // Acumular totales
            if ($item->es_bueno) {
                $total_devoluciones_buenos += $item->total_devolucion;
                $devoluciones_por_pedido[$pedido_id]['total_buenos'] += $item->total_devolucion;
                $count_buenos++;
            }
            if ($item->es_malo) {
                $total_devoluciones_malos += $item->total_devolucion;
                $devoluciones_por_pedido[$pedido_id]['total_malos'] += $item->total_devolucion;
                $count_malos++;
            }
        }

        $arr_send["devoluciones"] = [
            "pedidos" => array_values($devoluciones_por_pedido),
            "total_buenos" => moneda($total_devoluciones_buenos),
            "total_malos" => moneda($total_devoluciones_malos),
            "total_general" => moneda($total_devoluciones_buenos + $total_devoluciones_malos),
            "count_buenos" => $count_buenos,
            "count_malos" => $count_malos,
            "count_total" => $devoluciones_items->count(),
            "count_pedidos" => count($devoluciones_por_pedido)
        ];

        if ($type == "ver") {
            return view("reportes.cierre", $arr_send);
        } else if ($type == "getData") {
            // Retornar datos para uso interno (ej: ejecutarPostSync)
            return [
                $arr_send,
                $sucursal->correo,
                $sucursal->sucursal,
                $sucursal->sucursal . " | CIERRE DIARIO | " . $fechareq
            ];
        } else {
            //Enviar Central

            // (new sendCentral)->setGastos();
            // (new sendCentral)->setCentralData();
            // (new sendCentral)->setVentas();

            //Enviar Cierre

            $from1 = $sucursal->correo;
            $from = $sucursal->sucursal;
            $subject = $sucursal->sucursal . " | CIERRE DIARIO | " . $fechareq;
            try {

                $result = (new sendCentral)->sendAll([
                    $arr_send,
                    $from1,
                    $from,
                    $subject
                ]);
                
                // Asegurar respuesta JSON válida
                if (is_array($result)) {
                    return Response::json($result);
                } elseif (is_string($result)) {
                    return Response::json(["msj" => $result, "estado" => false]);
                }
                return Response::json(["msj" => "Sincronización completada", "estado" => true]);

            } catch (\Exception $e) {

                return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);

            }

        }
    }
    
    public function sendCuentasporCobrar()
    {
        $today = $this->today();
        $sucursal = sucursal::all()->first();

        $from1 = $sucursal->correo;
        $from = $sucursal->sucursal;
        $subject = $sucursal->sucursal . " | CUENTAS POR COBRAR | " . $today;
        $data = (new PagoPedidosController)->getDeudoresFun("", "saldo", "asc", $today);
        try {

            Mail::to($this->sends())->send(new enviarCuentaspagar([
                "data" => $data,
                "sucursal" => $sucursal,
                "today" => $today
            ], $from1, $from, $subject));

            return Response::json(["msj" => "Cuentas enviadas con Éxito", "estado" => true]);

        } catch (\Exception $e) {

            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);

        }
    }

    /**
     * Asignar pedido original a una devolución
     * Valida que el pedido original exista y tenga monto positivo
     */
    public function asignarPedidoOriginalDevolucion(Request $req)
    {
        $id_pedido_devolucion = $req->id_pedido_devolucion;
        $id_pedido_original = $req->id_pedido_original;

        // Validar que el pedido de devolución exista
        $pedidoDevolucion = pedidos::find($id_pedido_devolucion);
        if (!$pedidoDevolucion) {
            return Response::json(["msj" => "El pedido de devolución no existe", "estado" => false]);
        }

        // Validar que el pedido original exista
        $pedidoOriginal = pedidos::with(['items.producto'])->find($id_pedido_original);
        if (!$pedidoOriginal) {
            return Response::json(["msj" => "El pedido original #$id_pedido_original no existe", "estado" => false]);
        }

        // Validar que el pedido original no sea una devolución (debe tener monto positivo)
        $montoTotal = items_pedidos::where('id_pedido', $id_pedido_original)->sum('monto');
       /*  if ($montoTotal <= 0) {
            return Response::json(["msj" => "El pedido #$id_pedido_original no es válido como factura original (monto no positivo)", "estado" => false]);
        } */

        // Validar que el pedido original esté facturado (estado > 0)
        if ($pedidoOriginal->estado == 0) {
            return Response::json(["msj" => "El pedido #$id_pedido_original no ha sido facturado aún", "estado" => false]);
        }

        // Asignar el pedido original
        $pedidoDevolucion->isdevolucionOriginalid = $id_pedido_original;
        $pedidoDevolucion->save();

        // Obtener cantidades disponibles para devolución (restando devoluciones previas)
        $itemsDisponibles = $this->getItemsDisponiblesDevolucion($id_pedido_original);

        return Response::json([
            "msj" => "Pedido original #$id_pedido_original asignado correctamente",
            "estado" => true,
            "pedido_original" => $pedidoOriginal,
            "items_disponibles" => $itemsDisponibles
        ]);
    }

    /**
     * Endpoint para obtener items disponibles para devolución
     */
    public function getItemsDisponiblesDevolucionEndpoint(Request $req)
    {
        $id_pedido_original = $req->id_pedido_original;
        $itemsDisponibles = $this->getItemsDisponiblesDevolucion($id_pedido_original);
        return Response::json([
            "estado" => true,
            "items_disponibles" => $itemsDisponibles
        ]);
    }

    /**
     * Obtener items disponibles para devolución de un pedido original
     * Considera devoluciones previas
     */
    public function getItemsDisponiblesDevolucion($id_pedido_original)
    {
        // Obtener items del pedido original
        $itemsOriginal = items_pedidos::where('id_pedido', $id_pedido_original)
            ->with('producto:id,codigo_barras,descripcion,precio')
            ->get();

        // Obtener todas las devoluciones de este pedido original
        $devolucionesIds = pedidos::where('isdevolucionOriginalid', $id_pedido_original)->pluck('id');

        // Calcular cantidades ya devueltas por producto
        $cantidadesDevueltas = [];
        if ($devolucionesIds->count() > 0) {
            $itemsDevueltos = items_pedidos::whereIn('id_pedido', $devolucionesIds)
                ->where('cantidad', '<', 0)
                ->get();
            
            foreach ($itemsDevueltos as $item) {
                $idProducto = $item->id_producto;
                if (!isset($cantidadesDevueltas[$idProducto])) {
                    $cantidadesDevueltas[$idProducto] = 0;
                }
                // Las cantidades devueltas son negativas, las sumamos como positivas
                $cantidadesDevueltas[$idProducto] += abs($item->cantidad);
            }
        }

        // Calcular cantidades disponibles
        $itemsDisponibles = [];
        foreach ($itemsOriginal as $item) {
            $cantidadOriginal = $item->cantidad;
            $cantidadDevuelta = $cantidadesDevueltas[$item->id_producto] ?? 0;
            $cantidadDisponible = $cantidadOriginal - $cantidadDevuelta;

            if ($cantidadDisponible > 0) {
                $itemsDisponibles[] = [
                    'id_producto' => $item->id_producto,
                    'codigo_barras' => $item->producto->codigo_barras ?? '',
                    'descripcion' => $item->producto->descripcion ?? '',
                    'cantidad_original' => $cantidadOriginal,
                    'cantidad_devuelta' => $cantidadDevuelta,
                    'cantidad_disponible' => $cantidadDisponible,
                    'precio_unitario' => $item->precio_unitario ?? $item->producto->precio,
                    'tasa' => $item->tasa,
                    'descuento' => $item->descuento ?? 0,
                ];
            }
        }

        return $itemsDisponibles;
    }

    /**
     * Validar si un producto puede ser agregado como devolución
     */
    public function validarProductoDevolucion($id_pedido, $id_producto, $cantidad)
    {
        $pedido = pedidos::find($id_pedido);
        
        // Si no tiene pedido original asignado, no hay restricción
        if (!$pedido || !$pedido->isdevolucionOriginalid) {
            return ['valido' => true];
        }

        $itemsDisponibles = $this->getItemsDisponiblesDevolucion($pedido->isdevolucionOriginalid);
        
        // Buscar el producto en los items disponibles
        $itemEncontrado = null;
        foreach ($itemsDisponibles as $item) {
            if ($item['id_producto'] == $id_producto) {
                $itemEncontrado = $item;
                break;
            }
        }

        if (!$itemEncontrado) {
            // Verificar si el producto existía en la factura original pero ya fue devuelto
            $itemOriginal = items_pedidos::where('id_pedido', $pedido->isdevolucionOriginalid)
                ->where('id_producto', $id_producto)
                ->where('cantidad', '>', 0)
                ->first();

            if ($itemOriginal) {
                // El producto existía, buscar en qué factura de devolución fue devuelto
                $devolucionPrevia = pedidos::whereHas('items', function($q) use ($id_producto) {
                        $q->where('id_producto', $id_producto)
                          ->where('cantidad', '<', 0);
                    })
                    ->where('isdevolucionOriginalid', $pedido->isdevolucionOriginalid)
                    ->where('id', '!=', $id_pedido)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($devolucionPrevia) {
                    $fechaDevolucion = $devolucionPrevia->created_at 
                        ? $devolucionPrevia->created_at->format('d/m/Y H:i') 
                        : 'fecha desconocida';
                    
                    return [
                        'valido' => false,
                        'mensaje' => "Este producto ya fue devuelto completamente en la factura #{$devolucionPrevia->id} el {$fechaDevolucion}"
                    ];
                }

                return [
                    'valido' => false,
                    'mensaje' => "Este producto ya fue devuelto completamente de la factura original #{$pedido->isdevolucionOriginalid}"
                ];
            }

            return [
                'valido' => false,
                'mensaje' => 'Este producto no existe en la factura original #' . $pedido->isdevolucionOriginalid
            ];
        }

        // La cantidad de devolución es negativa, convertir a positiva para comparar
        $cantidadSolicitada = abs($cantidad);
        
        if ($cantidadSolicitada > $itemEncontrado['cantidad_disponible']) {
            return [
                'valido' => false,
                'mensaje' => "Cantidad máxima disponible para devolución: {$itemEncontrado['cantidad_disponible']} (Original: {$itemEncontrado['cantidad_original']}, Ya devuelto: {$itemEncontrado['cantidad_devuelta']})"
            ];
        }

        return [
            'valido' => true,
            'item_original' => $itemEncontrado
        ];
    }

    /**
     * Eliminar la asignación de pedido original de una devolución
     */
    public function eliminarPedidoOriginalDevolucion(Request $req)
    {
        $id_pedido = $req->id_pedido;

        $pedido = pedidos::find($id_pedido);
        if (!$pedido) {
            return Response::json([
                "msj" => "Pedido no encontrado",
                "estado" => false
            ]);
        }

        // Verificar que no tenga items con cantidad negativa ya agregados
        $itemsNegativos = items_pedidos::where('id_pedido', $id_pedido)
            ->where('cantidad', '<', 0)
            ->count();

        if ($itemsNegativos > 0) {
            return Response::json([
                "msj" => "No se puede eliminar la asignación porque ya hay productos devueltos en este pedido",
                "estado" => false
            ]);
        }

        $pedido->isdevolucionOriginalid = null;
        $pedido->save();

        return Response::json([
            "msj" => "Asignación de factura original eliminada",
            "estado" => true
        ]);
    }

    /**
     * Exportar ventas a CSV con desglose de métodos de pago
     * Admin (tipo_usuario=1): todos los pedidos
     * Otros usuarios: solo sus propios pedidos
     */
    public function exportarVentasCSV(Request $req)
    {
        $fecha1 = $req->fecha1 ?: date('Y-m-d');
        $fecha2 = $req->fecha2 ?: date('Y-m-d');
        
        // Obtener usuario y tipo de sesión
        $id_usuario = session("id_usuario");
        $tipo_usuario = session("tipo_usuario");
        $esAdmin = ($tipo_usuario == 1);
        
        // Obtener tasa de cambio actual
        $bs_rate = $this->get_moneda()['bs'] ?? 1;
        
        // Obtener pedidos con sus relaciones
        $query = pedidos::with([
            'pagos:id,id_pedido,tipo,monto,monto_original,moneda,referencia',
            'items:id,id_pedido,monto,descuento,cantidad',
            'vendedor:id,nombre,usuario',
            'cliente:id,nombre,identificacion',
            'referencias:id,id_pedido,descripcion,monto,estatus'
        ])
        ->whereBetween('fecha_factura', ["$fecha1 00:00:00", "$fecha2 23:59:59"])
        ->where('estado', '!=', 2); // Excluir anulados
        
        // Si NO es admin, filtrar solo por sus pedidos
        if (!$esAdmin) {
            $query->where('id_vendedor', $id_usuario);
        }
        
        $pedidos = $query->orderBy('id', 'asc')->get();
        
        // Construir CSV
        $csv = [];
        
        // Encabezados
        $headers = [
            'ID_Pedido',
            'Fecha',
            'Hora',
            'Estado',
            'Tasa_Bs',
            'Cajero_ID',
            'Cajero_Nombre',
            'Cliente_ID',
            'Cliente_Nombre',
            'Cliente_Cedula',
            'Cant_Items',
            'Total_USD',
            'Total_Bs',
            // Métodos de pago en USD
            'Transferencia_USD',
            'Transferencia_Ref',
            'Debito_USD',
            'Debito_Bs',
            'Debito_Ref',
            'Efectivo_USD',
            'Efectivo_Bs',
            'Credito_USD',
            'Biopago_USD',
            'Biopago_Ref',
        ];
        $csv[] = implode(',', $headers);
        
        foreach ($pedidos as $pedido) {
            // Calcular total del pedido
            $totalUSD = 0;
            $cantItems = 0;
            foreach ($pedido->items as $item) {
                $montoItem = $item->monto - ($item->monto * ($item->descuento / 100));
                $totalUSD += $montoItem;
                $cantItems += abs($item->cantidad);
            }
            $totalBs = $totalUSD * $bs_rate;
            
            // Inicializar montos por método de pago
            $pagosDesglose = [
                'transferencia_usd' => 0,
                'transferencia_ref' => [],
                'debito_usd' => 0,
                'debito_bs' => 0,
                'debito_ref' => [],
                'efectivo_usd' => 0,
                'efectivo_bs' => 0,
                'credito_usd' => 0,
                'biopago_usd' => 0,
                'biopago_ref' => [],
            ];
            
            foreach ($pedido->pagos as $pago) {
                $montoUSD = floatval($pago->monto);
                $ref = $pago->referencia ?? '';
                
                switch ($pago->tipo) {
                    case 1: // Transferencia - referencias vienen de pagos_referencias
                        $pagosDesglose['transferencia_usd'] += $montoUSD;
                        break;
                    case 2: // Débito - monto en USD, monto_original en Bs, referencia
                        $pagosDesglose['debito_usd'] += $montoUSD;
                        $pagosDesglose['debito_bs'] += floatval($pago->monto_original ?? 0);
                        if ($ref) $pagosDesglose['debito_ref'][] = $ref;
                        break;
                    case 3: // Efectivo
                        $pagosDesglose['efectivo_usd'] += $montoUSD;
                        // Si el pago es en bolívares (moneda = 2), calcular equivalente
                        if ($pago->moneda == 2) {
                            $pagosDesglose['efectivo_bs'] += floatval($pago->monto_original ?? ($montoUSD * $bs_rate));
                        } else {
                            $pagosDesglose['efectivo_bs'] += $montoUSD * $bs_rate;
                        }
                        break;
                    case 4: // Crédito
                        $pagosDesglose['credito_usd'] += $montoUSD;
                        break;
                    case 5: // Biopago
                        $pagosDesglose['biopago_usd'] += $montoUSD;
                        if ($ref) $pagosDesglose['biopago_ref'][] = $ref;
                        break;
                }
            }
            
            // Obtener referencias de transferencias desde pagos_referencias
            // Formato: referencia:monto-referencia:monto
            foreach ($pedido->referencias as $ref) {
                // Incluir todas las referencias (estatus 1 = aprobada, pero incluimos todas por si acaso)
                $pagosDesglose['transferencia_ref'][] = $ref->descripcion . ':' . number_format(floatval($ref->monto), 2, '.', '');
            }
            
            // Estado del pedido
            $estadoTexto = $pedido->estado == 0 ? 'Pendiente' : ($pedido->estado == 1 ? 'Procesado' : 'Anulado');
            
            // Extraer fecha y hora por separado
            $fechaPedido = date('Y-m-d', strtotime($pedido->fecha_factura));
            $horaPedido = date('H:i:s', strtotime($pedido->fecha_factura));
            
            // Construir fila
            $row = [
                $pedido->id,
                $fechaPedido,
                $horaPedido,
                $estadoTexto,
                number_format($bs_rate, 2, '.', ''),
                $pedido->id_vendedor,
                '"' . ($pedido->vendedor->nombre ?? 'N/A') . '"',
                $pedido->id_cliente ?? '',
                '"' . ($pedido->cliente->nombre ?? 'Sin cliente') . '"',
                $pedido->cliente->identificacion ?? '',
                $cantItems,
                number_format($totalUSD, 2, '.', ''),
                number_format($totalBs, 2, '.', ''),
                // Métodos de pago
                number_format($pagosDesglose['transferencia_usd'], 2, '.', ''),
                '"' . implode('-', $pagosDesglose['transferencia_ref']) . '"',
                number_format($pagosDesglose['debito_usd'], 2, '.', ''),
                number_format($pagosDesglose['debito_bs'], 2, '.', ''),
                '"' . implode('; ', $pagosDesglose['debito_ref']) . '"',
                number_format($pagosDesglose['efectivo_usd'], 2, '.', ''),
                number_format($pagosDesglose['efectivo_bs'], 2, '.', ''),
                number_format($pagosDesglose['credito_usd'], 2, '.', ''),
                number_format($pagosDesglose['biopago_usd'], 2, '.', ''),
                '"' . implode('; ', $pagosDesglose['biopago_ref']) . '"',
            ];
            
            $csv[] = implode(',', $row);
        }
        
        $contenido = implode("\n", $csv);
        $filename = "ventas_{$fecha1}_a_{$fecha2}.csv";
        
        return response($contenido)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }


}
