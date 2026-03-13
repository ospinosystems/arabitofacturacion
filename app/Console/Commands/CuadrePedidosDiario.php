<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;

/**
 * Cuadra pedidos por día y por máquina fiscal.
 * CSV: FECHA, MAQUINA_FISCAL, N_Z, RANGO_FACTURA, TOTAL_VENTA.
 * El valor MAQUINA_FISCAL del CSV se guarda en pedidos.maquina_fiscal.
 * No usa tabla de mapeo: cada fila del CSV asigna un bloque de pedidos a esa máquina.
 */
class CuadrePedidosDiario extends Command
{
    protected $signature = 'cuadre:pedidos-diario
                            {archivo : Ruta al CSV (FECHA, MAQUINA_FISCAL, RANGO_FACTURA, TOTAL_VENTA)}
                            {--desde-cero : Antes de procesar, resetea el cuadre en el rango de fechas del CSV}
                            {--dry-run : Solo mostrar qué se haría, sin escribir}';

    protected $description = 'Cuadre por día y máquina fiscal; maquina_fiscal se setea en cada pedido desde el CSV.';

    protected int $scale = 4;

    protected int $totalPedidosMarcados = 0;
    protected string $totalMontoAcumulado = '0';
    protected int $filasOmitidas = 0;
    protected int $filasProcesadas = 0;

