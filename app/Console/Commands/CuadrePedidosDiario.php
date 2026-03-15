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
 * CSV: FECHA, CONCEPTO, CEDULA, SERIE, NOTA DE CREDITO, AFECTADA, NUMERO DE Z, FACTURA, VENTA, TIPO.
 * - TIPO "FISCAL RANGO": carga por rango (FACTURA = inicio-fin); máquina = CONCEPTO; monto en VENTA (positivo).
 * - TIPO "FISCAL UNITARIA": una factura por pedido (FACTURA = número sin guión); máquina = CONCEPTO; monto en VENTA (positivo).
 * - TIPO "REDUCE EL TOTAL DE ESE DIA": notas de crédito (VENTA negativo); reducen el monto objetivo del día.
 * Monto objetivo del día = suma de positivos (FISCAL RANGO + FISCAL UNITARIA) menos negativos (REDUCE).
 * El valor CONCEPTO se guarda en pedidos.maquina_fiscal.
 */
class CuadrePedidosDiario extends Command
{
    protected $signature = 'cuadre:pedidos-diario
                            {archivo : Ruta al CSV (FECHA, CONCEPTO, FACTURA, VENTA, TIPO)}
                            {--desde-cero : Antes de procesar, resetea el cuadre en el rango de fechas del CSV}
                            {--dry-run : Solo mostrar qué se haría, sin escribir}
                            {--solo-fecha= : Procesar solo este día (YYYY-MM-DD)}';

    protected $description = 'Cuadre por día y máquina fiscal (CSV con FECHA, CONCEPTO, FACTURA, VENTA, TIPO).';

    protected int $scale = 4;

    protected int $totalPedidosMarcados = 0;
    protected string $totalMontoAcumulado = '0';
    protected int $filasOmitidas = 0;
    protected int $filasProcesadas = 0;
    protected int $filasRechazadasAjuste = 0;

