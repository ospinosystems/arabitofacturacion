<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;

/**
 * Reporte del cuadre diario: resumen por fecha+máquina, detalle por día (pedidos), detalle por pedido (ítems).
 * Export CSV en los tres niveles.
 */
class CuadreReportController extends Controller
{
    protected function dateExpr(): string
    {
        return 'DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at))';
    }

    /**
     * Construye el listado resumen (fecha, máquina, monto, rango facturas).
     */
    protected function buildResultados(?string $fechaDesde, ?string $fechaHasta): array
    {
        $dateExpr = $this->dateExpr();
        $sub = DB::table('pedidos')
            ->where('pedidos.valido', true)
            ->whereNotNull('pedidos.numero_factura');

        $numFacturaExpr = 'CAST(pedidos.numero_factura AS UNSIGNED)';
        if (Schema::hasColumn('pedidos', 'maquina_fiscal')) {
            $maquinaExpr = "COALESCE(pedidos.maquina_fiscal, '')";
            $sub->selectRaw($dateExpr . ' as fecha, ' . $maquinaExpr . ' as maquina_fiscal, MIN(' . $numFacturaExpr . ') as factura_inicio, MAX(' . $numFacturaExpr . ') as factura_fin, COUNT(pedidos.id) as cantidad');
            $sub->groupByRaw($dateExpr . ', ' . $maquinaExpr);
        } else {
            $sub->selectRaw($dateExpr . ' as fecha, \'\' as maquina_fiscal, MIN(' . $numFacturaExpr . ') as factura_inicio, MAX(' . $numFacturaExpr . ') as factura_fin, COUNT(pedidos.id) as cantidad');
            $sub->groupBy(DB::raw($dateExpr));
        }

        if ($fechaDesde) {
            $sub->whereRaw($dateExpr . ' >= ?', [$fechaDesde]);
        }
        if ($fechaHasta) {
            $sub->whereRaw($dateExpr . ' <= ?', [$fechaHasta]);
        }

        $dias = $sub->get();
        $resultados = [];
        foreach ($dias as $dia) {
            $fecha = $dia->fecha;
            $maquina = $dia->maquina_fiscal ?? '';

            $qPedidos = DB::table('pedidos')
                ->whereRaw($dateExpr . ' = ?', [$fecha])
                ->where('pedidos.valido', true);
            if (Schema::hasColumn('pedidos', 'maquina_fiscal')) {
                if ($maquina === '') {
                    $qPedidos->where(function ($q) {
                        $q->whereNull('pedidos.maquina_fiscal')->orWhere('pedidos.maquina_fiscal', '');
                    });
                } else {
                    $qPedidos->where('pedidos.maquina_fiscal', $maquina);
                }
            }
            $idsPedidos = $qPedidos->pluck('pedidos.id');

            $montoBs = 0;
            if ($idsPedidos->isNotEmpty()) {
                $montoBs = (float) DB::table('items_pedidos')
                    ->whereIn('id_pedido', $idsPedidos)
                    ->sum(DB::raw('COALESCE(monto_bs, 0)'));
            }

            $resultados[] = (object) [
                'fecha'           => $fecha,
                'maquina_fiscal'  => $maquina !== '' ? $maquina : '—',
                'monto_bs'        => $montoBs,
                'factura_inicio'  => $dia->factura_inicio ?? '—',
                'factura_fin'     => $dia->factura_fin ?? '—',
                'cantidad'        => (int) $dia->cantidad,
            ];
        }

        usort($resultados, function ($a, $b) {
            $c = strcmp($a->fecha, $b->fecha);
            return $c !== 0 ? $c : strcmp($a->maquina_fiscal, $b->maquina_fiscal);
        });

        return $resultados;
    }

    public function index(Request $request)
    {
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $resultados = $this->buildResultados($fechaDesde, $fechaHasta);

        return view('reportes.cuadre-diario', [
            'resultados'  => $resultados,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ]);
    }