    public function handle(): int
    {
        $path = $this->argument('archivo');
        $desdeCero = $this->option('desde-cero');
        $dryRun = $this->option('dry-run');

        if (!is_file($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return Command::FAILURE;
        }

        $filas = $this->leerArchivo($path);
        if (empty($filas)) {
            $this->error('No se encontraron filas válidas en el archivo.');
            return Command::FAILURE;
        }

        $columnMap = $this->detectarColumnas(empty($filas) ? [] : array_keys($filas[0]));
        $normalizadas = [];
        foreach ($filas as $idx => $fila) {
            $normalized = $this->normalizarFila($fila, $columnMap);
            if ($normalized === null) {
                if (empty($normalizadas) && $idx === 0) {
                    $this->warn('Primera fila ignorada (sin valor en columna de total venta o fecha/máquina).');
                }
                continue;
            }
            $normalizadas[] = $normalized;
        }

        $agregadas = $this->agregarPorDiaMaquina($normalizadas);
        $this->info('Filas en CSV: ' . count($filas) . ' → Agrupadas por (fecha + máquina): ' . count($agregadas) . ' (notas de crédito suman negativo al monto objetivo).');
        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirá en la base de datos.');
        }

        if ($desdeCero && !$dryRun && !empty($agregadas)) {
            $fechaDesde = min(array_column($agregadas, 'fecha'));
            $fechaHasta = max(array_column($agregadas, 'fecha'));
            $this->warn("Opción --desde-cero: se reseteará el cuadre entre {$fechaDesde} y {$fechaHasta} y luego se procesará el CSV.");
            if (!$this->confirm('¿Continuar?', true)) {
                return Command::SUCCESS;
            }
            $this->resetRangoFechas($fechaDesde, $fechaHasta);
        }

        foreach ($agregadas as $normalized) {
            $fecha = $normalized['fecha'];
            $maquina = $normalized['maquina_fiscal'];
            $montoObjetivo = $normalized['total_venta'];
            $facturaInicio = $normalized['factura_inicio'];
            $facturaFin = $normalized['factura_fin'];
            $cantidad = $normalized['cantidad'];

            $this->line("Procesando <comment>{$fecha}</comment> | <comment>{$maquina}</comment>");

            if (!$dryRun) {
                try {
                    $resultado = $this->procesarDiaMaquina($fecha, $maquina, $montoObjetivo, $facturaInicio, $facturaFin, $cantidad);
                    if ($resultado !== null && !empty($resultado['skipped'])) {
                        $this->filasOmitidas++;
                        $this->line('  → <fg=yellow>omitido (día+máquina ya procesado)</>');
                    } elseif ($resultado !== null) {
                        $this->filasProcesadas++;
                        $this->totalMontoAcumulado = bcadd($this->totalMontoAcumulado, $resultado['monto_total_dia'], $this->scale);
                        $this->line(sprintf(
                            '  → <info>%d facturas</info> (%s–%s), <info>%s Bs</info> (ajuste: <comment>%s Bs</comment>)',
                            $resultado['cantidad_facturas'],
                            $facturaInicio,
                            $facturaFin,
                            number_format((float) $resultado['monto_total_dia'], 4, ',', '.'),
                            number_format((float) $resultado['diferencia'], 4, ',', '.')
                        ));
                    } else {
                        $this->line('  → sin pedidos no válidos para esta fecha');
                    }
                } catch (\Throwable $e) {
                    $this->error("Error: " . $e->getMessage());
                    \Log::error('CuadrePedidosDiario: ' . $e->getMessage(), [
                        'fecha' => $fecha,
                        'maquina' => $maquina,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return Command::FAILURE;
                }
            }
        }

        if (empty($agregadas)) {
            $this->warn('No se normalizó ninguna fila. Compruebe que el CSV tenga cabecera: FECHA, MAQUINA_FISCAL, RANGO_FACTURA, TOTAL_VENTA (y que RANGO_FACTURA sea tipo "1-48").');
        }

        $this->mostrarResumen();
        return Command::SUCCESS;
    }

    /**
     * Resetea el cuadre en el rango de fechas dado: limpia numero_factura, maquina_fiscal, valido
     * y elimina ítems de ajuste (recalcula pago). Misma lógica que cuadre:pedidos-reset.
     */
    protected function resetRangoFechas(string $fechaDesde, string $fechaHasta): void
    {
        $dateExpr = 'DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at))';
        $pedidosReset = pedidos::where('valido', true)
            ->whereRaw($dateExpr . ' >= ?', [$fechaDesde])
            ->whereRaw($dateExpr . ' <= ?', [$fechaHasta])
            ->get();

        $total = $pedidosReset->count();
        if ($total === 0) {
            $this->line('  (No había pedidos válidos en ese rango.)');
            return;
        }

        $ids = $pedidosReset->pluck('id')->all();

        DB::transaction(function () use ($ids) {
            $itemsAjuste = items_pedidos::whereIn('id_pedido', $ids)
                ->whereNull('id_producto')->where('cantidad', 1)->where('monto', 0)->get();

            foreach ($itemsAjuste as $item) {
                $item->delete();
            }

            foreach ($itemsAjuste->pluck('id_pedido')->unique() as $idPedido) {
                $suma = (string) items_pedidos::where('id_pedido', $idPedido)->sum(DB::raw('COALESCE(monto_bs, 0)'));
                $pago = pago_pedidos::where('id_pedido', $idPedido)->first();
                if ($pago) {
                    $pago->monto = (float) $suma;
                    if (Schema::hasColumn('pago_pedidos', 'monto_bs')) {
                        $pago->monto_bs = (float) $suma;
                    }
                    $pago->save();
                }
            }

            $data = ['numero_factura' => null, 'valido' => false];
            if (Schema::hasColumn('pedidos', 'maquina_fiscal')) {
                $data['maquina_fiscal'] = null;
            }
            pedidos::whereIn('id', $ids)->update($data);
        });

        $this->info("  Reseteados {$total} pedidos en el rango. Iniciando cuadre desde el primer día del CSV.");
    }

    /**
     * Agrupa filas por (fecha, maquina_fiscal) y suma TOTAL_VENTA.
     * Así, si el mismo día y máquina tiene dos filas (venta positiva y nota de crédito negativa),
     * el monto objetivo es la suma (ej. 1000 + (-50) = 950).
     * Rango de facturas (inicio, fin, cantidad) se toma de la primera fila del grupo.
     */
    protected function agregarPorDiaMaquina(array $normalizadas): array
    {
        $grupos = [];
        foreach ($normalizadas as $row) {
            $key = $row['fecha'] . '|' . $row['maquina_fiscal'];
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'fecha'           => $row['fecha'],
                    'maquina_fiscal'  => $row['maquina_fiscal'],
                    'total_venta'     => $row['total_venta'],
                    'factura_inicio'  => $row['factura_inicio'],
                    'factura_fin'     => $row['factura_fin'],
                    'cantidad'        => $row['cantidad'],
                ];
            } else {
                $grupos[$key]['total_venta'] = bcadd($grupos[$key]['total_venta'], $row['total_venta'], $this->scale + 2);
                if (($grupos[$key]['factura_inicio'] === null || $grupos[$key]['factura_inicio'] === '') && isset($row['factura_inicio']) && $row['factura_inicio'] !== null && $row['factura_inicio'] !== '') {
                    $grupos[$key]['factura_inicio'] = $row['factura_inicio'];
                    $grupos[$key]['factura_fin'] = $row['factura_fin'];
                    $grupos[$key]['cantidad'] = $row['cantidad'];
                }
            }
        }
        return array_values(array_filter($grupos, function ($g) {
            return $g['factura_inicio'] !== null && $g['factura_inicio'] !== '' && (int) $g['cantidad'] >= 1;
        }));
    }

