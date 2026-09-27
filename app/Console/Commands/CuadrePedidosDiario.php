<?php

namespace App\Console\Commands;

use App\Models\items_pedidos;
use App\Models\pago_pedidos;
use App\Models\pedidos;
use App\Services\Cuadre\CuadreCsvReader;
use App\Services\Cuadre\CuadreResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuadra pedidos por día y por máquina fiscal contra un monto objetivo.
 *
 * Entrada: CSV/XLSX con FECHA, CONCEPTO (máquina), FACTURA (inicio-fin o número), VENTA (Bs), TIPO
 * (FISCAL RANGO / FISCAL UNITARIA / REDUCE EL TOTAL DE ESE DIA). Ver database/data/FORMATO_CUADRE_DIARIO.md.
 *
 * Por cada (fecha, máquina): entre los pedidos del día aún no válidos (estado=1 y monto > 0) elige exactamente N
 * (N = facturas del rango) cuya suma en Bs se acerque al objetivo (greedy + simulated annealing con límite de
 * tiempo), les asigna numero_factura consecutivo, maquina_fiscal y valido=1, y aplica la diferencia residual
 * ajustando el precio del ítem mayor del último pedido. Cada ajuste queda auditado en `cuadre_ajustes` para que
 * cuadre:pedidos-reset pueda restaurar los valores originales.
 */
class CuadrePedidosDiario extends Command
{
    protected $signature = 'cuadre:pedidos-diario
                            {archivo : Ruta al CSV/XLSX (FECHA, CONCEPTO, FACTURA, VENTA, TIPO)}
                            {--desde-cero : Antes de procesar, resetea el cuadre en el rango de fechas del archivo (restaurando ajustes)}
                            {--dry-run : Solo valida y agrupa el archivo; no consulta pedidos}
                            {--simular : Ejecuta la selección real (lee la BD) pero NO escribe; muestra objetivo, suma y ajuste por grupo}
                            {--si : No pedir confirmación (para corridas desatendidas)}
                            {--solo-fecha= : Procesar solo este día (YYYY-MM-DD)}
                            {--anio= : Procesar solo los días de este año (ej. 2024)}
                            {--desde= : Procesar solo grupos con fecha >= YYYY-MM-DD}
                            {--hasta= : Procesar solo grupos con fecha <= YYYY-MM-DD}
                            {--max-segundos=15 : Tiempo máximo de búsqueda (greedy + annealing) por grupo día+máquina}
                            {--umbral-ajuste=5 : Porcentaje de ajuste sobre el objetivo a partir del cual se avisa}
                            {--sin-filtro-estado : Incluir también pedidos con estado distinto de 1 (por defecto solo facturados)}
                            {--reporte= : Ruta de un CSV donde escribir el detalle por grupo (objetivo, seleccionados, suma, ajuste, real)}';

    protected $description = 'Cuadre por día y máquina fiscal contra monto objetivo (CSV/XLSX con FECHA, CONCEPTO, FACTURA, VENTA, TIPO).';

    protected int $scale = 4;

    protected int $totalPedidosMarcados = 0;
    protected string $totalMontoObjetivo = '0';
    protected string $totalMontoReal = '0';
    protected int $filasOmitidas = 0;
    protected int $filasProcesadas = 0;
    protected int $filasSinPedidos = 0;
    protected int $filasAjusteAlto = 0;
    protected int $pedidosExcluidosMontoCero = 0;

    protected float $maxSegundos = 15.0;
    protected float $umbralAjuste = 0.05;
    protected bool $filtrarEstado = true;
    protected bool $simular = false;

    protected bool $tieneMaquinaFiscal = false;
    protected bool $tieneEstado = false;
    protected bool $tieneAuditoria = false;
    protected bool $pagoTieneMontoBs = false;

    /** @var resource|null */
    protected $reporteFh = null;

