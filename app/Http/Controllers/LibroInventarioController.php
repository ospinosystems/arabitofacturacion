<?php

namespace App\Http\Controllers;

use App\Services\Inventario\LibroInventarioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Libro de entradas y salidas de inventario (registro del Art. 177 del Reglamento de la LISLR) reconstruido con
 * las facturas fiscales de compra (central) y las facturas de venta cuadradas. Ver LibroInventarioService.
 */
class LibroInventarioController extends Controller
{
    /** @return array{0: ?string, 1: string, 2: string[], 3: string, 4: string} */
    protected function filtros(Request $r, LibroInventarioService $svc): array
    {
        [$min, $max] = $svc->rangoDisponible();
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->input('desde')) ? $r->input('desde') : null;
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->input('hasta')) ? $r->input('hasta') : $max;
        $tipos = array_values(array_intersect(LibroInventarioService::TIPOS, array_map('strtoupper', (array) $r->input('tipos', LibroInventarioService::TIPOS_DEFECTO)))) ?: LibroInventarioService::TIPOS_DEFECTO;
        return [$desde, $hasta, $tipos, $min, $max];
    }

    protected function filtrarProductos(array $productos, Request $r): array
    {
        $q = mb_strtoupper(trim((string) $r->input('q')));
        $soloNeg = (bool) $r->input('negativos');
        $conMov = (bool) $r->input('con_movimientos');
        return array_filter($productos, function ($p) use ($q, $soloNeg, $conMov) {
            if ($soloNeg && !$p['negativo']) return false;
            if ($conMov && $p['movs'] === 0) return false;
            if ($q === '') return true;
            return mb_strpos(mb_strtoupper($p['descripcion'] . ' ' . $p['codigo'] . ' ' . $p['codigo_proveedor'] . ' ' . $p['id']), $q) !== false;
        });
    }

    public function index(Request $r, LibroInventarioService $svc)
    {
        set_time_limit(300);
        [$desde, $hasta, $tipos, $min, $max] = $this->filtros($r, $svc);
        $libro = $svc->construir($desde, $hasta, $tipos, null, false);
        $productos = $this->filtrarProductos($libro['productos'], $r);
        $porPagina = 300;
        $totalFilas = count($productos);
        $pagina = min(max(1, (int) $r->input('pagina', 1)), max(1, (int) ceil($totalFilas / $porPagina)));
        $productos = array_slice($productos, ($pagina - 1) * $porPagina, $porPagina, true);
        $hayEntradas = DB::table('inventario_entradas')->count();
        return view('reportes.libro-inventario', compact('libro', 'productos', 'desde', 'hasta', 'tipos', 'min', 'max', 'pagina', 'porPagina', 'totalFilas', 'hayEntradas')
            + ['empresa' => DB::table('sucursals')->first(), 'q' => $r->input('q'), 'negativos' => (bool) $r->input('negativos'), 'con_movimientos' => (bool) $r->input('con_movimientos')]);
    }

    public function producto(Request $r, LibroInventarioService $svc, string $id)
    {
        set_time_limit(300);
        [$desde, $hasta, $tipos, $min, $max] = $this->filtros($r, $svc);
        $key = ctype_digit($id) ? (int) $id : $id;
        $libro = $svc->construir($desde, $hasta, $tipos, $key, true);
        $producto = $libro['productos'][$key] ?? null;
        abort_if($producto === null, 404, 'Producto sin movimientos en el período.');
        return view('reportes.libro-inventario-producto', compact('libro', 'producto', 'desde', 'hasta', 'tipos', 'min', 'max') + ['empresa' => DB::table('sucursals')->first()]);
    }

    public function entradas(Request $r)
    {
        // Agregado por documento en una subconsulta (ONLY_FULL_GROUP_BY no permite e.* con GROUP BY e.id).
        $agg = DB::table('inventario_entrada_items')
            ->selectRaw('entrada_id, COUNT(*) items, COALESCE(SUM(cantidad),0) unidades, COALESCE(SUM(cantidad*costo_unitario_usd),0) costo_usd, SUM(id_producto IS NULL) sin_mapear')
            ->groupBy('entrada_id');
        $q = DB::table('inventario_entradas as e')
            ->leftJoinSub($agg, 'a', 'a.entrada_id', '=', 'e.id')
            ->select('e.*', DB::raw('COALESCE(a.items,0) as items'), DB::raw('COALESCE(a.unidades,0) as unidades'), DB::raw('COALESCE(a.costo_usd,0) as costo_usd'), DB::raw('COALESCE(a.sin_mapear,0) as sin_mapear'))
            ->orderByDesc('e.fecha_recepcion')->orderByDesc('e.id');
        if ($r->input('tipo')) $q->where('e.tipo', strtoupper($r->input('tipo')));
        if ($r->input('desde')) $q->where('e.fecha_recepcion', '>=', $r->input('desde'));
        if ($r->input('hasta')) $q->where('e.fecha_recepcion', '<=', $r->input('hasta'));
        if ($r->input('q')) $q->where(fn ($w) => $w->where('e.numero_documento', 'like', '%' . $r->input('q') . '%')->orWhere('e.proveedor', 'like', '%' . $r->input('q') . '%'));
        $entradas = $q->get();
        $resumen = ['n' => $entradas->count(), 'items' => $entradas->sum('items'), 'unidades' => $entradas->sum('unidades'), 'costo_usd' => $entradas->sum('costo_usd'), 'sin_mapear' => $entradas->sum('sin_mapear')];
        $porTipo = DB::table('inventario_entradas')->selectRaw('tipo, COUNT(*) n')->groupBy('tipo')->pluck('n', 'tipo')->all();
        return view('reportes.libro-inventario-entradas', compact('entradas', 'resumen', 'porTipo') + ['filtros' => $r->only(['tipo', 'desde', 'hasta', 'q'])]);
    }

    public function exportResumen(Request $r, LibroInventarioService $svc): StreamedResponse
    {
        set_time_limit(300);
        [$desde, $hasta, $tipos] = $this->filtros($r, $svc);
        $libro = $svc->construir($desde, $hasta, $tipos, null, false);
        $productos = $this->filtrarProductos($libro['productos'], $r);
        $nombre = "libro-inventario-resumen-{$libro['desde']}-a-{$libro['hasta']}.csv";
        return $this->csv($nombre, ['ID', 'CODIGO', 'COD_PROVEEDOR', 'DESCRIPCION', 'UNIDAD', 'EXIST_INICIAL', 'VALOR_INICIAL_USD', 'ENTRADAS', 'COSTO_ENTRADAS_USD', 'SALIDAS', 'COSTO_SALIDAS_USD', 'DEVOLUCIONES', 'EXISTENCIA', 'COSTO_PROMEDIO_USD', 'VALOR_USD', 'VALOR_BS', 'VENTA_USD', 'NEGATIVO'], function () use ($productos) {
            foreach ($productos as $p) {
                yield [$p['id'], $p['codigo'], $p['codigo_proveedor'], $p['descripcion'], $p['unidad'], $this->n($p['ini_qty'], 4), $this->n($p['ini_valor']), $this->n($p['ent_qty'], 4), $this->n($p['ent_valor']), $this->n($p['sal_qty'], 4), $this->n($p['sal_valor']), $this->n($p['dev_qty'], 4), $this->n($p['fin_qty'], 4), $this->n($p['prom'], 4), $this->n($p['fin_valor']), $this->n($p['fin_valor_bs']), $this->n($p['venta_usd']), $p['negativo'] ? 'SI' : ''];
            }
        });
    }

    public function exportMovimientos(Request $r, LibroInventarioService $svc): StreamedResponse
    {
        set_time_limit(600);
        [$desde, $hasta, $tipos] = $this->filtros($r, $svc);
        $id = $r->input('producto');
        $libro = $svc->construir($desde, $hasta, $tipos, $id !== null && $id !== '' ? (ctype_digit((string) $id) ? (int) $id : $id) : null, true);
        $fichas = $libro['productos'];
        $nombre = 'libro-inventario-movimientos-' . $libro['desde'] . '-a-' . $libro['hasta'] . ($id ? "-producto-$id" : '') . '.csv';
        return $this->csv($nombre, ['FECHA', 'TIPO', 'ORIGEN', 'DOCUMENTO', 'PROVEEDOR_O_CLIENTE', 'ID_PRODUCTO', 'CODIGO', 'DESCRIPCION', 'ENTRADA', 'SALIDA', 'COSTO_UNIT_USD', 'TOTAL_USD', 'TASA', 'TOTAL_BS', 'SALDO_CANT', 'SALDO_COSTO_PROM_USD', 'SALDO_VALOR_USD'], function () use ($libro, $fichas) {
            foreach ($libro['movimientos'] as $m) {
                $f = $fichas[$m['producto']] ?? null;
                yield [$m['fecha'], $m['tipo'], $m['subtipo'], $m['documento'], $m['tercero'], $m['producto'], $f['codigo'] ?? '', $f['descripcion'] ?? '', $this->n($m['entrada'], 4), $this->n($m['salida'], 4), $this->n($m['costo_unit'], 4), $this->n($m['total_usd']), $this->n($m['tasa'], 4), $this->n($m['total_bs']), $this->n($m['saldo_qty'], 4), $this->n($m['saldo_prom'], 4), $this->n($m['saldo_valor'])];
            }
        });
    }

    public function exportEntradas(Request $r): StreamedResponse
    {
        $q = DB::table('inventario_entrada_items as ei')->join('inventario_entradas as e', 'e.id', '=', 'ei.entrada_id')
            ->leftJoin('inventarios as i', 'i.id', '=', 'ei.id_producto')
            ->orderBy('e.fecha_recepcion')->orderBy('e.id')->orderBy('ei.id')
            ->select('e.fecha_recepcion', 'e.fecha_emision', 'e.tipo', 'e.numero_documento', 'e.numero_nota', 'e.proveedor', 'e.proveedor_rif', 'e.origen_sucursal', 'e.tasa_bs', 'e.anulado',
                'ei.central_id_producto', 'ei.id_producto', 'ei.mapeo', 'ei.cantidad', 'ei.cantidad_real', 'ei.costo_unitario_usd', 'i.codigo_barras', 'i.descripcion', 'ei.descripcion_origen');
        if ($r->input('tipo')) $q->where('e.tipo', strtoupper($r->input('tipo')));
        if ($r->input('desde')) $q->where('e.fecha_recepcion', '>=', $r->input('desde'));
        if ($r->input('hasta')) $q->where('e.fecha_recepcion', '<=', $r->input('hasta'));
        return $this->csv('libro-inventario-entradas.csv', ['FECHA_RECEPCION', 'FECHA_EMISION', 'TIPO', 'DOCUMENTO', 'NOTA', 'PROVEEDOR', 'RIF', 'ORIGEN', 'ANULADO', 'ID_PRODUCTO', 'ID_CENTRAL', 'MAPEO', 'CODIGO', 'DESCRIPCION', 'CANTIDAD', 'CANTIDAD_RECIBIDA', 'COSTO_UNIT_USD', 'TOTAL_USD', 'TASA', 'TOTAL_BS'], function () use ($q) {
            foreach ($q->cursor() as $x) {
                $tot = (float) $x->cantidad * (float) $x->costo_unitario_usd;
                yield [$x->fecha_recepcion, $x->fecha_emision, $x->tipo, $x->numero_documento, $x->numero_nota, $x->proveedor, $x->proveedor_rif, $x->origen_sucursal, $x->anulado ? 'SI' : '', $x->id_producto, $x->central_id_producto, $x->mapeo, $x->codigo_barras, $x->descripcion ?: $x->descripcion_origen, $this->n($x->cantidad, 4), $this->n($x->cantidad_real, 4), $this->n($x->costo_unitario_usd, 4), $this->n($tot), $this->n($x->tasa_bs, 4), $this->n($tot * (float) $x->tasa_bs)];
            }
        });
    }

    public function pdf(Request $r, LibroInventarioService $svc)
    {
        set_time_limit(600);
        ini_set('memory_limit', '2048M');
        [$desde, $hasta, $tipos] = $this->filtros($r, $svc);
        $libro = $svc->construir($desde, $hasta, $tipos, null, false);
        $productos = $this->filtrarProductos($libro['productos'], $r);
        // PHP-FPM tiene 512 MB fijos y DomPDF no cabe con miles de filas: el PDF completo se genera con inventario:libro-pdf.
        if (count($productos) > 1500) {
            return response('<p style="font-family:Arial;margin:30px">El PDF en línea admite hasta 1.500 productos (este filtro devuelve ' . number_format(count($productos), 0, ',', '.') . '). '
                . 'Acote el período o la búsqueda, o genere el libro completo con <code>php artisan inventario:libro-pdf</code>: el archivo queda en «Descargas completas».</p>', 413);
        }
        $html = view('reportes.libro-inventario-pdf', compact('libro', 'productos') + ['empresa' => DB::table('sucursals')->first()])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('letter', 'landscape')->setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'Helvetica', 'isFontSubsettingEnabled' => true, 'margin_left' => 8, 'margin_right' => 8, 'margin_top' => 8, 'margin_bottom' => 8]);
        return $pdf->download("libro-inventario-{$libro['desde']}-a-{$libro['hasta']}.pdf");
    }

    protected function csv(string $nombre, array $cabecera, callable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabecera, $filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $cabecera);
            foreach ($filas() as $f) fputcsv($out, $f);
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="' . $nombre . '"']);
    }

    protected function n($v, int $dec = 2): string
    {
        return number_format((float) $v, $dec, '.', '');
    }
}