    /**
     * Detecta qué claves del CSV corresponden a fecha, máquina, rango y total venta
     * por contenido del encabezado (no por nombres fijos). Solo se considera hasta la columna "Total venta".
     */
    protected function detectarColumnas(array $headerKeys): array
    {
        $headerKeys = array_values($headerKeys);
        $map = ['fecha' => null, 'maquina' => null, 'rango' => null, 'total_venta' => null];
        $lastTotalVentaIndex = null;

        foreach ($headerKeys as $index => $key) {
            $k = trim((string) $key);
            $u = mb_strtoupper($k);
            $uNorm = preg_replace('/\s+/', ' ', $u);

            if (stripos($u, 'FECHA') !== false) {
                $map['fecha'] = $key;
            }
            if (stripos($u, 'TOTAL') !== false && stripos($u, 'VENTA') !== false) {
                $map['total_venta'] = $key;
                $lastTotalVentaIndex = $index;
            }
            if ((stripos($u, 'MAQUINA') !== false || stripos($u, 'MÁQUINA') !== false) && stripos($u, 'FISCAL') !== false) {
                $map['maquina'] = $key;
            }
            if (stripos($u, 'FACTURA') !== false && (stripos($u, 'RANGO') !== false || stripos($u, 'N°') !== false || stripos($u, 'NUMERO') !== false || preg_match('/N[\s.]*FACTURA/', $uNorm))) {
                $map['rango'] = $key;
            }
            if (preg_match('/^ZZN\d+$/i', $k)) {
                $map['maquina'] = $key;
                if ($map['total_venta'] === null) {
                    $map['total_venta'] = $key;
                }
            }
        }

        if ($lastTotalVentaIndex !== null) {
            $map['_solo_hasta_index'] = $lastTotalVentaIndex;
        }
        return $map;
    }

    /**
     * Normaliza una fila usando el mapa de columnas detectado.
     * Solo se considera el movimiento si hay valor en la columna de total venta (y fecha y máquina para agrupar).
     * Si la columna de máquina fiscal tiene valor, se considera (resumen de ventas o nota de crédito).
     */
    protected function normalizarFila(array $fila, array $columnMap): ?array
    {
        $totalVentaKey = $columnMap['total_venta'] ?? null;
        $fechaKey = $columnMap['fecha'] ?? null;
        $maquinaKey = $columnMap['maquina'] ?? null;
        $rangoKey = $columnMap['rango'] ?? null;

        if ($totalVentaKey === null) {
            return null;
        }

        $totalVenta = trim((string) ($fila[$totalVentaKey] ?? '0'));
        if ($totalVenta === '') {
            return null;
        }

        $fecha = $fechaKey !== null ? trim((string) ($fila[$fechaKey] ?? '')) : '';
        if ($maquinaKey !== null) {
            $maquina = trim((string) ($fila[$maquinaKey] ?? ''));
            if ($maquina === '' && preg_match('/^ZZN\d+$/i', (string) $maquinaKey)) {
                $maquina = (string) $maquinaKey;
            }
        } else {
            $maquina = '';
        }
        $rango = $rangoKey !== null ? trim((string) ($fila[$rangoKey] ?? '')) : '';

        if ($fecha === '') {
            return null;
        }
        if ($maquina === '') {
            return null;
        }

        if ($rango !== '' && preg_match('/^(\d+)\s*-\s*(\d+)$/', $rango, $m)) {
            $inicio = (int) $m[1];
            $fin = (int) $m[2];
            if ($fin >= $inicio) {
                return [
                    'fecha'           => $fecha,
                    'maquina_fiscal'  => $maquina,
                    'total_venta'     => $totalVenta,
                    'factura_inicio'  => (string) $inicio,
                    'factura_fin'     => (string) $fin,
                    'cantidad'        => $fin - $inicio + 1,
                ];
            }
        }

        return [
            'fecha'           => $fecha,
            'maquina_fiscal'  => $maquina,
            'total_venta'     => $totalVenta,
            'factura_inicio'  => null,
            'factura_fin'     => null,
            'cantidad'        => 0,
        ];
    }