    public function handle(CuadreCsvReader $reader, CuadreResetService $resetService): int
    {
        $path = $this->argument('archivo');
        $desdeCero = (bool) $this->option('desde-cero');
        $dryRun = (bool) $this->option('dry-run');
        $this->simular = (bool) $this->option('simular');
        $soloFecha = $this->option('solo-fecha') ? trim((string) $this->option('solo-fecha')) : null;
        $soloAnio = $this->option('anio') ? trim((string) $this->option('anio')) : null;
        $desde = $this->option('desde') ? trim((string) $this->option('desde')) : null;
        $hasta = $this->option('hasta') ? trim((string) $this->option('hasta')) : null;
        $this->maxSegundos = max(1.0, (float) $this->option('max-segundos'));
        $this->umbralAjuste = max(0.0, (float) $this->option('umbral-ajuste')) / 100.0;
        $this->filtrarEstado = !$this->option('sin-filtro-estado');

        if (!is_file($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return Command::FAILURE;
        }

        // ── 1. Leer y normalizar el archivo ────────────────────────────────────────────────
        $normalizadas = $reader->leerNormalizado($path);
        $rechazos = $reader->razonesRechazo();
        if (!empty($rechazos)) {
            \Log::info('CuadrePedidosDiario: filas rechazadas', ['razones' => $rechazos]);
            $this->line('<comment>Filas rechazadas (primeras ' . min(5, count($rechazos)) . ' de ' . count($rechazos) . '):</comment>');
            foreach (array_slice($rechazos, 0, 5) as $r) {
                $this->line('  - ' . $r);
            }
        }
        if (empty($normalizadas)) {
            $this->error('No se encontraron filas válidas en el archivo. Cabecera esperada: FECHA, CONCEPTO, FACTURA, VENTA, TIPO (FISCAL RANGO / FISCAL UNITARIA / REDUCE EL TOTAL DE ESE DIA).');
            return Command::FAILURE;
        }

        $agregadas = $reader->agregarPorDiaMaquina($normalizadas);

        if ($soloFecha) {
            $agregadas = array_values(array_filter($agregadas, fn ($a) => $a['fecha'] === $soloFecha));
            $this->info("Filtro --solo-fecha={$soloFecha}: " . count($agregadas) . ' grupo(s).');
        }
        if ($soloAnio) {
            $agregadas = array_values(array_filter($agregadas, fn ($a) => substr($a['fecha'], 0, 4) === $soloAnio));
            $this->info("Filtro --anio={$soloAnio}: " . count($agregadas) . ' grupo(s).');
        }
        if ($desde) {
            $agregadas = array_values(array_filter($agregadas, fn ($a) => $a['fecha'] >= $desde));
        }
        if ($hasta) {
            $agregadas = array_values(array_filter($agregadas, fn ($a) => $a['fecha'] <= $hasta));
        }
        if ($desde || $hasta) {
            $this->info("Filtro --desde/--hasta ({$desde} → {$hasta}): " . count($agregadas) . ' grupo(s).');
        }

        $totalGrupos = count($agregadas);
        $objetivoTotal = '0';
        $facturasTotal = 0;
        foreach ($agregadas as $g) {
            $objetivoTotal = bcadd($objetivoTotal, $g['total_venta'], $this->scale);
            $facturasTotal += (int) $g['cantidad'];
        }
        $this->info(sprintf(
            'Filas normalizadas: %d → grupos (fecha + máquina): %d | facturas objetivo: %d | monto objetivo: %s Bs',
            count($normalizadas), $totalGrupos, $facturasTotal, number_format((float) $objetivoTotal, 2, ',', '.')
        ));
        if ($totalGrupos > 0) {
            $fechas = array_column($agregadas, 'fecha');
            $this->info('Rango de fechas: ' . min($fechas) . ' → ' . max($fechas));
            $this->info(sprintf(
                'Tiempo máximo estimado de búsqueda: %d grupos × %.0f s = %s (cota superior; los días con pocos pedidos terminan al instante)',
                $totalGrupos, $this->maxSegundos, $this->formatearDuracion($totalGrupos * $this->maxSegundos)
            ));
        }

        if ($totalGrupos === 0) {
            $this->warn('No hay grupos que procesar con los filtros dados.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se consulta ni se escribe en la base de datos.');
            $this->mostrarResumen();
            return Command::SUCCESS;
        }
        if ($this->simular) {
            $this->warn('Modo simulación: se ejecuta la selección real pero NO se escribe nada.');
        }

        // ── 2. Esquema ─────────────────────────────────────────────────────────────────────
        $this->tieneMaquinaFiscal = Schema::hasColumn('pedidos', 'maquina_fiscal');
        $this->tieneEstado = Schema::hasColumn('pedidos', 'estado');
        $this->tieneAuditoria = Schema::hasTable('cuadre_ajustes');
        $this->pagoTieneMontoBs = Schema::hasColumn('pago_pedidos', 'monto_bs');
        if (!Schema::hasColumn('pedidos', 'numero_factura') || !Schema::hasColumn('pedidos', 'valido')) {
            $this->error('La tabla pedidos no tiene numero_factura/valido. Ejecute php artisan migrate.');
            return Command::FAILURE;
        }
        if (!$this->tieneAuditoria && !$this->simular) {
            $this->warn('No existe la tabla cuadre_ajustes (ejecute php artisan migrate): los ajustes NO quedarán auditados y el reset no podrá restaurarlos.');
        }

        // ── 3. Reset opcional ──────────────────────────────────────────────────────────────
        if ($desdeCero && !$this->simular) {
            $fechaDesde = min(array_column($agregadas, 'fecha'));
            $fechaHasta = max(array_column($agregadas, 'fecha'));
            $ids = $resetService->idsEnRango($fechaDesde, $fechaHasta);
            $this->warn(sprintf('Opción --desde-cero: se resetearán %d pedidos válidos entre %s y %s (restaurando ajustes auditados).', count($ids), $fechaDesde, $fechaHasta));
            if (!$this->option('si') && !$this->confirm('¿Continuar?', true)) {
                return Command::SUCCESS;
            }
            if (!empty($ids)) {
                $stats = $resetService->resetear($ids);
                $this->info(sprintf('  Reseteados %d pedidos | ajustes restaurados: %d | ítems antiguos borrados: %d', $stats['pedidos'], $stats['ajustes_revertidos'], $stats['items_legacy_borrados']));
            } else {
                $this->line('  (No había pedidos válidos en ese rango.)');
            }
        }

        $this->abrirReporte();

        // ── 4. Procesar grupos ─────────────────────────────────────────────────────────────
        $inicioTotal = microtime(true);
        foreach ($agregadas as $i => $normalized) {
            $fecha = $normalized['fecha'];
            $maquina = $normalized['maquina_fiscal'];
            $montoObjetivo = bcadd($normalized['total_venta'], '0', $this->scale);
            $facturaInicio = (string) $normalized['factura_inicio'];
            $facturaFin = (string) $normalized['factura_fin'];
            $cantidad = (int) $normalized['cantidad'];

            $transcurrido = microtime(true) - $inicioTotal;
            $eta = $i > 0 ? ($transcurrido / $i) * ($totalGrupos - $i) : $totalGrupos * $this->maxSegundos;
            $this->line(sprintf(
                '[%d/%d] <comment>%s</comment> | <comment>%s</comment> | objetivo %s Bs, %d facturas (%s–%s) | transcurrido %s, ETA %s',
                $i + 1, $totalGrupos, $fecha, $maquina, number_format((float) $montoObjetivo, 2, ',', '.'), $cantidad,
                $facturaInicio, $facturaFin, $this->formatearDuracion($transcurrido), $this->formatearDuracion($eta)
            ));

            DB::reconnect();
            $maxReintentosConexion = 3;
            $reintentoConexion = 0;
            $procesado = false;
            while (!$procesado) {
                try {
                    $resultado = $this->procesarDiaMaquina($fecha, $maquina, $montoObjetivo, $facturaInicio, $facturaFin, $cantidad);
                    $this->registrarResultado($fecha, $maquina, $montoObjetivo, $facturaInicio, $facturaFin, $cantidad, $resultado);
                    $procesado = true;
                } catch (\Throwable $e) {
                    \Log::warning('CuadrePedidosDiario: excepción capturada', [
                        'fecha' => $fecha, 'maquina' => $maquina, 'mensaje' => $e->getMessage(),
                        'archivo' => $e->getFile(), 'linea' => $e->getLine(),
                        'es_gone_away' => $this->esErrorMysqlGoneAway($e), 'reintento' => $reintentoConexion,
                    ]);
                    if ($this->esErrorMysqlGoneAway($e) && $reintentoConexion < $maxReintentosConexion) {
                        $reintentoConexion++;
                        $this->line('  → MySQL cerró la conexión, reconectando e intentando de nuevo (' . $reintentoConexion . '/' . $maxReintentosConexion . ')…');
                        DB::reconnect();
                        continue;
                    }
                    $this->error('Error: ' . $e->getMessage());
                    \Log::error('CuadrePedidosDiario: fallo definitivo', ['fecha' => $fecha, 'maquina' => $maquina, 'mensaje' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    $this->cerrarReporte();
                    $this->mostrarResumen();
                    return Command::FAILURE;
                }
            }
        }

        $this->cerrarReporte();
        $this->mostrarResumen();
        $this->info('Duración total: ' . $this->formatearDuracion(microtime(true) - $inicioTotal));
        return Command::SUCCESS;
    }

    protected function registrarResultado(string $fecha, string $maquina, string $montoObjetivo, string $facturaInicio, string $facturaFin, int $cantidad, ?array $resultado): void
    {
        if ($resultado !== null && !empty($resultado['skipped'])) {
            $this->filasOmitidas++;
            $this->line('  → <fg=yellow>omitido (día+máquina ya procesado)</>');
            $this->escribirReporte([$fecha, $maquina, $montoObjetivo, $cantidad, $facturaInicio, $facturaFin, '', '', '', '', '', '', 'omitido']);
            return;
        }
        if ($resultado === null) {
            $this->filasSinPedidos++;
            $this->line('  → <fg=red>sin pedidos candidatos para esta fecha</>');
            $this->escribirReporte([$fecha, $maquina, $montoObjetivo, $cantidad, $facturaInicio, $facturaFin, 0, 0, '0', $montoObjetivo, '100', '0', 'sin_pedidos']);
            return;
        }

        $this->filasProcesadas++;
        $this->totalMontoObjetivo = bcadd($this->totalMontoObjetivo, $montoObjetivo, $this->scale);
        $this->totalMontoReal = bcadd($this->totalMontoReal, $resultado['monto_real_dia'], $this->scale);
        $pctAjuste = $resultado['pct_ajuste'] ?? 0;
        $linea = sprintf(
            '  → %s <info>%d/%d facturas</info> de %d candidatos | suma %s Bs | ajuste <comment>%s Bs</comment> (%.2f%%) | real %s Bs',
            $this->simular ? '<fg=cyan>[simulado]</>' : '',
            $resultado['cantidad_facturas'], $cantidad, $resultado['candidatos'],
            number_format((float) $resultado['suma_seleccionada'], 2, ',', '.'),
            number_format((float) $resultado['diferencia'], 2, ',', '.'),
            $pctAjuste * 100,
            number_format((float) $resultado['monto_real_dia'], 2, ',', '.')
        );
        if ($pctAjuste > $this->umbralAjuste) {
            $this->filasAjusteAlto++;
            $linea .= ' <fg=yellow>(AJUSTE ALTO)</>';
        }
        if ($resultado['cantidad_facturas'] < $cantidad) {
            $linea .= sprintf(' <fg=yellow>(faltan %d pedidos para completar el rango)</>', $cantidad - $resultado['cantidad_facturas']);
        }
        $this->line($linea);
        $this->escribirReporte([
            $fecha, $maquina, $montoObjetivo, $cantidad, $facturaInicio, $facturaFin,
            $resultado['candidatos'], $resultado['cantidad_facturas'], $resultado['suma_seleccionada'],
            $resultado['diferencia'], round($pctAjuste * 100, 4), $resultado['monto_real_dia'],
            $this->simular ? 'simulado' : 'procesado',
        ]);
    }

    /**
     * Procesa un grupo fecha + máquina. Devuelve null si no hay candidatos, ['skipped'=>true] si ya estaba
     * procesado, o el resultado con cantidades y montos.
     */
    protected function procesarDiaMaquina(string $fechaStr, string $maquinaFiscal, string $montoObjetivo, string $facturaInicio, string $facturaFin, int $cantidadObjetivo): ?array
    {
        $dateExpr = 'DATE(COALESCE(fecha_factura, created_at))';

        $yaProcesado = $this->tieneMaquinaFiscal
            && pedidos::whereRaw($dateExpr . ' = ?', [$fechaStr])
                ->where('maquina_fiscal', $maquinaFiscal)
                ->where('valido', true)
                ->exists();
        if ($yaProcesado) {
            return ['skipped' => true];
        }

        // Lectura y cómputo pesado FUERA de la transacción (la conexión se cierra si una transacción
        // queda abierta mucho tiempo sin consultas en algunos hostings).
        $query = pedidos::whereRaw($dateExpr . ' = ?', [$fechaStr])
            ->where(function ($q) {
                $q->whereNull('valido')->orWhere('valido', false);
            });
        if ($this->filtrarEstado && $this->tieneEstado) {
            $query->where('estado', 1);
        }
        $pedidos = $query->orderByRaw('COALESCE(fecha_factura, created_at) ASC')->orderBy('id')->get();

        if ($pedidos->isEmpty()) {
            return null;
        }

        // Una sola consulta para todos los montos por pedido.
        $ids = $pedidos->pluck('id')->all();
        $montosPorPedido = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $parcial = DB::table('items_pedidos')
                ->whereIn('id_pedido', $chunk)
                ->selectRaw('id_pedido, SUM(COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0)) as total')
                ->groupBy('id_pedido')
                ->pluck('total', 'id_pedido')
                ->all();
            foreach ($parcial as $k => $v) {
                $montosPorPedido[$k] = (string) $v;
            }
        }

        $pairs = [];
        foreach ($pedidos as $ped) {
            $monto = $montosPorPedido[$ped->id] ?? '0';
            if (bccomp($monto, '0', $this->scale) <= 0) {
                $this->pedidosExcluidosMontoCero++;
                continue; // sin ítems, devolución o monto cero: no puede ser factura fiscal
            }
            $pairs[] = ['pedido' => $ped, 'monto' => $monto];
        }
        if (empty($pairs)) {
            return null;
        }

        $total = count($pairs);
        $selected = collect();
        $sumaSelected = '0';

        if ($total <= $cantidadObjetivo) {
            $selected = collect($pairs)->map(fn ($p) => $p['pedido'])->values();
            foreach ($pairs as $p) {
                $sumaSelected = bcadd($sumaSelected, $p['monto'], $this->scale);
            }
        } else {
            $selectedIndices = $this->buscarSeleccion(array_map(fn ($p) => (float) $p['monto'], $pairs), $total, $cantidadObjetivo, (float) $montoObjetivo);
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

        $base = [
            'candidatos'        => $total,
            'cantidad_facturas' => $selected->count(),
            'suma_seleccionada' => $sumaSelected,
            'diferencia'        => $ajuste,
            'pct_ajuste'        => $pctAjuste,
        ];

        if ($this->simular) {
            $base['monto_real_dia'] = $montoObjetivo; // estimado: el ajuste aplicado quedaría a centavos del objetivo
            return $base;
        }

        $inicioNum = (int) $facturaInicio;
        $ultimo = $selected->last();

        return DB::transaction(function () use ($selected, $fechaStr, $maquinaFiscal, $sumaSelected, $ajuste, $inicioNum, $ultimo, $base) {
            foreach ($selected as $i => $ped) {
                $ped->numero_factura = (string) ($inicioNum + $i);
                if ($this->tieneMaquinaFiscal) {
                    $ped->maquina_fiscal = $maquinaFiscal;
                }
                $ped->valido = true;
                $ped->save();
                $this->totalPedidosMarcados++;
            }

            $aplicado = '0';
            if (bccomp($ajuste, '0', $this->scale) !== 0) {
                $aplicado = $this->aplicarAjuste($ultimo, $ajuste, $fechaStr, $maquinaFiscal);
            }
            $base['monto_real_dia'] = bcadd($sumaSelected, $aplicado, $this->scale);
            return $base;
        });
    }

    /**
     * Greedy (20 semillas) + simulated annealing con límite de tiempo. Devuelve índices seleccionados.
     * @param float[] $montosFloat
     * @return int[]
     */
    protected function buscarSeleccion(array $montosFloat, int $total, int $cantidadObjetivo, float $montoObjFloat): array
    {
        $globalBestIndices = null;
        $globalBestDiff = PHP_FLOAT_MAX;
        $inicio = microtime(true);
        $deadline = $inicio + $this->maxSegundos;

        for ($run = 0; $run < 20; $run++) {
            $result = $this->greedySeleccionRapida($montosFloat, $total, $cantidadObjetivo, $montoObjFloat);
            if ($result !== null && $result['abs_diff'] < $globalBestDiff) {
                $globalBestDiff = $result['abs_diff'];
                $globalBestIndices = $result['indices'];
            }
            if ($globalBestDiff < 0.005 || microtime(true) >= $deadline) break;
        }

        if ($globalBestIndices !== null && $globalBestDiff >= 0.005) {
            $saRun = 0;
            while (microtime(true) < $deadline) {
                $semilla = $saRun === 0
                    ? $globalBestIndices
                    : ($this->greedySeleccionRapida($montosFloat, $total, $cantidadObjetivo, $montoObjFloat)['indices'] ?? $globalBestIndices);
                $semillaSuma = 0.0;
                foreach ($semilla as $idx) $semillaSuma += $montosFloat[$idx];

                $saResult = $this->simulatedAnnealingRapido($montosFloat, $total, $semilla, $semillaSuma, $cantidadObjetivo, $montoObjFloat, $deadline);
                if ($saResult['abs_diff'] < $globalBestDiff) {
                    $globalBestDiff = $saResult['abs_diff'];
                    $globalBestIndices = $saResult['indices'];
                }
                if ($globalBestDiff < 0.005) break;
                $saRun++;
            }
        }

        return $globalBestIndices ?? [];
    }

    /**
     * Aplica el ajuste en Bs modificando el precio unitario del ítem de mayor monto del pedido,
     * recalcula el pago y deja auditoría en cuadre_ajustes. Devuelve el ajuste realmente aplicado (Bs).
     */
    protected function aplicarAjuste(pedidos $pedido, string $ajusteBs, string $fecha, string $maquinaFiscal): string
    {
        $item = items_pedidos::where('id_pedido', $pedido->id)
            ->whereNotNull('id_producto')
            ->where('cantidad', '>', 0)
            ->orderByRaw('COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0) DESC')
            ->first();

        if (!$item) {
            \Log::warning('CuadrePedidosDiario: sin ítems para ajustar en pedido', ['id_pedido' => $pedido->id]);
            return '0';
        }

        $tasa = (float) ($item->tasa ?? 0);
        if ($tasa <= 0) {
            $tasa = $this->obtenerTasaGlobal();
        }

        $orig = [
            'precio_unitario' => $item->precio_unitario,
            'monto'           => $item->monto,
            'monto_bs'        => $item->monto_bs,
        ];

        $cantidad = (float) $item->cantidad;
        $montoActualBs = (float) ($item->monto_bs ?? ((float) $item->monto * $tasa));
        $nuevoMontoBs = $montoActualBs + (float) $ajusteBs;
        $nuevoMontoUsd = $tasa > 0 ? $nuevoMontoBs / $tasa : $nuevoMontoBs;
        $nuevoPrecioUnitario = $cantidad > 0 ? $nuevoMontoUsd / $cantidad : $nuevoMontoUsd;

        // Redondear a máximo 1 decimal en USD (precio "creíble"); si es entero exacto queda entero.
        $nuevoPrecioUnitario = round($nuevoPrecioUnitario, 1);
        if ($nuevoPrecioUnitario <= 0) {
            $nuevoPrecioUnitario = 0.1;
        }

        $nuevoMontoUsd = round($cantidad * $nuevoPrecioUnitario, 4);
        $nuevoMontoBs = round($nuevoMontoUsd * $tasa, 4);

        $item->precio_unitario = $nuevoPrecioUnitario;
        $item->monto = $nuevoMontoUsd;
        $item->monto_bs = $nuevoMontoBs;
        $item->save();

        $aplicado = bcsub(number_format($nuevoMontoBs, 4, '.', ''), number_format($montoActualBs, 4, '.', ''), $this->scale);

        // Pago: recalcular con la suma real de ítems (o crear uno si no existe).
        $pago = pago_pedidos::where('id_pedido', $pedido->id)->orderBy('id')->first();
        $pagoOrig = null;
        $pagoCreado = false;
        $sumaBsItems = (float) DB::table('items_pedidos')
            ->where('id_pedido', $pedido->id)
            ->sum(DB::raw('COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0)'));
        if ($pago) {
            $pagoOrig = ['monto' => $pago->monto, 'monto_bs' => $pago->monto_bs ?? null, 'monto_original' => $pago->monto_original ?? null];
            $pago->monto = $sumaBsItems;
            if ($this->pagoTieneMontoBs) {
                $pago->monto_bs = $sumaBsItems;
            }
            $pago->save();
        } else {
            $datosPago = [
                'id_pedido'      => $pedido->id,
                'tipo'           => '5',
                'cuenta'         => 1,
                'monto'          => $sumaBsItems,
                'monto_original' => $sumaBsItems,
            ];
            if ($this->pagoTieneMontoBs) {
                $datosPago['monto_bs'] = $sumaBsItems;
            }
            $pago = pago_pedidos::create($datosPago);
            $pagoCreado = true;
        }

        if ($this->tieneAuditoria) {
            DB::table('cuadre_ajustes')->insert([
                'id_pedido'                  => $pedido->id,
                'fecha'                      => $fecha,
                'maquina_fiscal'             => $maquinaFiscal !== '' ? $maquinaFiscal : null,
                'ajuste_bs'                  => $ajusteBs,
                'ajuste_aplicado_bs'         => $aplicado,
                'id_item'                    => $item->id,
                'item_precio_unitario_orig'  => $orig['precio_unitario'],
                'item_monto_orig'            => $orig['monto'],
                'item_monto_bs_orig'         => $orig['monto_bs'],
                'item_precio_unitario_nuevo' => $nuevoPrecioUnitario,
                'item_monto_nuevo'           => $nuevoMontoUsd,
                'item_monto_bs_nuevo'        => $nuevoMontoBs,
                'id_pago'                    => $pago->id,
                'pago_creado'                => $pagoCreado,
                'pago_monto_orig'            => $pagoOrig['monto'] ?? null,
                'pago_monto_bs_orig'         => $pagoOrig['monto_bs'] ?? null,
                'pago_monto_original_orig'   => $pagoOrig['monto_original'] ?? null,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);
        }

        return $aplicado;
    }

    protected function obtenerTasaGlobal(): float
    {
        $tasaMoneda = DB::table('monedas')->where('tipo', 1)->orderBy('id', 'desc')->value('valor');
        return $tasaMoneda !== null && (float) $tasaMoneda > 0 ? (float) $tasaMoneda : 1.0;
    }

    protected function esErrorMysqlGoneAway(\Throwable $e): bool
    {
        for ($x = $e; $x instanceof \Throwable; $x = $x->getPrevious()) {
            $msg = strtolower($x->getMessage());
            if (str_contains($msg, '2006') || str_contains($msg, 'gone away') || str_contains($msg, 'lost connection')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Greedy rápido con floats: elige cantidadObjetivo índices que minimicen |suma - objetivo|.
     */
    protected function greedySeleccionRapida(array $montosFloat, int $total, int $cantidadObjetivo, float $montoObjetivo): ?array
    {
        if ($total < $cantidadObjetivo || $cantidadObjetivo < 1) return null;
        $indices = range(0, $total - 1);
        shuffle($indices);
        $selectedSet = [];
        $selectedIndices = [];
        $suma = 0.0;
        for ($k = 0; $k < $cantidadObjetivo; $k++) {
            $bestIdx = null;
            $bestDiff = PHP_FLOAT_MAX;
            foreach ($indices as $idx) {
                if (isset($selectedSet[$idx])) continue;
                $candidata = $suma + $montosFloat[$idx];
                $diff = abs($montoObjetivo - $candidata);
                if ($diff < $bestDiff || ($diff === $bestDiff && mt_rand(0, 1) === 0)) {
                    $bestDiff = $diff;
                    $bestIdx = $idx;
                }
            }
            if ($bestIdx === null) return null;
            $selectedIndices[] = $bestIdx;
            $selectedSet[$bestIdx] = true;
            $suma += $montosFloat[$bestIdx];
        }
        return ['indices' => $selectedIndices, 'suma' => $suma, 'abs_diff' => abs($montoObjetivo - $suma)];
    }

    /**
     * Simulated annealing con floats y límite de tiempo (deadline absoluto).
     */
    protected function simulatedAnnealingRapido(array $montosFloat, int $total, array $selectedIndices, float $currentSuma, int $cantidadObjetivo, float $montoObjetivo, float $deadline): array
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
        if ($totalNoSel === 0 || $cantidadObjetivo < 1) {
            return ['indices' => $bestIndices, 'suma' => $bestSuma, 'abs_diff' => $bestAbs];
        }
        $maxIter = 500000;
        $tInicial = max(1.0, $montoObjetivo * 0.01);
        $tFinal = 0.0001;
        $cooling = exp(log($tFinal / $tInicial) / $maxIter);
        $t = $tInicial;

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

    // ── Reporte CSV por grupo ─────────────────────────────────────────────────────────────

    protected function abrirReporte(): void
    {
        $ruta = $this->option('reporte');
        if (!$ruta) {
            return;
        }
        $dir = dirname($ruta);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $this->reporteFh = @fopen($ruta, 'w');
        if ($this->reporteFh === false) {
            $this->warn("No se pudo abrir el reporte {$ruta}; se continúa sin reporte.");
            $this->reporteFh = null;
            return;
        }
        fputcsv($this->reporteFh, ['fecha', 'maquina_fiscal', 'objetivo_bs', 'facturas_objetivo', 'factura_inicio', 'factura_fin', 'candidatos', 'facturas_asignadas', 'suma_seleccionada_bs', 'ajuste_bs', 'pct_ajuste', 'real_bs', 'estado']);
    }

    protected function escribirReporte(array $fila): void
    {
        if ($this->reporteFh) {
            fputcsv($this->reporteFh, $fila);
        }
    }

    protected function cerrarReporte(): void
    {
        if ($this->reporteFh) {
            fclose($this->reporteFh);
            $this->reporteFh = null;
            $this->info('Reporte por grupo escrito en: ' . $this->option('reporte'));
        }
    }

    protected function formatearDuracion(float $segundos): string
    {
        $segundos = max(0, (int) round($segundos));
        $h = intdiv($segundos, 3600);
        $m = intdiv($segundos % 3600, 60);
        $s = $segundos % 60;
        return $h > 0 ? sprintf('%dh %02dm %02ds', $h, $m, $s) : sprintf('%dm %02ds', $m, $s);
    }

    protected function mostrarResumen(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('           RESULTADO FINAL' . ($this->simular ? ' (SIMULACIÓN)' : ''));
        $this->info('═══════════════════════════════════════');
        $this->info('Grupos (día+máquina) procesados ....: ' . $this->filasProcesadas);
        $this->info('Grupos omitidos (ya procesados) ....: ' . $this->filasOmitidas);
        $this->info('Grupos sin pedidos candidatos ......: ' . $this->filasSinPedidos);
        if ($this->filasAjusteAlto > 0) {
            $this->warn('Grupos con ajuste alto (> ' . round($this->umbralAjuste * 100, 1) . '%) : ' . $this->filasAjusteAlto);
        }
        if ($this->pedidosExcluidosMontoCero > 0) {
            $this->line('Pedidos excluidos por monto <= 0 ...: ' . $this->pedidosExcluidosMontoCero);
        }
        $this->info('Pedidos marcados válidos ...........: ' . $this->totalPedidosMarcados);
        $this->info('Monto objetivo procesado (Bs) ......: ' . number_format((float) $this->totalMontoObjetivo, 2, ',', '.'));
        $this->info('Monto real logrado (Bs) ............: ' . number_format((float) $this->totalMontoReal, 2, ',', '.'));
        $this->info('═══════════════════════════════════════');
    }
}
