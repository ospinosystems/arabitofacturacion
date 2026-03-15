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
 * Reporte de ventas: resumen por fecha+máquina, detalle por día (pedidos), detalle por pedido (ítems).
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
     * Una sola consulta agregada con JOIN para evitar timeout en rangos grandes.
     */
    protected function buildResultados(?string $fechaDesde, ?string $fechaHasta): array
    {
        $dateExpr = $this->dateExpr();
        $maquinaExpr = Schema::hasColumn('pedidos', 'maquina_fiscal')
            ? "COALESCE(pedidos.maquina_fiscal, '')"
            : "''";
        $groupBy = $dateExpr . ', ' . $maquinaExpr;

        $query = DB::table('pedidos')
            ->join('items_pedidos', 'items_pedidos.id_pedido', '=', 'pedidos.id')
            ->where('pedidos.valido', true)
            ->whereNotNull('pedidos.numero_factura')
            ->selectRaw($dateExpr . ' as fecha, ' . $maquinaExpr . ' as maquina_fiscal,
                SUM(COALESCE(items_pedidos.monto_bs, 0)) as monto_bs,
                SUM(COALESCE(items_pedidos.monto, 0)) as monto_usd,
                SUM(CASE WHEN items_pedidos.tasa IS NOT NULL AND items_pedidos.tasa > 0 AND COALESCE(items_pedidos.monto, 0) > 0 THEN items_pedidos.tasa * COALESCE(items_pedidos.monto, 0) ELSE 0 END) / NULLIF(SUM(CASE WHEN items_pedidos.tasa IS NOT NULL AND items_pedidos.tasa > 0 AND COALESCE(items_pedidos.monto, 0) > 0 THEN COALESCE(items_pedidos.monto, 0) ELSE 0 END), 0) as tasa_ponderada,
                MIN(CAST(pedidos.numero_factura AS UNSIGNED)) as factura_inicio,
                MAX(CAST(pedidos.numero_factura AS UNSIGNED)) as factura_fin,
                COUNT(DISTINCT pedidos.id) as cantidad')
            ->groupByRaw($groupBy)
            ->orderByRaw($dateExpr)
            ->orderByRaw($maquinaExpr);

        if ($fechaDesde) {
            $query->whereRaw($dateExpr . ' >= ?', [$fechaDesde]);
        }
        if ($fechaHasta) {
            $query->whereRaw($dateExpr . ' <= ?', [$fechaHasta]);
        }

        $dias = $query->get();
        $resultados = [];
        foreach ($dias as $dia) {
            $montoUsd = (float) ($dia->monto_usd ?? 0);
            $montoBs = (float) ($dia->monto_bs ?? 0);
            // Tasa: promedio ponderado por monto USD de la tasa guardada en ítems (Bs/USD). Si no hay ítems con tasa, se usa total_bs/total_usd.
            $tasaDia = null;
            if (isset($dia->tasa_ponderada) && (float) $dia->tasa_ponderada > 0) {
                $tasaDia = (float) $dia->tasa_ponderada;
            } elseif ($montoUsd > 0 && $montoBs > 0) {
                $tasaDia = $montoBs / $montoUsd;
            }
            $maquina = $dia->maquina_fiscal ?? '';

            $resultados[] = (object) [
                'fecha'          => $dia->fecha,
                'maquina_fiscal' => $maquina !== '' ? $maquina : '—',
                'monto_usd'      => $montoUsd,
                'monto_bs'       => $montoBs,
                'tasa'           => $tasaDia,
                'factura_inicio' => $dia->factura_inicio !== null ? (string) $dia->factura_inicio : '—',
                'factura_fin'    => $dia->factura_fin !== null ? (string) $dia->factura_fin : '—',
                'cantidad'       => (int) $dia->cantidad,
            ];
        }

        return $resultados;
    }

    public function index(Request $request)
    {
        set_time_limit(120);
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $resultados = $this->buildResultados($fechaDesde, $fechaHasta);

        $totalDias = 0;
        $totalMontoUsd = 0.0;
        $totalMontoBs = 0.0;
        $totalCantidadPedidos = 0;
        $fechasVistas = [];
        foreach ($resultados as $r) {
            if (!in_array($r->fecha, $fechasVistas, true)) {
                $fechasVistas[] = $r->fecha;
                $totalDias++;
            }
            $totalMontoUsd += (float) ($r->monto_usd ?? 0);
            $totalMontoBs += (float) $r->monto_bs;
            $totalCantidadPedidos += (int) $r->cantidad;
        }

        return view('reportes.cuadre-diario', [
            'resultados'            => $resultados,
            'fecha_desde'           => $fechaDesde,
            'fecha_hasta'           => $fechaHasta,
            'total_dias'            => $totalDias,
            'total_monto_usd'       => $totalMontoUsd,
            'total_monto_bs'         => $totalMontoBs,
            'total_cantidad_pedidos' => $totalCantidadPedidos,
        ]);
    }

    public function exportResumen(Request $request): StreamedResponse
    {
        set_time_limit(120);
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $resultados = $this->buildResultados($fechaDesde, $fechaHasta);

        $filename = 'ventas-resumen-' . ($fechaDesde ?? 'todo') . '-' . ($fechaHasta ?? 'todo') . '.csv';

        return response()->streamDownload(function () use ($resultados) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['FECHA', 'MAQUINA_FISCAL', 'MONTO_USD', 'MONTO_BS', 'TASA_BS_USD', 'FACTURA_INICIO', 'FACTURA_FIN', 'CANTIDAD_PEDIDOS']);
            foreach ($resultados as $r) {
                $tasaStr = $r->tasa !== null ? number_format($r->tasa, 4, '.', '') : '';
                fputcsv($out, [$r->fecha, $r->maquina_fiscal, number_format($r->monto_usd ?? 0, 2, '.', ''), number_format($r->monto_bs, 2, '.', ''), $tasaStr, $r->factura_inicio, $r->factura_fin, $r->cantidad]);
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
            $totalUsd = (float) items_pedidos::where('id_pedido', $p->id)->sum(DB::raw('COALESCE(monto, 0)'));
            $cantItems = items_pedidos::where('id_pedido', $p->id)->count();
            $tasaBs = items_pedidos::where('id_pedido', $p->id)->whereNotNull('tasa')->value('tasa');
            $pedidosConTotales[] = (object) [
                'pedido'     => $p,
                'monto_usd'  => $totalUsd,
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
        $filename = 'ventas-pedidos-' . $fecha . '.csv';

        return response()->streamDownload(function () use ($pedidosList) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['NUMERO_FACTURA', 'MAQUINA_FISCAL', 'MONTO_USD', 'MONTO_BS', 'CANT_ITEMS', 'FECHA_FACTURA']);
            foreach ($pedidosList as $p) {
                $montoUsd = (float) items_pedidos::where('id_pedido', $p->id)->sum(DB::raw('COALESCE(monto, 0)'));
                $montoBs = (float) items_pedidos::where('id_pedido', $p->id)->sum(DB::raw('COALESCE(monto_bs, 0)'));
                $cantItems = items_pedidos::where('id_pedido', $p->id)->count();
                fputcsv($out, [$p->numero_factura ?? '', $p->maquina_fiscal ?? '', number_format($montoUsd, 2, '.', ''), number_format($montoBs, 2, '.', ''), $cantItems, $p->fecha_factura ?? $p->created_at]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function pedido(int $id)
    {
        $pedido = pedidos::with('cliente')->findOrFail($id);
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

        $subtotalUsd = $items->sum(function ($i) { return (float) ($i->monto ?? 0); });
        $subtotalBs = $items->sum(function ($i) { return (float) ($i->monto_bs ?? 0); });
        $tasaPedido = null;
        $itemConTasa = $items->first(function ($i) { return isset($i->tasa) && (float) $i->tasa > 0; });
        if ($itemConTasa !== null) {
            $tasaPedido = (float) $itemConTasa->tasa;
        } elseif ($subtotalUsd > 0 && $subtotalBs > 0) {
            $tasaPedido = $subtotalBs / $subtotalUsd;
        }

        return view('reportes.cuadre-diario-pedido', [
            'pedido'       => $pedido,
            'items'        => $items,
            'subtotalUsd'  => $subtotalUsd,
            'subtotalBs'   => $subtotalBs,
            'montoTotalUsd'=> $subtotalUsd,
            'montoTotalBs' => $subtotalBs,
            'tasaPedido'   => $tasaPedido,
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
     * Soporta formato nuevo (FECHA, CONCEPTO, FACTURA, VENTA, TIPO) y antiguo (MAQUINA_FISCAL, RANGO_FACTURA, TOTAL_VENTA).
     */
    protected function parsearCsvValidacion(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false || !is_string($content)) {
            return [];
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $delim = (strpos($path, '.tsv') !== false || (strpos($content, "\t") !== false && strpos($content, ',') === false)) ? "\t" : ',';
        $lines = array_map('trim', explode("\n", (string) $content));
        $header = str_getcsv(array_shift($lines), $delim);
        $header = array_map(function ($h) {
            $h = trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
            return $h;
        }, $header);

        $lastIndex = -1;
        foreach ($header as $i => $h) {
            $u = mb_strtoupper($h);
            if (stripos($u, 'VENTA') !== false || stripos($u, 'TIPO') !== false) {
                $lastIndex = max($lastIndex, $i);
            }
        }
        if ($lastIndex < 0) {
            $lastIndex = count($header) - 1;
        }
        $header = array_slice($header, 0, $lastIndex + 1);

        $map = ['fecha' => null, 'maquina' => null, 'rango' => null, 'total_venta' => null, 'tipo' => null];
        foreach ($header as $key) {
            $u = mb_strtoupper($key);
            if (stripos($u, 'FECHA') !== false) {
                $map['fecha'] = $key;
            }
            if (stripos($u, 'CONCEPTO') !== false) {
                $map['maquina'] = $key;
            }
            if (stripos($u, 'TOTAL') !== false && stripos($u, 'VENTA') !== false) {
                $map['total_venta'] = $key;
            }
            if (stripos($u, 'VENTA') !== false && stripos($u, 'TOTAL') === false && $map['total_venta'] === null) {
                $map['total_venta'] = $key;
            }
            if (stripos($u, 'TIPO') !== false) {
                $map['tipo'] = $key;
            }
            if ((stripos($u, 'MAQUINA') !== false || stripos($u, 'MÁQUINA') !== false) && stripos($u, 'FISCAL') !== false && $map['maquina'] === null) {
                $map['maquina'] = $key;
            }
            if (stripos($u, 'FACTURA') !== false) {
                $map['rango'] = $key;
            }
        }
        if ($map['total_venta'] === null || $map['fecha'] === null) {
            return [];
        }

        $normalizadas = [];
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
            $norm = $this->normalizarFilaValidacion($assoc, $map);
            if ($norm !== null) {
                $normalizadas[] = $norm;
            }
        }

        return $this->agregarPorDiaMaquinaValidacion($normalizadas);
    }

    /**
     * Normaliza una fila del CSV para validación (misma lógica que CuadrePedidosDiario).
     */
    protected function normalizarFilaValidacion(array $fila, array $map): ?array
    {
        $totalVenta = trim((string) ($fila[$map['total_venta']] ?? '0'));
        $totalVenta = preg_replace('/\s+/', '', $totalVenta);
        if ($totalVenta === '' || !is_numeric(str_replace(',', '.', $totalVenta))) {
            return null;
        }
        $totalVenta = str_replace(',', '.', $totalVenta);
        $fecha = trim((string) ($fila[$map['fecha']] ?? ''));
        if ($fecha === '') {
            return null;
        }
        $maquina = $map['maquina'] !== null ? trim((string) ($fila[$map['maquina']] ?? '')) : '';
        $factura = $map['rango'] !== null ? trim((string) ($fila[$map['rango']] ?? '')) : '';
        $tipoRaw = $map['tipo'] !== null ? trim(mb_strtoupper((string) ($fila[$map['tipo']] ?? ''))) : '';

        if ($map['tipo'] !== null && $tipoRaw !== '') {
            if (strpos($tipoRaw, 'REDUCE') !== false && strpos($tipoRaw, 'TOTAL') !== false) {
                return ['fecha' => $fecha, 'maquina_fiscal' => $maquina, 'total_venta' => $totalVenta, 'factura_inicio' => null, 'factura_fin' => null, 'cantidad' => 0];
            }
            if (strpos($tipoRaw, 'FISCAL') !== false && strpos($tipoRaw, 'UNITARIA') !== false) {
                if ($maquina === '') {
                    return null;
                }
                $num = preg_replace('/\D/', '', $factura);
                if ($num === '') {
                    return null;
                }
                return ['fecha' => $fecha, 'maquina_fiscal' => $maquina, 'total_venta' => $totalVenta, 'factura_inicio' => $num, 'factura_fin' => $num, 'cantidad' => 1];
            }
            if (strpos($tipoRaw, 'FISCAL') !== false && strpos($tipoRaw, 'RANGO') !== false) {
                if ($maquina === '') {
                    return null;
                }
                if ($factura !== '' && preg_match('/^(\d+)\s*-\s*(\d+)$/', $factura, $m)) {
                    $inicio = (int) $m[1];
                    $fin = (int) $m[2];
                    if ($fin >= $inicio) {
                        return ['fecha' => $fecha, 'maquina_fiscal' => $maquina, 'total_venta' => $totalVenta, 'factura_inicio' => (string) $inicio, 'factura_fin' => (string) $fin, 'cantidad' => $fin - $inicio + 1];
                    }
                }
                return null;
            }
        }

        if ($maquina === '') {
            return null;
        }
        if ($factura !== '' && preg_match('/^(\d+)\s*-\s*(\d+)$/', $factura, $m)) {
            $inicio = (int) $m[1];
            $fin = (int) $m[2];
            if ($fin >= $inicio) {
                return ['fecha' => $fecha, 'maquina_fiscal' => $maquina, 'total_venta' => $totalVenta, 'factura_inicio' => (string) $inicio, 'factura_fin' => (string) $fin, 'cantidad' => $fin - $inicio + 1];
            }
        }
        $num = preg_replace('/\D/', '', $factura);
        if ($num !== '') {
            return ['fecha' => $fecha, 'maquina_fiscal' => $maquina, 'total_venta' => $totalVenta, 'factura_inicio' => $num, 'factura_fin' => $num, 'cantidad' => 1];
        }
        return ['fecha' => $fecha, 'maquina_fiscal' => $maquina, 'total_venta' => $totalVenta, 'factura_inicio' => null, 'factura_fin' => null, 'cantidad' => 0];
    }

    /**
     * Agrupa por (fecha, maquina), aplica reducción diaria y combina rangos (misma lógica que CuadrePedidosDiario).
     */
    protected function agregarPorDiaMaquinaValidacion(array $normalizadas): array
    {
        $grupos = [];
        $reduccionPorDia = [];
        foreach ($normalizadas as $row) {
            $key = $row['fecha'] . '|' . $row['maquina_fiscal'];
            if ($row['maquina_fiscal'] === '') {
                $reduccionPorDia[$row['fecha']] = bcadd($reduccionPorDia[$row['fecha']] ?? '0', $row['total_venta'], 4);
                continue;
            }
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'total_venta' => $row['total_venta'],
                    'factura_inicio' => $row['factura_inicio'],
                    'factura_fin' => $row['factura_fin'],
                    'cantidad' => (int) $row['cantidad'],
                ];
            } else {
                $grupos[$key]['total_venta'] = bcadd($grupos[$key]['total_venta'], $row['total_venta'], 4);
                if ($row['factura_inicio'] !== null && $row['factura_inicio'] !== '') {
                    $inicio = (int) $row['factura_inicio'];
                    $fin = (int) ($row['factura_fin'] ?? $row['factura_inicio']);
                    $cant = (int) $row['cantidad'];
                    $grupos[$key]['factura_inicio'] = $grupos[$key]['factura_inicio'] !== null && $grupos[$key]['factura_inicio'] !== ''
                        ? (string) min((int) $grupos[$key]['factura_inicio'], $inicio)
                        : (string) $inicio;
                    $grupos[$key]['factura_fin'] = $grupos[$key]['factura_fin'] !== null && $grupos[$key]['factura_fin'] !== ''
                        ? (string) max((int) $grupos[$key]['factura_fin'], $fin)
                        : (string) $fin;
                    $grupos[$key]['cantidad'] += $cant;
                }
            }
        }
        $ordenados = [];
        foreach ($grupos as $key => $g) {
            if ($g['factura_inicio'] !== null && $g['factura_inicio'] !== '' && $g['cantidad'] >= 1) {
                $ordenados[] = array_merge($g, ['_key' => $key]);
            }
        }
        usort($ordenados, function ($a, $b) {
            $fechaA = explode('|', (string) ($a['_key'] ?? ''))[0] ?? '';
            $fechaB = explode('|', (string) ($b['_key'] ?? ''))[0] ?? '';
            $c = strcmp($fechaA, $fechaB);
            return $c !== 0 ? $c : strcmp((string) ($a['_key'] ?? ''), (string) ($b['_key'] ?? ''));
        });
        $primeraPorFecha = [];
        foreach ($ordenados as $g) {
            $f = explode('|', (string) ($g['_key'] ?? ''))[0] ?? '';
            if (!isset($primeraPorFecha[$f])) {
                $primeraPorFecha[$f] = $g['_key'];
            }
        }
        $out = [];
        foreach ($ordenados as $g) {
            $key = $g['_key'];
            unset($g['_key']);
            $f = explode('|', (string) $key)[0] ?? '';
            $reduc = $reduccionPorDia[$f] ?? '0';
            if ($reduc !== '0' && $primeraPorFecha[$f] === $key) {
                $g['total_venta'] = bcadd($g['total_venta'], $reduc, 4);
            }
            $out[$key] = $g;
        }
        return $out;
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

        $filename = 'ventas-pedido-' . $id . '-items.csv';

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
