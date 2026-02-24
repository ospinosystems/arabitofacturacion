<?php

namespace App\Http\Controllers;

use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\PprEntrega;
use App\Models\clientes;
use App\Models\usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Response;

class PPRController extends Controller
{
    /**
     * Vista del módulo PPR (solo portero).
     */
    public function index()
    {
        return view('ppr.index');
    }

    /**
     * Obtener pedido para despachar (con items y totales ya entregados).
     * GET /ppr/pedido/{id}
     */
    public function getPedido($id)
    {
        $pedido = (new PedidosController)->getPedidoFun($id, 'todos', 1, 1, 1);
        if (!$pedido) {
            return Response::json(['estado' => false, 'msj' => 'Pedido no encontrado']);
        }
        if ($pedido->estado == 2) {
            return Response::json(['estado' => false, 'msj' => 'Pedido anulado']);
        }
        $entregasPorItem = PprEntrega::where('id_pedido', $id)
            ->select('id_item_pedido', DB::raw('SUM(unidades_entregadas) as total_entregado'))
            ->groupBy('id_item_pedido')
            ->pluck('total_entregado', 'id_item_pedido');
        foreach ($pedido->items as $item) {
            if (!$item->producto) {
                $item->setAttribute('entregado_hasta', 0);
                $item->setAttribute('pendiente', (float) $item->cantidad);
                continue;
            }
            $entregado = (float) $entregasPorItem->get($item->id, 0);
            $pendiente = max(0, (float) $item->cantidad - $entregado);
            $item->setAttribute('entregado_hasta', $entregado);
            $item->setAttribute('pendiente', $pendiente);
        }
        $yaDespachadoCompleto = false;
        $fechaUltimoDespacho = null;
        $itemsConPendiente = collect($pedido->items)->filter(function ($i) {
            return isset($i->pendiente) && $i->pendiente > 0;
        });
        if ($itemsConPendiente->isEmpty() && PprEntrega::where('id_pedido', $id)->exists()) {
            $yaDespachadoCompleto = true;
            $ultima = PprEntrega::where('id_pedido', $id)->orderBy('created_at', 'desc')->value('created_at');
            if ($ultima) {
                $fechaUltimoDespacho = \Carbon\Carbon::parse($ultima)->format('d/m/Y H:i');
            }
        }
        $cliente = $pedido->cliente ? $pedido->cliente : null;
        $esCF = $cliente && trim((string) $cliente->nombre) === 'CF';
        // Cliente anclado = ya se guardaron nombre/cédula/teléfono en la primera entrega parcial CF
        $clienteAnclado = $esCF && $cliente && trim((string) ($cliente->identificacion ?? '')) !== '';
        return Response::json([
            'estado' => true,
            'pedido' => $pedido,
            'cliente_es_cf' => $esCF,
            'cliente_anclado' => $clienteAnclado,
            'ya_despachado_completo' => $yaDespachadoCompleto,
            'fecha_ultimo_despacho' => $fechaUltimoDespacho,
        ]);
    }

