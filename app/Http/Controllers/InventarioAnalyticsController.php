<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Salud de Inventario": analítica de ventas + reposición. 100% sobre datos LOCALES
 * (inventarios + items_pedidos + pedidos). No usa ni duplica catálogos
 * (categorias/marcas/proveedores), que viven en central: el nombre de categoría se
 * resuelve aparte vía proxy a central (categorias()); aquí solo se filtra por el id local.
 *
 * Venta = pedido cobrado (estado=1). Ventas NETAS: las devoluciones
 * (pedidos.isdevolucionOriginalid no nulo) restan unidades y montos.
 * Costo = inventarios.precio_base actual (no hay costo histórico por línea → aproximación).
 */
class InventarioAnalyticsController extends Controller
{
    /** Expresión SQL del signo: +1 venta normal, -1 devolución. */
    private const SG = "CASE WHEN COALESCE(p.isdevolucionOriginalid,0)=0 THEN 1 ELSE -1 END";

    public function resumen(Request $req)
    {
        set_time_limit(120);

        $desdeD = $req->desde ?: date('Y-m-d');
        $hastaD = $req->hasta ?: date('Y-m-d');
        $desde = $desdeD . ' 00:00:00';
        $hasta = $hastaD . ' 23:59:59';
        $dias = max(1, (int) floor((strtotime($hastaD) - strtotime($desdeD)) / 86400) + 1);
        $cobertura = max(1, (int) ($req->cobertura_dias ?: 7));
        $q = trim((string) $req->q);
        $idCategoria = $req->id_categoria;
        $ordenDir = strtolower($req->orden_dir) === 'asc' ? 'asc' : 'desc';
        $ordenCol = $req->orden_col ?: 'utilidad';
        $perPage = min(500, max(5, (int) ($req->per_page ?: 50)));
        $page = max(1, (int) ($req->page ?: 1));

        $sg = self::SG;

        // ── Serie diaria (para gráficas) ──
        $serie = DB::table('items_pedidos as ip')
            ->join('pedidos as p', 'p.id', '=', 'ip.id_pedido')
            ->leftJoin('inventarios as inv', 'inv.id', '=', 'ip.id_producto')
            ->where('p.estado', 1)
            ->whereBetween('ip.created_at', [$desde, $hasta])
            ->selectRaw("DATE(ip.created_at) as fecha,
                SUM($sg*ip.monto) as venta,
                SUM($sg*ip.cantidad*COALESCE(inv.precio_base,0)) as base,
                SUM($sg*ip.cantidad) as unidades")
            ->groupByRaw('DATE(ip.created_at)')
            ->orderByRaw('DATE(ip.created_at)')
            ->get()
            ->map(function ($r) {
                $venta = (float) $r->venta;
                $base = (float) $r->base;
                return [
                    'fecha' => $r->fecha,
                    'venta' => round($venta, 2),
                    'base' => round($base, 2),
                    'utilidad' => round($venta - $base, 2),
                    'unidades' => round((float) $r->unidades, 2),
                ];
            });

        // ── Subquery de ventas netas por producto (builder fresco cada vez) ──
        $ventasSub = function () use ($sg, $desde, $hasta) {
            return DB::table('items_pedidos as ip')
                ->join('pedidos as p', 'p.id', '=', 'ip.id_pedido')
                ->where('p.estado', 1)
                ->whereBetween('ip.created_at', [$desde, $hasta])
                ->selectRaw("ip.id_producto,
                    SUM($sg*ip.cantidad) as unidades,
                    SUM($sg*ip.monto) as venta")
                ->groupBy('ip.id_producto');
        };

        // ── Productos: desde inventarios LEFT JOIN ventas ──
        $vdp = "(COALESCE(v.unidades,0)/$dias)";
        $base = DB::table('inventarios as inv')
            ->leftJoinSub($ventasSub(), 'v', 'v.id_producto', '=', 'inv.id')
            ->where('inv.activo', 1);
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('inv.descripcion', 'like', "%$q%")
                    ->orWhere('inv.codigo_barras', 'like', "%$q%")
                    ->orWhere('inv.codigo_proveedor', 'like', "%$q%");
            });
        }
        if ($idCategoria !== null && $idCategoria !== '') {
            $base->where('inv.id_categoria', $idCategoria);
        }
        // Filtro de "valor mínimo" (precio base): limpia productos chicos de alta rotación
        // (tornillos, etc.) de la lista de alertas, para enfocarse en lo importante (congeladores…).
        $minPrecio = (float) ($req->min_precio ?: 0);
        if ($minPrecio > 0) {
            $base->where('inv.precio_base', '>=', $minPrecio);
        }
        // Rango por "tamaño": grandes >100, medianos 20–100, pequeños <20 (max exclusivo).
        $maxPrecio = (float) ($req->max_precio ?: 0);
        if ($maxPrecio > 0) {
            $base->where('inv.precio_base', '<', $maxPrecio);
        }