    public function exportResumen(Request $request): StreamedResponse
    {
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $resultados = $this->buildResultados($fechaDesde, $fechaHasta);

        $filename = 'cuadre-diario-resumen-' . ($fechaDesde ?? 'todo') . '-' . ($fechaHasta ?? 'todo') . '.csv';

        return response()->streamDownload(function () use ($resultados) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['FECHA', 'MAQUINA_FISCAL', 'MONTO_BS', 'FACTURA_INICIO', 'FACTURA_FIN', 'CANTIDAD_PEDIDOS']);
            foreach ($resultados as $r) {
                fputcsv($out, [$r->fecha, $r->maquina_fiscal, number_format($r->monto_bs, 4, '.', ''), $r->factura_inicio, $r->factura_fin, $r->cantidad]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function dia(Request $request, string $fecha)
    {
        $dateExpr = $this->dateExpr();
        $maquinaFiscal = $request->get('maquina_fiscal');

        $query = pedidos::whereRaw($dateExpr . ' = ?', [$fecha])
            ->where('pedidos.valido', true)
            ->orderBy('pedidos.numero_factura')
            ->orderBy('pedidos.id');

        if (Schema::hasColumn('pedidos', 'maquina_fiscal') && $maquinaFiscal !== null && $maquinaFiscal !== '') {
            $query->where('pedidos.maquina_fiscal', $maquinaFiscal);
        }

        $pedidosList = $query->get();
        $pedidosConTotales = [];
        foreach ($pedidosList as $p) {
            $totalBs = (float) items_pedidos::where('id_pedido', $p->id)->sum(DB::raw('COALESCE(monto_bs, 0)'));
            $cantItems = items_pedidos::where('id_pedido', $p->id)->count();
            $tasaBs = items_pedidos::where('id_pedido', $p->id)->whereNotNull('tasa')->value('tasa');
            $pedidosConTotales[] = (object) [
                'pedido'     => $p,
                'monto_bs'   => $totalBs,
                'cant_items' => $cantItems,
                'tasa_bs'    => $tasaBs !== null ? (float) $tasaBs : null,
            ];
        }

        return view('reportes.cuadre-diario-dia', [
            'fecha'          => $fecha,
            'maquina_fiscal' => $maquinaFiscal,
            'pedidos'        => $pedidosConTotales,
        ]);
    }

    public function exportDia(Request $request, string $fecha): StreamedResponse
    {
        $maquinaFiscal = $request->get('maquina_fiscal');
        $dateExpr = $this->dateExpr();

        $query = pedidos::whereRaw($dateExpr . ' = ?', [$fecha])
            ->where('pedidos.valido', true)
            ->orderBy('pedidos.numero_factura')
            ->orderBy('pedidos.id');

        if (Schema::hasColumn('pedidos', 'maquina_fiscal') && $maquinaFiscal !== null && $maquinaFiscal !== '') {
            $query->where('pedidos.maquina_fiscal', $maquinaFiscal);
        }

        $pedidosList = $query->get();
        $filename = 'cuadre-diario-pedidos-' . $fecha . '.csv';

        return response()->streamDownload(function () use ($pedidosList) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID_PEDIDO', 'NUMERO_FACTURA', 'MAQUINA_FISCAL', 'MONTO_BS', 'CANT_ITEMS', 'FECHA_FACTURA']);
            foreach ($pedidosList as $p) {
                $montoBs = (float) items_pedidos::where('id_pedido', $p->id)->sum(DB::raw('COALESCE(monto_bs, 0)'));
                $cantItems = items_pedidos::where('id_pedido', $p->id)->count();
                fputcsv($out, [$p->id, $p->numero_factura ?? '', $p->maquina_fiscal ?? '', number_format($montoBs, 4, '.', ''), $cantItems, $p->fecha_factura ?? $p->created_at]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function pedido(int $id)
    {
        $pedido = pedidos::findOrFail($id);
        $items = items_pedidos::where('id_pedido', $id)
            ->leftJoin('inventarios', 'inventarios.id', '=', 'items_pedidos.id_producto')
            ->select(
                'items_pedidos.*',
                'inventarios.codigo_barras',
                'inventarios.codigo_proveedor',
                'inventarios.descripcion as producto_descripcion'
            )
            ->orderBy('items_pedidos.id')
            ->get();

        return view('reportes.cuadre-diario-pedido', [
            'pedido' => $pedido,
            'items'  => $items,
        ]);
    }

    /**
     * Tabla de validación: por cada día (fecha + máquina) muestra monto, rango facturas y dos checks:
     * - Check CSV: si se pasó CSV, indica si el resultado en DB coincide con las indicaciones del CSV.
     * - Check natural: que no haya "muchos pedidos en cero y un solo pedido de ajuste" (ningún pedido > 50% del total del día).
     * Todo se resuelve con una sola consulta agregada para evitar timeout con muchos pedidos.
     */
    public function validacion(Request $request)
    {
        set_time_limit(120);

        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $csvPath = $request->get('csv'); // ruta opcional al CSV de indicaciones originales

        $dateExprP = 'DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at))';
        $maquinaSel = Schema::hasColumn('pedidos', 'maquina_fiscal')
            ? 'COALESCE(pedidos.maquina_fiscal, \'\')'
            : '\'\'';
        $groupByMaq = Schema::hasColumn('pedidos', 'maquina_fiscal')
            ? 'COALESCE(pedidos.maquina_fiscal, \'\')'
            : '\'\'';

        $rawSql = "SELECT {$dateExprP} as fecha, {$maquinaSel} as maquina_fiscal,
            SUM(COALESCE(ip.total, 0)) as total_bs,
            MIN(CAST(pedidos.numero_factura AS UNSIGNED)) as factura_inicio,
            MAX(CAST(pedidos.numero_factura AS UNSIGNED)) as factura_fin,
            COUNT(pedidos.id) as cantidad,
            MAX(COALESCE(ip.total, 0)) as max_single
            FROM pedidos
            LEFT JOIN (SELECT id_pedido, SUM(COALESCE(monto_bs, 0)) as total FROM items_pedidos GROUP BY id_pedido) ip ON ip.id_pedido = pedidos.id
            WHERE pedidos.valido = 1 AND pedidos.numero_factura IS NOT NULL";
        $bindings = [];
        if ($fechaDesde) {
            $rawSql .= ' AND ' . $dateExprP . ' >= ?';
            $bindings[] = $fechaDesde;
        }
        if ($fechaHasta) {
            $rawSql .= ' AND ' . $dateExprP . ' <= ?';
            $bindings[] = $fechaHasta;
        }
        $rawSql .= " GROUP BY {$dateExprP}, {$groupByMaq} ORDER BY fecha, maquina_fiscal";

        $dias = $bindings ? DB::select($rawSql, $bindings) : DB::select($rawSql);

        $umbralAjuste = 0.5;
        $filas = [];
        foreach ($dias as $d) {
            $totalBs = (float) $d->total_bs;
            $maxSingle = (float) $d->max_single;
            $checkNatural = ($totalBs <= 0) || ($maxSingle <= $umbralAjuste * $totalBs);
            $filas[] = (object) [
                'fecha' => $d->fecha,
                'maquina_fiscal' => $d->maquina_fiscal !== '' ? $d->maquina_fiscal : '—',
                'monto_bs' => $totalBs,
                'factura_inicio' => $d->factura_inicio !== null ? (string) $d->factura_inicio : '—',
                'factura_fin' => $d->factura_fin !== null ? (string) $d->factura_fin : '—',
                'cantidad' => (int) $d->cantidad,
                'check_csv' => null,
                'check_natural' => $checkNatural,
            ];
        }

        $csvEsperado = [];
        if ($csvPath && is_file($csvPath)) {
            $csvEsperado = $this->parsearCsvValidacion($csvPath);
            foreach ($filas as $f) {
                $maqKey = $f->maquina_fiscal === '—' ? '' : $f->maquina_fiscal;
                $key = $f->fecha . '|' . $maqKey;
                $esp = $csvEsperado[$key] ?? null;
                if ($esp === null) {
                    $f->check_csv = false;
                    continue;
                }
                $montoCoincide = abs((float) $f->monto_bs - (float) $esp['total_venta']) < 0.01;
                $rangoCoincide = (string) $f->factura_inicio === (string) $esp['factura_inicio'] && (string) $f->factura_fin === (string) $esp['factura_fin'];
                $cantCoincide = (int) $f->cantidad === (int) $esp['cantidad'];
                $f->check_csv = $montoCoincide && $rangoCoincide && $cantCoincide;
            }
        }

        return view('reportes.cuadre-diario-validacion', [
            'filas' => $filas,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'csv_path' => $csvPath,
            'tiene_csv' => !empty($csvEsperado),
        ]);
    }

    /**
     * Parsea el CSV de indicaciones (mismo formato que cuadre:pedidos-diario) y devuelve [ 'fecha|maquina' => [ total_venta, factura_inicio, factura_fin, cantidad ] ]
     */
    protected function parsearCsvValidacion(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $delim = (strpos($path, '.tsv') !== false || (strpos($content, "\t") !== false && strpos($content, ',') === false)) ? "\t" : ',';
        $lines = array_map('trim', explode("\n", $content));
        $header = str_getcsv(array_shift($lines), $delim);
        $header = array_map(function ($h) {
            $h = trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
            return $h;
        }, $header);

        $lastIndex = count($header) - 1;
        foreach ($header as $i => $h) {
            if (stripos(mb_strtoupper($h), 'TOTAL') !== false && stripos(mb_strtoupper($h), 'VENTA') !== false) {
                $lastIndex = $i;
                break;
            }
        }
        $header = array_slice($header, 0, $lastIndex + 1);

        $map = ['fecha' => null, 'maquina' => null, 'rango' => null, 'total_venta' => null];
        foreach ($header as $key) {
            $u = mb_strtoupper($key);
            if (stripos($u, 'FECHA') !== false) {
                $map['fecha'] = $key;
            }
            if (stripos($u, 'TOTAL') !== false && stripos($u, 'VENTA') !== false) {
                $map['total_venta'] = $key;
            }
            if ((stripos($u, 'MAQUINA') !== false || stripos($u, 'MÁQUINA') !== false) && stripos($u, 'FISCAL') !== false) {
                $map['maquina'] = $key;
            }
            if (stripos($u, 'FACTURA') !== false && (stripos($u, 'RANGO') !== false || stripos($u, 'N°') !== false || stripos($u, 'NUMERO') !== false)) {
                $map['rango'] = $key;
            }
        }
        if ($map['total_venta'] === null || $map['fecha'] === null) {
            return [];
        }

        $grupos = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $row = str_getcsv($line, $delim);
            $row = array_pad($row, count($header), '');
            $row = array_slice($row, 0, count($header));
            $assoc = array_combine($header, $row);
            if ($assoc === false) {
                continue;
            }

            $totalVenta = trim((string) ($assoc[$map['total_venta']] ?? '0'));
            if ($totalVenta === '') {
                continue;
            }
            $fecha = trim((string) ($assoc[$map['fecha']] ?? ''));
            $maquina = $map['maquina'] !== null ? trim((string) ($assoc[$map['maquina']] ?? '')) : '';
            if ($fecha === '' || $maquina === '') {
                continue;
            }
            $rango = $map['rango'] !== null ? trim((string) ($assoc[$map['rango']] ?? '')) : '';
            $inicio = null;
            $fin = null;
            if ($rango !== '' && preg_match('/^(\d+)\s*-\s*(\d+)$/', $rango, $m)) {
                $inicio = (string) (int) $m[1];
                $fin = (string) (int) $m[2];
            }
            $key = $fecha . '|' . $maquina;
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'total_venta' => $totalVenta,
                    'factura_inicio' => $inicio,
                    'factura_fin' => $fin,
                    'cantidad' => $inicio !== null && $fin !== null ? (int) $fin - (int) $inicio + 1 : 0,
                ];
            } else {
                $grupos[$key]['total_venta'] = (string) (bcadd($grupos[$key]['total_venta'], $totalVenta, 4));
            }
        }
        return $grupos;
    }

    public function exportPedido(int $id): StreamedResponse
    {
        $pedido = pedidos::findOrFail($id);
        $items = items_pedidos::where('id_pedido', $id)
            ->leftJoin('inventarios', 'inventarios.id', '=', 'items_pedidos.id_producto')
            ->select(
                'items_pedidos.id',
                'items_pedidos.cantidad',
                'items_pedidos.monto',
                'items_pedidos.monto_bs',
                'items_pedidos.precio_unitario',
                'inventarios.codigo_barras',
                'inventarios.codigo_proveedor',
                'inventarios.descripcion as producto_descripcion'
            )
            ->orderBy('items_pedidos.id')
            ->get();

        $filename = 'cuadre-diario-pedido-' . $id . '-items.csv';

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['CODIGO_BARRAS', 'CODIGO_PROVEEDOR', 'DESCRIPCION', 'CANTIDAD', 'PRECIO_UNIT', 'MONTO', 'MONTO_BS']);
            foreach ($items as $item) {
                fputcsv($out, [
                    $item->codigo_barras ?? '',
                    $item->codigo_proveedor ?? '',
                    $item->producto_descripcion ?? '',
                    $item->cantidad,
                    $item->precio_unitario ?? '',
                    $item->monto ?? '',
                    $item->monto_bs ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