    /**
     * Registrar entrega (marcar unidades entregadas) e imprimir ticket.
     * POST /ppr/registrar-entrega
     * Body: id_pedido, items: [{ id_item_pedido, unidades_entregadas }], (si CF) nombre, apellido, cedula, telefono
     */
    public function registrarEntrega(Request $req)
    {
        $req->validate([
            'id_pedido' => 'required|integer|exists:pedidos,id',
            'items' => 'required|array',
            'items.*.id_item_pedido' => 'required|integer|exists:items_pedidos,id',
            'items.*.unidades_entregadas' => 'required|numeric|min:0',
        ]);
        $id_pedido = (int) $req->id_pedido;
        $id_portero = session('id_usuario');
        $pedido = pedidos::with(['cliente', 'items' => function ($q) {
            $q->with('producto');
        }])->find($id_pedido);
        if (!$pedido || $pedido->estado == 2) {
            return Response::json(['estado' => false, 'msj' => 'Pedido no encontrado o anulado']);
        }
        $esCF = $pedido->cliente && trim((string) $pedido->cliente->nombre) === 'CF';
        $retiroTotal = filter_var($req->get('retiro_total'), FILTER_VALIDATE_BOOLEAN);
        // Solo exigir datos CF si es retiro parcial y el pedido aún no tiene cliente anclado (identificación guardada)
        $clienteAnclado = $esCF && $pedido->cliente && trim((string) ($pedido->cliente->identificacion ?? '')) !== '';
        if ($esCF && !$retiroTotal && !$clienteAnclado) {
            $req->validate([
                'nombre' => 'required|string|max:255',
                'apellido' => 'nullable|string|max:255',
                'cedula' => 'required|string|max:100',
                'telefono' => 'required|string|max:50',
            ]);
        }
        $itemsReq = $req->items;
        $idsItem = array_column($itemsReq, 'id_item_pedido');
        $itemsPedido = items_pedidos::where('id_pedido', $id_pedido)->whereIn('id', $idsItem)->get()->keyBy('id');
        $totalARegistrar = 0;
        foreach ($itemsReq as $row) {
            $u = (float) ($row['unidades_entregadas'] ?? 0);
            if ($u <= 0) continue;
            $item = $itemsPedido->get($row['id_item_pedido'] ?? 0);
            if (!$item) continue;
            $yaEntregado = (float) PprEntrega::where('id_pedido', $id_pedido)->where('id_item_pedido', $item->id)->sum('unidades_entregadas');
            $maxPermitido = max(0, (float) $item->cantidad - $yaEntregado);
            $totalARegistrar += min($u, $maxPermitido);
        }
        if ($totalARegistrar <= 0) {
            $ultima = PprEntrega::where('id_pedido', $id_pedido)->orderBy('created_at', 'desc')->value('created_at');
            $fecha = $ultima ? \Carbon\Carbon::parse($ultima)->format('d/m/Y H:i') : '—';
            return Response::json([
                'estado' => false,
                'msj' => 'Este pedido ya fue despachado completamente. Fecha del despacho: ' . $fecha,
            ]);
        }
        DB::beginTransaction();
        try {
            foreach ($itemsReq as $row) {
                $u = (float) ($row['unidades_entregadas'] ?? 0);
                if ($u <= 0) {
                    continue;
                }
                $item = $itemsPedido->get($row['id_item_pedido'] ?? 0);
                if (!$item) {
                    continue;
                }
                $yaEntregado = (float) PprEntrega::where('id_pedido', $id_pedido)
                    ->where('id_item_pedido', $item->id)
                    ->sum('unidades_entregadas');
                $maxPermitido = max(0, (float) $item->cantidad - $yaEntregado);
                $aRegistrar = min($u, $maxPermitido);
                if ($aRegistrar <= 0) {
                    continue;
                }
                PprEntrega::create([
                    'id_pedido' => $id_pedido,
                    'id_item_pedido' => $item->id,
                    'id_producto' => $item->id_producto,
                    'unidades_entregadas' => $aRegistrar,
                    'id_usuario_portero' => $id_portero,
                ]);
                $nuevoTotal = $yaEntregado + $aRegistrar;
                if ($item->producto && abs($nuevoTotal - (float) $item->cantidad) < 0.0001) {
                    $item->entregado = true;
                    $item->save();
                }
            }
            $nombres = null;
            $identificacion = null;
            $telefonoTicket = $pedido->cliente ? ($pedido->cliente->telefono ?? '') : '';
            if ($esCF) {
                if ($retiroTotal) {
                    $nombres = 'Consumidor final';
                    $identificacion = '';
                } else {
                    if ($clienteAnclado) {
                        $nombres = $pedido->cliente->nombre ?? '';
                        $identificacion = $pedido->cliente->identificacion ?? '';
                        $telefonoTicket = $pedido->cliente->telefono ?? '';
                    } else {
                        $nombres = trim(($req->nombre ?? '') . ' ' . ($req->apellido ?? ''));
                        $identificacion = trim($req->cedula ?? '');
                        $telefonoTicket = trim($req->get('telefono', ''));
                        // Anclar cliente: crear cliente con datos y asignar al pedido para no pedir de nuevo en próximas entregas parciales
                        $clienteNuevo = clientes::create([
                            'nombre' => $nombres,
                            'identificacion' => $identificacion,
                            'telefono' => $telefonoTicket,
                        ]);
                        $pedido->id_cliente = $clienteNuevo->id;
                        $pedido->save();
                    }
                }
            } elseif ($pedido->cliente) {
                $nombres = $pedido->cliente->nombre ?? '';
                $identificacion = $pedido->cliente->identificacion ?? '';
            }
            // Detalle PPR: por cada ítem, cantidad total, entregado, pendiente e historial de entregas (fecha/hora)
            $entregasPorItem = PprEntrega::where('id_pedido', $id_pedido)->orderBy('created_at')->get()->groupBy('id_item_pedido');
            $pprDetalle = [];
            foreach ($pedido->items as $item) {
                if (!$item->producto) {
                    continue;
                }
                $cantidad = (float) $item->cantidad;
                $entregasItem = $entregasPorItem->get($item->id, collect());
                $entregado = (float) $entregasItem->sum('unidades_entregadas');
                $pendiente = max(0, $cantidad - $entregado);
                $pprDetalle[] = [
                    'descripcion' => $item->producto->descripcion ?? '—',
                    'codigo_barras' => $item->producto->codigo_barras ?? '',
                    'codigo_proveedor' => $item->producto->codigo_proveedor ?? '',
                    'cantidad' => $cantidad,
                    'entregado' => $entregado,
                    'pendiente' => $pendiente,
                    'entregas' => $entregasItem->map(fn ($e) => [
                        'unidades_entregadas' => (float) $e->unidades_entregadas,
                        'created_at' => $e->created_at,
                    ])->values()->toArray(),
                ];
            }
            // Si todos los productos tienen por entregar (pendiente) cero, no imprimir ticket
            $todosPendienteCero = collect($pprDetalle)->every(fn ($r) => (float) ($r['pendiente'] ?? 0) <= 0);
            if ($todosPendienteCero) {
                DB::commit();
                return Response::json([
                    'estado' => true,
                    'msj' => 'Entrega registrada. No se imprime ticket (pedido ya entregado por completo).',
                ]);
            }
            $imprimirReq = new Request([
                'id' => $id_pedido,
                'nombres' => $nombres,
                'identificacion' => $identificacion,
                'telefono' => $telefonoTicket,
                'moneda' => $req->get('moneda', 'bs'),
                'printer' => $req->get('printer', 1),
                'from_ppr' => true,
                'ppr_copias' => $retiroTotal ? 1 : 2,
                'ppr_detalle' => $pprDetalle,
            ]);
            $tickera = new tickera();
            $result = $tickera->imprimir($imprimirReq);
            $resp = $result->getData(true);
            if (isset($resp['estado']) && $resp['estado'] === true) {
                DB::commit();
                return Response::json([
                    'estado' => true,
                    'msj' => 'Entrega registrada y ticket enviado a imprimir.',
                    'imprimir' => $resp,
                ]);
            }
            DB::commit();
            return Response::json([
                'estado' => true,
                'msj' => 'Entrega registrada. No se pudo imprimir: ' . ($resp['msj'] ?? ''),
                'imprimir' => $resp,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Histórico: pedidos con entregas PPR (despachados total o parcial).
     * GET /ppr/historico?fecha_desde=Y-m-d&fecha_hasta=Y-m-d&id_pedido=
     */
    public function historico(Request $req)
    {
        $fechaDesde = $req->get('fecha_desde', now()->subDays(7)->toDateString());
        $fechaHasta = $req->get('fecha_hasta', now()->toDateString());
        $idPedido = $req->get('id_pedido');
        $query = PprEntrega::with(['pedido:id,created_at,fecha_factura', 'itemPedido.producto:id,descripcion,codigo_barras,codigo_proveedor', 'usuarioPortero:id,nombre'])
            ->whereBetween(DB::raw('DATE(ppr_entregas.created_at)'), [$fechaDesde, $fechaHasta]);
        if ($idPedido) {
            $query->where('id_pedido', $idPedido);
        }
        $entregas = $query->orderBy('ppr_entregas.created_at', 'desc')->limit(500)->get();
        $porPedido = [];
        foreach ($entregas as $e) {
            $id = $e->id_pedido;
            if (!isset($porPedido[$id])) {
                $porPedido[$id] = [
                    'id_pedido' => $id,
                    'fecha' => $e->pedido->fecha_factura ?? $e->pedido->created_at ?? $e->created_at,
                    'items' => [],
                    'entregas' => [],
                ];
            }
            $producto = $e->itemPedido && $e->itemPedido->producto ? $e->itemPedido->producto : null;
            $porPedido[$id]['entregas'][] = [
                'id_item_pedido' => $e->id_item_pedido,
                'id_producto' => $e->id_producto,
                'unidades_entregadas' => (float) $e->unidades_entregadas,
                'fecha' => $e->created_at,
                'portero' => $e->usuarioPortero ? $e->usuarioPortero->nombre : null,
                'producto_desc' => $producto ? $producto->descripcion : null,
                'codigo_barras' => $producto ? $producto->codigo_barras : null,
                'codigo_proveedor' => $producto ? $producto->codigo_proveedor : null,
            ];
        }
        $totalesPorItem = [];
        foreach ($entregas as $e) {
            $key = $e->id_pedido . '_' . $e->id_item_pedido;
            if (!isset($totalesPorItem[$key])) {
                $totalesPorItem[$key] = ['id_pedido' => $e->id_pedido, 'id_item_pedido' => $e->id_item_pedido, 'total' => 0, 'producto_desc' => null];
            }
            $totalesPorItem[$key]['total'] += (float) $e->unidades_entregadas;
            if ($e->itemPedido && $e->itemPedido->producto) {
                $totalesPorItem[$key]['producto_desc'] = $e->itemPedido->producto->descripcion;
            }
        }
        return Response::json([
            'estado' => true,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'por_pedido' => array_values($porPedido),
            'totales_por_item' => array_values($totalesPorItem),
        ]);
    }

    /**
     * Lista PPR para DICI: filtros fecha, número pedido, caja (vendedor). Muestra vista con tabla y botón generar reporte.
     * GET /ppr/reporte
     */
    public function reporteLista(Request $req)
    {
        $fechaDesde = $req->get('fecha_desde', now()->subDays(30)->toDateString());
        $fechaHasta = $req->get('fecha_hasta', now()->toDateString());
        $idPedido = $req->get('id_pedido');
        $idCaja = $req->get('id_caja'); // id_vendedor del pedido (cajero/vendedor)

        $query = PprEntrega::with([
            'pedido:id,created_at,fecha_factura,id_vendedor',
            'pedido.vendedor:id,nombre,usuario',
            'itemPedido.producto:id,descripcion,codigo_barras,codigo_proveedor',
            'usuarioPortero:id,nombre',
        ])
            ->whereBetween(DB::raw('DATE(ppr_entregas.created_at)'), [$fechaDesde, $fechaHasta]);

        if ($idPedido) {
            $query->where('id_pedido', $idPedido);
        }
        if ($idCaja !== null && $idCaja !== '') {
            $query->whereHas('pedido', function ($q) use ($idCaja) {
                $q->where('id_vendedor', $idCaja);
            });
        }

        $entregas = $query->orderBy('ppr_entregas.created_at', 'desc')->limit(1000)->get();

        $porPedido = [];
        foreach ($entregas as $e) {
            $id = $e->id_pedido;
            if (!isset($porPedido[$id])) {
                $p = $e->pedido;
                $porPedido[$id] = [
                    'id_pedido' => $id,
                    'fecha_pedido' => $p ? ($p->fecha_factura ?? $p->created_at) : $e->created_at,
                    'vendedor_nombre' => $p && $p->vendedor ? ($p->vendedor->nombre ?? $p->vendedor->usuario) : '—',
                    'id_vendedor' => $p ? $p->id_vendedor : null,
                    'entregas' => [],
                ];
            }
            $producto = $e->itemPedido && $e->itemPedido->producto ? $e->itemPedido->producto : null;
            $porPedido[$id]['entregas'][] = [
                'id' => $e->id,
                'id_item_pedido' => $e->id_item_pedido,
                'id_producto' => $e->id_producto,
                'unidades_entregadas' => (float) $e->unidades_entregadas,
                'fecha_hora' => $e->created_at,
                'portero' => $e->usuarioPortero ? $e->usuarioPortero->nombre : null,
                'producto_desc' => $producto ? $producto->descripcion : null,
                'codigo_barras' => $producto ? $producto->codigo_barras : null,
                'codigo_proveedor' => $producto ? $producto->codigo_proveedor : null,
            ];
        }
        $porPedido = array_values($porPedido);

        $vendedores = usuarios::select('id', 'nombre', 'usuario')->orderBy('nombre')->get();

        return view('ppr.reporte-list', [
            'porPedido' => $porPedido,
            'vendedores' => $vendedores,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'id_pedido' => $idPedido,
            'id_caja' => $idCaja,
        ]);
    }

    /**
     * Reporte PPR en Blade (todos los detalles y fechas) para imprimir/PDF.
     * GET /ppr/reporte/ver?fecha_desde=&fecha_hasta=&id_pedido=&id_caja=
     */
    public function reporteVer(Request $req)
    {
        $fechaDesde = $req->get('fecha_desde', now()->subDays(30)->toDateString());
        $fechaHasta = $req->get('fecha_hasta', now()->toDateString());
        $idPedido = $req->get('id_pedido');
        $idCaja = $req->get('id_caja');

        $query = PprEntrega::with([
            'pedido:id,created_at,fecha_factura,id_vendedor,id_cliente',
            'pedido.vendedor:id,nombre,usuario',
            'pedido.cliente:id,nombre,identificacion',
            'itemPedido.producto:id,descripcion,codigo_barras,codigo_proveedor',
            'usuarioPortero:id,nombre',
        ])
            ->whereBetween(DB::raw('DATE(ppr_entregas.created_at)'), [$fechaDesde, $fechaHasta]);

        if ($idPedido) {
            $query->where('id_pedido', $idPedido);
        }
        if ($idCaja !== null && $idCaja !== '') {
            $query->whereHas('pedido', function ($q) use ($idCaja) {
                $q->where('id_vendedor', $idCaja);
            });
        }

        $entregas = $query->orderBy('ppr_entregas.created_at', 'desc')->limit(2000)->get();

        $porPedido = [];
        foreach ($entregas as $e) {
            $id = $e->id_pedido;
            if (!isset($porPedido[$id])) {
                $p = $e->pedido;
                $cliente = $p && $p->relationLoaded('cliente') ? $p->cliente : null;
                $porPedido[$id] = [
                    'id_pedido' => $id,
                    'fecha_pedido' => $p ? ($p->fecha_factura ?? $p->created_at) : null,
                    'vendedor_nombre' => $p && $p->vendedor ? ($p->vendedor->nombre ?? $p->vendedor->usuario) : '—',
                    'cliente_nombre' => $cliente ? $cliente->nombre : '—',
                    'cliente_identificacion' => $cliente ? $cliente->identificacion : '',
                    'entregas' => [],
                ];
            }
            $producto = $e->itemPedido && $e->itemPedido->producto ? $e->itemPedido->producto : null;
            $porPedido[$id]['entregas'][] = [
                'unidades_entregadas' => (float) $e->unidades_entregadas,
                'fecha_hora' => $e->created_at,
                'portero' => $e->usuarioPortero ? $e->usuarioPortero->nombre : null,
                'producto_desc' => $producto ? $producto->descripcion : null,
                'codigo_barras' => $producto ? $producto->codigo_barras : null,
                'codigo_proveedor' => $producto ? $producto->codigo_proveedor : null,
            ];
        }
        $porPedido = array_values($porPedido);

        return view('ppr.reporte', [
            'porPedido' => $porPedido,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ]);
    }
}