        $base->selectRaw("inv.id, inv.descripcion, inv.codigo_barras, inv.codigo_proveedor,
            inv.id_categoria, inv.cantidad as stock, inv.precio_base, inv.precio,
            COALESCE(v.unidades,0) as unidades,
            COALESCE(v.venta,0) as venta,
            (COALESCE(v.unidades,0)*inv.precio_base) as base,
            (COALESCE(v.venta,0) - COALESCE(v.unidades,0)*inv.precio_base) as utilidad,
            $vdp as vdp,
            CASE WHEN $vdp>0 THEN inv.cantidad/$vdp ELSE NULL END as dias_inventario,
            GREATEST(0, CEIL($vdp*$cobertura) - inv.cantidad) as necesarias,
            GREATEST(0, CEIL($vdp*$cobertura) - inv.cantidad) * inv.precio_base as valor_reponer,
            CASE WHEN inv.cantidad>0 AND COALESCE(v.unidades,0)=0 THEN 1 ELSE 0 END as muerto");

        // Filtro de vista: muertos / reposicion (HAVING sobre los alias calculados).
        $vista = $req->vista;
        if ($vista === 'muertos') {
            $base->havingRaw('muerto = 1');
            if (!$req->orden_col) $ordenCol = 'stock';
        } elseif ($vista === 'reposicion') {
            $base->havingRaw('necesarias > 0');
            // por defecto se ordena por VALOR a reponer (necesarias × precio_base): así un
            // congelador pesa más que tornillos baratos aunque falten muchos.
            if (!$req->orden_col) $ordenCol = 'valor_reponer';
        }

        // Filtro por TIPO DE ALERTA (sobre los alias calculados; respeta la paginación):
        //   sin_stock = vende pero stock 0 · critico ≤ cobertura/3 días · alerta ≤ cobertura días.
        $alerta = $req->alerta;
        if ($alerta === 'sin_stock') {
            $base->havingRaw('stock <= 0 AND unidades > 0');
        } elseif ($alerta === 'critico') {
            $base->havingRaw('stock > 0 AND dias_inventario IS NOT NULL AND dias_inventario <= ?', [$cobertura / 3]);
        } elseif ($alerta === 'alerta') {
            $base->havingRaw('stock > 0 AND dias_inventario IS NOT NULL AND dias_inventario > ? AND dias_inventario <= ?', [$cobertura / 3, $cobertura]);
        }

        $ordenables = [
            'utilidad' => 'utilidad', 'venta' => 'venta', 'unidades' => 'unidades',
            'dias_inventario' => 'dias_inventario', 'necesarias' => 'necesarias',
            'valor_reponer' => 'valor_reponer', 'stock' => 'stock', 'descripcion' => 'inv.descripcion',
        ];
        $col = $ordenables[$ordenCol] ?? 'utilidad';

        // Total para paginación (envuelve la consulta filtrada como subconsulta).
        $total = DB::query()->fromSub(clone $base, 'sub')->count();

        $productos = $base->orderByRaw("$col $ordenDir")->forPage($page, $perPage)->get()->map(function ($r) {
            $venta = (float) $r->venta;
            $b = (float) $r->base;
            $util = (float) $r->utilidad;
            return [
                'id' => (int) $r->id,
                'descripcion' => $r->descripcion,
                'codigo_barras' => $r->codigo_barras,
                'codigo_proveedor' => $r->codigo_proveedor,
                'id_categoria' => $r->id_categoria,
                'stock' => (float) $r->stock,
                'precio_base' => (float) $r->precio_base,
                'precio' => (float) $r->precio,
                'unidades' => round((float) $r->unidades, 2),
                'venta' => round($venta, 2),
                'base' => round($b, 2),
                'utilidad' => round($util, 2),
                'margen' => $venta > 0 ? round($util / $venta * 100, 1) : 0,
                'vdp' => round((float) $r->vdp, 3),
                'dias_inventario' => $r->dias_inventario !== null ? round((float) $r->dias_inventario, 1) : null,
                'necesarias' => (int) $r->necesarias,
                'valor_reponer' => round((float) $r->valor_reponer, 2),
                'muerto' => ((int) $r->muerto) === 1,
            ];
        });

        // ── KPIs ──
        $kv = DB::table('items_pedidos as ip')
            ->join('pedidos as p', 'p.id', '=', 'ip.id_pedido')
            ->leftJoin('inventarios as inv', 'inv.id', '=', 'ip.id_producto')
            ->where('p.estado', 1)
            ->whereBetween('ip.created_at', [$desde, $hasta])
            ->selectRaw("SUM($sg*ip.monto) as venta,
                SUM($sg*ip.cantidad*COALESCE(inv.precio_base,0)) as base,
                SUM($sg*ip.cantidad) as unidades,
                COUNT(DISTINCT ip.id_producto) as productos_vendidos")
            ->first();

        $valorInv = DB::table('inventarios')->where('activo', 1)
            ->selectRaw("SUM(cantidad*precio_base) as costo, SUM(cantidad*precio) as venta")
            ->first();

        $muertos = DB::table('inventarios as inv')
            ->leftJoinSub($ventasSub(), 'v', 'v.id_producto', '=', 'inv.id')
            ->where('inv.activo', 1)->where('inv.cantidad', '>', 0)
            ->whereRaw('COALESCE(v.unidades,0) = 0')
            ->count();

        $ventaTot = (float) ($kv->venta ?? 0);
        $baseTot = (float) ($kv->base ?? 0);
        $utilTot = $ventaTot - $baseTot;

        $kpis = [
            'venta' => round($ventaTot, 2),
            'base' => round($baseTot, 2),
            'utilidad' => round($utilTot, 2),
            'margen' => $ventaTot > 0 ? round($utilTot / $ventaTot * 100, 1) : 0,
            'unidades' => round((float) ($kv->unidades ?? 0), 2),
            'productos_vendidos' => (int) ($kv->productos_vendidos ?? 0),
            'productos_muertos' => (int) $muertos,
            'valor_inventario_costo' => round((float) ($valorInv->costo ?? 0), 2),
            'valor_inventario_venta' => round((float) ($valorInv->venta ?? 0), 2),
            'dias_periodo' => $dias,
            'cobertura_dias' => $cobertura,
        ];

        return response()->json([
            'estado' => true,
            'kpis' => $kpis,
            'serie' => $serie,
            'productos' => $productos,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Catálogo de categorías traído de CENTRAL (sin duplicar local). El front lo usa para el
     * dropdown de filtro y para mostrar el nombre. Si central no responde, devuelve lista vacía
     * y el módulo sigue funcionando sin el filtro de categoría.
     */
    public function categorias()
    {
        try {
            $resp = (new sendCentral())->requestToCentral('get', '/getCategorias');
            $data = $resp->ok() ? ($resp->json() ?: []) : [];
            return response()->json(['estado' => true, 'categorias' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['estado' => false, 'categorias' => [], 'msj' => $e->getMessage()]);
        }
    }
}