    public function handle(): int
    {
        $path = $this->argument('archivo');
        $desdeCero = $this->option('desde-cero');
        $dryRun = $this->option('dry-run');
        $soloFecha = $this->option('solo-fecha') ? trim((string) $this->option('solo-fecha')) : null;

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

        if ($soloFecha !== null && $soloFecha !== '') {
            $agregadas = array_values(array_filter($agregadas, function ($a) use ($soloFecha) {
                return isset($a['fecha']) && $a['fecha'] === $soloFecha;
            }));
            $this->info("Filtro --solo-fecha={$soloFecha}: se procesarán " . count($agregadas) . ' grupo(s) (fecha + máquina).');
        }

        $this->info('Filas en CSV: ' . count($filas) . ' → Agrupadas por (fecha + máquina): ' . count($agregadas) . ' (REDUCE EL TOTAL DE ESE DIA resta del monto objetivo).');
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
                // Reconexión preventiva cada iteración (evita "MySQL server has gone away" por inactividad en Cloudways).
                \Log::info('CuadrePedidosDiario: inicio iteración', ['fecha' => $fecha, 'maquina' => $maquina, 'paso' => 'antes_reconnect']);
                DB::reconnect();
                \Log::info('CuadrePedidosDiario: reconnect hecho', ['fecha' => $fecha, 'maquina' => $maquina]);
                $maxReintentosConexion = 3;
                $resultado = null;
                $reintentoConexion = 0;
                $procesado = false;
                while (!$procesado) {
                    try {
                        $resultado = $this->procesarDiaMaquina($fecha, $maquina, $montoObjetivo, $facturaInicio, $facturaFin, $cantidad);

                        if ($resultado !== null && !empty($resultado['skipped'])) {
                            $this->filasOmitidas++;
                            $this->line('  → <fg=yellow>omitido (día+máquina ya procesado)</>');
                        } elseif ($resultado !== null) {
                            $this->filasProcesadas++;
                            $this->totalMontoAcumulado = bcadd($this->totalMontoAcumulado, $resultado['monto_total_dia'], $this->scale);
                            $linea = sprintf(
                                '  → <info>%d facturas</info> (%s–%s), <info>%s Bs</info> (ajuste: <comment>%s Bs</comment>)',
                                $resultado['cantidad_facturas'],
                                $facturaInicio,
                                $facturaFin,
                                number_format((float) $resultado['monto_total_dia'], 4, ',', '.'),
                                number_format((float) $resultado['diferencia'], 4, ',', '.')
                            );
                            $pctAjuste = $resultado['pct_ajuste'] ?? 0;
                            if ($pctAjuste > 0.05) {
                                $linea .= ' <fg=yellow>(ajuste alto: ' . round($pctAjuste * 100, 1) . '%)</>';
                            }
                            $this->line($linea);
                        } else {
                            $this->line('  → sin pedidos no válidos para esta fecha');
                        }
                        $procesado = true;
                    } catch (\Throwable $e) {
                        \Log::warning('CuadrePedidosDiario: excepción capturada', [
                            'fecha' => $fecha,
                            'maquina' => $maquina,
                            'mensaje' => $e->getMessage(),
                            'codigo' => $e->getCode(),
                            'archivo' => $e->getFile(),
                            'linea' => $e->getLine(),
                            'es_gone_away' => $this->esErrorMysqlGoneAway($e),
                            'reintento_actual' => $reintentoConexion,
                            'max_reintentos' => $maxReintentosConexion,
                        ]);
                        if ($this->esErrorMysqlGoneAway($e) && $reintentoConexion < $maxReintentosConexion) {
                            $reintentoConexion++;
                            $this->line('  → MySQL cerró la conexión, reconectando e intentando de nuevo (' . $reintentoConexion . '/' . $maxReintentosConexion . ')…');
                            \Log::info('CuadrePedidosDiario: reconectando por gone away', ['fecha' => $fecha, 'maquina' => $maquina, 'intento' => $reintentoConexion]);
                            DB::reconnect();
                            continue;
                        }
                        $this->error("Error: " . $e->getMessage());
                        \Log::error('CuadrePedidosDiario: fallo definitivo', [
                            'fecha' => $fecha,
                            'maquina' => $maquina,
                            'mensaje' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        return Command::FAILURE;
                    }
                }
            }
        }

        if (empty($agregadas)) {
            $this->warn('No se normalizó ninguna fila. Compruebe que el CSV tenga cabecera: FECHA, CONCEPTO, FACTURA, VENTA, TIPO (FISCAL RANGO / FISCAL UNITARIA / REDUCE EL TOTAL DE ESE DIA).');
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
     * Agrupa filas por (fecha, maquina_fiscal). Suma total_venta (positivos FISCAL RANGO/UNITARIA y negativos REDUCE).
     * Combina rangos/unitarias: factura_inicio = min, factura_fin = max, cantidad = suma.
     * Si hay REDUCE con maquina vacía (reducción del día), se resta del primer grupo de esa fecha.
     */
    protected function agregarPorDiaMaquina(array $normalizadas): array
    {
        $grupos = [];
        $reduccionPorDia = [];

        foreach ($normalizadas as $row) {
            $key = $row['fecha'] . '|' . $row['maquina_fiscal'];
            if ($row['maquina_fiscal'] === '') {
                $reduccionPorDia[$row['fecha']] = bcadd($reduccionPorDia[$row['fecha']] ?? '0', $row['total_venta'], $this->scale + 2);
                continue;
            }
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'fecha'           => $row['fecha'],
                    'maquina_fiscal'  => $row['maquina_fiscal'],
                    'total_venta'     => $row['total_venta'],
                    'factura_inicio'  => $row['factura_inicio'],
                    'factura_fin'     => $row['factura_fin'],
                    'cantidad'        => (int) $row['cantidad'],
                ];
            } else {
                $grupos[$key]['total_venta'] = bcadd($grupos[$key]['total_venta'], $row['total_venta'], $this->scale + 2);
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

        // Grupos que solo tienen REDUCE (sin factura/cantidad) se filtran; pasar su monto a reducción del día.
        foreach ($grupos as $key => $g) {
            if (($g['factura_inicio'] === null || $g['factura_inicio'] === '' || $g['cantidad'] < 1) && bccomp($g['total_venta'], '0', $this->scale + 2) !== 0) {
                $reduccionPorDia[$g['fecha']] = bcadd($reduccionPorDia[$g['fecha']] ?? '0', $g['total_venta'], $this->scale + 2);
            }
        }

        $ordenados = array_values(array_filter($grupos, function ($g) {
            return $g['factura_inicio'] !== null && $g['factura_inicio'] !== '' && $g['cantidad'] >= 1;
        }));

        usort($ordenados, function ($a, $b) {
            $c = strcmp($a['fecha'], $b['fecha']);
            return $c !== 0 ? $c : strcmp($a['maquina_fiscal'], $b['maquina_fiscal']);
        });

        $primeraMaquinaPorFecha = [];
        foreach ($ordenados as $g) {
            $f = $g['fecha'];
            if (!isset($primeraMaquinaPorFecha[$f])) {
                $primeraMaquinaPorFecha[$f] = $g['fecha'] . '|' . $g['maquina_fiscal'];
            }
        }

        foreach ($ordenados as $i => $g) {
            $key = $g['fecha'] . '|' . $g['maquina_fiscal'];
            $reduc = $reduccionPorDia[$g['fecha']] ?? '0';
            if ($reduc !== '0' && $primeraMaquinaPorFecha[$g['fecha']] === $key) {
                $ordenados[$i]['total_venta'] = bcadd($g['total_venta'], $reduc, $this->scale + 2);
            }
        }

        return $ordenados;
    }

    /**
     * Detecta columnas del CSV: FECHA, CONCEPTO (máquina), FACTURA, VENTA, TIPO.
     * Acepta también formato antiguo: MAQUINA_FISCAL, RANGO_FACTURA, TOTAL_VENTA.
     */
    protected function detectarColumnas(array $headerKeys): array
    {
        $headerKeys = array_values($headerKeys);
        $map = ['fecha' => null, 'maquina' => null, 'rango' => null, 'total_venta' => null, 'tipo' => null];
        $lastIndex = count($headerKeys) - 1;

        foreach ($headerKeys as $index => $key) {
            $k = trim((string) $key);
            $u = mb_strtoupper($k);
            $uNorm = preg_replace('/\s+/', ' ', $u);

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
            if (stripos($u, 'FACTURA') !== false && (stripos($u, 'RANGO') !== false || stripos($u, 'N°') !== false || stripos($u, 'NUMERO') !== false || preg_match('/N[\s.]*FACTURA/', $uNorm) || $u === 'FACTURA')) {
                $map['rango'] = $key;
            }
            if (preg_match('/^ZZN\d+$/i', $k) && $map['maquina'] === null) {
                $map['maquina'] = $key;
                if ($map['total_venta'] === null) {
                    $map['total_venta'] = $key;
                }
            }
        }

        foreach (['total_venta', 'tipo'] as $col) {
            if ($map[$col] !== null) {
                $idx = array_search($map[$col], $headerKeys, true);
                if ($idx !== false && $idx > $lastIndex) {
                    $lastIndex = $idx;
                }
            }
        }
        $map['_solo_hasta_index'] = $lastIndex;
        return $map;
    }

    private const TIPO_FISCAL_RANGO = 'FISCAL RANGO';
    private const TIPO_FISCAL_UNITARIA = 'FISCAL UNITARIA';
    private const TIPO_REDUCE_DIA = 'REDUCE EL TOTAL DE ESE DIA';

    /**
     * Normaliza una fila usando el mapa de columnas detectado.
     * TIPO "FISCAL RANGO": rango en FACTURA (inicio-fin), monto positivo en VENTA, máquina = CONCEPTO.
     * TIPO "FISCAL UNITARIA": un número en FACTURA (sin guión), monto positivo, máquina = CONCEPTO.
     * TIPO "REDUCE EL TOTAL DE ESE DIA": monto negativo en VENTA; reduce el objetivo del día (CONCEPTO puede estar vacío).
     */
    protected function normalizarFila(array $fila, array $columnMap): ?array
    {
        $totalVentaKey = $columnMap['total_venta'] ?? null;
        $fechaKey = $columnMap['fecha'] ?? null;
        $maquinaKey = $columnMap['maquina'] ?? null;
        $rangoKey = $columnMap['rango'] ?? null;
        $tipoKey = $columnMap['tipo'] ?? null;

        if ($totalVentaKey === null || $fechaKey === null) {
            return null;
        }

        $totalVenta = trim((string) ($fila[$totalVentaKey] ?? '0'));
        $totalVenta = preg_replace('/\s+/', '', $totalVenta);
        if ($totalVenta === '' || !is_numeric(str_replace(',', '.', $totalVenta))) {
            return null;
        }
        $totalVenta = str_replace(',', '.', $totalVenta);

        $fecha = trim((string) ($fila[$fechaKey] ?? ''));
        if ($fecha === '') {
            return null;
        }

        $maquina = $maquinaKey !== null ? trim((string) ($fila[$maquinaKey] ?? '')) : '';
        if ($maquinaKey !== null && $maquina === '' && preg_match('/^ZZN\d+$/i', (string) $maquinaKey)) {
            $maquina = (string) $maquinaKey;
        }

        $factura = $rangoKey !== null ? trim((string) ($fila[$rangoKey] ?? '')) : '';
        $tipoRaw = $tipoKey !== null ? trim(mb_strtoupper((string) ($fila[$tipoKey] ?? ''))) : '';

        if ($tipoKey !== null && $tipoRaw !== '') {
            if (strpos($tipoRaw, 'REDUCE') !== false && strpos($tipoRaw, 'TOTAL') !== false) {
                return [
                    'fecha'           => $fecha,
                    'maquina_fiscal'  => $maquina,
                    'total_venta'     => $totalVenta,
                    'factura_inicio'  => null,
                    'factura_fin'     => null,
                    'cantidad'        => 0,
                    'tipo'            => self::TIPO_REDUCE_DIA,
                ];
            }
            if (strpos($tipoRaw, 'FISCAL') !== false && strpos($tipoRaw, 'UNITARIA') !== false) {
                if ($maquina === '') {
                    return null;
                }
                $num = preg_replace('/\D/', '', $factura);
                if ($num === '') {
                    return null;
                }
                return [
                    'fecha'           => $fecha,
                    'maquina_fiscal'  => $maquina,
                    'total_venta'     => $totalVenta,
                    'factura_inicio'  => $num,
                    'factura_fin'     => $num,
                    'cantidad'        => 1,
                    'tipo'            => self::TIPO_FISCAL_UNITARIA,
                ];
            }
            if (strpos($tipoRaw, 'FISCAL') !== false && strpos($tipoRaw, 'RANGO') !== false) {
                if ($maquina === '') {
                    return null;
                }
                if ($factura !== '' && preg_match('/^(\d+)\s*-\s*(\d+)$/', $factura, $m)) {
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
                            'tipo'            => self::TIPO_FISCAL_RANGO,
                        ];
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
                return [
                    'fecha'           => $fecha,
                    'maquina_fiscal'  => $maquina,
                    'total_venta'     => $totalVenta,
                    'factura_inicio'  => (string) $inicio,
                    'factura_fin'     => (string) $fin,
                    'cantidad'        => $fin - $inicio + 1,
                    'tipo'            => self::TIPO_FISCAL_RANGO,
                ];
            }
        }

        $num = preg_replace('/\D/', '', $factura);
        if ($num !== '') {
            return [
                'fecha'           => $fecha,
                'maquina_fiscal'  => $maquina,
                'total_venta'     => $totalVenta,
                'factura_inicio'  => $num,
                'factura_fin'     => $num,
                'cantidad'        => 1,
                'tipo'            => self::TIPO_FISCAL_UNITARIA,
            ];
        }

        return [
            'fecha'           => $fecha,
            'maquina_fiscal'  => $maquina,
            'total_venta'     => $totalVenta,
            'factura_inicio'  => null,
            'factura_fin'     => null,
            'cantidad'        => 0,
            'tipo'            => self::TIPO_REDUCE_DIA,
        ];
    }

    /**
     * Lee el CSV. Incluye columnas hasta "VENTA" o "TOTAL VENTA" y hasta "TIPO" si existe (formato nuevo).
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

        \Log::info('CuadrePedidosDiario: procesarDiaMaquina inicio', ['fecha' => $fechaStr, 'maquina' => $maquinaFiscal, 'paso' => 'yaProcesado_check']);
        $yaProcesado = Schema::hasColumn('pedidos', 'maquina_fiscal')
            && pedidos::whereRaw('DATE(COALESCE(fecha_factura, created_at)) = ?', [$fechaStr])
                ->where('maquina_fiscal', $maquinaFiscal)
                ->where('valido', true)
                ->exists();
        if ($yaProcesado) {
            \Log::info('CuadrePedidosDiario: día+máquina ya procesado', ['fecha' => $fechaStr, 'maquina' => $maquinaFiscal]);
            return ['skipped' => true];
        }

        // Lectura y cómputo pesado FUERA de la transacción para evitar "MySQL server has gone away"
        // (en Cloudways la conexión se cierra si la transacción está abierta mucho tiempo sin consultas).
        $pedidos = pedidos::whereRaw('DATE(COALESCE(fecha_factura, created_at)) = ?', [$fechaStr])
            ->where(function ($q) {
                $q->whereNull('valido')->orWhere('valido', false)->orWhere('valido', 0);
            })
            ->orderByRaw('COALESCE(fecha_factura, created_at) ASC')
            ->orderBy('id')
            ->get();

        \Log::info('CuadrePedidosDiario: pedidos candidatos obtenidos', ['fecha' => $fechaStr, 'maquina' => $maquinaFiscal, 'total_pedidos' => $pedidos->count()]);
        if ($pedidos->isEmpty()) {
            return null;
        }

        // Una sola consulta para todos los montos por pedido (evita N+1).
        $ids = $pedidos->pluck('id')->all();
        $montosPorPedido = DB::table('items_pedidos')
            ->whereIn('id_pedido', $ids)
            ->selectRaw('id_pedido, SUM(COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0)) as total')
            ->groupBy('id_pedido')
            ->pluck('total', 'id_pedido')
            ->map(fn ($v) => (string) $v)
            ->all();
        $pairs = [];
        foreach ($pedidos as $ped) {
            $pairs[] = ['pedido' => $ped, 'monto' => $montosPorPedido[$ped->id] ?? '0'];
        }
        \Log::info('CuadrePedidosDiario: montos por pedido calculados', ['fecha' => $fechaStr, 'maquina' => $maquinaFiscal, 'pairs' => count($pairs)]);

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
            $maxSegundos = 15;
            $montoObjFloat = (float) $montoObjetivo;
            $montosFloat = array_map(fn ($p) => (float) $p['monto'], $pairs);
            $globalBestIndices = null;
            $globalBestSuma = null;
            $globalBestDiff = PHP_FLOAT_MAX;
            $inicio = microtime(true);
            $deadline = $inicio + $maxSegundos;

            // Greedy: 20 intentos rápidos con distintas semillas
            for ($run = 0; $run < 20; $run++) {
                $result = $this->greedySeleccionRapida($montosFloat, $total, $cantidadObjetivo, $montoObjFloat);
                if ($result !== null && $result['abs_diff'] < $globalBestDiff) {
                    $globalBestDiff = $result['abs_diff'];
                    $globalBestIndices = $result['indices'];
                    $globalBestSuma = $result['suma'];
                }
                if ($globalBestDiff < 0.005) break;
            }

            // SA: múltiples corridas con distintas semillas hasta agotar el tiempo
            if ($globalBestIndices !== null) {
                $saRun = 0;
                while (microtime(true) < $deadline) {
                    $semilla = $saRun === 0
                        ? $globalBestIndices
                        : $this->greedySeleccionRapida($montosFloat, $total, $cantidadObjetivo, $montoObjFloat)['indices'] ?? $globalBestIndices;
                    $semillaSuma = 0.0;
                    foreach ($semilla as $idx) $semillaSuma += $montosFloat[$idx];

                    $saResult = $this->simulatedAnnealingRapido($montosFloat, $total, $semilla, $semillaSuma, $cantidadObjetivo, $montoObjFloat, $maxSegundos, $inicio);
                    if ($saResult['abs_diff'] < $globalBestDiff) {
                        $globalBestDiff = $saResult['abs_diff'];
                        $globalBestIndices = $saResult['indices'];
                        $globalBestSuma = $saResult['suma'];
                    }
                    if ($globalBestDiff < 0.005) break;
                    $saRun++;
                }
            }

            $selectedIndices = $globalBestIndices ?? [];
            $sumaSelected = '0';
            foreach ($selectedIndices as $idx) {
                $sumaSelected = bcadd($sumaSelected, $pairs[$idx]['monto'], $this->scale);
            }

            $selected = collect($selectedIndices)->map(fn ($idx) => $pairs[$idx]['pedido'])->values();

            $selected = $selected->sort(function ($a, $b) {
                $fechaA = $a->fecha_factura ?? $a->created_at;
                $fechaB = $b->fecha_factura ?? $b->created_at;
                $cmp = strcmp((string) $fechaA, (string) $fechaB);
                return $cmp !== 0 ? $cmp : ($a->id <=> $b->id);
            })->values();
        }

        $ajuste = bcsub($montoObjetivo, $sumaSelected, $this->scale);
        $montoObjFloat = (float) $montoObjetivo;
        $pctAjuste = $montoObjFloat > 0 ? abs((float) $ajuste) / $montoObjFloat : 0.0;
        // Siempre aplicamos la mejor solución encontrada (aunque ajuste > 5%); se avisa en salida si es alto.

        $inicioNum = (int) $facturaInicio;
        $ultimo = $selected->last();
        $nuevoTotal = bcadd($sumaSelected, $ajuste, $this->scale);

        // Transacción corta: solo escrituras (evita timeout por conexión inactiva en Cloudways).
        \Log::info('CuadrePedidosDiario: iniciando transacción de escritura', ['fecha' => $fechaStr, 'maquina' => $maquinaFiscal, 'selected_count' => $selected->count()]);
        return DB::transaction(function () use ($selected, $fechaStr, $maquinaFiscal, $facturaInicio, $sumaSelected, $ajuste, $nuevoTotal, $inicioNum, $ultimo, $pctAjuste) {
            \Log::info('CuadrePedidosDiario: asignando factura y guardando pedidos', ['fecha' => $fechaStr, 'maquina' => $maquinaFiscal, 'selected_count' => $selected->count()]);
            foreach ($selected as $i => $ped) {
                $ped->numero_factura = (string) ($inicioNum + $i);
                if (Schema::hasColumn('pedidos', 'maquina_fiscal')) {
                    $ped->maquina_fiscal = $maquinaFiscal;
                }
                $ped->valido = true;
                $ped->save();
                $this->totalPedidosMarcados++;
            }

            if (bccomp($ajuste, '0', $this->scale) !== 0) {
                $ajusteFloat = (float) $ajuste;
                $this->aplicarAjusteEnItemExistente($ultimo->id, $ajusteFloat);
                $nuevoTotalFloat = (float) $nuevoTotal;
                $pago = pago_pedidos::where('id_pedido', $ultimo->id)->first();
                if ($pago) {
                    $sumaBsItems = (float) DB::table('items_pedidos')
                        ->where('id_pedido', $ultimo->id)
                        ->sum(DB::raw('COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0)'));
                    $pago->monto = $sumaBsItems;
                    if (Schema::hasColumn('pago_pedidos', 'monto_bs')) {
                        $pago->monto_bs = $sumaBsItems;
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

            \Log::info('CuadrePedidosDiario: transacción completada', ['fecha' => $fechaStr, 'maquina' => $maquinaFiscal]);
            return [
                'cantidad_facturas' => $selected->count(),
                'monto_total_dia'   => $nuevoTotal,
                'diferencia'        => $ajuste,
                'pct_ajuste'        => $pctAjuste,
            ];
        });
    }

    protected function absDiff(string $montoObjetivo, string $suma, int $scale): string
    {
        $d = bcsub($montoObjetivo, $suma, $scale);
        return $d[0] === '-' ? bcsub('0', $d, $scale) : $d;
    }

    /**
     * Detecta si la excepción es "MySQL server has gone away" (timeout/conexión cerrada).
     */
    protected function esErrorMysqlGoneAway(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '2006') || str_contains(strtolower($msg), 'gone away')) {
            return true;
        }
        $previous = $e->getPrevious();
        if ($previous instanceof \Throwable) {
            $prevMsg = $previous->getMessage();
            if (str_contains($prevMsg, '2006') || str_contains(strtolower($prevMsg), 'gone away')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Aplica el ajuste en Bs modificando el precio_unitario (USD) de un ítem existente del pedido.
     * Elige el ítem con mayor monto_bs para que el cambio de precio sea proporcionalmente menor.
     * Redondea precio_unitario a máximo 1 decimal (nunca 2).
     */
    protected function aplicarAjusteEnItemExistente(int $idPedido, float $ajusteBs): void
    {
        $item = items_pedidos::where('id_pedido', $idPedido)
            ->whereNotNull('id_producto')
            ->where('cantidad', '>', 0)
            ->orderByRaw('COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0) DESC')
            ->first();

        if (!$item) {
            \Log::warning('CuadrePedidosDiario: sin ítems para ajustar en pedido', ['id_pedido' => $idPedido]);
            return;
        }

        $tasa = (float) ($item->tasa ?? 0);
        if ($tasa <= 0) {
            $tasa = $this->obtenerTasaGlobal();
        }

        $cantidad = (float) $item->cantidad;
        $montoActualBs = (float) ($item->monto_bs ?? ($item->monto * $tasa));
        $nuevoMontoBs = $montoActualBs + $ajusteBs;
        $nuevoMontoUsd = $tasa > 0 ? $nuevoMontoBs / $tasa : $nuevoMontoBs;
        $nuevoPrecioUnitario = $cantidad > 0 ? $nuevoMontoUsd / $cantidad : $nuevoMontoUsd;

        // Redondear a máximo 1 decimal (nunca 2): si es entero exacto, queda entero.
        $nuevoPrecioUnitario = round($nuevoPrecioUnitario, 1);

        $nuevoMontoUsd = round($cantidad * $nuevoPrecioUnitario, 4);
        $nuevoMontoBs = round($nuevoMontoUsd * $tasa, 4);

        $item->precio_unitario = $nuevoPrecioUnitario;
        $item->monto = $nuevoMontoUsd;
        $item->monto_bs = $nuevoMontoBs;
        $item->save();
    }

    /**
     * Obtiene la tasa Bs global de la tabla monedas (tipo=1).
     */
    protected function obtenerTasaGlobal(): float
    {
        $tasaMoneda = DB::table('monedas')->where('tipo', 1)->orderBy('id', 'desc')->value('valor');
        return $tasaMoneda !== null && (float) $tasaMoneda > 0 ? (float) $tasaMoneda : 1.0;
    }

    /**
     * Greedy rápido con floats: elige cantidadObjetivo índices que minimicen |suma - objetivo|.
     */
    protected function greedySeleccionRapida(array $montosFloat, int $total, int $cantidadObjetivo, float $montoObjetivo): ?array
    {
        if ($total < $cantidadObjetivo || $cantidadObjetivo < 1) return null;
        $indices = range(0, $total - 1);
        shuffle($indices);
        $selectedIndices = [];
        $suma = 0.0;
        for ($k = 0; $k < $cantidadObjetivo; $k++) {
            $bestIdx = null;
            $bestDiff = PHP_FLOAT_MAX;
            $remaining = $cantidadObjetivo - $k - 1;
            foreach ($indices as $idx) {
                if (in_array($idx, $selectedIndices, true)) continue;
                $candidata = $suma + $montosFloat[$idx];
                $diff = abs($montoObjetivo - $candidata);
                if ($diff < $bestDiff || ($diff === $bestDiff && mt_rand(0, 1) === 0)) {
                    $bestDiff = $diff;
                    $bestIdx = $idx;
                }
            }
            if ($bestIdx === null) return null;
            $selectedIndices[] = $bestIdx;
            $suma += $montosFloat[$bestIdx];
        }
        return ['indices' => $selectedIndices, 'suma' => $suma, 'abs_diff' => abs($montoObjetivo - $suma)];
    }

    /**
     * SA rápido con floats y límite de tiempo.
     */
    protected function simulatedAnnealingRapido(array $montosFloat, int $total, array $selectedIndices, float $currentSuma, int $cantidadObjetivo, float $montoObjetivo, int $maxSegundos, float $inicioTime): array
    {
        $currentAbs = abs($montoObjetivo - $currentSuma);
        $bestIndices = $selectedIndices;
        $bestSuma = $currentSuma;
        $bestAbs = $currentAbs;
        $selectedSet = array_flip($selectedIndices);
        $noSeleccionados = [];
        for ($u = 0; $u < $total; $u++) {
            if (!isset($selectedSet[$u])) $noSeleccionados[] = $u;
        }
        $totalNoSel = count($noSeleccionados);
        if ($totalNoSel === 0) {
            return ['indices' => $bestIndices, 'suma' => $bestSuma, 'abs_diff' => $bestAbs];
        }
        $maxIter = 500000;
        $tInicial = max(1.0, $montoObjetivo * 0.01);
        $tFinal = 0.0001;
        $cooling = exp(log($tFinal / $tInicial) / $maxIter);
        $t = $tInicial;
        $deadline = $inicioTime + $maxSegundos;

        for ($it = 0; $it < $maxIter; $it++) {
            if (($it & 8191) === 0 && microtime(true) >= $deadline) break;
            if ($bestAbs < 0.005) break;
            $i = mt_rand(0, $cantidadObjetivo - 1);
            $idxOut = $selectedIndices[$i];
            $j = mt_rand(0, $totalNoSel - 1);
            $idxIn = $noSeleccionados[$j];

            $newSuma = $currentSuma - $montosFloat[$idxOut] + $montosFloat[$idxIn];
            $newAbs = abs($montoObjetivo - $newSuma);
            $delta = $newAbs - $currentAbs;
            if ($delta <= 0 || (mt_rand() / mt_getrandmax()) < exp(-$delta / $t)) {
                $selectedIndices[$i] = $idxIn;
                $noSeleccionados[$j] = $idxOut;
                unset($selectedSet[$idxOut]);
                $selectedSet[$idxIn] = true;
                $currentSuma = $newSuma;
                $currentAbs = $newAbs;
                if ($newAbs < $bestAbs) {
                    $bestAbs = $newAbs;
                    $bestSuma = $newSuma;
                    $bestIndices = $selectedIndices;
                }
            }
            $t *= $cooling;
        }
        return ['indices' => $bestIndices, 'suma' => $bestSuma, 'abs_diff' => $bestAbs];
    }

    /**
     * Una corrida greedy: elige cantidadObjetivo pedidos que minimicen |suma - montoObjetivo|.
     * Desempate aleatorio cuando varios dan la misma distancia.
     *
     * @param array<int, array{pedido: mixed, monto: string}> $pairs
     * @return array{indices: array<int>, suma: string, abs_diff: string}|null
     */
    protected function greedySeleccion(array $pairs, int $total, int $cantidadObjetivo, string $montoObjetivo): ?array
    {
        $indicesOrden = array_keys($pairs);
        shuffle($indicesOrden);
        $selectedIndices = [];
        $suma = '0';

        for ($k = 0; $k < $cantidadObjetivo; $k++) {
            $bestAbsDiff = null;
            $candidatos = [];
            foreach ($indicesOrden as $idx) {
                if (in_array($idx, $selectedIndices, true)) {
                    continue;
                }
                $newSum = bcadd($suma, $pairs[$idx]['monto'], $this->scale);
                $diff = bcsub($montoObjetivo, $newSum, $this->scale + 2);
                $absDiff = $diff[0] === '-' ? bcsub('0', $diff, $this->scale + 2) : $diff;
                $cmp = $bestAbsDiff === null ? -1 : bccomp($absDiff, $bestAbsDiff, $this->scale + 2);
                if ($cmp < 0) {
                    $bestAbsDiff = $absDiff;
                    $candidatos = [$idx];
                } elseif ($cmp === 0) {
                    $candidatos[] = $idx;
                }
            }
            if (empty($candidatos)) {
                return null;
            }
            $bestIdx = $candidatos[array_rand($candidatos)];
            $selectedIndices[] = $bestIdx;
            $suma = bcadd($suma, $pairs[$bestIdx]['monto'], $this->scale);
        }

        $diff = bcsub($montoObjetivo, $suma, $this->scale + 2);
        $absDiff = $diff[0] === '-' ? bcsub('0', $diff, $this->scale + 2) : $diff;

        return [
            'indices'   => $selectedIndices,
            'suma'      => $suma,
            'abs_diff'  => $absDiff,
        ];
    }

    /**
     * Simulated annealing: refina la selección con intercambios aleatorios.
     * Acepta empeoras con probabilidad exp((oldDiff - newDiff) / T); T disminuye con el tiempo.
     *
     * @param array<int, array{pedido: mixed, monto: string}> $pairs
     * @param array<int> $selectedIndices
     * @return array<int>
     */
    protected function simulatedAnnealing(array $pairs, int $total, array $selectedIndices, string $currentSuma, int $cantidadObjetivo, string $montoObjetivo): array
    {
        $scale = $this->scale + 2;
        $diffToAbs = function ($suma) use ($montoObjetivo, $scale) {
            $d = bcsub($montoObjetivo, $suma, $scale);
            return $d[0] === '-' ? bcsub('0', $d, $scale) : $d;
        };

        $currentAbs = $diffToAbs($currentSuma);
        $bestIndices = $selectedIndices;
        $bestSuma = $currentSuma;
        $bestAbs = $currentAbs;

        $selectedSet = array_flip($selectedIndices);
        $iteraciones = min(150000, 1000 * $total);
        $tInicial = 100.0;
        $tFinal = 0.0002;
        $t = $tInicial;
        $cooling = exp(log($tFinal / $tInicial) / $iteraciones);

        for ($it = 0; $it < $iteraciones; $it++) {
            $i = array_rand($selectedIndices);
            $idxOut = $selectedIndices[$i];
            $restantes = [];
            for ($u = 0; $u < $total; $u++) {
                if (!isset($selectedSet[$u])) {
                    $restantes[] = $u;
                }
            }
            if (empty($restantes)) {
                continue;
            }
            $idxIn = $restantes[array_rand($restantes)];

            $newSuma = bcsub(bcadd($currentSuma, $pairs[$idxIn]['monto'], $this->scale), $pairs[$idxOut]['monto'], $this->scale);
            $newAbs = $diffToAbs($newSuma);
            $delta = bcsub($newAbs, $currentAbs, $scale);
            $accept = bccomp($delta, '0', $scale) <= 0
                || lcg_value() < exp(-max(0.0, (float) $delta) / $t);

            if ($accept) {
                $selectedIndices[$i] = $idxIn;
                unset($selectedSet[$idxOut]);
                $selectedSet[$idxIn] = true;
                $currentSuma = $newSuma;
                $currentAbs = $newAbs;
                if (bccomp($newAbs, $bestAbs, $scale) < 0) {
                    $bestAbs = $newAbs;
                    $bestSuma = $newSuma;
                    $bestIndices = $selectedIndices;
                }
            }

            $t *= $cooling;
        }

        return $bestIndices;
    }

    protected function mostrarResumen(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('           RESULTADO FINAL');
        $this->info('═══════════════════════════════════════');
        $this->info('Filas (día+máquina) procesadas .....: ' . $this->filasProcesadas);
        $this->info('Filas omitidas (ya procesadas) ....: ' . $this->filasOmitidas);
        if ($this->filasRechazadasAjuste > 0) {
            $this->warn('Filas rechazadas (ajuste > 5%) ..: ' . $this->filasRechazadasAjuste);
        }
        $this->info('Pedidos marcados válidos ...........: ' . $this->totalPedidosMarcados);
        $this->info('Monto total (Bs) ...................: ' . number_format((float) $this->totalMontoAcumulado, 4, ',', '.'));
        $this->info('═══════════════════════════════════════');
    }
}