    /**
     * Lee el CSV y solo conserva columnas hasta la que dice "Total venta" (inclusive).
     */
    protected function leerArchivo(string $path): array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $delim = (strpos($path, '.tsv') !== false || (strpos($content, "\t") !== false && strpos($content, ',') === false)) ? "\t" : ',';
        $lines = array_map('trim', explode("\n", $content));
        $header = str_getcsv(array_shift($lines), $delim);
        $header = array_map(function ($h) {
            $h = trim($h);
            return preg_replace('/^\xEF\xBB\xBF/', '', $h);
        }, $header);

        $lastIndex = count($header) - 1;
        foreach ($header as $i => $h) {
            $u = mb_strtoupper($h);
            if (stripos($u, 'TOTAL') !== false && stripos($u, 'VENTA') !== false) {
                $lastIndex = $i;
                break;
            }
        }
        $header = array_slice($header, 0, $lastIndex + 1);

        $out = [];
        foreach ($lines as $line) {
            if ($line === '') continue;
            $row = str_getcsv($line, $delim);
            $row = array_pad($row, count($header), '');
            $row = array_slice($row, 0, $lastIndex + 1);
            $assoc = array_combine($header, $row);
            if ($assoc !== false) {
                $out[] = $assoc;
            }
        }
        return $out;
    }

    /**
     * Procesa una fila: fecha + máquina. Pedidos de esa fecha no válidos; ventana óptima; asigna numero_factura y maquina_fiscal (del CSV).
     */
    protected function procesarDiaMaquina(string $fecha, string $maquinaFiscal, string $montoObjetivo, string $facturaInicio, string $facturaFin, int $cantidadObjetivo): ?array
    {
        $fechaStr = $fecha;

        $yaProcesado = Schema::hasColumn('pedidos', 'maquina_fiscal')
            && pedidos::whereRaw('DATE(COALESCE(fecha_factura, created_at)) = ?', [$fechaStr])
                ->where('maquina_fiscal', $maquinaFiscal)
                ->where('valido', true)
                ->exists();
        if ($yaProcesado) {
            return ['skipped' => true];
        }

        return DB::transaction(function () use ($fechaStr, $maquinaFiscal, $montoObjetivo, $facturaInicio, $facturaFin, $cantidadObjetivo) {
            $pedidos = pedidos::whereRaw('DATE(COALESCE(fecha_factura, created_at)) = ?', [$fechaStr])
                ->where(function ($q) {
                    $q->whereNull('valido')->orWhere('valido', false)->orWhere('valido', 0);
                })
                ->orderByRaw('COALESCE(fecha_factura, created_at) ASC')
                ->orderBy('id')
                ->get();

            if ($pedidos->isEmpty()) {
                return null;
            }

            $pairs = [];
            foreach ($pedidos as $ped) {
                $monto = (string) DB::table('items_pedidos')
                    ->where('id_pedido', $ped->id)
                    ->sum(DB::raw('COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0)'));
                $pairs[] = ['pedido' => $ped, 'monto' => $monto];
            }

            $total = count($pairs);
            $selected = collect();
            $sumaSelected = '0';

            if ($total <= $cantidadObjetivo) {
                $selected = collect($pairs)->map(fn ($p) => $p['pedido'])->values();
                $sumaSelected = array_reduce(
                    array_column($pairs, 'monto'),
                    fn ($c, $m) => bcadd($c ?? '0', $m, $this->scale),
                    '0'
                );
            } else {
                // Selección greedy: en cada paso se elige el pedido que deja la suma más cerca del objetivo.
                // Así se obtiene una mezcla natural de montos (pequeños, medianos, grandes), no una banda igual.
                $selectedIndices = [];
                $suma = '0';
                for ($k = 0; $k < $cantidadObjetivo; $k++) {
                    $bestIdx = null;
                    $bestAbsDiff = null;
                    foreach (array_keys($pairs) as $idx) {
                        if (in_array($idx, $selectedIndices, true)) {
                            continue;
                        }
                        $newSum = bcadd($suma, $pairs[$idx]['monto'], $this->scale);
                        $diff = bcsub($montoObjetivo, $newSum, $this->scale + 2);
                        $absDiff = $diff[0] === '-' ? bcsub('0', $diff, $this->scale + 2) : $diff;
                        if ($bestAbsDiff === null || bccomp($absDiff, $bestAbsDiff, $this->scale + 2) < 0) {
                            $bestAbsDiff = $absDiff;
                            $bestIdx = $idx;
                        }
                    }
                    $selectedIndices[] = $bestIdx;
                    $suma = bcadd($suma, $pairs[$bestIdx]['monto'], $this->scale);
                }
                $sumaSelected = $suma;

                // Búsqueda local: intercambiar un pedido seleccionado por uno no seleccionado si mejora el ajuste
                $currentDiff = bcsub($montoObjetivo, $sumaSelected, $this->scale + 2);
                $currentAbsDiff = $currentDiff[0] === '-' ? bcsub('0', $currentDiff, $this->scale + 2) : $currentDiff;
                $maxSwaps = 5000; // límite para no demorar indefinidamente
                $swaps = 0;
                do {
                    $improved = false;
                    for ($i = 0; $i < $cantidadObjetivo && $swaps < $maxSwaps; $i++) {
                        $s = $selectedIndices[$i];
                        $restantes = array_diff(range(0, $total - 1), $selectedIndices);
                        foreach ($restantes as $u) {
                            $newSum = bcsub(bcadd($sumaSelected, $pairs[$u]['monto'], $this->scale), $pairs[$s]['monto'], $this->scale);
                            $newDiff = bcsub($montoObjetivo, $newSum, $this->scale + 2);
                            $newAbsDiff = $newDiff[0] === '-' ? bcsub('0', $newDiff, $this->scale + 2) : $newDiff;
                            if (bccomp($newAbsDiff, $currentAbsDiff, $this->scale + 2) < 0) {
                                $selectedIndices[$i] = (int) $u;
                                $sumaSelected = $newSum;
                                $currentAbsDiff = $newAbsDiff;
                                $improved = true;
                                $swaps++;
                                break;
                            }
                        }
                    }
                } while ($improved && $swaps < $maxSwaps);

                $selected = collect($selectedIndices)->map(fn ($idx) => $pairs[$idx]['pedido'])->values();

                // Ordenar seleccionados por fecha/id para asignar numero_factura en orden cronológico
                $selected = $selected->sortBy([
                    fn ($p) => $p->fecha_factura ?? $p->created_at,
                    fn ($p) => $p->id,
                ])->values();
            }

            $inicioNum = (int) $facturaInicio;
            foreach ($selected as $i => $ped) {
                $ped->numero_factura = (string) ($inicioNum + $i);
                if (Schema::hasColumn('pedidos', 'maquina_fiscal')) {
                    $ped->maquina_fiscal = $maquinaFiscal;
                }
                $ped->valido = true;
                $ped->save();
                $this->totalPedidosMarcados++;
            }

            $ajuste = bcsub($montoObjetivo, $sumaSelected, $this->scale);
            $ultimo = $selected->last();
            $nuevoTotal = bcadd($sumaSelected, $ajuste, $this->scale);

            if (bccomp($ajuste, '0', $this->scale) !== 0) {
                items_pedidos::create([
                    'id_pedido'   => $ultimo->id,
                    'id_producto' => null,
                    'cantidad'   => 1,
                    'descuento'  => 0,
                    'monto'      => 0,
                    'monto_bs'   => (float) $ajuste,
                    'condicion'  => 0,
                ]);
                $pago = pago_pedidos::where('id_pedido', $ultimo->id)->first();
                $nuevoTotalFloat = (float) $nuevoTotal;
                if ($pago) {
                    $pago->monto = $nuevoTotalFloat;
                    if (Schema::hasColumn('pago_pedidos', 'monto_bs')) {
                        $pago->monto_bs = $nuevoTotalFloat;
                    }
                    $pago->save();
                } else {
                    $datosPago = [
                        'id_pedido'      => $ultimo->id,
                        'tipo'           => '5',
                        'cuenta'         => 1,
                        'monto'          => $nuevoTotalFloat,
                        'monto_original' => $nuevoTotalFloat,
                    ];
                    if (Schema::hasColumn('pago_pedidos', 'monto_bs')) {
                        $datosPago['monto_bs'] = $nuevoTotalFloat;
                    }
                    pago_pedidos::create($datosPago);
                }
            }

            return [
                'cantidad_facturas' => $selected->count(),
                'monto_total_dia'   => $nuevoTotal,
                'diferencia'        => $ajuste,
            ];
        });
    }

    protected function mostrarResumen(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('           RESULTADO FINAL');
        $this->info('═══════════════════════════════════════');
        $this->info('Filas (día+máquina) procesadas .....: ' . $this->filasProcesadas);
        $this->info('Filas omitidas (ya procesadas) ....: ' . $this->filasOmitidas);
        $this->info('Pedidos marcados válidos ...........: ' . $this->totalPedidosMarcados);
        $this->info('Monto total (Bs) ...................: ' . number_format((float) $this->totalMontoAcumulado, 4, ',', '.'));
        $this->info('═══════════════════════════════════════');
    }
}
