<?php
namespace App\Http\Controllers;

use App\Models\cajas;
use App\Models\catcajas;
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
        $front_only = $req->boolean('front_only');
        $uuid = $req->input('uuid');
        $items = $req->input('items', []);

        if ($front_only && $uuid && is_array($items) && count($items) > 0) {
            // Pedido solo en front: crear en central con estado=0 (pendiente), sin método de pago
            $central = (new sendCentral)->setPedidoFrontInCentral($uuid, $req->transferirpedidoa, $items);
            return $central;
        }

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

        return $ret->limit(6)
            ->orderBy("estado", "asc")
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
            ->whereNotIn('pp.tipo', [4]) // Excluir créditos
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
            ->whereNotIn('pp.tipo', [4]) // Excluir créditos
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

    // TEMPORAL: eliminar pedido forzado solo para usuario "admin"
    function delpedidoAdmin(Request $req)
    {
        if (session('usuario') !== 'admin') {
            return response()->json(['msj' => 'Sin permisos', 'estado' => false], 403);
        }
        $id = $req->id;
        if (!$id) {
            return response()->json(['msj' => 'Falta el id', 'estado' => false], 400);
        }
        return $this->delPedidoFun($id, 'admin-force');
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

                $producto = inventario::select(["cantidad"])->where('id', $id_producto)->lockForUpdate()->first();
                
                if ($pedido->estado==0) {
                
                    if($item->delete()){
                        
                        $ctSeter = $producto->cantidad + $old_ct;
        
                        if ($condicion!=1) {
                            //Si no es garantia
                            (new InventarioController)->descontarInventario($id_producto,$ctSeter, $producto->cantidad, $pedido_id, "ELI.VENTA");
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
    /** TTL en segundos para cache de pedidos procesados (24 horas) */
    const CACHE_PEDIDO_PROCESADO_TTL = 86400;
    /** Solo cachear pedidos creados en los últimos N días (evita llenar cache con cientos de pedidos viejos) */
    const CACHE_PEDIDO_PROCESADO_DIAS_RECIENTES = 7;

    public function getPedidoFun($id_pedido, $filterMetodoPagoToggle = "todos", $cop = 1, $bs = 1, $factor = 1, $clean = false)
    {
        $id_pedido = (int) $id_pedido;
        $cacheKey = 'pedido_procesado_' . $id_pedido . '_' . $cop . '_' . $bs . '_' . $factor . '_' . ($clean ? '1' : '0');

        // Solo cachear pedidos procesados (estado != 0) y recientes: consulta ligera
        $row = pedidos::where('id', $id_pedido)->select('estado', 'created_at')->first();
        if ($row && (int) $row->estado !== 0) {
            $limiteReciente = now()->subDays(self::CACHE_PEDIDO_PROCESADO_DIAS_RECIENTES);
            if ($row->created_at && $row->created_at >= $limiteReciente) {
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        }

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
        
        $result = $clean ? $pedido->makeHidden('items') : $pedido;

        // Guardar en cache solo si está procesado y es reciente (evita saturar cache)
        if ((int) $pedido->estado !== 0) {
            $limiteReciente = now()->subDays(self::CACHE_PEDIDO_PROCESADO_DIAS_RECIENTES);
            if ($pedido->created_at && $pedido->created_at >= $limiteReciente) {
                Cache::put($cacheKey, $result, self::CACHE_PEDIDO_PROCESADO_TTL);
            }
        }

        return $result;
    }
    public function getPedido(Request $req, $factor = 1)
    {
        $cop = $this->get_moneda()["cop"];
        $bs = $this->get_moneda()["bs"];

        $id = $req->id;
        
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

    // ==================== FUNCIONES AUXILIARES PARA CERRARFUN ====================
    
    /**
     * Extraer puntos adicionales del request
     */
    private function extraerPuntosAdicionales($request) {
        if (empty($request)) return [];
        
        $data = is_array($request) ? $request : (array) $request;
        return isset($data['puntos']) && is_array($data['puntos']) ? $data['puntos'] : $data;
    }

    /**
     * Extraer lotes pinpad del request
     */
    private function extraerLotesPinpadRequest($request) {
        if (empty($request)) return null;
        
        if (is_array($request) && isset($request['lotes_pinpad'])) {
            return $request['lotes_pinpad'];
        }
        if (is_object($request) && isset($request->lotes_pinpad)) {
            return $request->lotes_pinpad;
        }
        
        return null;
    }

    /**
     * Determinar moneda de un pago basándose en el campo moneda o ratio
     */
    private function determinarMonedaPago($pago) {
        $moneda_raw = strtolower(trim($pago->moneda ?? ''));
        
        if (in_array($moneda_raw, ['dolar', 'usd', 'dolares'])) return 'USD';
        if (in_array($moneda_raw, ['bs', 'bolivar', 'bolivares'])) return 'BS';
        if (in_array($moneda_raw, ['cop', 'peso', 'pesos'])) return 'COP';
        
        // Fallback usando ratio monto_original / monto
        $monto = floatval($pago->monto);
        $monto_original = floatval($pago->monto_original ?? 0);
        
        if ($monto_original == 0 || abs($monto_original - $monto) < 0.01) return 'USD';
        
        if ($monto > 0) {
            $ratio = $monto_original / $monto;
            if ($ratio >= 30 && $ratio <= 50) return 'BS';
            if ($ratio >= 3000 && $ratio <= 5000) return 'COP';
        }
        
        return 'USD';
    }

    // ==================== FIN FUNCIONES AUXILIARES ====================

    /**
     * Función limpia para calcular el cierre del día
     * 
     * @param string $fecha Fecha del cierre
     * @param array $datosUsuario Datos originales ingresados por el usuario
     * @param array $opciones Opciones de configuración
     * @return array|JsonResponse Resultado del cálculo del cierre
     */
    public function cerrarFun($fecha, $datosUsuario = [], $opciones = [])
    {
        \Log::info('=== CERRARFUN - INICIO ===');
        \Log::info('CERRARFUN - Fecha: ' . $fecha);
        \Log::info('CERRARFUN - Datos recibidos: ' . json_encode($datosUsuario, JSON_PRETTY_PRINT));
        \Log::info('CERRARFUN - Opciones recibidas: ' . json_encode($opciones, JSON_PRETTY_PRINT));
        
        // Extraer datos del usuario
        $caja_usd = floatval($datosUsuario['caja_usd'] ?? 0);
        $caja_bs = floatval($datosUsuario['caja_bs'] ?? 0);
        $caja_cop = floatval($datosUsuario['caja_cop'] ?? 0);
        $total_biopago = floatval($datosUsuario['total_biopago'] ?? 0);
        $dejar_usd = floatval($datosUsuario['dejar_usd'] ?? 0);
        $dejar_bs = floatval($datosUsuario['dejar_bs'] ?? 0);
        $dejar_cop = floatval($datosUsuario['dejar_cop'] ?? 0);
        $puntos_adicionales_request = $datosUsuario['puntos_adicionales'] ?? [];
        
        \Log::info('CERRARFUN - Datos extraídos:');
        \Log::info('CERRARFUN - caja_usd: ' . $caja_usd);
        \Log::info('CERRARFUN - caja_bs: ' . $caja_bs);
        \Log::info('CERRARFUN - caja_cop: ' . $caja_cop);
        \Log::info('CERRARFUN - total_biopago: ' . $total_biopago);
        \Log::info('CERRARFUN - dejar_usd: ' . $dejar_usd);
        \Log::info('CERRARFUN - dejar_bs: ' . $dejar_bs);
        \Log::info('CERRARFUN - dejar_cop: ' . $dejar_cop);
        \Log::info('CERRARFUN - puntos_adicionales: ' . json_encode($puntos_adicionales_request, JSON_PRETTY_PRINT));
        
        // total_punto se calcula automáticamente, NO viene del usuario
        
        // Extraer opciones
        $grafica = $opciones['grafica'] ?? false;
        $totalizarcierre = $opciones['totalizarcierre'] ?? false;
        $check_pendiente = $opciones['check_pendiente'] ?? true;
        $usuario = $opciones['usuario'] ?? null;
        
        \Log::info('CERRARFUN - Opciones extraídas:');
        \Log::info('CERRARFUN - grafica: ' . ($grafica ? 'true' : 'false'));
        \Log::info('CERRARFUN - totalizarcierre: ' . ($totalizarcierre ? 'true' : 'false'));
        \Log::info('CERRARFUN - check_pendiente: ' . ($check_pendiente ? 'true' : 'false'));
        \Log::info('CERRARFUN - usuario: ' . json_encode($usuario));
        
        // Preparar array de caja efectivo para compatibilidad con código legacy
        $caja_efectivo = [
            'caja_usd' => $caja_usd,
            'caja_bs' => $caja_bs,
            'caja_cop' => $caja_cop
        ];
        
        
        if (!$fecha) {
            return Response::json(["msj" => "Error: Fecha invalida", "estado" => false]);
        }

        if ($check_pendiente) {
            $pedido_pendientes_check = pedidos::where("estado", 0)->get();
            if (count($pedido_pendientes_check)) {
                $pendientes_info = $pedido_pendientes_check->map(function ($q) {
                    return [
                        'id' => $q->id,
                        'fecha_factura' => $q->fecha_factura,
                        'cliente' => $q->cliente->nombre ?? null,
                        'monto' => $q->monto ?? null,
                        'estado' => $q->estado,
                    ];
                });

                // Construir resumen de los pedidos pendientes
                $resumen_pedidos = $pedido_pendientes_check->map(function($q){
                    return "N° " . $q->id . " (Fecha: " . $q->fecha_factura . ")";
                })->implode(", ");
                
                return Response::json([
                    "msj" => "Error: Hay pedidos pendientes. (" . $resumen_pedidos . ")",
                    "detalle" => $pendientes_info,
                    "estado" => false
                ]);
            }
        }

        $id_vendedor = $usuario ? (is_array($usuario) ? $usuario : [$usuario]) : $this->selectUsersTotalizar($totalizarcierre,$fecha);
        
        \Log::info('CERRARFUN - ID Vendedores: ' . json_encode($id_vendedor));
        
        $ultimo_cierre = $this->ultimoCierre($fecha, $id_vendedor);

        $caja_inicial = 0;
        $caja_inicialpeso = 0;
        $caja_inicialbs = 0;
        if ($ultimo_cierre) {
            // Saldo inicial en dólares solamente (sin convertir los otros)
            $caja_inicial = round($ultimo_cierre->sum("dejar_dolar"), 3);
            // Saldo inicial en pesos (COP)
            $caja_inicialpeso = round($ultimo_cierre->sum("dejar_peso"), 3);
            // Saldo inicial en bolívares (BS)
            $caja_inicialbs = round($ultimo_cierre->sum("dejar_bss"), 3);
        }
        
        \Log::info('CERRARFUN - Caja Inicial:');
        \Log::info('CERRARFUN - caja_inicial (USD): ' . $caja_inicial);
        \Log::info('CERRARFUN - caja_inicialpeso (COP): ' . $caja_inicialpeso);
        \Log::info('CERRARFUN - caja_inicialbs (BS): ' . $caja_inicialbs);
        
        // ========== CARGAR PEDIDOS CON TODAS SUS RELACIONES (EAGER LOADING) ==========
        // Esto evita N+1 queries y mejora el rendimiento significativamente
        $pedidos_collection = pedidos::where("fecha_factura", "LIKE", $fecha . "%")
            ->where("estado", 1)
            ->whereIn("id_vendedor", $id_vendedor)
            ->with([
                'items.producto',
                'cliente',
                'pagos', // Traer todos los pagos
                'referencias' // Traer referencias de pagos (para transferencias)
            ])
            ->get();
        
        \Log::info('CERRARFUN - Pedidos encontrados: ' . $pedidos_collection->count());
        \Log::info('CERRARFUN - IDs de pedidos: ' . $pedidos_collection->pluck('id')->toJson());
        
        // Cargar TODOS los cierres del día con sus puntos UNA SOLA VEZ (optimización)
        $cierres_del_dia = cierres::where("fecha", $fecha)
            ->whereIn("id_usuario", $id_vendedor)
            ->with(['metodosPago' => function($query) {
                $query->where('tipo_pago', 2); // Solo débitos (lotes)
            }])
            ->get();
        
        \Log::info('CERRARFUN - Cierres del día encontrados: ' . $cierres_del_dia->count());
        foreach ($cierres_del_dia as $cierre_dia) {
            \Log::info('CERRARFUN - Cierre ID: ' . $cierre_dia->id . ', Usuario: ' . $cierre_dia->id_usuario . ', Tipo: ' . $cierre_dia->tipo_cierre);
        }
        
        // Obtener lotes de débito de CierresMetodosPago
        $puntosAdicional = $cierres_del_dia->pluck('metodosPago')->flatten();
        
        \Log::info('CERRARFUN - Puntos adicionales desde BD: ' . $puntosAdicional->count());
        
        // Filtrar solo cierres tipo cajero (tipo_cierre = 0) para biopagos
        $cierres_tipo_cajero = $cierres_del_dia->where('tipo_cierre', 0);
        
        \Log::info('CERRARFUN - Cierres tipo cajero: ' . $cierres_tipo_cajero->count());
        

        /////Montos de ganancias
        //Var precio
        //Var precio_base
        //Var desc_total
        //Var ganancia
        //Var porcentaje

        // ========== EXTRAER DATOS YA CARGADOS CON EAGER LOADING ==========
        
        // Extraer todos los pagos de los pedidos (ya cargados, sin query adicional)
        $todos_pagos = $pedidos_collection->pluck('pagos')->flatten()->sortByDesc('id');
        
        \Log::info('CERRARFUN - Total pagos encontrados: ' . $todos_pagos->count());
        \Log::info('CERRARFUN - Pagos por tipo:');
        \Log::info('CERRARFUN - Tipo 1 (Transferencia): ' . $todos_pagos->where('tipo', 1)->count() . ', Suma: ' . $todos_pagos->where('tipo', 1)->sum('monto'));
        \Log::info('CERRARFUN - Tipo 2 (Débito): ' . $todos_pagos->where('tipo', 2)->count() . ', Suma: ' . $todos_pagos->where('tipo', 2)->sum('monto'));
        \Log::info('CERRARFUN - Tipo 3 (Efectivo): ' . $todos_pagos->where('tipo', 3)->count() . ', Suma: ' . $todos_pagos->where('tipo', 3)->sum('monto'));
        \Log::info('CERRARFUN - Tipo 4 (Crédito): ' . $todos_pagos->where('tipo', 4)->count() . ', Suma: ' . $todos_pagos->where('tipo', 4)->sum('monto'));
        \Log::info('CERRARFUN - Tipo 5 (Biopago): ' . $todos_pagos->where('tipo', 5)->count() . ', Suma: ' . $todos_pagos->where('tipo', 5)->sum('monto'));
        
        // Usar items ya cargados con eager loading (sin query adicional)
        // ==========================================
        // PASO 1: PROCESAR ITEMS Y CLASIFICARLOS
        // ==========================================
        $items_procesados = $pedidos_collection->pluck('items')->flatten()->map(function ($item) {
            // Diferenciar entre items de productos y abonos
            if (isset($item->producto)) {
                // Item de producto: calcular montos con y sin descuento
                $factor_descuento = 1 - ($item->descuento / 100);
                
                // Usar precio_unitario del item si existe, sino usar precio actual del producto
                $precio_unitario = $item->precio_unitario ?? $item->producto->precio;
                
                // COSTO: No se ve afectado por descuentos (es lo que pagaste al proveedor)
                $item->costo_bruto = $item->producto->precio_base * $item->cantidad;
                $item->costo_neto = $item->costo_bruto;  // Costo siempre igual, sin descuento
                
                // VENTA: Sí se ve afectada por descuentos (es lo que cobra al cliente)
                // Usar precio_unitario en lugar de producto->precio
                $item->venta_bruta = $precio_unitario * $item->cantidad;
                $item->venta_neta = $item->venta_bruta * $factor_descuento;
                
                $item->es_abono = false;
            } else {
                // Item sin producto = abono a crédito
                $item->costo_bruto = 0;
                $item->venta_bruta = 0;
                $item->costo_neto = 0;
                $item->venta_neta = 0;
                $item->es_abono = true;
            }
            return $item;
        });
        
        // ==========================================
        // PASO 2: IDENTIFICAR PEDIDOS A CRÉDITO
        // ==========================================
        // Un pedido es a crédito si:
        // 1. Tiene pagos tipo 4 (crédito), O
        // 2. Es un pedido export
        
        $pedidos_export_ids = $pedidos_collection->where('export', 1)->pluck('id');
        $pedidos_con_pago_credito_ids = $todos_pagos->where('tipo', 4)->pluck('id_pedido')->unique();
        $pedidos_credito_ids = $pedidos_export_ids->merge($pedidos_con_pago_credito_ids)->unique();
        
        // ==========================================
        // PASO 3: SEPARAR PRODUCTOS COBRADOS VS CRÉDITO
        // ==========================================
        $productos = $items_procesados->where('es_abono', false);
        
        // Productos de ventas COBRADAS (excluye créditos)
        $productos_cobrados = $productos->filter(function($item) use ($pedidos_credito_ids) {
            return !$pedidos_credito_ids->contains($item->id_pedido);
        });
        
        // Productos de ventas A CRÉDITO (no generan ganancia hasta cobrarse)
        $productos_credito = $productos->filter(function($item) use ($pedidos_credito_ids) {
            return $pedidos_credito_ids->contains($item->id_pedido);
        });
        
        // ==========================================
        // PASO 4: SUMAR TOTALES DE PRODUCTOS COBRADOS
        // ==========================================
        // COSTOS: No cambian con descuentos (es lo que pagaste al proveedor)
        $costo_productos_bruto = $productos_cobrados->sum('costo_bruto');
        $costo_productos_neto = $productos_cobrados->sum('costo_neto');  // Igual que bruto
        
        // VENTAS: Sí cambian con descuentos
        $venta_productos_bruto = $productos_cobrados->sum('venta_bruta');  // Sin descuentos
        $venta_productos_neto = $productos_cobrados->sum('venta_neta');     // Con descuentos aplicados
        
        // ==========================================
        // PASO 5: CALCULAR ABONOS A CRÉDITO
        // ==========================================
        $monto_abonos = $items_procesados->where('es_abono', true)->sum('monto');
        
        // ==========================================
        // PASO 6: CALCULAR CRÉDITOS (VENTAS NO COBRADAS)
        // ==========================================
        // Créditos = monto de productos vendidos a crédito
        $monto_export = $productos_credito->filter(function($item) use ($pedidos_export_ids) {
            return $pedidos_export_ids->contains($item->id_pedido);
        })->sum('monto');
        
        $monto_creditos_productos = $productos_credito->sum('venta_neta');
        $monto_creditos = $todos_pagos->where('tipo', 4)->sum('monto') + $monto_creditos_productos;
        
        // ==========================================
        // PASO 7: CALCULAR MARGEN DE GANANCIA (Solo productos cobrados)
        // ==========================================
        // Margen = (Venta - Costo) / Costo
        // Calculado solo con productos COBRADOS, excluye créditos
        if ($costo_productos_neto == 0) {
            $margen_porcentaje = 0;
            $factor_margen = 1.0; // Sin margen: venta = costo
        } else {
            $ganancia_productos_cobrados = $venta_productos_neto - $costo_productos_neto;
            $margen_porcentaje = round(($ganancia_productos_cobrados * 100) / $costo_productos_neto, 2);
            $factor_margen = 1 + ($margen_porcentaje / 100);
        }
        
        // Proteger contra factor_margen cero o negativo (puede pasar si margen_porcentaje <= -100)
        if ($factor_margen <= 0) {
            $factor_margen = 1.0; // Fallback seguro
        }
        
        // ==========================================
        // PASO 8: CALCULAR COSTO BASE DE ABONOS
        // ==========================================
        // Los ABONOS sí generan ganancia (son cobros efectivos de créditos pasados)
        // Aplicar margen inverso para obtener el costo base: costo = venta / factor_margen
        $costo_abonos = $factor_margen > 0 ? round($monto_abonos / $factor_margen, 2) : round($monto_abonos, 2);
        
        // Los CRÉDITOS NO generan ganancia hasta que se cobren
        // Se calculan solo para reporte informativo
        $costo_creditos = $factor_margen > 0 ? round($monto_creditos / $factor_margen, 2) : round($monto_creditos, 2);
        
        // ==========================================
        // PASO 9: CALCULAR TOTALES FINALES DEL CIERRE
        // ==========================================
        
        // --- GANANCIA REAL (Solo ventas cobradas) ---
        // Incluye: productos cobrados + abonos
        // Excluye: productos vendidos a crédito (no generan ganancia hasta cobrarse)
        $costo_cobrado = $costo_productos_neto + $costo_abonos;
        $venta_cobrada = $venta_productos_neto + $monto_abonos;
        $ganancia_neta = $venta_cobrada - $costo_cobrado;
        
        // --- TOTALES PARA REPORTE (valores del movimiento del día) ---
        // COSTOS: Siempre iguales (no se afectan por descuentos)
        $costo_total_bruto = $costo_productos_bruto + $costo_abonos;
        $costo_total_neto = $costo_productos_neto + $costo_abonos;  // Igual que bruto
        
        // VENTAS: Difieren por descuentos aplicados
        $venta_total_bruto = $venta_productos_bruto + $monto_abonos;  // Sin descuentos
        $venta_total_neto = $venta_productos_neto + $monto_abonos;     // Con descuentos (VALOR REAL)
        
        // ==========================================
        // PASO 8: MAPEAR A VARIABLES LEGACY (compatibilidad)
        // ==========================================
        // Mantener nombres originales para no romper código downstream
        $inv = $items_procesados->map(function($item) {
            // Mapear a estructura legacy
            $item->base_total = $item->costo_neto;
            $item->venta_total = $item->venta_neta;
            $item->sin_base_total = $item->costo_bruto;
            $item->sin_venta_total = $item->venta_bruta;
            $item->monto_abono = $item->es_abono ? $item->monto : 0;
            return $item;
        });
        
        // Variables legacy para compatibilidad con código downstream
        $base_total = $costo_productos_neto;
        $venta_total = $venta_productos_neto;
        $sin_base_total = $costo_productos_bruto;
        $sin_venta_total = $venta_productos_bruto;
        $monto_abono = $monto_abonos;
        $total_credito = $monto_creditos;
        $total_export = $monto_export;
        
        $ganancia_bruta = $venta_productos_neto - $costo_productos_neto;
        $porcentaje = $margen_porcentaje;
        $divisor = $factor_margen;
        
        $base_credito = $costo_creditos;
        $venta_credito = $monto_creditos;
        $base_abono = $costo_abonos;
        $venta_abono = $monto_abonos;
        
        // Variables legacy para reportes
        $sin_precio_base = $costo_total_bruto;  // Costo bruto (sin descuentos) - informativo
        $sin_precio = $venta_total_bruto;        // Venta bruta (sin descuentos) - informativo
        
        $precio_base = $costo_total_neto;        // Costo real (igual que bruto, costos no tienen descuentos)
        $precio = $venta_total_bruto;            // Venta SIN descuentos aplicados
        $desc_total = $venta_total_neto;         // Venta CON descuentos aplicados (VALOR REAL)
        $ganancia = $ganancia_neta;              // Ganancia real del día


        /////End Montos de ganancias

        
        // Reutilizar $cierres_del_dia en lugar de hacer otra consulta
        $tipo_accion = $cierres_del_dia->where('id_usuario', session("id_usuario"))->isNotEmpty() ? "editar" : "guardar";

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


        ///Abonos del Dia
        // Usar pedidos ya cargados en lugar de hacer otra consulta
        $abonosdeldia = 0;
        $pedidos_abonos = $pedidos_collection
            ->filter(function($pedido) {
                // Filtrar pedidos que tienen abonos (cuenta = 0)
                return $pedido->pagos->where("cuenta", 0)->where("monto", "<>", 0)->isNotEmpty();
            })
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

            "total_caja" => 0,
            "total_punto" => 0,
            "total_biopago" => 0,

            "estado_efec" => 0,
            "msj_efec" => "",

            "estado_punto" => 0,
            "msj_punto" => "",
            //Montos de ganancias
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

            "tipo_accion" => $tipo_accion,

        ];
        
        // ========== PROCESAR PAGOS YA CARGADOS ==========
        // $todos_pagos ya fue definido arriba en la línea ~1417
        
        $numventas_arr = [];
        $suma_por_tipo = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        
        \Log::info('CERRARFUN - Procesando pagos individuales:');
        $todos_pagos->map(function ($q) use (&$arr_pagos, &$numventas_arr, &$suma_por_tipo) {
            if (array_key_exists($q->tipo, $arr_pagos)) {
                $arr_pagos[$q->tipo] += $q->monto;
            } else {
                $arr_pagos[$q->tipo] = $q->monto;
            }
            
            if (isset($suma_por_tipo[$q->tipo])) {
                $suma_por_tipo[$q->tipo] += $q->monto;
            }
            
            if ($q->tipo != 4) {  // Solo excluir créditos (tipo 4)
                $hora = date("h:i", strtotime($q->updated_at));
                if (!array_key_exists($q->id_pedido, $numventas_arr)) {
                    $numventas_arr[$q->id_pedido] = ["hora" => $hora, "monto" => $q->monto, "id_pedido" => $q->id_pedido];
                } else {
                    $numventas_arr[$q->id_pedido]["monto"] = $numventas_arr[$q->id_pedido]["monto"] + $q->monto;
                }
                $arr_pagos["total"] += $q->monto;
            }
        });
        
        \Log::info('CERRARFUN - Suma de pagos por tipo (después de procesar):');
        \Log::info('CERRARFUN - Tipo 1 (Transferencia): ' . $suma_por_tipo[1] . ' USD, arr_pagos[1]=' . ($arr_pagos[1] ?? 0));
        \Log::info('CERRARFUN - Tipo 2 (Débito): ' . $suma_por_tipo[2] . ' USD, arr_pagos[2]=' . ($arr_pagos[2] ?? 0));
        \Log::info('CERRARFUN - Tipo 3 (Efectivo): ' . $suma_por_tipo[3] . ' USD, arr_pagos[3]=' . ($arr_pagos[3] ?? 0));
        \Log::info('CERRARFUN - Tipo 4 (Crédito): ' . $suma_por_tipo[4] . ' USD, arr_pagos[4]=' . ($arr_pagos[4] ?? 0));
        \Log::info('CERRARFUN - Tipo 5 (Biopago): ' . $suma_por_tipo[5] . ' USD, arr_pagos[5]=' . ($arr_pagos[5] ?? 0));
        \Log::info('CERRARFUN - Total (sin créditos): ' . ($arr_pagos["total"] ?? 0));
            
        $arr_pagos["numventas"] = count($numventas_arr);
        $arr_pagos["ventas"] = array_values($numventas_arr);
        if ($grafica) {
            $arr_pagos["grafica"] = array_values($numventas_arr);
        }
        
        // Los cuadres y diferencias se calculan en el cuadre detallado más adelante
        // (se eliminó el bloque legacy porque total_punto ahora se calcula automáticamente) 

        $arr_pagos["transferencia_digital"] = $arr_pagos[1];
        $arr_pagos["debito_digital"] = $arr_pagos[2];
        $arr_pagos["efectivo_digital"] = $arr_pagos[3];
        $arr_pagos["biopago_digital"] = $arr_pagos[5];
        
        \Log::info('CERRARFUN - Montos digitales calculados:');
        \Log::info('CERRARFUN - transferencia_digital: ' . $arr_pagos["transferencia_digital"]);
        \Log::info('CERRARFUN - debito_digital: ' . $arr_pagos["debito_digital"]);
        \Log::info('CERRARFUN - efectivo_digital: ' . $arr_pagos["efectivo_digital"]);
        \Log::info('CERRARFUN - biopago_digital: ' . $arr_pagos["biopago_digital"]);
        
        // Las variables $dejar_usd, $dejar_cop, $dejar_bs ya están definidas al inicio de la función (líneas 1307-1309)
        
        // ========== PROCESAMIENTO DE OTROS PUNTOS (DÉBITO/CRÉDITO NO-PINPAD) ==========
        // Extraer puntos adicionales UNA VEZ y reutilizar para lotes y cálculos
        $puntosFromRequest = $this->extraerPuntosAdicionales($puntos_adicionales_request);
        
        \Log::info('CERRARFUN - Puntos desde request extraídos: ' . count($puntosFromRequest));
        \Log::info('CERRARFUN - Puntos desde request: ' . json_encode($puntosFromRequest, JSON_PRETTY_PRINT));
        
        $lotes = [];
        $biopagos = [];
        $puntos_otros = []; // Consolidado para reutilizar después
        
        if (!empty($puntosFromRequest)) {
            // Usar datos del request (cálculo en tiempo real)
            foreach ($puntosFromRequest as $punto) {
                // CORRECCIÓN: Si el punto es un array anidado (array de arrays), aplanarlo
                if (is_array($punto) && isset($punto[0]) && is_array($punto[0])) {
                    // Es un array anidado, procesar cada elemento del array interno
                    foreach ($punto as $punto_individual) {
                        $categoria = is_array($punto_individual) ? ($punto_individual['categoria'] ?? null) : ($punto_individual->categoria ?? null);
                        
                        // Excluir pinpad (ya se procesó aparte)
                        if ($categoria == 'pinpad') continue;
                        
                        // Guardar para cálculos posteriores
                        $puntos_otros[] = $punto_individual;
                        
                        // Crear entrada en lotes
                        $lotes[] = [
                            "monto" => floatval(is_array($punto_individual) ? ($punto_individual['monto_real'] ?? $punto_individual['monto'] ?? 0) : ($punto_individual->monto_real ?? $punto_individual->monto ?? 0)),
                            "lote" => is_array($punto_individual) ? ($punto_individual['descripcion'] ?? '') : ($punto_individual->descripcion ?? ''),
                            "banco" => is_array($punto_individual) ? ($punto_individual['banco'] ?? '') : ($punto_individual->banco ?? ''),
                        ];
                    }
                } else {
                    // Punto normal (objeto o array simple)
                    $categoria = is_array($punto) ? ($punto['categoria'] ?? null) : ($punto->categoria ?? null);
                    
                    // Excluir pinpad (ya se procesó aparte)
                    if ($categoria == 'pinpad') continue;
                    
                    // Guardar para cálculos posteriores
                    $puntos_otros[] = $punto;
                    
                    // Crear entrada en lotes
                    $lotes[] = [
                        "monto" => floatval(is_array($punto) ? ($punto['monto_real'] ?? $punto['monto'] ?? 0) : ($punto->monto_real ?? $punto->monto ?? 0)),
                        "lote" => is_array($punto) ? ($punto['descripcion'] ?? '') : ($punto->descripcion ?? ''),
                        "banco" => is_array($punto) ? ($punto['banco'] ?? '') : ($punto->banco ?? ''),
                    ];
                }
            }
        } else {
            // Usar datos de la BD (cierres guardados - desde CierresMetodosPago)
            // IMPORTANTE: Cuando es consolidado, hay MÚLTIPLES registros (uno por cada caja)
            // Necesitamos obtener TODOS los registros de 'otros_puntos' de TODAS las cajas
            $registros_otros = $cierres_del_dia->pluck('metodosPago')
                ->flatten()
                ->where('subtipo', 'otros_puntos');
            
            \Log::info('OTROS PUNTOS - Consolidado: Encontrados ' . $registros_otros->count() . ' registros de otras cajas');
            
            // Procesar TODOS los registros de todas las cajas
            foreach ($registros_otros as $registro_otros) {
                $metadatos = is_string($registro_otros->metadatos) ? json_decode($registro_otros->metadatos, true) : $registro_otros->metadatos;
                $puntos_desde_bd = $metadatos['puntos'] ?? [];
                
                \Log::info('OTROS PUNTOS - Procesando registro ID: ' . $registro_otros->id . ', Cierre ID: ' . $registro_otros->id_cierre . ', Puntos: ' . count($puntos_desde_bd));
                
                // Procesar cada punto individual desde el JSON
                foreach ($puntos_desde_bd as $punto) {
                    // Guardar para cálculos posteriores
                    $puntos_otros[] = $punto;
                    
                    $lotes[] = [
                        "monto" => floatval($punto['monto_real'] ?? $punto['monto'] ?? 0),
                        "lote" => $punto['descripcion'] ?? '',
                        "banco" => $punto['banco'] ?? '',
                    ];
                    
                    \Log::info('OTROS PUNTOS - Punto agregado: ' . ($punto['descripcion'] ?? 'N/A') . ', Monto: ' . ($punto['monto_real'] ?? $punto['monto'] ?? 0));
                }
            }
            
            \Log::info('OTROS PUNTOS - Total puntos consolidados: ' . count($puntos_otros) . ', Total lotes: ' . count($lotes));
        }
        
        // Cargar biopagos desde los cierres tipo cajero
        foreach ($cierres_tipo_cajero as $cierre) {
            if ($cierre->biopagoserial && $cierre->biopagoserialmontobs) {
                $biopagos[] = [
                    "monto" => $cierre->biopagoserialmontobs,
                    "serial" => $cierre->biopagoserial,
                ];
            }
        }


        // El cálculo de efectivo_guardado se realiza más adelante después de calcular los valores de efectivo por moneda

        $arr_pagos["lotes"] = $lotes;
        $arr_pagos["biopagos"] = $biopagos;

        // Generar estructura de pagos agrupados por tipo y moneda
        $pagos_por_moneda = [];
        // Reutilizar $todos_pagos en lugar de hacer otra consulta
        $pagos_raw = $todos_pagos->whereNotIn("tipo", [4]); // Excluir créditos

        \Log::info('CERRARFUN - Agrupando pagos por tipo y moneda:');
        \Log::info('CERRARFUN - Pagos a procesar (sin créditos): ' . $pagos_raw->count());
        
        $contador_por_tipo_moneda = [];
        
        foreach ($pagos_raw as $pago) {
            $tipo = $pago->tipo;
            
            // Determinar la moneda real del pago
            $moneda = ($tipo == 3) ? $this->determinarMonedaPago($pago) : strtoupper($pago->moneda ?? 'USD');
            
            // Inicializar contador
            $key_contador = $tipo . '_' . $moneda;
            if (!isset($contador_por_tipo_moneda[$key_contador])) {
                $contador_por_tipo_moneda[$key_contador] = ['count' => 0, 'suma_monto' => 0, 'suma_monto_original' => 0];
            }
            $contador_por_tipo_moneda[$key_contador]['count']++;
            $contador_por_tipo_moneda[$key_contador]['suma_monto'] += floatval($pago->monto);
            $contador_por_tipo_moneda[$key_contador]['suma_monto_original'] += floatval($pago->monto_original ?? $pago->monto);
            
            // Inicializar tipo si no existe
            if (!isset($pagos_por_moneda[$tipo])) {
                $pagos_por_moneda[$tipo] = [
                    'tipo' => $tipo,
                    'monedas' => []
                ];
            }
            
            // Inicializar moneda dentro del tipo si no existe
            if (!isset($pagos_por_moneda[$tipo]['monedas'][$moneda])) {
                $pagos_por_moneda[$tipo]['monedas'][$moneda] = [
                    'moneda' => $moneda,
                    'monto' => 0,
                    'monto_original' => 0,
                    'pagos' => []
                ];
            }
            
            // Sumar montos
            $pagos_por_moneda[$tipo]['monedas'][$moneda]['monto'] += floatval($pago->monto);
            $pagos_por_moneda[$tipo]['monedas'][$moneda]['monto_original'] += floatval($pago->monto_original ?? $pago->monto);
            
            // Agregar pago individual al array
            $pagos_por_moneda[$tipo]['monedas'][$moneda]['pagos'][] = [
                'id' => $pago->id,
                'id_pedido' => $pago->id_pedido,
                'monto' => floatval($pago->monto),
                'monto_original' => floatval($pago->monto_original ?? $pago->monto),
                'moneda' => $moneda,
                'referencia' => $pago->referencia,
                'created_at' => $pago->created_at,
                'categoria' => null,
                'banco' => null,
            ];
        }
        
        \Log::info('CERRARFUN - Resumen de pagos por tipo y moneda:');
        foreach ($contador_por_tipo_moneda as $key => $data) {
            list($tipo, $moneda) = explode('_', $key, 2);
            \Log::info('CERRARFUN - Tipo ' . $tipo . ' (' . $moneda . '): ' . $data['count'] . ' pagos, Suma monto=' . $data['suma_monto'] . ', Suma monto_original=' . $data['suma_monto_original']);
        }
        
        \Log::info('CERRARFUN - Estructura pagos_por_moneda:');
        foreach ($pagos_por_moneda as $tipo_key => $tipo_data) {
            \Log::info('CERRARFUN - Tipo ' . $tipo_key . ' tiene ' . count($tipo_data['monedas']) . ' monedas');
            foreach ($tipo_data['monedas'] as $moneda_key => $moneda_data) {
                \Log::info('CERRARFUN -   Moneda ' . $moneda_key . ': monto=' . $moneda_data['monto'] . ', monto_original=' . $moneda_data['monto_original'] . ', cantidad_pagos=' . count($moneda_data['pagos']));
            }
        }
        
        // Si hay transferencias, usar referencias ya cargadas con eager loading
        if (isset($pagos_por_moneda[1])) {
            // Usar referencias ya cargadas (sin query adicional)
            $referencias = $pedidos_collection->pluck('referencias')->flatten();
            
            // Agrupar por id_pedido para asignar a pagos
            $referencias_por_pedido = $referencias->groupBy('id_pedido');
            
            // Asignar categoria y banco a cada pago
            foreach ($pagos_por_moneda[1]['monedas'] as $moneda_key => &$moneda_data) {
                foreach ($moneda_data['pagos'] as &$pago_item) {
                    $id_pedido = $pago_item['id_pedido'];
                    
                    // Si hay referencias para este pedido
                    if (isset($referencias_por_pedido[$id_pedido]) && $referencias_por_pedido[$id_pedido]->isNotEmpty()) {
                        // Tomar la primera referencia para categoria y banco
                        $primera_ref = $referencias_por_pedido[$id_pedido]->first();
                        
                        $pago_item['categoria'] = $primera_ref->categoria;
                        $pago_item['banco'] = $primera_ref->banco;
                        $pago_item['referencia'] = $primera_ref->descripcion;
                        // Guardar el monto real de pagos_referencias
                        $pago_item['monto_referencia'] = floatval($primera_ref->monto);
                    }
                }
                unset($pago_item);
            }
            unset($moneda_data);
            
            // Crear estructura agrupada por categoria y banco para el frontend
            $transferencias_agrupadas = [];
            foreach ($referencias as $ref) {
                $categoria = $ref->categoria ?: 'Sin Categoría';
                $banco = $ref->banco ?: 'Sin Banco';
                
                if (!isset($transferencias_agrupadas[$categoria])) {
                    $transferencias_agrupadas[$categoria] = [];
                }
                
                if (!isset($transferencias_agrupadas[$categoria][$banco])) {
                    $transferencias_agrupadas[$categoria][$banco] = [
                        'cantidad' => 0,
                        'monto_total' => 0,
                        'transacciones' => []
                    ];
                }
                
                $transferencias_agrupadas[$categoria][$banco]['cantidad']++;
                $transferencias_agrupadas[$categoria][$banco]['monto_total'] += floatval($ref->monto);
                $transferencias_agrupadas[$categoria][$banco]['transacciones'][] = [
                    'id' => $ref->id,
                    'id_pedido' => $ref->id_pedido,
                    'descripcion' => $ref->descripcion,
                    'monto' => floatval($ref->monto),
                    'created_at' => $ref->created_at
                ];
            }
            
            $arr_pagos["transferencias_agrupadas"] = $transferencias_agrupadas;
        }

        // Convertir a array indexado para JSON
        $pagos_por_moneda_final = [];
        foreach ($pagos_por_moneda as $tipo_data) {
            $tipo_data['monedas'] = array_values($tipo_data['monedas']);
            $pagos_por_moneda_final[] = $tipo_data;
        }

        $arr_pagos["pagos_por_moneda"] = $pagos_por_moneda_final;

        // ========== PROCESAMIENTO DE PAGOS PINPAD ==========
        // Los pagos de pinpad son débitos (tipo 2) procesados a través del dispositivo POS
        // Se identifican por tener pos_terminal o pos_amount (monto en Bs * 100)
        // Se agrupan por terminal para generar lotes de cierre
        
        $lotes_pinpad = [];
        
        // Filtrar pagos de pinpad de la colección de todos los pagos
        $pagos_pinpad = $todos_pagos->filter(function($pago) {
            return $pago->tipo == 2 && ( // Débito
                (!empty($pago->pos_terminal)) || 
                (!empty($pago->pos_amount) && $pago->pos_amount != 0)
            );
        });

        // Agrupar por terminal (los pagos ya vienen únicos de la BD)
        $agrupados_por_terminal = [];
        
        foreach ($pagos_pinpad as $pago) {
            // Determinar terminal (manejar caso sin terminal)
            // Normalizar terminal: trim + convertir a string para evitar problemas de tipo
            $terminal = trim(strval($pago->pos_terminal ?? ''));
            
            if (empty($terminal) && !empty($pago->pos_amount)) {
                $terminal = 'SIN_TERMINAL_' . $pago->id;
            }
            if (empty($terminal)) continue; // Saltar si no hay terminal
            
            // Inicializar lote si no existe
            if (!isset($agrupados_por_terminal[$terminal])) {
                $agrupados_por_terminal[$terminal] = [
                    'terminal' => $terminal,
                    'monto_bs' => 0,
                    'monto_usd' => 0,
                    'cantidad_transacciones' => 0,
                    'transacciones' => [],
                    'banco' => null
                ];
            }
            
            // Calcular monto en Bs (viene multiplicado por 100)
            $monto_bs = floatval($pago->pos_amount ?? 0) / 100;
            
            // Acumular montos
            $agrupados_por_terminal[$terminal]['monto_bs'] += $monto_bs;
            $agrupados_por_terminal[$terminal]['monto_usd'] += floatval($pago->monto);
            $agrupados_por_terminal[$terminal]['cantidad_transacciones']++;
            
            // Agregar transacción
            $agrupados_por_terminal[$terminal]['transacciones'][] = [
                'id' => $pago->id,
                'id_pedido' => $pago->id_pedido,
                'monto_original' => floatval($pago->monto_original ?? 0),
                'monto' => floatval($pago->monto),
                'referencia' => $pago->referencia,
                'terminal' => $pago->pos_terminal,
                'estado' => $pago->pos_message,
                'responsecode' => $pago->pos_responsecode,
                'lote' => $pago->pos_lote,
                'pos_amount' => floatval($pago->pos_amount ?? 0),
                'created_at' => $pago->created_at,
                'updated_at' => $pago->updated_at
            ];
        }
        
        

        // Consolidar bancos de 2 fuentes: request (prioridad) y BD guardada
        $bancos_por_terminal = [];
        
        // 1. Cargar bancos desde BD (cierres guardados - desde CierresMetodosPago)
        // Ahora los lotes pinpad están consolidados en un solo registro con subtipo 'pinpad'
        $registro_pinpad = $cierres_del_dia->pluck('metodosPago')
            ->flatten()
            ->where('subtipo', 'pinpad')
            ->first();
        
        if ($registro_pinpad) {
            $metadatos = is_string($registro_pinpad->metadatos) ? json_decode($registro_pinpad->metadatos, true) : $registro_pinpad->metadatos;
            $lotes_pinpad_bd = $metadatos['lotes'] ?? [];
            
            // Extraer banco por terminal de cada lote
            foreach ($lotes_pinpad_bd as $lote) {
                $terminal = $lote['terminal'] ?? null;
                $banco_nombre = $lote['banco_nombre'] ?? ($lote['banco'] ?? null);
                
                if ($terminal && $banco_nombre) {
                    // Normalizar terminal: trim + convertir a string
                    $terminal_normalizado = trim(strval($terminal));
                    $bancos_por_terminal[$terminal_normalizado] = $banco_nombre;
                }
            }
        }
        
        // 2. Sobrescribir con bancos del request (tienen prioridad)
        $lotes_pinpad_request = $this->extraerLotesPinpadRequest($puntos_adicionales_request);
        
        if ($lotes_pinpad_request) {
            foreach ($lotes_pinpad_request as $lote_request) {
                $terminal = is_array($lote_request) ? ($lote_request['terminal'] ?? null) : ($lote_request->terminal ?? null);
                $banco = is_array($lote_request) ? ($lote_request['banco'] ?? null) : ($lote_request->banco ?? null);
                
                if ($terminal && $banco) {
                    // Normalizar terminal: trim + convertir a string
                    $terminal_normalizado = trim(strval($terminal));
                    $bancos_por_terminal[$terminal_normalizado] = $banco;
                }
            }
        }
        
        // 3. Asignar bancos a lotes agrupados
        foreach ($agrupados_por_terminal as $terminal => &$lote) {
            $lote['banco'] = $bancos_por_terminal[$terminal] ?? null;
        }
        unset($lote); // Limpiar referencia del foreach

        // Convertir a array indexado para JSON
        $lotes_pinpad = array_values($agrupados_por_terminal);
        
        $arr_pagos["lotes_pinpad"] = $lotes_pinpad;

        // Calcular cuadre de efectivo por moneda (Bolívares y Pesos)
        $caja_bs_entregado = isset($caja_efectivo["caja_bs"]) ? floatval($caja_efectivo["caja_bs"]) : 0;
        $caja_cop_entregado = isset($caja_efectivo["caja_cop"]) ? floatval($caja_efectivo["caja_cop"]) : 0;
        
        \Log::info('CERRARFUN - Efectivo entregado (real contado):');
        \Log::info('CERRARFUN - caja_usd: ' . $caja_usd);
        \Log::info('CERRARFUN - caja_bs_entregado: ' . $caja_bs_entregado);
        \Log::info('CERRARFUN - caja_cop_entregado: ' . $caja_cop_entregado);
        
        // Obtener efectivo digital en Bs y COP
        $efectivo_bs_digital = 0;
        $efectivo_cop_digital = 0;
        
        if (isset($pagos_por_moneda[3])) { // Tipo 3 = Efectivo
            \Log::info('CERRARFUN - Pagos efectivo por moneda encontrados: ' . count($pagos_por_moneda[3]['monedas']));
            foreach ($pagos_por_moneda[3]['monedas'] as $moneda_key => $moneda_data) {
                \Log::info('CERRARFUN - Efectivo moneda ' . $moneda_key . ': monto=' . $moneda_data['monto'] . ', monto_original=' . $moneda_data['monto_original']);
                if ($moneda_data['moneda'] == 'bs') {
                    $efectivo_bs_digital = $moneda_data['monto_original'];
                } elseif ($moneda_data['moneda'] == 'cop') {
                    $efectivo_cop_digital = $moneda_data['monto_original'];
                }
            }
        }
        
        \Log::info('CERRARFUN - Efectivo digital calculado:');
        \Log::info('CERRARFUN - efectivo_digital_usd: ' . $arr_pagos["efectivo_digital"]);
        \Log::info('CERRARFUN - efectivo_bs_digital: ' . $efectivo_bs_digital);
        \Log::info('CERRARFUN - efectivo_cop_digital: ' . $efectivo_cop_digital);
        
        // Calcular diferencias
        $diff_efectivo_bs = $caja_bs_entregado - $efectivo_bs_digital;
        $diff_efectivo_cop = $caja_cop_entregado - $efectivo_cop_digital;
        
        // Agregar al array de respuesta
        $arr_pagos["caja_bs_entregado"] = round($caja_bs_entregado, 2);
        $arr_pagos["caja_cop_entregado"] = round($caja_cop_entregado, 2);
        $arr_pagos["efectivo_bs_digital"] = round($efectivo_bs_digital, 2);
        $arr_pagos["efectivo_cop_digital"] = round($efectivo_cop_digital, 2);
        $arr_pagos["diff_efectivo_bs"] = round($diff_efectivo_bs, 2);
        $arr_pagos["diff_efectivo_cop"] = round($diff_efectivo_cop, 2);
        
        // Mensajes de cuadre para Bs y COP
        if ($caja_bs_entregado > 0) {
            if (abs($diff_efectivo_bs) <= 5) {
                $arr_pagos["msj_efectivo_bs"] = "Cuadrado Bs. Diferencia: " . $diff_efectivo_bs;
                $arr_pagos["estado_efectivo_bs"] = 1;
            } else {
                $arr_pagos["msj_efectivo_bs"] = ($diff_efectivo_bs > 0 ? "Sobran " : "Faltan ") . abs($diff_efectivo_bs) . " Bs";
                $arr_pagos["estado_efectivo_bs"] = 0;
            }
        }
        
        if ($caja_cop_entregado > 0) {
            if (abs($diff_efectivo_cop) <= 5) {
                $arr_pagos["msj_efectivo_cop"] = "Cuadrado COP. Diferencia: " . $diff_efectivo_cop;
                $arr_pagos["estado_efectivo_cop"] = 1;
            } else {
                $arr_pagos["msj_efectivo_cop"] = ($diff_efectivo_cop > 0 ? "Sobran " : "Faltan ") . abs($diff_efectivo_cop) . " COP";
                $arr_pagos["estado_efectivo_cop"] = 0;
            }
        }

        // ========== CUADRE DETALLADO POR TIPO Y MONEDA ==========
        \Log::info('=== CERRARFUN - CALCULANDO CUADRE DETALLADO ===');
        $cuadre_detallado = [];
        
        // Extraer montos digitales de efectivo por moneda desde pagos_por_moneda
        $efectivo_digital_usd = 0;
        $efectivo_digital_bs = 0;
        $efectivo_digital_cop = 0;
        
        foreach ($pagos_por_moneda as $tipo_key => $tipo_data) {
            if ($tipo_key == 3) { // Efectivo
                foreach ($tipo_data['monedas'] as $moneda_key => $moneda_data) {
                    if ($moneda_key == 'USD') {
                        $efectivo_digital_usd = floatval($moneda_data['monto']);
                    } elseif ($moneda_key == 'BS') {
                        $efectivo_digital_bs = floatval($moneda_data['monto_original']);
                    } elseif ($moneda_key == 'COP') {
                        $efectivo_digital_cop = floatval($moneda_data['monto_original']);
                    }
                }
            }
        }
        
        \Log::info('CERRARFUN - Efectivo digital extraído de pagos_por_moneda:');
        \Log::info('CERRARFUN - efectivo_digital_usd: ' . $efectivo_digital_usd);
        \Log::info('CERRARFUN - efectivo_digital_bs: ' . $efectivo_digital_bs);
        \Log::info('CERRARFUN - efectivo_digital_cop: ' . $efectivo_digital_cop);
        
        // 1. EFECTIVO - USD
        $efectivo_usd_real = isset($caja_efectivo["caja_usd"]) ? floatval($caja_efectivo["caja_usd"]) : 0;
        $efectivo_usd_inicial = floatval($caja_inicial);
        $efectivo_usd_digital = $efectivo_digital_usd;
        // Fórmula correcta: Real - Digital - Inicial
        $efectivo_usd_diferencia = $efectivo_usd_real - $efectivo_usd_digital - $efectivo_usd_inicial;
        
        \Log::info('CERRARFUN - Efectivo USD:');
        \Log::info('CERRARFUN - Real: ' . $efectivo_usd_real . ' (desde caja_efectivo["caja_usd"])');
        \Log::info('CERRARFUN - Digital: ' . $efectivo_usd_digital . ' (desde pagos_por_moneda)');
        \Log::info('CERRARFUN - Inicial: ' . $efectivo_usd_inicial . ' (desde ultimo_cierre->sum("dejar_dolar"))');
        \Log::info('CERRARFUN - Diferencia: ' . $efectivo_usd_diferencia . ' (Real - Digital - Inicial)');
        
        $cuadre_detallado['efectivo_usd'] = [
            'tipo' => 'efectivo',
            'moneda' => 'USD',
            'real' => round($efectivo_usd_real, 2),
            'inicial' => round($efectivo_usd_inicial, 2),
            'digital' => round($efectivo_usd_digital, 2),
            'diferencia' => round($efectivo_usd_diferencia, 2),
            'estado' => abs($efectivo_usd_diferencia) <= 5 ? 'cuadrado' : ($efectivo_usd_diferencia > 0 ? 'sobra' : 'falta'),
            'mensaje' => abs($efectivo_usd_diferencia) <= 5 ? 'CUADRADO' : ($efectivo_usd_diferencia > 0 ? 'SOBRAN' : 'FALTAN')
        ];
        
        // 2. EFECTIVO - BS
        $efectivo_bs_real = floatval($caja_bs_entregado);
        $efectivo_bs_inicial = floatval($caja_inicialbs);
        $efectivo_bs_digital_calc = $efectivo_digital_bs;
        // Fórmula correcta: Real - Digital - Inicial
        $efectivo_bs_diferencia = $efectivo_bs_real - $efectivo_bs_digital_calc - $efectivo_bs_inicial;
        
        \Log::info('CERRARFUN - Efectivo BS:');
        \Log::info('CERRARFUN - Real: ' . $efectivo_bs_real . ' (desde caja_bs_entregado)');
        \Log::info('CERRARFUN - Digital: ' . $efectivo_bs_digital_calc . ' (desde pagos_por_moneda)');
        \Log::info('CERRARFUN - Inicial: ' . $efectivo_bs_inicial . ' (desde ultimo_cierre->sum("dejar_bss"))');
        \Log::info('CERRARFUN - Diferencia: ' . $efectivo_bs_diferencia . ' (Real - Digital - Inicial)');
        
        $cuadre_detallado['efectivo_bs'] = [
            'tipo' => 'efectivo',
            'moneda' => 'BS',
            'real' => round($efectivo_bs_real, 2),
            'inicial' => round($efectivo_bs_inicial, 2),
            'digital' => round($efectivo_bs_digital_calc, 2),
            'diferencia' => round($efectivo_bs_diferencia, 2),
            'estado' => abs($efectivo_bs_diferencia) <= 5 ? 'cuadrado' : ($efectivo_bs_diferencia > 0 ? 'sobra' : 'falta'),
            'mensaje' => abs($efectivo_bs_diferencia) <= 5 ? 'CUADRADO' : ($efectivo_bs_diferencia > 0 ? 'SOBRAN' : 'FALTAN')
        ];
        
        // 3. EFECTIVO - COP
        $efectivo_cop_real = floatval($caja_cop_entregado);
        $efectivo_cop_inicial = floatval($caja_inicialpeso);
        $efectivo_cop_digital_calc = $efectivo_digital_cop;
        // Fórmula correcta: Real - Digital - Inicial
        $efectivo_cop_diferencia = $efectivo_cop_real - $efectivo_cop_digital_calc - $efectivo_cop_inicial;
        
        \Log::info('CERRARFUN - Efectivo COP:');
        \Log::info('CERRARFUN - Real: ' . $efectivo_cop_real . ' (desde caja_cop_entregado)');
        \Log::info('CERRARFUN - Digital: ' . $efectivo_cop_digital_calc . ' (desde pagos_por_moneda)');
        \Log::info('CERRARFUN - Inicial: ' . $efectivo_cop_inicial . ' (desde ultimo_cierre->sum("dejar_peso"))');
        \Log::info('CERRARFUN - Diferencia: ' . $efectivo_cop_diferencia . ' (Real - Digital - Inicial)');
        
        $cuadre_detallado['efectivo_cop'] = [
            'tipo' => 'efectivo',
            'moneda' => 'COP',
            'real' => round($efectivo_cop_real, 2),
            'inicial' => round($efectivo_cop_inicial, 2),
            'digital' => round($efectivo_cop_digital_calc, 2),
            'diferencia' => round($efectivo_cop_diferencia, 2),
            'estado' => abs($efectivo_cop_diferencia) <= 5 ? 'cuadrado' : ($efectivo_cop_diferencia > 0 ? 'sobra' : 'falta'),
            'mensaje' => abs($efectivo_cop_diferencia) <= 5 ? 'CUADRADO' : ($efectivo_cop_diferencia > 0 ? 'SOBRAN' : 'FALTAN')
        ];
        
        // Calcular efectivo guardado por moneda separado (sin convertir)
        // Fórmula correcta: Efectivo guardado = Efectivo disponible (real) - Lo que se deja en caja
        
        // USD: efectivo_usd_real (lo que hay físicamente) - dejar_usd (lo que se deja)
        $efectivo_guardado_usd_final = $efectivo_usd_real - floatval($dejar_usd);
        
        // BS: efectivo_bs_real - dejar_bs
        $efectivo_guardado_bs = $efectivo_bs_real - floatval($dejar_bs);
        
        // COP: efectivo_cop_real - dejar_cop
        $efectivo_guardado_cop = $efectivo_cop_real - floatval($dejar_cop);
        
        // Actualizar efectivo_guardado en USD (para compatibilidad)
        $arr_pagos["efectivo_guardado"] = round($efectivo_guardado_usd_final, 2);
        $arr_pagos["efectivo_guardado_usd"] = round($efectivo_guardado_usd_final, 2);
        $arr_pagos["efectivo_guardado_bs"] = round($efectivo_guardado_bs, 2);
        $arr_pagos["efectivo_guardado_cop"] = round($efectivo_guardado_cop, 2);
        
        // 4. DÉBITO - PINPAD (en Bs)
        // Los lotes pinpad ya vienen calculados correctamente de los pagos reales
        // No hay "digital" vs "real" aquí - es el mismo valor de las transacciones procesadas
        $debito_pinpad_total = 0;
        \Log::info('CERRARFUN - Lotes pinpad encontrados: ' . count($lotes_pinpad));
        foreach ($lotes_pinpad as $index => $lote) {
            $monto_lote = floatval($lote['monto_bs']);
            $debito_pinpad_total += $monto_lote;
            \Log::info('CERRARFUN - Lote pinpad #' . $index . ': terminal=' . ($lote['terminal'] ?? 'N/A') . ', monto_bs=' . $monto_lote . ', acumulado=' . $debito_pinpad_total);
        }
        
        \Log::info('CERRARFUN - Debito Pinpad Total: ' . $debito_pinpad_total . ' BS');
        
        // En pinpad, real = digital (son transacciones ya procesadas por el dispositivo)
        $cuadre_detallado['debito_pinpad'] = [
            'tipo' => 'debito',
            'subtipo' => 'pinpad',
            'moneda' => 'BS',
            'real' => round($debito_pinpad_total, 2),
            'inicial' => 0,
            'digital' => round($debito_pinpad_total, 2),
            'diferencia' => 0, // Siempre cuadrado porque es el mismo valor
            'estado' => 'cuadrado',
            'mensaje' => 'CUADRADO'
        ];
        
        // 5. DÉBITO - OTROS PUNTOS (en Bs)
        // Reutilizar $puntos_otros ya consolidados anteriormente
        // IMPORTANTE: "Otros Puntos" incluye TODOS los lotes (DEBITO y CREDITO)
        $debito_otros_real = 0;
        
        \Log::info('DEBITO OTROS - Iniciando cálculo. Total puntos_otros: ' . count($puntos_otros));
        \Log::info('DEBITO OTROS - totalizarcierre: ' . ($totalizarcierre ? 'true' : 'false'));
        
        foreach ($puntos_otros as $index => $punto) {
            $categoria = is_array($punto) ? ($punto['categoria'] ?? null) : ($punto->categoria ?? null);
            $monto = is_array($punto) ? ($punto['monto_real'] ?? $punto['monto'] ?? 0) : ($punto->monto_real ?? $punto->monto ?? 0);
            
            \Log::info('DEBITO OTROS - Punto #' . $index . ': Categoria=' . ($categoria ?? 'NULL') . ', Monto=' . $monto);
            
            // Sumar solo DEBITO y CREDITO (pinpad ya está excluido)
            if ($categoria == 'DEBITO' || $categoria == 'CREDITO') {
                $debito_otros_real += floatval($monto);
                \Log::info('DEBITO OTROS - Sumado. Acumulado: ' . $debito_otros_real);
            } else {
                \Log::info('DEBITO OTROS - Omitido (categoria no es DEBITO/CREDITO)');
            }
        }
        
        \Log::info('DEBITO OTROS - Total REAL calculado: ' . $debito_otros_real . ' Bs');
        
        // Calcular digital de otros puntos (débito tipo 2 que NO son pinpad)
        $debito_otros_digital = 0;
        // Reutilizar $todos_pagos en lugar de hacer otra consulta
        $pagos_debito_otros = $todos_pagos->filter(function($pago) {
            return $pago->tipo == 2 && (empty($pago->pos_terminal) || $pago->pos_terminal == "");
        });
        
        \Log::info('DEBITO OTROS - Total pagos digitales encontrados: ' . $pagos_debito_otros->count());
        
        foreach ($pagos_debito_otros as $pago) {
            $monto_pago = floatval($pago->monto_original ?? $pago->monto);
            $debito_otros_digital += $monto_pago;
            \Log::info('DEBITO OTROS - Pago digital ID: ' . $pago->id . ', Monto: ' . $monto_pago . ', Acumulado: ' . $debito_otros_digital);
        }
        
        \Log::info('DEBITO OTROS - Total DIGITAL calculado: ' . $debito_otros_digital . ' Bs');
        
        $debito_otros_diferencia = $debito_otros_real - $debito_otros_digital;
        
        \Log::info('DEBITO OTROS - Diferencia: ' . $debito_otros_diferencia . ' Bs');
        
        $cuadre_detallado['debito_otros'] = [
            'tipo' => 'debito',
            'subtipo' => 'otros_puntos',
            'moneda' => 'BS',
            'real' => round($debito_otros_real, 2),
            'inicial' => 0,
            'digital' => round($debito_otros_digital, 2),
            'diferencia' => round($debito_otros_diferencia, 2),
            'estado' => abs($debito_otros_diferencia) <= 5 ? 'cuadrado' : ($debito_otros_diferencia > 0 ? 'sobra' : 'falta'),
            'mensaje' => abs($debito_otros_diferencia) <= 5 ? 'CUADRADO' : ($debito_otros_diferencia > 0 ? 'SOBRAN' : 'FALTAN')
        ];
        
        \Log::info('DEBITO OTROS - Cuadre guardado: ' . json_encode($cuadre_detallado['debito_otros']));
        
        // CALCULAR TOTAL_PUNTO AUTOMÁTICAMENTE (suma de todos los lotes en Bs)
        $total_punto_real = $debito_pinpad_total + $debito_otros_real;
        $arr_pagos["total_punto"] = round($total_punto_real, 2);
        
        // 6. TRANSFERENCIA (en USD)
        // Las transferencias siempre cuadran porque son digitales (no hay "real" vs "digital")
        $transferencia_total = isset($arr_pagos[1]) ? floatval($arr_pagos[1]) : 0;
        
        \Log::info('CERRARFUN - Transferencia:');
        \Log::info('CERRARFUN - Total: ' . $transferencia_total . ' USD (desde arr_pagos[1] = suma de pagos tipo 1)');
        
        $cuadre_detallado['transferencia'] = [
            'tipo' => 'transferencia',
            'moneda' => 'USD',
            'real' => round($transferencia_total, 2),
            'inicial' => 0,
            'digital' => round($transferencia_total, 2),
            'diferencia' => 0, // Siempre cuadrado porque es el mismo valor
            'estado' => 'cuadrado',
            'mensaje' => 'CUADRADO'
        ];
        
        \Log::info('=== CERRARFUN - CUADRE DETALLADO FINAL ===');
        \Log::info('CERRARFUN - Cuadre detallado completo: ' . json_encode($cuadre_detallado, JSON_PRETTY_PRINT));
        \Log::info('CERRARFUN - Total punto calculado: ' . $total_punto_real . ' BS');
        \Log::info('=== CERRARFUN - FIN ===');
        
        $arr_pagos["cuadre_detallado"] = $cuadre_detallado;

        return $arr_pagos;
    }
    public function getCierres(Request $req)
    {

        $fechaGetCierre = $req->fechaGetCierre;
        $fechaGetCierre2 = $req->fechaGetCierre2;
        $tipoUsuarioCierre = $req->tipoUsuarioCierre;

        if (!$fechaGetCierre && !$fechaGetCierre2) {
            $cierres = cierres::with("usuario")
                ->when($tipoUsuarioCierre !== null && $tipoUsuarioCierre !== "", function ($q) use ($tipoUsuarioCierre) {
                    $q->where("tipo_cierre", $tipoUsuarioCierre);
                })
                ->orderBy("fecha", "desc");
        } else {
            $cierres = cierres::with("usuario")
                ->whereBetween("fecha", [$fechaGetCierre, $fechaGetCierre2])
                ->when($tipoUsuarioCierre !== null && $tipoUsuarioCierre !== "", function ($q) use ($tipoUsuarioCierre) {
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
    /**
     * Endpoint para calcular cierre del día
     * Recibe datos del usuario y los procesa con cerrarFun
     * NOTA: total_punto se calcula automáticamente sumando lotes
     */
    public function cerrar(Request $req)
    {
        return $this->cerrarFun(
            $this->today(),
            [
                'caja_usd' => $req->caja_usd ?? 0,
                'caja_bs' => $req->caja_bs ?? 0,
                'caja_cop' => $req->caja_cop ?? 0,
                'total_biopago' => $req->total_biopago ?? 0,
                'dejar_usd' => $req->dejar_usd ?? 0,
                'dejar_bs' => $req->dejar_bs ?? 0,
                'dejar_cop' => $req->dejar_cop ?? 0,
                'puntos_adicionales' => $req->dataPuntosAdicionales ?? []
            ],
            [
                'grafica' => false,
                'totalizarcierre' => filter_var($req->totalizarcierre, FILTER_VALIDATE_BOOLEAN),
                'check_pendiente' => true,
                'usuario' => null
            ]
        );
    }

    
    
    
    public function guardarCierre(Request $req)
    {
        try {

            //return $req->all();
            
            $id_usuario = session("id_usuario");
            $today = (new PedidosController)->today();

            $cop = $this->get_moneda()["cop"];
            $bs = $this->get_moneda()["bs"];

            $totalizarcierre = filter_var($req->totalizarcierre, FILTER_VALIDATE_BOOLEAN);
            $dataPuntosAdicionales = $req->dataPuntosAdicionales;
            $lotesPinpad = $req->lotesPinpad ?? [];

            $id_vendedor = $this->selectUsersTotalizar($totalizarcierre,$today);
            $tipo_cierre = $totalizarcierre ? 1 : 0;
            
            // ========== VALIDAR SI EXISTE CIERRE TIPO ADMINISTRADOR ==========
            // Si hay un cierre tipo 1 (administrador/totalizar), NO permitir guardar cierres de cajero
            if ($tipo_cierre == 0) { // Solo validar si es un cierre de cajero
                $cierre_admin_existente = cierres::where("fecha", $today)
                    ->where("tipo_cierre", 1) // Tipo 1 = Administrador/Totalizar
                    ->first();
                
                if ($cierre_admin_existente) {
                    return Response::json([
                        "msj" => "No se puede guardar el cierre. Ya existe un cierre de administrador para este día. Si desea editar este cierre, debe reversar los cierres.",
                        "estado" => false
                    ]);
                }
            }
            
            // ========== RECALCULAR TODO INTERNAMENTE DESDE DATOS ORIGINALES ==========
            // Preparar datos originales del usuario
            $dejar = [
                "dejar_bs" => $req->dejar_bs ?? 0,
                "dejar_usd" => $req->dejar_usd ?? 0,
                "dejar_cop" => $req->dejar_cop ?? 0
            ];
            
            $caja_efectivo = [
                "caja_usd" => $req->caja_usd ?? 0,
                "caja_bs" => $req->caja_bs ?? 0,
                "caja_cop" => $req->caja_cop ?? 0
            ];
            
            // Extraer puntos adicionales del request
            // El frontend puede enviar dataPuntosAdicionales como:
            // 1. Array plano de lotes (formato antiguo)
            // 2. Objeto con puntos y lotes_pinpad (formato nuevo pero anidado)
            // 3. Array de puntos + lotesPinpad separado (formato actual del frontend limpio)
            $puntos_adicionales_request = [];
            if (is_array($dataPuntosAdicionales)) {
                // Array plano - asumimos que son solo "otros puntos"
                $puntos_adicionales_request = [
                    "puntos" => $dataPuntosAdicionales,
                    "lotes_pinpad" => $lotesPinpad
                ];
            } else if (is_object($dataPuntosAdicionales)) {
                // Objeto anidado
                $puntos_adicionales_request = [
                    "puntos" => $dataPuntosAdicionales->puntos ?? [],
                    "lotes_pinpad" => $dataPuntosAdicionales->lotes_pinpad ?? $lotesPinpad
                ];
            } else {
                // Si dataPuntosAdicionales es null, solo usar lotesPinpad
                $puntos_adicionales_request = [
                    "puntos" => [],
                    "lotes_pinpad" => $lotesPinpad
                ];
            }
            
            // RECALCULAR TODO usando cerrarFun con datos originales
            // NOTA: total_punto se calcula automáticamente dentro de cerrarFun
            
            // Para consolidados, verificar qué cierres de cajeros existen
            if ($tipo_cierre == 1) {
                $cierres_cajeros_antes = cierres::where('fecha', $today)
                    ->where('tipo_cierre', 0)
                    ->get();
            }
            
            $resultado_cierre = $this->cerrarFun(
                $today,
                [
                    'caja_usd' => $req->caja_usd ?? 0,
                    'caja_bs' => $req->caja_bs ?? 0,
                    'caja_cop' => $req->caja_cop ?? 0,
                    'total_biopago' => $req->total_biopago ?? 0,
                    'dejar_usd' => $dejar['dejar_usd'] ?? 0,
                    'dejar_bs' => $dejar['dejar_bs'] ?? 0,
                    'dejar_cop' => $dejar['dejar_cop'] ?? 0,
                    'puntos_adicionales' => $puntos_adicionales_request
                ],
                [
                    'grafica' => false,
                    'totalizarcierre' => $totalizarcierre,
                    'check_pendiente' => true,
                    'usuario' => null
                ]
            );
            
            // Obtener los cálculos desde la respuesta JSON
            // cerrarFun retorna Response::json, necesitamos extraer el contenido
            $calculos = null;
            if ($resultado_cierre instanceof \Illuminate\Http\JsonResponse) {
                $calculos = $resultado_cierre->getData(true); // true para obtener como array
            } else {
                // Si es un array directo (no debería pasar, pero por seguridad)
                $calculos = is_array($resultado_cierre) ? $resultado_cierre : json_decode($resultado_cierre, true);
            }
            
            // Validar que los cálculos sean válidos
            if (!is_array($calculos) || (isset($calculos['estado']) && $calculos['estado'] === false)) {
                return Response::json($calculos ?? ["msj" => "Error al calcular el cierre", "estado" => false], 400);
            }
            
            // Extraer datos calculados para usar en guardado
            $cuadre_detallado = $calculos['cuadre_detallado'] ?? [];
            
            // Si es consolidado, verificar que el valor sea la suma de todas las cajas
            if ($tipo_cierre == 1) {
                $cierres_cajeros_para_verificar = cierres::where('fecha', $today)
                    ->where('tipo_cierre', 0)
                    ->get();
                
                $suma_verificacion = 0;
                foreach ($cierres_cajeros_para_verificar as $cierre_cajero_ver) {
                    $registro_otros_ver = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero_ver->id)
                        ->where('tipo_pago', 2)
                        ->where('subtipo', 'otros_puntos')
                        ->first();
                    
                    if ($registro_otros_ver) {
                        $suma_verificacion += floatval($registro_otros_ver->monto_real ?? 0);
                    }
                }
            }
            
            $efectivo_guardado_usd = $calculos['efectivo_guardado_usd'] ?? $calculos['efectivo_guardado'] ?? 0;
            $efectivo_guardado_bs = $calculos['efectivo_guardado_bs'] ?? 0;
            $efectivo_guardado_cop = $calculos['efectivo_guardado_cop'] ?? 0;
            // ========== FIN RECÁLCULO ==========

            if ($tipo_cierre==0 && session("tipo_usuario")==1) {
                return "Siendo Administrador, solo puede hacer cierre totalizado";
            }

           /*  $check = cajas::where("estatus",0);
            
            if ($check->count()) {
                return "Hay movimientos de Caja PENDIENTES";
            } */

            // Inicializar variables que se usan fuera del bloque de tipo_cierre
            $total_punto_real = 0;
            $lotes_para_guardar = [];

            // Si es cierre admin, sumar todos los lotes de todas las cajas guardadas
            if ($tipo_cierre == 1) {
                // Obtener todos los cierres de cajeros de esta fecha
                $cierres_cajeros = cierres::where('fecha', $today)
                    ->where('tipo_cierre', 0)
                    ->get();
                
                // Sumar todos los lotes (pinpad + otros puntos) de todos los cierres de cajeros
                foreach ($cierres_cajeros as $cierre_cajero) {
                    // Obtener lotes pinpad
                    $registro_pinpad = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero->id)
                        ->where('tipo_pago', 2)
                        ->where('subtipo', 'pinpad')
                        ->first();
                    
                    if ($registro_pinpad && $registro_pinpad->monto_real) {
                        $total_punto_real += floatval($registro_pinpad->monto_real);
                    }
                    
                    // Obtener lotes otros puntos
                    $registro_otros = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero->id)
                        ->where('tipo_pago', 2)
                        ->where('subtipo', 'otros_puntos')
                        ->first();
                    
                    if ($registro_otros && $registro_otros->monto_real) {
                        $total_punto_real += floatval($registro_otros->monto_real);
                    }
                }
            }

            if ($tipo_cierre==0) {
                // Extraer puntos adicionales y lotes pinpad del objeto dataPuntosAdicionales
                $puntosAdicionales = [];
                $lotesPinpadArray = [];
                
                if (is_array($dataPuntosAdicionales)) {
                    // Si dataPuntosAdicionales es un array directo, 
                    // verificar si tiene estructura {puntos, lotes_pinpad} o es array plano
                    // Primero verificar si es un array asociativo con keys 'puntos' y 'lotes_pinpad'
                    if (isset($dataPuntosAdicionales['puntos']) || isset($dataPuntosAdicionales['lotes_pinpad'])) {
                        $puntosAdicionales = $dataPuntosAdicionales['puntos'] ?? [];
                        $lotesPinpadArray = $dataPuntosAdicionales['lotes_pinpad'] ?? [];
                    } else {
                        // Array plano de otros puntos (formato antiguo)
                        $puntosAdicionales = $dataPuntosAdicionales;
                    }
                } else if (is_object($dataPuntosAdicionales)) {
                    // Si es un objeto con puntos y lotes_pinpad (formato nuevo)
                    $puntosAdicionales = $dataPuntosAdicionales->puntos ?? [];
                    $lotesPinpadArray = $dataPuntosAdicionales->lotes_pinpad ?? [];
                }
                
                // Convertir objetos a arrays para facilitar el acceso
                $puntosAdicionales = json_decode(json_encode($puntosAdicionales), true);
                $lotesPinpadArray = json_decode(json_encode($lotesPinpadArray), true);

                // OPTIMIZACIÓN: Usar total_punto calculado por cerrarFun (ya está en calculos)
                // NO recalcular, solo usar el valor ya calculado
                $total_punto_real = floatval($calculos['total_punto'] ?? 0);
                
                // Preparar datos de lotes para guardar después del cierre
                // (se guardarán en CierresMetodosPago después de tener id_cierre)
                $lotes_para_guardar = [
                    'otros_puntos' => $puntosAdicionales,
                    'lotes_pinpad' => $lotesPinpadArray
                ];
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

            // Validar efectivo guardado usando valor calculado internamente
            if ($efectivo_guardado_usd < 0) {
                return Response::json(["msj" => "Error: Esta guardando mas efectivo de lo disponible", "estado" => false]);
            }
            
            // ========== VALIDAR FALTANTE A NIVEL GENERAL ==========
            // OPTIMIZACIÓN: Usar directamente los datos calculados por cerrarFun (cuadre_detallado)
            // NO recalcular nada, solo usar los valores ya calculados
            
            \Log::info("=== VALIDACION FALTANTE - TIPO CIERRE: " . $tipo_cierre . " ===");
            \Log::info("VALIDACION FALTANTE - Fecha: " . $today);
            \Log::info("VALIDACION FALTANTE - ID Usuario: " . $id_usuario);
            
            // Obtener tasas de cambio (evitar división por cero)
            $tasa_bs_safe = $bs > 0 ? $bs : 1;
            $tasa_cop_safe = $cop > 0 ? $cop : 1;
            
            \Log::info("VALIDACION FALTANTE - Tasas: BS=" . $tasa_bs_safe . ", COP=" . $tasa_cop_safe);
            
            // ========== LOGS DETALLADOS DEL CUADRE_DETALLADO ==========
            \Log::info("VALIDACION FALTANTE - Cuadre detallado completo: " . json_encode($cuadre_detallado, JSON_PRETTY_PRINT));
            
            // ========== USAR DATOS DIRECTAMENTE DEL CUADRE_DETALLADO (ya calculados por cerrarFun) ==========
            // Efectivo: usar valores del cuadre_detallado y convertir a USD
            $efectivo_usd_real_raw = floatval($cuadre_detallado['efectivo_usd']['real'] ?? 0);
            $efectivo_bs_real_raw = floatval($cuadre_detallado['efectivo_bs']['real'] ?? 0);
            $efectivo_cop_real_raw = floatval($cuadre_detallado['efectivo_cop']['real'] ?? 0);
            
            $efectivo_usd_digital_raw = floatval($cuadre_detallado['efectivo_usd']['digital'] ?? 0);
            $efectivo_bs_digital_raw = floatval($cuadre_detallado['efectivo_bs']['digital'] ?? 0);
            $efectivo_cop_digital_raw = floatval($cuadre_detallado['efectivo_cop']['digital'] ?? 0);
            
            $efectivo_usd_inicial_raw = floatval($cuadre_detallado['efectivo_usd']['inicial'] ?? 0);
            $efectivo_bs_inicial_raw = floatval($cuadre_detallado['efectivo_bs']['inicial'] ?? 0);
            $efectivo_cop_inicial_raw = floatval($cuadre_detallado['efectivo_cop']['inicial'] ?? 0);
            
            \Log::info("VALIDACION FALTANTE - Efectivo RAW - USD Real: " . $efectivo_usd_real_raw . ", Digital: " . $efectivo_usd_digital_raw . ", Inicial: " . $efectivo_usd_inicial_raw);
            \Log::info("VALIDACION FALTANTE - Efectivo RAW - BS Real: " . $efectivo_bs_real_raw . ", Digital: " . $efectivo_bs_digital_raw . ", Inicial: " . $efectivo_bs_inicial_raw);
            \Log::info("VALIDACION FALTANTE - Efectivo RAW - COP Real: " . $efectivo_cop_real_raw . ", Digital: " . $efectivo_cop_digital_raw . ", Inicial: " . $efectivo_cop_inicial_raw);
            
            $efectivo_real_usd = $efectivo_usd_real_raw + 
                                ($efectivo_bs_real_raw / $tasa_bs_safe) + 
                                ($efectivo_cop_real_raw / $tasa_cop_safe);
            
            $efectivo_digital_usd = $efectivo_usd_digital_raw + 
                                   ($efectivo_bs_digital_raw / $tasa_bs_safe) + 
                                   ($efectivo_cop_digital_raw / $tasa_cop_safe);
            
            $efectivo_inicial_usd = $efectivo_usd_inicial_raw + 
                                   ($efectivo_bs_inicial_raw / $tasa_bs_safe) + 
                                   ($efectivo_cop_inicial_raw / $tasa_cop_safe);
            
            \Log::info("VALIDACION FALTANTE - Efectivo USD Convertido - Real: " . $efectivo_real_usd . ", Digital: " . $efectivo_digital_usd . ", Inicial: " . $efectivo_inicial_usd);
            
            // Débito: usar valores del cuadre_detallado y convertir a USD
            $debito_pinpad_real_raw = floatval($cuadre_detallado['debito_pinpad']['real'] ?? 0);
            $debito_otros_real_raw = floatval($cuadre_detallado['debito_otros']['real'] ?? 0);
            $debito_pinpad_digital_raw = floatval($cuadre_detallado['debito_pinpad']['digital'] ?? 0);
            $debito_otros_digital_raw = floatval($cuadre_detallado['debito_otros']['digital'] ?? 0);
            
            \Log::info("VALIDACION FALTANTE - Debito RAW - Pinpad Real: " . $debito_pinpad_real_raw . " BS, Digital: " . $debito_pinpad_digital_raw . " BS");
            \Log::info("VALIDACION FALTANTE - Debito RAW - Otros Real: " . $debito_otros_real_raw . " BS, Digital: " . $debito_otros_digital_raw . " BS");
            
            $debito_real_usd = ($debito_pinpad_real_raw / $tasa_bs_safe) + 
                              ($debito_otros_real_raw / $tasa_bs_safe);
            
            $debito_digital_usd = ($debito_pinpad_digital_raw / $tasa_bs_safe) + 
                                 ($debito_otros_digital_raw / $tasa_bs_safe);
            
            \Log::info("VALIDACION FALTANTE - Debito USD Convertido - Real: " . $debito_real_usd . ", Digital: " . $debito_digital_usd);
            
            // Transferencia: usar valores del cuadre_detallado (ya está en USD)
            $transferencia_real_usd = floatval($cuadre_detallado['transferencia']['real'] ?? 0);
            $transferencia_digital_usd = floatval($cuadre_detallado['transferencia']['digital'] ?? 0);
            
            \Log::info("VALIDACION FALTANTE - Transferencia USD - Real: " . $transferencia_real_usd . ", Digital: " . $transferencia_digital_usd);
            
            // Biopago: usar valores del request/calculos (ya está en USD)
            $biopago_real_usd = floatval($req->total_biopago ?? 0);
            $biopago_digital_usd = floatval($calculos['biopago_digital'] ?? 0);
            
            \Log::info("VALIDACION FALTANTE - Biopago USD - Real: " . $biopago_real_usd . ", Digital: " . $biopago_digital_usd);
            
            // ========== CALCULAR TOTALES ==========
            $total_inicial = $efectivo_inicial_usd; // Efectivo tiene inicial, débito y transferencia no
            
            $total_real = $efectivo_real_usd + $debito_real_usd + $transferencia_real_usd + $biopago_real_usd;
            
            $total_digital = $efectivo_digital_usd + $debito_digital_usd + $transferencia_digital_usd + $biopago_digital_usd;
            
            // Fórmula exacta del reporte: total_diferencia = total_real - total_digital - total_inicial
            $total_diferencia = $total_real - $total_digital - $total_inicial;
            
            // Asignar descuadre total en dólares a calculos
            $calculos['descuadre'] = round($total_diferencia, 2);
            
            \Log::info("VALIDACION FALTANTE - Total Inicial: " . $total_inicial . " USD");
            \Log::info("VALIDACION FALTANTE - Total Real: " . $efectivo_real_usd . " + " . $debito_real_usd . " + " . $transferencia_real_usd . " + " . $biopago_real_usd . " = " . $total_real . " USD");
            \Log::info("VALIDACION FALTANTE - Total Digital: " . $efectivo_digital_usd . " + " . $debito_digital_usd . " + " . $transferencia_digital_usd . " + " . $biopago_digital_usd . " = " . $total_digital . " USD");
            \Log::info("VALIDACION FALTANTE - Total Diferencia (descuadre): " . $total_real . " - " . $total_digital . " - " . $total_inicial . " = " . $total_diferencia . " USD");
            
            // ========== PARA CONSOLIDADOS: VERIFICAR VALORES DESDE CIERRES GUARDADOS ==========
            if ($tipo_cierre == 1) {
                \Log::info("=== VALIDACION FALTANTE - VERIFICACION CONSOLIDADO ===");
                
                $cierres_cajeros_verificar = cierres::where('fecha', $today)
                    ->where('tipo_cierre', 0)
                    ->get();
                
                \Log::info("VALIDACION FALTANTE - Cantidad cierres de cajeros encontrados: " . $cierres_cajeros_verificar->count());
                
                $suma_efectivo_usd_real = 0;
                $suma_efectivo_bs_real = 0;
                $suma_efectivo_cop_real = 0;
                $suma_efectivo_usd_digital = 0;
                $suma_efectivo_bs_digital = 0;
                $suma_efectivo_cop_digital = 0;
                $suma_efectivo_usd_inicial = 0;
                $suma_efectivo_bs_inicial = 0;
                $suma_efectivo_cop_inicial = 0;
                $suma_debito_pinpad_real = 0;
                $suma_debito_otros_real = 0;
                $suma_debito_pinpad_digital = 0;
                $suma_debito_otros_digital = 0;
                $suma_transferencia_real = 0;
                $suma_transferencia_digital = 0;
                $suma_biopago_real = 0;
                $suma_biopago_digital = 0;
                
                foreach ($cierres_cajeros_verificar as $cierre_cajero_ver) {
                    \Log::info("VALIDACION FALTANTE - Cierre Cajero ID: " . $cierre_cajero_ver->id . ", Usuario: " . $cierre_cajero_ver->id_usuario);
                    
                    // Logs detallados de valores del cierre
                    \Log::info("VALIDACION FALTANTE - Valores del cierre directamente:");
                    \Log::info("VALIDACION FALTANTE -   efectivo_actual: " . ($cierre_cajero_ver->efectivo_actual ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   efectivo_actual_bs: " . ($cierre_cajero_ver->efectivo_actual_bs ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   efectivo_actual_cop: " . ($cierre_cajero_ver->efectivo_actual_cop ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   efectivo_digital: " . ($cierre_cajero_ver->efectivo_digital ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   efectivo_digital_bs: " . ($cierre_cajero_ver->efectivo_digital_bs ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   efectivo_digital_cop: " . ($cierre_cajero_ver->efectivo_digital_cop ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   caja_inicial: " . ($cierre_cajero_ver->caja_inicial ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   caja_inicialbs: " . ($cierre_cajero_ver->caja_inicialbs ?? 'NULL'));
                    \Log::info("VALIDACION FALTANTE -   caja_inicialcop: " . ($cierre_cajero_ver->caja_inicialcop ?? 'NULL'));
                    
                    // Obtener efectivo desde CierresMetodosPago
                    $registro_efectivo = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero_ver->id)
                        ->where('tipo_pago', 3) // Tipo 3 = Efectivo
                        ->first();
                    
                    \Log::info("VALIDACION FALTANTE - Registro efectivo en CierresMetodosPago: " . ($registro_efectivo ? 'ENCONTRADO (ID: ' . $registro_efectivo->id . ')' : 'NO ENCONTRADO'));
                    
                    if ($registro_efectivo) {
                        $metadatos_ef = is_string($registro_efectivo->metadatos) 
                            ? json_decode($registro_efectivo->metadatos, true) 
                            : ($registro_efectivo->metadatos ?? []);
                        
                        \Log::info("VALIDACION FALTANTE - Metadatos efectivo: " . json_encode($metadatos_ef, JSON_PRETTY_PRINT));
                        
                        $suma_efectivo_usd_real += floatval($metadatos_ef['monto_usd_real'] ?? 0);
                        $suma_efectivo_bs_real += floatval($metadatos_ef['monto_bs_real'] ?? 0);
                        $suma_efectivo_cop_real += floatval($metadatos_ef['monto_cop_real'] ?? 0);
                        $suma_efectivo_usd_digital += floatval($metadatos_ef['monto_usd_digital'] ?? 0);
                        $suma_efectivo_bs_digital += floatval($metadatos_ef['monto_bs_digital'] ?? 0);
                        $suma_efectivo_cop_digital += floatval($metadatos_ef['monto_cop_digital'] ?? 0);
                        $suma_efectivo_usd_inicial += floatval($metadatos_ef['monto_usd_inicial'] ?? 0);
                        $suma_efectivo_bs_inicial += floatval($metadatos_ef['monto_bs_inicial'] ?? 0);
                        $suma_efectivo_cop_inicial += floatval($metadatos_ef['monto_cop_inicial'] ?? 0);
                        
                        \Log::info("VALIDACION FALTANTE - Sumando desde CierresMetodosPago:");
                        \Log::info("VALIDACION FALTANTE -   USD Real: " . ($metadatos_ef['monto_usd_real'] ?? 0) . ", Acumulado: " . $suma_efectivo_usd_real);
                        \Log::info("VALIDACION FALTANTE -   BS Real: " . ($metadatos_ef['monto_bs_real'] ?? 0) . ", Acumulado: " . $suma_efectivo_bs_real);
                        \Log::info("VALIDACION FALTANTE -   COP Real: " . ($metadatos_ef['monto_cop_real'] ?? 0) . ", Acumulado: " . $suma_efectivo_cop_real);
                    } else {
                        // Si no hay registro, usar valores del cierre directamente
                        $efectivo_usd_real_cierre = floatval($cierre_cajero_ver->efectivo_actual ?? 0);
                        $efectivo_bs_real_cierre = floatval($cierre_cajero_ver->efectivo_actual_bs ?? 0);
                        $efectivo_cop_real_cierre = floatval($cierre_cajero_ver->efectivo_actual_cop ?? 0);
                        $efectivo_usd_digital_cierre = floatval($cierre_cajero_ver->efectivo_digital ?? 0);
                        $efectivo_bs_digital_cierre = floatval($cierre_cajero_ver->efectivo_digital_bs ?? 0);
                        $efectivo_cop_digital_cierre = floatval($cierre_cajero_ver->efectivo_digital_cop ?? 0);
                        $efectivo_usd_inicial_cierre = floatval($cierre_cajero_ver->caja_inicial ?? 0);
                        $efectivo_bs_inicial_cierre = floatval($cierre_cajero_ver->caja_inicialbs ?? 0);
                        $efectivo_cop_inicial_cierre = floatval($cierre_cajero_ver->caja_inicialcop ?? 0);
                        
                        \Log::info("VALIDACION FALTANTE - Sumando desde cierre directamente:");
                        \Log::info("VALIDACION FALTANTE -   USD Real: " . $efectivo_usd_real_cierre . ", Acumulado antes: " . $suma_efectivo_usd_real);
                        \Log::info("VALIDACION FALTANTE -   BS Real: " . $efectivo_bs_real_cierre . ", Acumulado antes: " . $suma_efectivo_bs_real);
                        \Log::info("VALIDACION FALTANTE -   COP Real: " . $efectivo_cop_real_cierre . ", Acumulado antes: " . $suma_efectivo_cop_real);
                        
                        $suma_efectivo_usd_real += $efectivo_usd_real_cierre;
                        $suma_efectivo_bs_real += $efectivo_bs_real_cierre;
                        $suma_efectivo_cop_real += $efectivo_cop_real_cierre;
                        $suma_efectivo_usd_digital += $efectivo_usd_digital_cierre;
                        $suma_efectivo_bs_digital += $efectivo_bs_digital_cierre;
                        $suma_efectivo_cop_digital += $efectivo_cop_digital_cierre;
                        $suma_efectivo_usd_inicial += $efectivo_usd_inicial_cierre;
                        $suma_efectivo_bs_inicial += $efectivo_bs_inicial_cierre;
                        $suma_efectivo_cop_inicial += $efectivo_cop_inicial_cierre;
                        
                        \Log::info("VALIDACION FALTANTE -   Acumulado después - USD: " . $suma_efectivo_usd_real . ", BS: " . $suma_efectivo_bs_real . ", COP: " . $suma_efectivo_cop_real);
                    }
                    
                    // Obtener débito pinpad
                    $registro_pinpad = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero_ver->id)
                        ->where('tipo_pago', 2)
                        ->where('subtipo', 'pinpad')
                        ->first();
                    
                    if ($registro_pinpad) {
                        $suma_debito_pinpad_real += floatval($registro_pinpad->monto_real ?? 0);
                        $suma_debito_pinpad_digital += floatval($registro_pinpad->monto_digital ?? 0);
                    }
                    
                    // Obtener débito otros puntos
                    $registro_otros = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero_ver->id)
                        ->where('tipo_pago', 2)
                        ->where('subtipo', 'otros_puntos')
                        ->first();
                    
                    if ($registro_otros) {
                        $suma_debito_otros_real += floatval($registro_otros->monto_real ?? 0);
                        $suma_debito_otros_digital += floatval($registro_otros->monto_digital ?? 0);
                    }
                    
                    // Obtener transferencia
                    $registro_transf = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero_ver->id)
                        ->where('tipo_pago', 1) // Tipo 1 = Transferencia
                        ->first();
                    
                    if ($registro_transf) {
                        $suma_transferencia_real += floatval($registro_transf->monto_real ?? 0);
                        $suma_transferencia_digital += floatval($registro_transf->monto_digital ?? 0);
                    } else {
                        $suma_transferencia_real += floatval($cierre_cajero_ver->transferencia ?? 0);
                        $suma_transferencia_digital += floatval($cierre_cajero_ver->transferencia_digital ?? 0);
                    }
                    
                    // Obtener biopago
                    $registro_bio = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero_ver->id)
                        ->where('tipo_pago', 4) // Tipo 4 = Biopago
                        ->first();
                    
                    if ($registro_bio) {
                        $suma_biopago_real += floatval($registro_bio->monto_real ?? 0);
                        $suma_biopago_digital += floatval($registro_bio->monto_digital ?? 0);
                    } else {
                        $suma_biopago_real += floatval($cierre_cajero_ver->caja_biopago ?? 0);
                        $suma_biopago_digital += floatval($cierre_cajero_ver->biopago_digital ?? 0);
                    }
                }
                
                \Log::info("VALIDACION FALTANTE - SUMA DESDE CIERRES GUARDADOS:");
                \Log::info("VALIDACION FALTANTE - Efectivo USD - Real: " . $suma_efectivo_usd_real . ", Digital: " . $suma_efectivo_usd_digital . ", Inicial: " . $suma_efectivo_usd_inicial);
                \Log::info("VALIDACION FALTANTE - Efectivo BS - Real: " . $suma_efectivo_bs_real . ", Digital: " . $suma_efectivo_bs_digital . ", Inicial: " . $suma_efectivo_bs_inicial);
                \Log::info("VALIDACION FALTANTE - Efectivo COP - Real: " . $suma_efectivo_cop_real . ", Digital: " . $suma_efectivo_cop_digital . ", Inicial: " . $suma_efectivo_cop_inicial);
                \Log::info("VALIDACION FALTANTE - Debito Pinpad - Real: " . $suma_debito_pinpad_real . " BS, Digital: " . $suma_debito_pinpad_digital . " BS");
                \Log::info("VALIDACION FALTANTE - Debito Otros - Real: " . $suma_debito_otros_real . " BS, Digital: " . $suma_debito_otros_digital . " BS");
                \Log::info("VALIDACION FALTANTE - Transferencia - Real: " . $suma_transferencia_real . " USD, Digital: " . $suma_transferencia_digital . " USD");
                \Log::info("VALIDACION FALTANTE - Biopago - Real: " . $suma_biopago_real . " USD, Digital: " . $suma_biopago_digital . " USD");
                
                // Convertir a USD para comparar
                $suma_efectivo_real_usd_verif = $suma_efectivo_usd_real + ($suma_efectivo_bs_real / $tasa_bs_safe) + ($suma_efectivo_cop_real / $tasa_cop_safe);
                $suma_efectivo_digital_usd_verif = $suma_efectivo_usd_digital + ($suma_efectivo_bs_digital / $tasa_bs_safe) + ($suma_efectivo_cop_digital / $tasa_cop_safe);
                $suma_efectivo_inicial_usd_verif = $suma_efectivo_usd_inicial + ($suma_efectivo_bs_inicial / $tasa_bs_safe) + ($suma_efectivo_cop_inicial / $tasa_cop_safe);
                $suma_debito_real_usd_verif = ($suma_debito_pinpad_real / $tasa_bs_safe) + ($suma_debito_otros_real / $tasa_bs_safe);
                $suma_debito_digital_usd_verif = ($suma_debito_pinpad_digital / $tasa_bs_safe) + ($suma_debito_otros_digital / $tasa_bs_safe);
                
                $suma_total_real_verif = $suma_efectivo_real_usd_verif + $suma_debito_real_usd_verif + $suma_transferencia_real + $suma_biopago_real;
                $suma_total_digital_verif = $suma_efectivo_digital_usd_verif + $suma_debito_digital_usd_verif + $suma_transferencia_digital + $suma_biopago_digital;
                $suma_total_diferencia_verif = $suma_total_real_verif - $suma_total_digital_verif - $suma_efectivo_inicial_usd_verif;
                
                \Log::info("VALIDACION FALTANTE - COMPARACION:");
                \Log::info("VALIDACION FALTANTE - Efectivo Real USD - Cuadre: " . $efectivo_real_usd . " vs Suma: " . $suma_efectivo_real_usd_verif . " (Diferencia: " . ($efectivo_real_usd - $suma_efectivo_real_usd_verif) . ")");
                \Log::info("VALIDACION FALTANTE - Efectivo Digital USD - Cuadre: " . $efectivo_digital_usd . " vs Suma: " . $suma_efectivo_digital_usd_verif . " (Diferencia: " . ($efectivo_digital_usd - $suma_efectivo_digital_usd_verif) . ")");
                \Log::info("VALIDACION FALTANTE - Efectivo Inicial USD - Cuadre: " . $efectivo_inicial_usd . " vs Suma: " . $suma_efectivo_inicial_usd_verif . " (Diferencia: " . ($efectivo_inicial_usd - $suma_efectivo_inicial_usd_verif) . ")");
                \Log::info("VALIDACION FALTANTE - Debito Real USD - Cuadre: " . $debito_real_usd . " vs Suma: " . $suma_debito_real_usd_verif . " (Diferencia: " . ($debito_real_usd - $suma_debito_real_usd_verif) . ")");
                \Log::info("VALIDACION FALTANTE - Debito Digital USD - Cuadre: " . $debito_digital_usd . " vs Suma: " . $suma_debito_digital_usd_verif . " (Diferencia: " . ($debito_digital_usd - $suma_debito_digital_usd_verif) . ")");
                \Log::info("VALIDACION FALTANTE - Transferencia Real USD - Cuadre: " . $transferencia_real_usd . " vs Suma: " . $suma_transferencia_real . " (Diferencia: " . ($transferencia_real_usd - $suma_transferencia_real) . ")");
                \Log::info("VALIDACION FALTANTE - Transferencia Digital USD - Cuadre: " . $transferencia_digital_usd . " vs Suma: " . $suma_transferencia_digital . " (Diferencia: " . ($transferencia_digital_usd - $suma_transferencia_digital) . ")");
                \Log::info("VALIDACION FALTANTE - Biopago Real USD - Cuadre: " . $biopago_real_usd . " vs Suma: " . $suma_biopago_real . " (Diferencia: " . ($biopago_real_usd - $suma_biopago_real) . ")");
                \Log::info("VALIDACION FALTANTE - Biopago Digital USD - Cuadre: " . $biopago_digital_usd . " vs Suma: " . $suma_biopago_digital . " (Diferencia: " . ($biopago_digital_usd - $suma_biopago_digital) . ")");
                \Log::info("VALIDACION FALTANTE - Total Diferencia - Cuadre: " . $total_diferencia . " vs Suma: " . $suma_total_diferencia_verif . " (Diferencia: " . ($total_diferencia - $suma_total_diferencia_verif) . ")");
            }
            
            // ========== VALIDAR FALTANTE ==========
            // Si total_diferencia < -5: FALTANTE (bloquear)
            // Si total_diferencia > 0: SOBRANTE (no bloquear, es aceptable)
            // Si total_diferencia entre -5 y 0: diferencia pequeña (no bloquear)
            if ($total_diferencia < -5) {
                \Log::warning("FALTANTE DETECTADO: " . $total_diferencia . " USD (Tipo Cierre: " . $tipo_cierre . ")");
                $mensaje_faltante = "NO SE PUEDE GUARDAR EL CIERRE\n\n";
                $mensaje_faltante .= "HAY UN FALTANTE GENERAL DE $" . number_format(abs($total_diferencia), 2) . " USD\n\n";
                $mensaje_faltante .= "===========================================\n";
                $mensaje_faltante .= "DESGLOSE (MISMO CALCULO DEL REPORTE):\n";
                $mensaje_faltante .= "===========================================\n";
                $mensaje_faltante .= "Saldo Inicial: $" . number_format($total_inicial, 2) . " USD\n";
                $mensaje_faltante .= "Total Real: $" . number_format($total_real, 2) . " USD\n";
                $mensaje_faltante .= "Total Digital: $" . number_format($total_digital, 2) . " USD\n";
                $mensaje_faltante .= "-------------------------------------------\n";
                $mensaje_faltante .= "DIFERENCIA (FALTANTE): $" . number_format($total_diferencia, 2) . " USD\n\n";
                $mensaje_faltante .= "===========================================\n";
                $mensaje_faltante .= "DESGLOSE POR TIPO:\n";
                $mensaje_faltante .= "===========================================\n";
                $mensaje_faltante .= "Efectivo Real: $" . number_format($efectivo_real_usd, 2) . " USD\n";
                $mensaje_faltante .= "Efectivo Digital: $" . number_format($efectivo_digital_usd, 2) . " USD\n";
                $mensaje_faltante .= "Efectivo Inicial: $" . number_format($efectivo_inicial_usd, 2) . " USD\n";
                $mensaje_faltante .= "Debito Real: $" . number_format($debito_real_usd, 2) . " USD\n";
                $mensaje_faltante .= "Debito Digital: $" . number_format($debito_digital_usd, 2) . " USD\n";
                $mensaje_faltante .= "Transferencia Real: $" . number_format($transferencia_real_usd, 2) . " USD\n";
                $mensaje_faltante .= "Transferencia Digital: $" . number_format($transferencia_digital_usd, 2) . " USD\n";
                $mensaje_faltante .= "Biopago Real: $" . number_format($biopago_real_usd, 2) . " USD\n";
                $mensaje_faltante .= "Biopago Digital: $" . number_format($biopago_digital_usd, 2) . " USD\n\n";
                $mensaje_faltante .= "===========================================\n";
                $mensaje_faltante .= "EXPLICACION:\n";
                $mensaje_faltante .= "===========================================\n";
                $mensaje_faltante .= "La diferencia calculada es: Total Real - Total Digital - Saldo Inicial\n";
                $mensaje_faltante .= "Resultado: $" . number_format($total_diferencia, 2) . " USD (FALTANTE)\n\n";
                $mensaje_faltante .= "Esto indica que falta dinero en caja. Revise:\n";
                $mensaje_faltante .= "- Los montos de efectivo contado\n";
                $mensaje_faltante .= "- Los lotes de debito (Pinpad y Otros Puntos)\n";
                $mensaje_faltante .= "- Las transferencias registradas\n";
                $mensaje_faltante .= "- El saldo inicial de la caja\n\n";
                $mensaje_faltante .= "No se puede guardar el cierre hasta resolver este faltante.";
                
                return Response::json([
                    "msj" => $mensaje_faltante,
                    "estado" => false,
                    "total_diferencia" => $total_diferencia,
                    "detalle" => [
                        "total_inicial" => $total_inicial,
                        "total_real" => $total_real,
                        "total_digital" => $total_digital,
                        "efectivo_real_usd" => $efectivo_real_usd,
                        "efectivo_digital_usd" => $efectivo_digital_usd,
                        "efectivo_inicial_usd" => $efectivo_inicial_usd,
                        "debito_real_usd" => $debito_real_usd,
                        "debito_digital_usd" => $debito_digital_usd,
                        "transferencia_real_usd" => $transferencia_real_usd,
                        "transferencia_digital_usd" => $transferencia_digital_usd,
                        "biopago_real_usd" => $biopago_real_usd,
                        "biopago_digital_usd" => $biopago_digital_usd
                    ]
                ]);
            }
                
                // ==================================================================
                // ESTRATEGIA SIMPLIFICADA: SIEMPRE ELIMINAR Y CREAR NUEVO
                // Un cierre es un snapshot del día. Si te equivocaste, lo correcto
                // es eliminarlo completamente y crear uno nuevo con datos correctos.
                // ==================================================================
                
                // Verificar si ya existe un cierre del día
                $cierre_existente = cierres::where("fecha", $today)->where("id_usuario", $id_usuario)->first();
                
                if ($cierre_existente) {
                    // Eliminar relaciones (métodos de pago)
                    \App\Models\CierresMetodosPago::where('id_cierre', $cierre_existente->id)->delete();
                    
                    // Eliminar el cierre (lotes_debito se elimina automáticamente)
                    $cierre_existente->delete();
                }
                
                // SIEMPRE crear nuevo cierre
                $objcierres = new cierres;

                // Usar valores REALES del cuadre_detallado (dinero físico contado), NO digitales
                // Efectivo: (Real - Inicial) para cada moneda, todo convertido a USD
                // Fórmula: (Real USD + Real BS convertido + Real COP convertido) - (Inicial USD + Inicial BS convertido + Inicial COP convertido)
                $efectivo_usd_real = floatval($cuadre_detallado['efectivo_usd']['real'] ?? 0);
                $efectivo_usd_inicial = floatval($cuadre_detallado['efectivo_usd']['inicial'] ?? 0);
                
                $efectivo_bs_real = floatval($cuadre_detallado['efectivo_bs']['real'] ?? 0);
                $efectivo_bs_inicial = floatval($cuadre_detallado['efectivo_bs']['inicial'] ?? 0);
                
                $efectivo_cop_real = floatval($cuadre_detallado['efectivo_cop']['real'] ?? 0);
                $efectivo_cop_inicial = floatval($cuadre_detallado['efectivo_cop']['inicial'] ?? 0);
                
                // Evitar división por cero
                $tasa_bs_safe = $bs > 0 ? $bs : 1;
                $tasa_cop_safe = $cop > 0 ? $cop : 1;
                
                // Calcular efectivo neto (Real - Inicial) para cada moneda, convertido a USD
                $efectivo_usd_neto = $efectivo_usd_real - $efectivo_usd_inicial;
                $efectivo_bs_neto = ($efectivo_bs_real - $efectivo_bs_inicial) / $tasa_bs_safe;
                $efectivo_cop_neto = ($efectivo_cop_real - $efectivo_cop_inicial) / $tasa_cop_safe;
                
                // Sumar todos los efectivos netos en USD (dinero que realmente entró)
                $objcierres->efectivo = $efectivo_usd_neto + $efectivo_bs_neto + $efectivo_cop_neto;
                
                // Transferencia: valor real (ya está en USD)
                $objcierres->transferencia = floatval($cuadre_detallado['transferencia']['real'] ?? 0);
                
                // Débito: sumar pinpad y otros_puntos convertidos a USD (ambos están en BS, se dividen por tasa_bs)
                $debito_pinpad_real = floatval($cuadre_detallado['debito_pinpad']['real'] ?? 0);
                $debito_otros_real = floatval($cuadre_detallado['debito_otros']['real'] ?? 0);
                
                // Sumar ambos débitos convertidos a USD
                $objcierres->debito = ($debito_pinpad_real / $tasa_bs_safe) + 
                    ($debito_otros_real / $tasa_bs_safe);
                
                // Biopago: valor real del dinero de biopago
                $objcierres->caja_biopago = floatval($req->total_biopago ?? 0);
                
                // Usar datos calculados internamente
                $objcierres->debito_digital = floatval($calculos['debito_digital'] ?? 0); 
                $objcierres->efectivo_digital = floatval($calculos['efectivo_digital'] ?? 0); 
                $objcierres->transferencia_digital = floatval($calculos['transferencia_digital'] ?? 0); 
                $objcierres->biopago_digital = floatval($calculos['biopago_digital'] ?? 0); 
                $objcierres->descuadre = floatval($calculos['descuadre'] ?? 0); 

                $objcierres->dejar_dolar = floatval($req->dejar_usd);
                $objcierres->dejar_peso = floatval($req->dejar_cop);
                $objcierres->dejar_bss = floatval($req->dejar_bs);

                // Usar efectivo guardado calculado internamente
                $objcierres->efectivo_guardado = floatval($efectivo_guardado_usd);
                $objcierres->efectivo_guardado_cop = floatval($efectivo_guardado_cop);
                $objcierres->efectivo_guardado_bs = floatval($efectivo_guardado_bs);
                $objcierres->tasa = $bs;
                $objcierres->nota = $req->notaCierre;
                $objcierres->id_usuario = $id_usuario;
                $objcierres->fecha = $today;
                
                // Usar datos calculados internamente
                $objcierres->precio = floatval($calculos['precio'] ?? 0);
                $objcierres->precio_base = floatval($calculos['precio_base'] ?? 0);
                $objcierres->ganancia = floatval($calculos['ganancia'] ?? 0);
                $objcierres->porcentaje = floatval($calculos['porcentaje'] ?? 0);
                $objcierres->numventas = intval($calculos['numventas'] ?? 0);
                $objcierres->desc_total = floatval($calculos['desc_total'] ?? 0);

                $objcierres->efectivo_actual = floatval($req->caja_usd ?? 0);
                $objcierres->efectivo_actual_cop = floatval($req->caja_cop ?? 0);
                $objcierres->efectivo_actual_bs = floatval($req->caja_bs ?? 0);
                $objcierres->puntodeventa_actual_bs = floatval($total_punto_real);

                $objcierres->tipo_cierre = $tipo_cierre;

                $objcierres->tasacop = $cop;

                $objcierres->numreportez = $req->numreportez ?? null;
                $objcierres->ventaexcento = floatval($req->ventaexcento ?? 0);
                $objcierres->ventagravadas = floatval($req->ventagravadas ?? 0);
                $objcierres->ivaventa = floatval($req->ivaventa ?? 0);
                $objcierres->totalventa = floatval($req->totalventa ?? 0);
                $objcierres->ultimafactura = $req->ultimafactura ?? null;

                $objcierres->efecadiccajafbs = floatval($req->efecadiccajafbs ?? 0);
                $objcierres->efecadiccajafcop = floatval($req->efecadiccajafcop ?? 0);
                $objcierres->efecadiccajafdolar = floatval($req->efecadiccajafdolar ?? 0);
                $objcierres->efecadiccajafeuro = floatval($req->efecadiccajafeuro ?? 0);

                $objcierres->inventariobase = floatval($calculos['total_inventario_base'] ?? $req->inventariobase ?? 0);
                $objcierres->inventarioventa = floatval($calculos['total_inventario'] ?? $req->inventarioventa ?? 0);

                $objcierres->credito = floatval($req->credito ?? 0);
                $objcierres->creditoporcobrartotal = floatval($req->creditoporcobrartotal ?? 0);
                $objcierres->abonosdeldia = floatval($req->abonosdeldia ?? 0);

                // Biopago (si aplica)
                $objcierres->biopagoserial = $req->serialbiopago ?? null;
                $objcierres->biopagoserialmontobs = floatval($req->total_biopago ?? 0);
                
                // Guardar cuadre_detallado completo como JSON (flexible y sin campos redundantes)
                // IMPORTANTE: Para consolidado, asegurar que debito_otros tenga la suma correcta
                if ($tipo_cierre == 1) {
                    // Verificar y corregir si es necesario
                    $debito_otros_calculado = floatval($cuadre_detallado['debito_otros']['real'] ?? 0);
                    
                    // Sumar directamente desde CierresMetodosPago para verificar
                    $suma_verificacion_otros = 0;
                    $cierres_cajeros_verificar = cierres::where('fecha', $today)
                        ->where('tipo_cierre', 0)
                        ->get();
                    
                    foreach ($cierres_cajeros_verificar as $cierre_cajero_ver) {
                        $registro_otros_ver = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero_ver->id)
                            ->where('tipo_pago', 2)
                            ->where('subtipo', 'otros_puntos')
                            ->first();
                        
                        if ($registro_otros_ver) {
                            $suma_verificacion_otros += floatval($registro_otros_ver->monto_real ?? 0);
                        }
                    }
                    
                    // Si hay discrepancia, corregir el cuadre_detallado
                    if (abs($debito_otros_calculado - $suma_verificacion_otros) > 0.01) {
                        // Corregir el valor en cuadre_detallado
                        $cuadre_detallado['debito_otros']['real'] = $suma_verificacion_otros;
                        $cuadre_detallado['debito_otros']['diferencia'] = 
                            $suma_verificacion_otros - floatval($cuadre_detallado['debito_otros']['digital'] ?? 0);
                        
                        $diferencia_otros = abs($cuadre_detallado['debito_otros']['diferencia']);
                        if ($diferencia_otros <= 5) {
                            $cuadre_detallado['debito_otros']['estado'] = 'cuadrado';
                            $cuadre_detallado['debito_otros']['mensaje'] = 'CUADRADO';
                        } else if ($cuadre_detallado['debito_otros']['diferencia'] > 0) {
                            $cuadre_detallado['debito_otros']['estado'] = 'sobra';
                            $cuadre_detallado['debito_otros']['mensaje'] = 'SOBRAN';
                        } else {
                            $cuadre_detallado['debito_otros']['estado'] = 'falta';
                            $cuadre_detallado['debito_otros']['mensaje'] = 'FALTAN';
                        }
                    }
                }
                
                $objcierres->cuadre_detallado = $cuadre_detallado;
                
                $objcierres->save();
                
                // Verificar qué se guardó realmente
                $cierre_guardado = cierres::find($objcierres->id);
                
                $cuadre_guardado = is_string($cierre_guardado->cuadre_detallado) 
                    ? json_decode($cierre_guardado->cuadre_detallado, true) 
                    : $cierre_guardado->cuadre_detallado;
                
                // Invalidar caché de addNewPedido para este usuario y fecha
                $cache_key = "cierre_guardado_usuario_{$id_usuario}_fecha_{$today}";
                Cache::forget($cache_key);
                
                // Guardar métodos de pago RESUMEN en CierresMetodosPago
                // (Efectivo, Transferencia, Biopago, Crédito)
                // NOTA: Los débitos NO se guardan aquí, se guardan consolidados más abajo
                $this->guardarMetodosPagoCierre($objcierres->id, $cuadre_detallado);
                
                // Guardar débitos CONSOLIDADOS (2 registros con cuadre y detalle en JSON)
                // (2 registros: 1 para Pinpad con su cuadre, 1 para Otros Puntos con su cuadre)
                // Los lotes individuales van en metadatos JSON
                if ($tipo_cierre == 0 && isset($lotes_para_guardar)) {
                    
                    // === REGISTRO 1: PINPAD (Total con cuadre) ===
                    if (!empty($lotes_para_guardar['lotes_pinpad'])) {
                        // OPTIMIZACIÓN: Usar valores directamente del cuadre_detallado (ya calculados por cerrarFun)
                        // NO recalcular, solo usar los valores ya calculados
                        $monto_pinpad_real = floatval($cuadre_detallado['debito_pinpad']['real'] ?? 0);
                        $monto_pinpad_digital = floatval($cuadre_detallado['debito_pinpad']['digital'] ?? 0);
                        $monto_pinpad_inicial = floatval($cuadre_detallado['debito_pinpad']['inicial'] ?? 0);
                        $monto_pinpad_diferencia = floatval($cuadre_detallado['debito_pinpad']['diferencia'] ?? 0);
                        $estado_pinpad = $cuadre_detallado['debito_pinpad']['estado'] ?? 'cuadrado';
                        $mensaje_pinpad = $cuadre_detallado['debito_pinpad']['mensaje'] ?? 'CUADRADO';
                        
                        \App\Models\CierresMetodosPago::create([
                            'id_cierre' => $objcierres->id,
                            'tipo_pago' => 2, // Débito
                            'subtipo' => 'pinpad',
                            'moneda' => 'BS',
                            'monto_real' => $monto_pinpad_real,
                            'monto_inicial' => $monto_pinpad_inicial,
                            'monto_digital' => $monto_pinpad_digital,
                            'monto_diferencia' => $monto_pinpad_diferencia,
                            'estado_cuadre' => $estado_pinpad,
                            'mensaje_cuadre' => $mensaje_pinpad,
                            'metadatos' => [
                                'lotes' => $lotes_para_guardar['lotes_pinpad'],
                                'cantidad_lotes' => count($lotes_para_guardar['lotes_pinpad']),
                                'fecha' => $today
                            ]
                        ]);
                    }
                    
                    // === REGISTRO 2: OTROS PUNTOS (Total con cuadre) ===
                    if (!empty($lotes_para_guardar['otros_puntos'])) {
                        // OPTIMIZACIÓN: Usar valores directamente del cuadre_detallado (ya calculados por cerrarFun)
                        // NO recalcular, solo usar los valores ya calculados
                        $monto_otros_real = floatval($cuadre_detallado['debito_otros']['real'] ?? 0);
                        $monto_otros_digital = floatval($cuadre_detallado['debito_otros']['digital'] ?? 0);
                        $monto_otros_inicial = floatval($cuadre_detallado['debito_otros']['inicial'] ?? 0);
                        $monto_otros_diferencia = floatval($cuadre_detallado['debito_otros']['diferencia'] ?? 0);
                        $estado_otros = $cuadre_detallado['debito_otros']['estado'] ?? 'cuadrado';
                        $mensaje_otros = $cuadre_detallado['debito_otros']['mensaje'] ?? 'CUADRADO';
                        
                        \App\Models\CierresMetodosPago::create([
                            'id_cierre' => $objcierres->id,
                            'tipo_pago' => 2, // Débito
                            'subtipo' => 'otros_puntos',
                            'moneda' => 'BS',
                            'monto_real' => $monto_otros_real,
                            'monto_inicial' => $monto_otros_inicial,
                            'monto_digital' => $monto_otros_digital,
                            'monto_diferencia' => $monto_otros_diferencia,
                            'estado_cuadre' => $estado_otros,
                            'mensaje_cuadre' => $mensaje_otros,
                            'metadatos' => [
                                'puntos' => $lotes_para_guardar['otros_puntos'],
                                'cantidad_puntos' => count($lotes_para_guardar['otros_puntos']),
                                'fecha' => $today
                            ]
                        ]);
                    }
                }
                
                // Si es cierre consolidado (tipo_cierre == 1), guardar también los registros de pinpad y otros puntos consolidados
                if ($tipo_cierre == 1) {
                    // Obtener todos los cierres de cajeros de esta fecha
                    $cierres_cajeros_para_consolidar = cierres::where('fecha', $today)
                        ->where('tipo_cierre', 0)
                        ->with('metodosPago')
                        ->get();
                    
                    // Consolidar lotes de pinpad y otros puntos desde todos los cierres de cajeros
                    $lotes_pinpad_consolidados = [];
                    $lotes_otros_consolidados = [];
                    
                    foreach ($cierres_cajeros_para_consolidar as $cierre_cajero) {
                        $metodos_pago = $cierre_cajero->metodosPago;
                        
                        // Buscar pinpad en los metodosPago
                        $registro_pinpad = $metodos_pago->where('subtipo', 'pinpad')->first();
                        if ($registro_pinpad) {
                            $metadatos = is_string($registro_pinpad->metadatos) 
                                ? json_decode($registro_pinpad->metadatos, true) 
                                : $registro_pinpad->metadatos;
                            
                            if (isset($metadatos['lotes'])) {
                                $lotes_pinpad_consolidados = array_merge($lotes_pinpad_consolidados, $metadatos['lotes']);
                            }
                        }
                        
                        // Buscar otros_puntos en los metodosPago
                        $registro_otros = $metodos_pago->where('subtipo', 'otros_puntos')->first();
                        if ($registro_otros) {
                            $metadatos = is_string($registro_otros->metadatos) 
                                ? json_decode($registro_otros->metadatos, true) 
                                : $registro_otros->metadatos;
                            
                            if (isset($metadatos['puntos'])) {
                                $lotes_otros_consolidados = array_merge($lotes_otros_consolidados, $metadatos['puntos']);
                            }
                        }
                    }
                    
                    // === REGISTRO 1: PINPAD CONSOLIDADO ===
                    if (!empty($lotes_pinpad_consolidados) || isset($cuadre_detallado['debito_pinpad'])) {
                        // Usar valores directamente del cuadre_detallado (ya calculados por cerrarFun)
                        $monto_pinpad_real = floatval($cuadre_detallado['debito_pinpad']['real'] ?? 0);
                        $monto_pinpad_digital = floatval($cuadre_detallado['debito_pinpad']['digital'] ?? 0);
                        $monto_pinpad_inicial = floatval($cuadre_detallado['debito_pinpad']['inicial'] ?? 0);
                        $monto_pinpad_diferencia = floatval($cuadre_detallado['debito_pinpad']['diferencia'] ?? 0);
                        $estado_pinpad = $cuadre_detallado['debito_pinpad']['estado'] ?? 'cuadrado';
                        $mensaje_pinpad = $cuadre_detallado['debito_pinpad']['mensaje'] ?? 'CUADRADO';
                        
                        \App\Models\CierresMetodosPago::create([
                            'id_cierre' => $objcierres->id,
                            'tipo_pago' => 2, // Débito
                            'subtipo' => 'pinpad',
                            'moneda' => 'BS',
                            'monto_real' => $monto_pinpad_real,
                            'monto_inicial' => $monto_pinpad_inicial,
                            'monto_digital' => $monto_pinpad_digital,
                            'monto_diferencia' => $monto_pinpad_diferencia,
                            'estado_cuadre' => $estado_pinpad,
                            'mensaje_cuadre' => $mensaje_pinpad,
                            'metadatos' => [
                                'lotes' => $lotes_pinpad_consolidados,
                                'cantidad_lotes' => count($lotes_pinpad_consolidados),
                                'fecha' => $today
                            ]
                        ]);
                    }
                    
                    // === REGISTRO 2: OTROS PUNTOS CONSOLIDADO ===
                    if (!empty($lotes_otros_consolidados) || isset($cuadre_detallado['debito_otros'])) {
                        // Usar valores directamente del cuadre_detallado (ya calculados por cerrarFun)
                        $monto_otros_real = floatval($cuadre_detallado['debito_otros']['real'] ?? 0);
                        $monto_otros_digital = floatval($cuadre_detallado['debito_otros']['digital'] ?? 0);
                        $monto_otros_inicial = floatval($cuadre_detallado['debito_otros']['inicial'] ?? 0);
                        $monto_otros_diferencia = floatval($cuadre_detallado['debito_otros']['diferencia'] ?? 0);
                        $estado_otros = $cuadre_detallado['debito_otros']['estado'] ?? 'cuadrado';
                        $mensaje_otros = $cuadre_detallado['debito_otros']['mensaje'] ?? 'CUADRADO';
                        
                        \App\Models\CierresMetodosPago::create([
                            'id_cierre' => $objcierres->id,
                            'tipo_pago' => 2, // Débito
                            'subtipo' => 'otros_puntos',
                            'moneda' => 'BS',
                            'monto_real' => $monto_otros_real,
                            'monto_inicial' => $monto_otros_inicial,
                            'monto_digital' => $monto_otros_digital,
                            'monto_diferencia' => $monto_otros_diferencia,
                            'estado_cuadre' => $estado_otros,
                            'mensaje_cuadre' => $mensaje_otros,
                            'metadatos' => [
                                'puntos' => $lotes_otros_consolidados,
                                'cantidad_puntos' => count($lotes_otros_consolidados),
                                'fecha' => $today
                            ]
                        ]);
                    }
                }


                $CajaFuerteEntradaCierreDolar = floatval($req->CajaFuerteEntradaCierreDolar);
                $CajaFuerteEntradaCierreCop = floatval($req->CajaFuerteEntradaCierreCop);
                $CajaFuerteEntradaCierreBs = floatval($req->CajaFuerteEntradaCierreBs);

                if ($tipo_cierre==1) {
                    cajas::where("concepto","INGRESO DESDE CIERRE")->where("fecha",$today)->delete();
                    cajas::where("concepto","FALTANTE DE CAJA $today")->where("fecha",$today)->delete();

                    // Usar descuadre calculado internamente
                    $descuadre_calculado = floatval($calculos['descuadre'] ?? 0);
                    if ($descuadre_calculado < -5) {
                        cajas::updateOrCreate([
                            "fecha"=>$today,
                            "concepto" => "FALTANTE DE CAJA $today",
                        ],[
                            "concepto" => "FALTANTE DE CAJA $today",
                            "categoria" => 27,
                            "id_departamento" => null,
                            "tipo" => 1,
                            "fecha" => $today,

                            "montodolar" => abs($descuadre_calculado),
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
    
    /**
     * Obtener vista totalizada de cierres por usuario
     * Usa cerrarFun para cada usuario y consolida los resultados
     */
    public function getTotalizarCierre(Request $req)
    {
        // Aumentar tiempo de ejecución para operaciones pesadas
        set_time_limit(300); // 5 minutos
        
        try {
            $fecha = $req->fechaCierre ?? $this->today();
            
            // Verificar si existe cierre de administrador guardado para esta fecha
            $cierre_admin_existente = cierres::where('fecha', $fecha)
                ->where('tipo_cierre', 1) // Tipo 1 = Administrador
                ->exists();
            
            // Obtener todos los usuarios cajeros que tienen cierres guardados en esta fecha
            // OPTIMIZACIÓN: Cargar metodosPago con eager loading para evitar N+1 queries
            $cierres_cajeros = cierres::where('fecha', $fecha)
                ->where('tipo_cierre', 0) // Solo cierres de cajero
                ->with([
                    'usuario',
                    'metodosPago' => function($query) {
                        $query->where('tipo_pago', 2); // Solo débitos (lotes)
                    }
                ])
                ->get();
            
            // Verificar que todas las cajas que tuvieron pedidos hoy hayan hecho cierre
            // Solo considerar usuarios tipo caja (tipo_usuario = 4)
            $usuarios_con_pedidos = pedidos::whereBetween('fecha_factura', ["$fecha 00:00:00", "$fecha 23:59:59"])
                ->whereNotNull('id_vendedor')
                ->join('usuarios', 'pedidos.id_vendedor', '=', 'usuarios.id')
                ->where('usuarios.tipo_usuario', 4)
                ->distinct()
                ->pluck('pedidos.id_vendedor')
                ->toArray();
            
            if (!empty($usuarios_con_pedidos)) {
                $usuarios_con_cierre = $cierres_cajeros->pluck('id_usuario')->toArray();
                $usuarios_sin_cierre = array_diff($usuarios_con_pedidos, $usuarios_con_cierre);
                
                if (!empty($usuarios_sin_cierre)) {
                    // Obtener nombres de los usuarios sin cierre
                    $nombres_usuarios_sin_cierre = usuarios::whereIn('id', $usuarios_sin_cierre)
                        ->pluck('nombre')
                        ->toArray();
                    
                    return Response::json([
                        'estado' => false,
                        'msj' => 'Las siguientes cajas tuvieron pedidos hoy pero no realizaron cierre: ' . implode(', ', $nombres_usuarios_sin_cierre)
                    ], 200);
                }
            }
            
            if ($cierres_cajeros->isEmpty()) {
                return Response::json([
                    'estado' => false,
                    'msj' => 'No hay cierres guardados para esta fecha'
                ]);
            }
            
            // Obtener IDs de todos los usuarios con cierre
            $ids_usuarios = $cierres_cajeros->pluck('id_usuario')->toArray();
            
            // ==============================================
            // 1. GENERAR CIERRE CONSOLIDADO DE TODOS
            // ==============================================
            
            // Sumar valores de efectivo real, dejar y guardar de todos los cierres
            $total_caja_usd = $cierres_cajeros->sum('efectivo_actual');
            $total_caja_cop = $cierres_cajeros->sum('efectivo_actual_cop');
            $total_caja_bs = $cierres_cajeros->sum('efectivo_actual_bs');
            
            $total_dejar_usd = $cierres_cajeros->sum('dejar_dolar');
            $total_dejar_cop = $cierres_cajeros->sum('dejar_peso');
            $total_dejar_bs = $cierres_cajeros->sum('dejar_bss');
            
            // OPTIMIZACIÓN: Usar metodosPago ya cargados con eager loading
            // Sumar lotes de débito de todos los cierres (desde CierresMetodosPago)
            $lotes_pinpad_consolidados = [];
            $lotes_otros_consolidados = [];
            
            foreach ($cierres_cajeros as $cierre) {
                // Usar relación ya cargada (sin query adicional)
                $metodos_pago = $cierre->metodosPago;
                
                // Buscar pinpad en los metodosPago ya cargados
                $registro_pinpad = $metodos_pago->where('subtipo', 'pinpad')->first();
                if ($registro_pinpad) {
                    $metadatos = is_string($registro_pinpad->metadatos) 
                        ? json_decode($registro_pinpad->metadatos, true) 
                        : $registro_pinpad->metadatos;
                    
                    if (isset($metadatos['lotes'])) {
                        $lotes_pinpad_consolidados = array_merge($lotes_pinpad_consolidados, $metadatos['lotes']);
                    }
                }
                
                // Buscar otros_puntos en los metodosPago ya cargados
                $registro_otros = $metodos_pago->where('subtipo', 'otros_puntos')->first();
                if ($registro_otros) {
                    $metadatos = is_string($registro_otros->metadatos) 
                        ? json_decode($registro_otros->metadatos, true) 
                        : $registro_otros->metadatos;
                    
                    if (isset($metadatos['puntos'])) {
                        $lotes_otros_consolidados = array_merge($lotes_otros_consolidados, $metadatos['puntos']);
                    }
                }
            }
            
            // Llamar a cerrarFun UNA SOLA VEZ con TODOS los usuarios
            // OPTIMIZACIÓN: Desactivar check_pendiente ya que estamos totalizando cierres ya guardados
            $resultado_cierre_consolidado = $this->cerrarFun(
                $fecha,
                [
                    "caja_usd" => $total_caja_usd,
                    "caja_cop" => $total_caja_cop,
                    "caja_bs" => $total_caja_bs,
                    "dejar_usd" => $total_dejar_usd,
                    "dejar_cop" => $total_dejar_cop,
                    "dejar_bs" => $total_dejar_bs,
                    "puntos_adicionales" => array_merge($lotes_pinpad_consolidados, $lotes_otros_consolidados)
                ],
                [
                    'totalizarcierre' => true,
                    'grafica' => false,
                    'check_pendiente' => false, // Optimización: no verificar pendientes en totalizar
                    'usuario' => $ids_usuarios // Pasar IDs de usuarios para filtrar pedidos
                ]
            );
            
            // Extraer datos de cerrarFun (puede retornar JsonResponse o array)
            $cierre_consolidado = null;
            if ($resultado_cierre_consolidado instanceof \Illuminate\Http\JsonResponse) {
                $cierre_consolidado = $resultado_cierre_consolidado->getData(true);
            } else {
                $cierre_consolidado = is_array($resultado_cierre_consolidado) ? $resultado_cierre_consolidado : json_decode($resultado_cierre_consolidado, true);
            }
            
            // ==============================================
            // 2. GENERAR CIERRES INDIVIDUALES DE CADA CAJERO
            // ==============================================
            $cierres_individuales = [];
            
            foreach ($cierres_cajeros as $cierre) {
                $usuario = $cierre->usuario;
                
                // OPTIMIZACIÓN: Usar metodosPago ya cargados con eager loading (sin queries adicionales)
                $metodos_pago = $cierre->metodosPago;
                
                // Obtener lotes individuales del usuario
                $lotes_pinpad_usuario = [];
                $registro_pinpad = $metodos_pago->where('subtipo', 'pinpad')->first();
                if ($registro_pinpad) {
                    $metadatos = is_string($registro_pinpad->metadatos) 
                        ? json_decode($registro_pinpad->metadatos, true) 
                        : $registro_pinpad->metadatos;
                    $lotes_pinpad_usuario = $metadatos['lotes'] ?? [];
                }
                
                $lotes_otros_usuario = [];
                $registro_otros = $metodos_pago->where('subtipo', 'otros_puntos')->first();
                if ($registro_otros) {
                    $metadatos = is_string($registro_otros->metadatos) 
                        ? json_decode($registro_otros->metadatos, true) 
                        : $registro_otros->metadatos;
                    $lotes_otros_usuario = $metadatos['puntos'] ?? [];
                }
                
                // Llamar a cerrarFun para este usuario específico
                // OPTIMIZACIÓN: Desactivar check_pendiente ya que estamos totalizando cierres ya guardados
                $resultado_cierre_individual = $this->cerrarFun(
                    $fecha,
                    [
                        "caja_usd" => $cierre->efectivo_actual ?? 0,
                        "caja_cop" => $cierre->efectivo_actual_cop ?? 0,
                        "caja_bs" => $cierre->efectivo_actual_bs ?? 0,
                        "dejar_usd" => $cierre->dejar_dolar ?? 0,
                        "dejar_cop" => $cierre->dejar_peso ?? 0,
                        "dejar_bs" => $cierre->dejar_bss ?? 0,
                        "puntos_adicionales" => array_merge($lotes_pinpad_usuario, $lotes_otros_usuario)
                    ],
                    [
                        'totalizarcierre' => false,
                        'grafica' => false,
                        'check_pendiente' => false, // Optimización: no verificar pendientes en totalizar
                        'usuario' => [$cierre->id_usuario]
                    ]
                );
                
                // Extraer datos de cerrarFun (puede retornar JsonResponse o array)
                $cierre_individual = null;
                if ($resultado_cierre_individual instanceof \Illuminate\Http\JsonResponse) {
                    $cierre_individual = $resultado_cierre_individual->getData(true);
                } else {
                    $cierre_individual = is_array($resultado_cierre_individual) ? $resultado_cierre_individual : json_decode($resultado_cierre_individual, true);
                }
                
                // Usar valores ya calculados por cerrarFun() (no recalcular)
                $efectivo_usd = $cierre->efectivo_actual ?? 0;
                $efectivo_cop = $cierre->efectivo_actual_cop ?? 0;
                $efectivo_bs = $cierre->efectivo_actual_bs ?? 0;
                
                $dejar_usd = $cierre->dejar_dolar ?? 0;
                $dejar_cop = $cierre->dejar_peso ?? 0;
                $dejar_bs = $cierre->dejar_bss ?? 0;
                
                // Usar efectivo_guardado ya calculado por cerrarFun()
                $guardar_usd = floatval($cierre_individual['efectivo_guardado_usd'] ?? ($efectivo_usd - $dejar_usd));
                $guardar_cop = floatval($cierre_individual['efectivo_guardado_cop'] ?? ($efectivo_cop - $dejar_cop));
                $guardar_bs = floatval($cierre_individual['efectivo_guardado_bs'] ?? ($efectivo_bs - $dejar_bs));
                
                $cierres_individuales[] = [
                    'id_usuario' => $cierre->id_usuario,
                    'nombre_usuario' => $usuario->nombre ?? 'Desconocido',
                    'usuario' => $usuario->usuario ?? 'N/A',
                    'cierre' => $cierre_individual,
                    'datos_usuario' => [
                        'efectivo_real' => [
                            'usd' => $efectivo_usd,
                            'cop' => $efectivo_cop,
                            'bs' => $efectivo_bs
                        ],
                        'dejar_caja' => [
                            'usd' => $dejar_usd,
                            'cop' => $dejar_cop,
                            'bs' => $dejar_bs
                        ],
                        'efectivo_guardado' => [
                            'usd' => $guardar_usd,
                            'cop' => $guardar_cop,
                            'bs' => $guardar_bs
                        ]
                    ]
                ];
            }
            
            // Usar efectivo_guardado ya calculado por cerrarFun() (no recalcular)
            $total_guardar_usd = floatval($cierre_consolidado['efectivo_guardado_usd'] ?? ($total_caja_usd - $total_dejar_usd));
            $total_guardar_cop = floatval($cierre_consolidado['efectivo_guardado_cop'] ?? ($total_caja_cop - $total_dejar_cop));
            $total_guardar_bs = floatval($cierre_consolidado['efectivo_guardado_bs'] ?? ($total_caja_bs - $total_dejar_bs));
            
            // Retornar cierre consolidado + cierres individuales + datos introducidos
            return Response::json([
                'estado' => true,
                'cierre_consolidado' => $cierre_consolidado,
                'datos_consolidados' => [
                    'efectivo_real' => [
                        'usd' => $total_caja_usd,
                        'cop' => $total_caja_cop,
                        'bs' => $total_caja_bs
                    ],
                    'dejar_caja' => [
                        'usd' => $total_dejar_usd,
                        'cop' => $total_dejar_cop,
                        'bs' => $total_dejar_bs
                    ],
                    'efectivo_guardado' => [
                        'usd' => $total_guardar_usd,
                        'cop' => $total_guardar_cop,
                        'bs' => $total_guardar_bs
                    ]
                ],
                'cierres_individuales' => $cierres_individuales,
                'fecha' => $fecha,
                'numero_usuarios' => count($ids_usuarios),
                'cierre_admin_guardado' => $cierre_admin_existente
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                "msj" => "Error al obtener totalizado: " . $e->getMessage() . " LINEA " . $e->getLine(), 
                "estado" => false
            ]);
        }
    }
    
    /**
     * Guardar métodos de pago en tabla flexible para análisis y agrupación
     * 
     * @param int $id_cierre ID del cierre
     * @param array $cuadre_detallado Array con los cuadres detallados de cerrarFun
     */
    /**
     * Guarda métodos de pago RESUMEN en CierresMetodosPago
     * 
     * IMPORTANTE: NO guarda lotes de débito individuales (pinpad, otros puntos)
     * Solo guarda: efectivo (USD/BS/COP), transferencia, biopago, crédito
     * Los lotes de débito se guardan individualmente en guardarCierre()
     */
    private function guardarMetodosPagoCierre($id_cierre, $cuadre_detallado)
    {
        if (empty($cuadre_detallado) || !is_array($cuadre_detallado)) {
            return;
        }
        
        // Mapeo de tipos de pago según el sistema
        // 1 = transferencia, 2 = débito (SKIP), 3 = efectivo, 4 = crédito, 5 = biopago
        
        foreach ($cuadre_detallado as $tipo_key => $cuadre_item) {
            if (!is_array($cuadre_item)) {
                continue;
            }
            
            $tipo_pago = null;
            $subtipo = $cuadre_item['subtipo'] ?? null;
            $moneda = strtoupper($cuadre_item['moneda'] ?? 'USD');
            
            // Determinar tipo_pago según el tipo del cuadre
            switch ($tipo_key) {
                case 'transferencia':
                    $tipo_pago = 1;
                    break;
                case 'debito_pinpad':
                case 'debito_otros':
                    // SKIP: Los lotes de débito se guardan individualmente más adelante
                    // No guardamos los totales aquí para evitar duplicación
                    continue 2; // Salta al siguiente item del foreach
                case 'efectivo_usd':
                case 'efectivo_bs':
                case 'efectivo_cop':
                    $tipo_pago = 3;
                    break;
                case 'credito':
                    $tipo_pago = 4;
                    break;
                case 'biopago':
                    $tipo_pago = 5;
                    break;
                default:
                    // Para métodos nuevos, intentar obtener tipo del metadato
                    $tipo_pago = $cuadre_item['tipo_pago'] ?? null;
                    break;
            }
            
            if ($tipo_pago === null) {
                continue; // Skip si no se puede determinar el tipo
            }
            
            // Guardar método de pago
            $metodoPago = new \App\Models\CierresMetodosPago();
            $metodoPago->id_cierre = $id_cierre;
            $metodoPago->tipo_pago = $tipo_pago;
            $metodoPago->subtipo = $subtipo;
            $metodoPago->moneda = $moneda;
            $metodoPago->monto_real = floatval($cuadre_item['real'] ?? 0);
            $metodoPago->monto_inicial = floatval($cuadre_item['inicial'] ?? 0);
            $metodoPago->monto_digital = floatval($cuadre_item['digital'] ?? 0);
            $metodoPago->monto_diferencia = floatval($cuadre_item['diferencia'] ?? 0);
            $metodoPago->estado_cuadre = $cuadre_item['estado'] ?? null;
            $metodoPago->mensaje_cuadre = $cuadre_item['mensaje'] ?? null;
            
            // Guardar metadatos adicionales (tipo, subtipo, etc.)
            $metadatos = [
                'tipo_key' => $tipo_key,
                'tipo' => $cuadre_item['tipo'] ?? null,
            ];
            if (isset($cuadre_item['transacciones'])) {
                $metadatos['cantidad_transacciones'] = count($cuadre_item['transacciones']);
            }
            $metodoPago->metadatos = $metadatos;
            
            // Guardar método de pago
            $metodoPago->save();
        }
    }
    public function addNewPedido()
    {
        $usuario = session("id_usuario");
        $today = $this->today();

        // OPTIMIZACIÓN: Usar caché para evitar consultas repetidas en el mismo día
        // La clave del caché es única por usuario y fecha
        $cache_key = "cierre_guardado_usuario_{$usuario}_fecha_{$today}";
        
        // Intentar obtener del caché primero (más rápido)
        $tiene_cierre_guardado = Cache::remember($cache_key, now()->endOfDay(), function () use ($usuario, $today) {
            // Solo verificar si existe (más eficiente que first())
            return cierres::where('fecha', $today)
                ->where('id_usuario', $usuario)
                ->where('tipo_cierre', 0) // Solo cierres de cajero
                ->exists();
        });

        if ($tiene_cierre_guardado) {
            return Response::json([
                'msj' => 'No puede crear nuevos pedidos. Ya existe un cierre guardado para el día de hoy. Si necesita crear más pedidos, debe eliminar el cierre primero.',
                'estado' => false
            ], 200);
        }

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
        
        // Detectar si el usuario es administrador (nivel 1)
        $es_admin = session("nivel") == 1 || session("tipo_usuario") == 1;

        $sucursal = sucursal::all()->first();
        
        // Si es admin, mostrar cierre consolidado; si es cajero, mostrar su cierre individual
        if ($es_admin) {
            // Obtener cierre de administrador (tipo_cierre = 1)
            $cierre = cierres::with("usuario")
                ->where("fecha", $fechareq)
                ->where('tipo_cierre', 1)
                ->first();
            
            if (!$cierre) {
                return "No hay cierre de administrador guardado para esta fecha";
            }
            
            // Obtener todos los cierres de cajeros para mostrar individuales
            $cierres_cajeros = cierres::with("usuario")
                ->where("fecha", $fechareq)
                ->where('tipo_cierre', 0)
                ->get();
            
            $id_vendedor = $cierres_cajeros->pluck('id_usuario')->toArray();
            $totalizarcierre = true;
        } else {
            // Cajero: obtener su cierre individual
            $id_vendedor = [$usuarioLogin];
            $cierre = cierres::with("usuario")
                ->where("fecha", $fechareq)
                ->where('id_usuario', $usuarioLogin)
                ->where('tipo_cierre', 0)
                ->first();
            
            if (!$cierre) {
                return "No hay cierre guardado para esta fecha";
            }
            
            $cierres_cajeros = collect([$cierre]);
            $totalizarcierre = false;
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

        // Extraer datos del cuadre_detallado guardado (SIN CÁLCULOS - solo de BD)
        $cuadre_consolidado = null;
        $cuadres_individuales = [];
        
        if ($totalizarcierre && $cierre) {
            // Cierre consolidado desde el cierre admin (sin recálculos)
            $cuadre_consolidado = is_string($cierre->cuadre_detallado) 
                ? json_decode($cierre->cuadre_detallado, true) 
                : $cierre->cuadre_detallado;
            
            \Log::info('=== VER CIERRE - CONSOLIDADO ===');
            \Log::info('VER CIERRE - Cierre consolidado ID: ' . $cierre->id . ', Tipo: ' . $cierre->tipo_cierre . ', Fecha: ' . $fechareq);
            \Log::info('VER CIERRE - Total cierres de cajeros: ' . $cierres_cajeros->count());
            
            // Sumar valores de debito_otros de todas las cajas individuales para comparar
            $suma_individuales_debito_otros = 0;
            $valores_individuales = [];
            
            // Cuadres individuales desde cierres de cajeros (sin recálculos)
            foreach ($cierres_cajeros as $cierre_cajero) {
                $cuadre_individual = is_string($cierre_cajero->cuadre_detallado) 
                    ? json_decode($cierre_cajero->cuadre_detallado, true) 
                    : $cierre_cajero->cuadre_detallado;
                
                $debito_otros_individual = floatval($cuadre_individual['debito_otros']['real'] ?? 0);
                $suma_individuales_debito_otros += $debito_otros_individual;
                
                $valores_individuales[] = [
                    'cierre_id' => $cierre_cajero->id,
                    'usuario_id' => $cierre_cajero->id_usuario,
                    'debito_otros_real' => $debito_otros_individual
                ];
                
                \Log::info('VER CIERRE - Cierre individual ID: ' . $cierre_cajero->id . ', Usuario: ' . $cierre_cajero->id_usuario . ', Debito otros real: ' . $debito_otros_individual);
                
                $cuadres_individuales[] = [
                    'cierre' => $cierre_cajero,
                    'cuadre_detallado' => $cuadre_individual,
                    'usuario' => $cierre_cajero->usuario
                ];
            }
            
            $debito_otros_consolidado = floatval($cuadre_consolidado['debito_otros']['real'] ?? 0);
            
            \Log::info('=== COMPARACIÓN DEBITO OTROS ===');
            \Log::info('VER CIERRE - Suma de individuales: ' . $suma_individuales_debito_otros . ' Bs');
            \Log::info('VER CIERRE - Valor en consolidado: ' . $debito_otros_consolidado . ' Bs');
            \Log::info('VER CIERRE - Diferencia: ' . ($suma_individuales_debito_otros - $debito_otros_consolidado) . ' Bs');
            \Log::info('VER CIERRE - Valores individuales: ' . json_encode($valores_individuales));
            \Log::info('VER CIERRE - Cuadre completo debito_otros consolidado: ' . json_encode($cuadre_consolidado['debito_otros'] ?? []));
            
            // También verificar desde CierresMetodosPago
            $suma_metodos_pago = 0;
            foreach ($cierres_cajeros as $cierre_cajero) {
                $registro_otros = \App\Models\CierresMetodosPago::where('id_cierre', $cierre_cajero->id)
                    ->where('tipo_pago', 2)
                    ->where('subtipo', 'otros_puntos')
                    ->first();
                
                if ($registro_otros) {
                    $monto_real = floatval($registro_otros->monto_real ?? 0);
                    $suma_metodos_pago += $monto_real;
                    \Log::info('VER CIERRE - CierresMetodosPago - Cierre ID: ' . $cierre_cajero->id . ', monto_real: ' . $monto_real);
                }
            }
            
            \Log::info('VER CIERRE - Suma desde CierresMetodosPago: ' . $suma_metodos_pago . ' Bs');
            \Log::info('VER CIERRE - Comparación: Consolidado=' . $debito_otros_consolidado . ', Suma Individuales=' . $suma_individuales_debito_otros . ', Suma MetodosPago=' . $suma_metodos_pago);
            
        } else if ($cierre) {
            // Cierre individual (sin recálculos)
            $cuadre_consolidado = is_string($cierre->cuadre_detallado) 
                ? json_decode($cierre->cuadre_detallado, true) 
                : $cierre->cuadre_detallado;
            
            \Log::info('VER CIERRE - Cierre individual ID: ' . $cierre->id);
            \Log::info('VER CIERRE - Debito otros real: ' . ($cuadre_consolidado['debito_otros']['real'] ?? 'NO EXISTE'));
        }
        
        // Obtener datos SOLO de cierres y cierres_metodos_pago (SIN CÁLCULOS hasta NOTA)
        // Los datos después de NOTA sí pueden venir de otros modelos
        
        // Obtener lotes desde CierresMetodosPago guardados
        $lotes_pinpad_guardados = [];
        $lotes_otros_puntos = [];
        
        // Obtener todos los cierres del día para obtener los lotes
        $cierres_del_dia = cierres::where("fecha", $fechareq)
            ->when($totalizarcierre, function($q) {
                // Si es consolidado, obtener cierres de cajeros (tipo_cierre = 0)
                return $q->where("tipo_cierre", 0);
            }, function($q) use ($usuarioLogin) {
                // Si es individual, obtener solo el cierre del usuario
                return $q->where("tipo_cierre", 0)
                    ->when($usuarioLogin, function($q2) use ($usuarioLogin) {
                        return $q2->where("id_usuario", $usuarioLogin);
                    });
            })
            ->with('metodosPago')
            ->get();
        
        // Extraer lotes desde metadatos de CierresMetodosPago y construir resumen por banco
        $resumen_electronicos_por_banco = [];
        foreach ($cierres_del_dia as $cierre_item) {
            foreach ($cierre_item->metodosPago as $metodo_pago) {
                $metadatos = is_string($metodo_pago->metadatos) 
                    ? json_decode($metodo_pago->metadatos, true) 
                    : $metodo_pago->metadatos;
                
                if ($metodo_pago->subtipo == 'pinpad') {
                    $lotes = $metadatos['lotes'] ?? [];
                    foreach ($lotes as $lote) {
                        $banco = $lote['banco_nombre'] ?? $lote['banco'] ?? '';
                        $monto_raw = floatval($lote['monto_bs'] ?? $lote['monto'] ?? 0);
                        $lotes_pinpad_guardados[] = [
                            'banco' => $banco,
                            'lote' => $lote['terminal'] ?? $lote['lote'] ?? '',
                            'monto' => number_format($monto_raw, 2),
                        ];
                        $banco_key = $banco ?: 'Sin banco';
                        if (!isset($resumen_electronicos_por_banco[$banco_key])) {
                            $resumen_electronicos_por_banco[$banco_key] = ['puntos_venta' => 0, 'pinpad' => 0, 'transferencia' => 0];
                        }
                        $resumen_electronicos_por_banco[$banco_key]['pinpad'] += $monto_raw;
                    }
                } else if ($metodo_pago->subtipo == 'otros_puntos') {
                    $lotes = $metadatos['lotes'] ?? [];
                    foreach ($lotes as $lote) {
                        $banco = $lote['banco'] ?? '';
                        $monto_raw = floatval($lote['monto'] ?? 0);
                        $lotes_otros_puntos[] = [
                            'banco' => $banco,
                            'lote' => $lote['descripcion'] ?? $lote['lote'] ?? '',
                            'monto' => number_format($monto_raw, 2),
                        ];
                        $banco_key = $banco ?: 'Sin banco';
                        if (!isset($resumen_electronicos_por_banco[$banco_key])) {
                            $resumen_electronicos_por_banco[$banco_key] = ['puntos_venta' => 0, 'pinpad' => 0, 'transferencia' => 0];
                        }
                        $resumen_electronicos_por_banco[$banco_key]['puntos_venta'] += $monto_raw;
                    }
                }
            }
        }
        // Sumar transferencias por banco desde referencias (tipo 1 = Transferencia)
        foreach ($pagos_referencias as $ref) {
            if (intval($ref->tipo) === 1) {
                $banco_key = trim($ref->banco) ?: 'Sin banco';
                if (!isset($resumen_electronicos_por_banco[$banco_key])) {
                    $resumen_electronicos_por_banco[$banco_key] = ['puntos_venta' => 0, 'pinpad' => 0, 'transferencia' => 0];
                }
                $resumen_electronicos_por_banco[$banco_key]['transferencia'] += floatval($ref->monto);
            }
        }
        ksort($resumen_electronicos_por_banco);
        
        // Obtener caja inicial desde cuadre_detallado guardado (valores guardados en cierres)
        $caja_inicial = 0;
        $caja_inicialpeso = 0;
        $caja_inicialbs = 0;
        
        if ($cuadre_consolidado) {
            // Leer valores iniciales desde cuadre_detallado guardado
            $caja_inicial = floatval($cuadre_consolidado['efectivo_usd']['inicial'] ?? 0);
            $caja_inicialpeso = floatval($cuadre_consolidado['efectivo_cop']['inicial'] ?? 0);
            $caja_inicialbs = floatval($cuadre_consolidado['efectivo_bs']['inicial'] ?? 0);
        }
        
        // Construir facturado solo con valores de cierres (para compatibilidad)
        $facturado = [
            // Valores de cierres guardados
            "precio" => $cierre->precio ?? 0,
            "precio_base" => $cierre->precio_base ?? 0,
            "ganancia" => $cierre->ganancia ?? 0,
            "porcentaje" => $cierre->porcentaje ?? 0,
            "desc_total" => $cierre->desc_total ?? 0,
            "numventas" => $cierre->numventas ?? 0,
            "total" => $cierre->totalventa ?? 0,
            
            // Métodos de pago desde cierres (valores guardados)
            "1" => $cierre->transferencia ?? 0,
            "2" => $cierre->debito ?? 0,
            "3" => $cierre->efectivo ?? 0,
            "4" => 0, // Crédito - viene de otros modelos (después de NOTA)
            "5" => $cierre->caja_biopago ?? 0,
            
            // Totales guardados
            "total_caja" => $cierre->efectivo ?? 0,
            "total_punto" => $cierre->debito ?? 0,
            "total_biopago" => $cierre->caja_biopago ?? 0,
            
            // Caja inicial desde cuadre_detallado guardado (valores guardados en cierres)
            "caja_inicial" => $caja_inicial,
            "caja_inicialpeso" => $caja_inicialpeso,
            "caja_inicialbs" => $caja_inicialbs,
            
            // Lotes desde CierresMetodosPago
            "lotes" => array_merge($lotes_pinpad_guardados, $lotes_otros_puntos),
            "biopagos" => $cierre->biopagoserial ? [[
                "serial" => $cierre->biopagoserial,
                "monto" => $cierre->biopagoserialmontobs ?? 0
            ]] : [],
        ];
        
        // Valores que vienen de otros modelos (después de NOTA - se calculan directamente)
        // Estos valores se usan DESPUÉS de la sección NOTA, así que está bien calcularlos
        // NO usar cerrarFun() - calcular directamente desde modelos
        
        // Total inventario
        $total_inventario = DB::table("inventarios")
            ->select(DB::raw("sum(precio*cantidad) as suma"))->first()->suma ?? 0;
        $total_inventario_base = DB::table("inventarios")
            ->select(DB::raw("sum(precio_base*cantidad) as suma"))->first()->suma ?? 0;
        
        // Créditos totales
        $cred_total = clientes::selectRaw("*,@credito := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND tipo=4) as credito,
        @abono := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND cuenta=0) as abono,
        (@credito-@abono) as saldo")
            ->get(["saldo"])
            ->sum("saldo");
        
        // Abonos del día
        $pedidos_del_dia = pedidos::where("fecha_factura", "LIKE", $fechareq . "%")
            ->where("estado", 1)
            ->whereIn("id_vendedor", $id_vendedor)
            ->with(['pagos', 'cliente'])
            ->get();
        
        $abonosdeldia = 0;
        $pedidos_abonos = $pedidos_del_dia
            ->filter(function($pedido) {
                // Filtrar pedidos que tienen abonos (cuenta = 0)
                return $pedido->pagos->where("cuenta", 0)->where("monto", "<>", 0)->isNotEmpty();
            })
            ->map(function ($q) use (&$abonosdeldia) {
                $saldoDebe = $q->pagos->where("tipo", 4)->sum("monto");
                $saldoAbono = $q->pagos->where("cuenta", 0)->sum("monto");
                
                $q->saldoDebe = $saldoDebe;
                $q->saldoAbono = $saldoAbono;
                
                $abonosdeldia += $q->pagos->where("cuenta", 0)->sum("monto");
                return $q;
            });
        // Calcular cierre_tot desde valores guardados (no calculado)
        $cierre_tot = $cierre->debito + $cierre->efectivo + $cierre->transferencia + $cierre->caja_biopago;
        
        // Calcular facturado_tot desde valores guardados (no calculado)
        $facturado_tot = $facturado[2] + $facturado[3] + $facturado[1] + $facturado[5];
        
        $arr_send = [
            "referencias" => $pagos_referencias,
            "resumen_electronicos_por_banco" => $resumen_electronicos_por_banco,
            "cierre" => $cierre,
            "cierre_tot" => moneda($cierre_tot),

            // Valores que vienen de otros modelos (después de NOTA)
            "total_inventario" => ($total_inventario),
            "total_inventario_format" => moneda($total_inventario),
            "total_inventario_base" => ($total_inventario_base),
            "total_inventario_base_format" => moneda($total_inventario_base),

            // Valores desde cierres guardados (sin cálculos)
            "precio" => moneda($cierre->precio ?? 0),
            "precio_base" => moneda($cierre->precio_base ?? 0),
            "ganancia" => moneda(round($cierre->ganancia ?? 0, 2)),
            "porcentaje" => moneda($cierre->porcentaje ?? 0),
            "desc_total" => moneda(round($cierre->desc_total ?? 0, 2)),
            "facturado" => $facturado,
            "facturado_tot" => moneda($facturado_tot),
            "sucursal" => $sucursal,
            "movimientos" => $movimientos,
            "movimientosInventario" => $movimientosInventario,
            
            // Datos del cuadre desde cierres guardados (sin cálculos)
            "cuadre_consolidado" => $cuadre_consolidado,
            "cuadres_individuales" => $cuadres_individuales,
            "totalizarcierre" => $totalizarcierre,
            "numero_cajas" => count($cuadres_individuales),
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



        $arr_send["cierre"]["debito"] = ($arr_send["cierre"]["debito"]);
        $arr_send["cierre"]["efectivo"] = ($arr_send["cierre"]["efectivo"]);
        $arr_send["cierre"]["transferencia"] = ($arr_send["cierre"]["transferencia"]);
        $arr_send["cierre"]["caja_biopago"] = ($arr_send["cierre"]["caja_biopago"]);

        $arr_send["cierre"]["dejar_dolar"] = ($arr_send["cierre"]["dejar_dolar"]);
        $arr_send["cierre"]["dejar_peso"] = ($arr_send["cierre"]["dejar_peso"]);
        $arr_send["cierre"]["dejar_bss"] = ($arr_send["cierre"]["dejar_bss"]);
        $arr_send["cierre"]["tasa"] = ($arr_send["cierre"]["tasa"]);
        $arr_send["cierre"]["tasacop"] = ($arr_send["cierre"]["tasacop"]);
        
        $arr_send["cierre"]["numreportez"] = ($arr_send["cierre"]["numreportez"]);
        $arr_send["cierre"]["ventaexcento"] = ($arr_send["cierre"]["ventaexcento"]);
        $arr_send["cierre"]["ventagravadas"] = ($arr_send["cierre"]["ventagravadas"]);
        $arr_send["cierre"]["ivaventa"] = ($arr_send["cierre"]["ivaventa"]);
        $arr_send["cierre"]["totalventa"] = ($arr_send["cierre"]["totalventa"]);
        $arr_send["cierre"]["ultimafactura"] = ($arr_send["cierre"]["ultimafactura"]);

        $arr_send["cajas"]["caja_montodolar"] = ($caja_montodolar);
        $arr_send["cajas"]["caja_montopeso"] = ($caja_montopeso);
        $arr_send["cajas"]["caja_montobs"] = ($caja_montobs);


        $arr_send["cierre"]["efectivo_guardado"] = ($arr_send["cierre"]["efectivo_guardado"]);
        $arr_send["cierre"]["efectivo_guardado_cop"] = ($arr_send["cierre"]["efectivo_guardado_cop"]);
        $arr_send["cierre"]["efectivo_guardado_bs"] = ($arr_send["cierre"]["efectivo_guardado_bs"]);
        $arr_send["facturado"]["total"] = ($arr_send["facturado"]["total"]);
        $arr_send["facturado"]["caja_inicial"] = ($arr_send["facturado"]["caja_inicial"]);
        $arr_send["facturado"]["caja_inicialpeso"] = ($arr_send["facturado"]["caja_inicialpeso"]);
        $arr_send["facturado"]["caja_inicialbs"] = ($arr_send["facturado"]["caja_inicialbs"]);

        $arr_send["facturado"]["total_caja"] = ($arr_send["facturado"]["total_caja"]);
        $arr_send["facturado"]["total_punto"] = ($arr_send["facturado"]["total_punto"]);
        $arr_send["facturado"]["total_biopago"] = ($arr_send["facturado"]["total_biopago"]);

        $arr_send["facturado"]["1"] = ($arr_send["facturado"]["1"]);
        $arr_send["facturado"]["2"] = ($arr_send["facturado"]["2"]);
        $arr_send["facturado"]["3"] = ($arr_send["facturado"]["3"]);
        $arr_send["facturado"]["4"] = ($arr_send["facturado"]["4"]);
        $arr_send["facturado"]["5"] = ($arr_send["facturado"]["5"]);

        $arr_send["total_inventario_format"] = ($arr_send["total_inventario_format"]);
        $arr_send["total_inventario_base_format"] = ($arr_send["total_inventario_base_format"]);
        $arr_send["precio"] = ($arr_send["precio"]);
        $arr_send["precio_base"] = ($arr_send["precio_base"]);
        $arr_send["ganancia"] = ($arr_send["ganancia"]);
        $arr_send["porcentaje"] = ($arr_send["porcentaje"]);
        $arr_send["desc_total"] = ($arr_send["desc_total"]);
        $arr_send["cierre_tot"] = ($arr_send["cierre_tot"]);
        $arr_send["facturado_tot"] = ($arr_send["facturado_tot"]);

        $arr_send["cierre"]["debito"] = ($arr_send["cierre"]["debito"]);
        $arr_send["cierre"]["efectivo"] = ($arr_send["cierre"]["efectivo"]);
        $arr_send["cierre"]["transferencia"] = ($arr_send["cierre"]["transferencia"]);
        $arr_send["cierre"]["caja_biopago"] = ($arr_send["cierre"]["caja_biopago"]);

        $arr_send["cierre"]["dejar_dolar"] = ($arr_send["cierre"]["dejar_dolar"]);
        $arr_send["cierre"]["dejar_peso"] = ($arr_send["cierre"]["dejar_peso"]);
        $arr_send["cierre"]["dejar_bss"] = ($arr_send["cierre"]["dejar_bss"]);
        $arr_send["cierre"]["tasa"] = ($arr_send["cierre"]["tasa"]);
        $arr_send["cierre"]["tasacop"] = ($arr_send["cierre"]["tasacop"]);

        $arr_send["cierre"]["efectivo_guardado"] = ($arr_send["cierre"]["efectivo_guardado"]);
        $arr_send["cierre"]["efectivo_guardado_cop"] = ($arr_send["cierre"]["efectivo_guardado_cop"]);
        $arr_send["cierre"]["efectivo_guardado_bs"] = ($arr_send["cierre"]["efectivo_guardado_bs"]);

        $arr_send["facturado"]["numventas"] = ($arr_send["facturado"]["numventas"]);
        $arr_send["facturado"]["total"] = ($arr_send["facturado"]["total"]);
        $arr_send["facturado"]["caja_inicial"] = ($arr_send["facturado"]["caja_inicial"]);
        $arr_send["facturado"]["caja_inicialpeso"] = ($arr_send["facturado"]["caja_inicialpeso"]);
        $arr_send["facturado"]["caja_inicialbs"] = ($arr_send["facturado"]["caja_inicialbs"]);

        $arr_send["facturado"]["total_caja"] = ($arr_send["facturado"]["total_caja"]);
        $arr_send["facturado"]["total_punto"] = ($arr_send["facturado"]["total_punto"]);
        $arr_send["facturado"]["total_biopago"] = ($arr_send["facturado"]["total_biopago"]);

        $arr_send["facturado"]["1"] = ($arr_send["facturado"]["1"]);
        $arr_send["facturado"]["2"] = ($arr_send["facturado"]["2"]);
        $arr_send["facturado"]["3"] = ($arr_send["facturado"]["3"]);
        $arr_send["facturado"]["4"] = ($arr_send["facturado"]["4"]);
        $arr_send["facturado"]["5"] = ($arr_send["facturado"]["5"]);

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
            // Descarga CSV: una tabla, una fila por pedido. Columnas: métodos de pago (original + USD), ítems (venta bruta, descuento, venta neta), Total_pagos_USD, Total_items_USD, Diferencia.
            if ($req->get('descargar_csv') == '1') {
                $tasa_bs = $cierre->tasa ?? 36;
                $tasa_cop = $cierre->tasacop ?? 4000;
                $tasa_bs_safe = $tasa_bs > 0 ? $tasa_bs : 36;
                $tasa_cop_safe = $tasa_cop > 0 ? $tasa_cop : 4000;
                $max_items = 20;

                $pedidos_csv = pedidos::where("fecha_factura", "LIKE", $fechareq . "%")
                    ->where("estado", 1)
                    ->whereIn("id_vendedor", $id_vendedor)
                    ->with(['pagos', 'items' => function ($q) { $q->with('producto'); }])
                    ->orderBy('id')
                    ->get();

                $pedidos_credito_ids_csv = $pedidos_csv->pluck('pagos')->flatten()->where('tipo', 4)->pluck('id_pedido')->unique()->merge(
                    $pedidos_csv->where('export', 1)->pluck('id')
                )->unique();

                $encabezados = [
                    'id_pedido',
                    'fecha_pedido',
                    // Grupo 1: Métodos de pago (original + USD)
                    'Transferencia_original', 'Transferencia_USD',
                    'Debito_original', 'Debito_USD',
                    'Efectivo_USD_original', 'Efectivo_USD',
                    'Efectivo_BS_original', 'Efectivo_BS_USD',
                    'Efectivo_COP_original', 'Efectivo_COP_USD',
                    'Biopago_original', 'Biopago_USD',
                ];
                for ($i = 1; $i <= $max_items; $i++) {
                    $encabezados[] = "Item{$i}_nombre";
                    $encabezados[] = "Item{$i}_venta_bruta";
                    $encabezados[] = "Item{$i}_descuento_pct";
                    $encabezados[] = "Item{$i}_venta_neta";
                }
                $encabezados[] = 'Total_pagos_USD';
                $encabezados[] = 'Total_items_USD';
                $encabezados[] = 'Diferencia';

                $filas = [];
                foreach ($pedidos_csv as $pedido) {
                    $es_credito = $pedidos_credito_ids_csv->contains($pedido->id);
                    $pagos_pedido = $pedido->pagos->filter(function ($p) {
                        return in_array((int)$p->tipo, [1, 2, 3, 5]);
                    });

                    $metodos = [
                        'Transferencia' => ['original' => 0, 'usd' => 0],
                        'Debito'        => ['original' => 0, 'usd' => 0],
                        'Efectivo_USD'  => ['original' => 0, 'usd' => 0],
                        'Efectivo_BS'   => ['original' => 0, 'usd' => 0],
                        'Efectivo_COP'  => ['original' => 0, 'usd' => 0],
                        'Biopago'       => ['original' => 0, 'usd' => 0],
                    ];
                    foreach ($pagos_pedido as $pago) {
                        $moneda = ($pago->tipo == 3) ? $this->determinarMonedaPago($pago) : strtoupper($pago->moneda ?? 'USD');
                        $monto = floatval($pago->monto);
                        $monto_orig = floatval($pago->monto_original ?? $pago->monto);
                        if ($moneda === 'USD') {
                            $monto_usd = $monto;
                        } elseif ($moneda === 'BS') {
                            $monto_usd = $monto_orig / $tasa_bs_safe;
                        } else {
                            $monto_usd = $monto_orig / $tasa_cop_safe;
                        }
                        if ((int)$pago->tipo === 1) {
                            $metodos['Transferencia']['original'] += $monto_orig;
                            $metodos['Transferencia']['usd'] += $monto_usd;
                        } elseif ((int)$pago->tipo === 2) {
                            $metodos['Debito']['original'] += $monto_orig;
                            $metodos['Debito']['usd'] += $monto_usd;
                        } elseif ((int)$pago->tipo === 3) {
                            if ($moneda === 'USD') {
                                $metodos['Efectivo_USD']['original'] += $monto_orig;
                                $metodos['Efectivo_USD']['usd'] += $monto_usd;
                            } elseif ($moneda === 'BS') {
                                $metodos['Efectivo_BS']['original'] += $monto_orig;
                                $metodos['Efectivo_BS']['usd'] += $monto_usd;
                            } else {
                                $metodos['Efectivo_COP']['original'] += $monto_orig;
                                $metodos['Efectivo_COP']['usd'] += $monto_usd;
                            }
                        } elseif ((int)$pago->tipo === 5) {
                            $metodos['Biopago']['original'] += $monto_orig;
                            $metodos['Biopago']['usd'] += $monto_usd;
                        }
                    }
                    $total_pagos_usd = $metodos['Transferencia']['usd'] + $metodos['Debito']['usd']
                        + $metodos['Efectivo_USD']['usd'] + $metodos['Efectivo_BS']['usd']
                        + $metodos['Efectivo_COP']['usd'] + $metodos['Biopago']['usd'];

                    $items_pedido = $es_credito ? collect() : $pedido->items->filter(function ($item) {
                        return isset($item->producto);
                    });
                    $items_data = [];
                    $total_items_usd = 0;
                    foreach ($items_pedido as $item) {
                        $precio_unit = $item->precio_unitario ?? ($item->producto->precio ?? 0);
                        $factor_descuento = 1 - (floatval($item->descuento ?? 0) / 100);
                        $venta_bruta = $precio_unit * $item->cantidad;
                        $venta_neta = $venta_bruta * $factor_descuento;
                        $items_data[] = [
                            'nombre' => $item->producto ? ($item->producto->nombre ?? $item->producto->codigo ?? '') : '',
                            'venta_bruta' => $venta_bruta,
                            'descuento_pct' => $item->descuento ?? 0,
                            'venta_neta' => $venta_neta,
                        ];
                        $total_items_usd += $venta_neta;
                    }

                    $fila = [
                        $pedido->id,
                        $pedido->fecha_factura ? \Carbon\Carbon::parse($pedido->fecha_factura)->format('Y-m-d H:i:s') : '',
                        number_format($metodos['Transferencia']['original'], 2, '.', ''),
                        number_format($metodos['Transferencia']['usd'], 2, '.', ''),
                        number_format($metodos['Debito']['original'], 2, '.', ''),
                        number_format($metodos['Debito']['usd'], 2, '.', ''),
                        number_format($metodos['Efectivo_USD']['original'], 2, '.', ''),
                        number_format($metodos['Efectivo_USD']['usd'], 2, '.', ''),
                        number_format($metodos['Efectivo_BS']['original'], 2, '.', ''),
                        number_format($metodos['Efectivo_BS']['usd'], 2, '.', ''),
                        number_format($metodos['Efectivo_COP']['original'], 2, '.', ''),
                        number_format($metodos['Efectivo_COP']['usd'], 2, '.', ''),
                        number_format($metodos['Biopago']['original'], 2, '.', ''),
                        number_format($metodos['Biopago']['usd'], 2, '.', ''),
                    ];
                    for ($i = 0; $i < $max_items; $i++) {
                        if (isset($items_data[$i])) {
                            $fila[] = $items_data[$i]['nombre'];
                            $fila[] = number_format($items_data[$i]['venta_bruta'], 2, '.', '');
                            $fila[] = number_format($items_data[$i]['descuento_pct'], 2, '.', '');
                            $fila[] = number_format($items_data[$i]['venta_neta'], 2, '.', '');
                        } else {
                            $fila[] = '';
                            $fila[] = '';
                            $fila[] = '';
                            $fila[] = '';
                        }
                    }
                    $diferencia = $total_pagos_usd - $total_items_usd;
                    $fila[] = number_format($total_pagos_usd, 2, '.', '');
                    $fila[] = number_format($total_items_usd, 2, '.', '');
                    $fila[] = number_format($diferencia, 2, '.', '');

                    $filas[] = $fila;
                }

                $csv = function () use ($fechareq, $encabezados, $filas) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['Conciliación cierre - Una fila por pedido - Fecha: ' . $fechareq], ';');
                    fputcsv($out, [], ';');
                    fputcsv($out, $encabezados, ';');
                    foreach ($filas as $f) {
                        fputcsv($out, $f, ';');
                    }
                    fclose($out);
                };

                $nombre_archivo = 'cierre_conciliacion_' . $fechareq . '.csv';
                return response()->streamDownload($csv, $nombre_archivo, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $nombre_archivo . '"',
                ]);
            }
            $arr_send['url_descargar_csv'] = url('verCierre') . '?type=ver&fecha=' . $fechareq . '&descargar_csv=1';
            if ($usuario) {
                $arr_send['url_descargar_csv'] .= '&usuario=' . $usuario;
            }
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
        $id_pedido_original   = $req->id_pedido_original;

        // Detectar si el pedido de devolución es front-only (UUID string, no numérico)
        $esFrontOnly = !is_numeric($id_pedido_devolucion);

        // Solo buscar en BD si NO es front-only
        if (!$esFrontOnly) {
            $pedidoDevolucion = pedidos::find($id_pedido_devolucion);
            if (!$pedidoDevolucion) {
                return Response::json(["msj" => "El pedido de devolución no existe", "estado" => false]);
            }
        }

        // Validar que el pedido original exista
        $pedidoOriginal = pedidos::with(['items.producto'])->find($id_pedido_original);
        if (!$pedidoOriginal) {
            return Response::json(["msj" => "El pedido original #$id_pedido_original no existe", "estado" => false]);
        }

        // Validar que el pedido original esté facturado (estado > 0)
        if ($pedidoOriginal->estado == 0) {
            return Response::json(["msj" => "El pedido #$id_pedido_original no ha sido facturado aún", "estado" => false]);
        }

        // Si es un pedido real en BD, guardar la asignación ahora mismo
        if (!$esFrontOnly) {
            $pedidoDevolucion->isdevolucionOriginalid = $id_pedido_original;
            $pedidoDevolucion->save();
        }
        // Si es front-only, el front almacenará isdevolucionOriginalid en su estado local
        // y lo enviará junto con los datos al crear el pedido en setPagoPedido.

        // Obtener cantidades disponibles para devolución (restando devoluciones previas)
        $itemsDisponibles = $this->getItemsDisponiblesDevolucion($id_pedido_original);

        return Response::json([
            "msj" => "Pedido original #$id_pedido_original asignado correctamente",
            "estado" => true,
            "front_only" => $esFrontOnly,
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

        // Pedido front-only (UUID): no existe en BD; el front quita la asignación en estado local
        if ($id_pedido && !is_numeric($id_pedido)) {
            return Response::json([
                "msj" => "Asignación de factura original eliminada",
                "estado" => true,
                "front_only" => true,
            ]);
        }

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
