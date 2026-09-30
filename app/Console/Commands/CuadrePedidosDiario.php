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
                            {--tolerancia-bs=1 : La búsqueda se detiene cuando |suma - objetivo| <= este monto en Bs (el resto lo cubre el ajuste)}
                            {--umbral-ajuste=5 : Porcentaje de ajuste sobre el objetivo a partir del cual se avisa}
                            {--sin-filtro-estado : Incluir también pedidos con estado distinto de 1 (por defecto solo facturados)}
                            {--sin-absorber : No incluir como candidatos los pedidos de días que el libro salta (ver --max-dias-absorber)}
                            {--max-dias-absorber=3 : Máximo de días seguidos sin objetivo que se absorben en el grupo siguiente de la misma máquina}
                            {--dias-relleno=3 : Si con los pedidos del día no se puede llegar al objetivo, usar también los que sobraron de hasta N días anteriores (0 = no)}
                            {--reporte= : Ruta de un CSV donde escribir el detalle por grupo (objetivo, seleccionados, suma, ajuste, real)}
                            {--por-dia-maquina : Agrupar por día + máquina (antes) en vez de un grupo por fila del libro (cada Z y cada factura unitaria)}';

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
    protected float $toleranciaBs = 1.0;
    protected float $umbralAjuste = 0.05;
    /** @var array<int,bool> ids de pedidos excluidos por monto <= 0 (para contarlos una sola vez) */
    protected array $excluidosMontoCero = [];
    protected bool $filtrarEstado = true;
    protected int $diasRelleno = 3;
    protected ?float $tasaGlobal = null;

    /** @var array<string, string[]> "fecha|maquina" => días anteriores sin objetivo cuyos pedidos entran en ese grupo */
    protected array $diasAbsorbidos = [];
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
        $this->toleranciaBs = max(0.005, (float) $this->option('tolerancia-bs'));
        $this->umbralAjuste = max(0.0, (float) $this->option('umbral-ajuste')) / 100.0;
        $this->filtrarEstado = !$this->option('sin-filtro-estado');
        $this->diasRelleno = max(0, (int) $this->option('dias-relleno'));

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

        $agregadas = $this->option('por-dia-maquina') ? $reader->agregarPorDiaMaquina($normalizadas) : $reader->agregarPorFila($normalizadas);

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

        $this->calcularDiasAbsorbidos($agregadas);

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
                'Tiempo máximo estimado de búsqueda: %d grupos × %.0f s = %s (cota superior; la búsqueda se corta al llegar a ±%.3f Bs del objetivo)',
                $totalGrupos, $this->maxSegundos, $this->formatearDuracion($totalGrupos * $this->maxSegundos), $this->toleranciaBs
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
                    $resultado = $this->procesarDiaMaquina($fecha, $maquina, $montoObjetivo, $facturaInicio, $facturaFin, $cantidad, $normalized['numeros'] ?? []);
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
    /**
     * Días que el libro de ventas "salta" (ningún concepto fiscal tiene filas ese día) y cuyas ventas quedaron en el
     * cierre Z del día siguiente (típico: el Z se cerró a la mañana siguiente y el libro trae dos Z ese día).
     * Para cada grupo (fecha, máquina) se anotan los días sin objetivo entre la fecha anterior de esa máquina y la
     * fecha del grupo; sus pedidos entran como candidatos del grupo. Solo aplica a máquinas fiscales (serial), no a
     * conceptos como "SERIE R" (facturas manuales), y hasta --max-dias-absorber días hacia atrás.
     */
    protected function calcularDiasAbsorbidos(array $agregadas): void
    {
        $this->diasAbsorbidos = [];
        if ($this->option('sin-absorber')) {
            return;
        }
        $maxDias = max(0, (int) $this->option('max-dias-absorber'));
        $esFiscal = fn (string $m) => (bool) preg_match('/^[A-Z]{1,4}[A-Z0-9]{5,}$/', $m);
        $fechasConObjetivo = [];
        $fechasPorMaquina = [];
        foreach ($agregadas as $g) {
            if ($esFiscal($g['maquina_fiscal'])) {
                $fechasConObjetivo[$g['fecha']] = true;
                $fechasPorMaquina[$g['maquina_fiscal']][$g['fecha']] = true;
            }
        }
        foreach ($fechasPorMaquina as $maquina => $fechas) {
            $lista = array_keys($fechas);
            sort($lista);
            for ($i = 1; $i < count($lista); $i++) {
                $prev = $lista[$i - 1];
                $actual = $lista[$i];
                $absorbidos = [];
                $d = date('Y-m-d', strtotime($prev . ' +1 day'));
                while ($d < $actual && count($absorbidos) < $maxDias) {
                    if (!isset($fechasConObjetivo[$d])) {
                        $absorbidos[] = $d;
                    }
                    $d = date('Y-m-d', strtotime($d . ' +1 day'));
                }
                if ($d < $actual) {
                    $absorbidos = []; // hueco más largo que el máximo: no se absorbe nada (mejor reportarlo como sin pedidos)
                }
                if (!empty($absorbidos)) {
                    $this->diasAbsorbidos[$actual . '|' . $maquina] = $absorbidos;
                }
            }
        }
        if (!empty($this->diasAbsorbidos)) {
            $this->info('Días sin objetivo en el libro que se absorben en el grupo siguiente de su máquina: ' . count($this->diasAbsorbidos) . ' grupo(s).');
        }
    }

    protected function procesarDiaMaquina(string $fechaStr, string $maquinaFiscal, string $montoObjetivo, string $facturaInicio, string $facturaFin, int $cantidadObjetivo, array $numeros = []): ?array
    {
        // Ya procesado = alguna de sus facturas ya está asignada. Se busca por número y no por fecha: con relleno o días
        // absorbidos el grupo puede no tener ningún pedido de su propia fecha.
        $yaProcesado = $this->tieneMaquinaFiscal
            && pedidos::where('maquina_fiscal', $maquinaFiscal)
                ->where('valido', true)
                ->where(function ($q) use ($numeros, $cantidadObjetivo, $facturaInicio, $facturaFin) {
                    if (count($numeros) === $cantidadObjetivo) {
                        $q->whereIn('numero_factura', array_map('strval', $numeros));
                    } else {
                        $q->whereRaw('CAST(numero_factura AS UNSIGNED) BETWEEN ? AND ?', [(int) $facturaInicio, (int) $facturaFin]);
                    }
                })
                ->exists();
        if ($yaProcesado) {
            return ['skipped' => true];
        }

        // Lectura y cómputo pesado FUERA de la transacción (la conexión se cierra si una transacción
        // queda abierta mucho tiempo sin consultas en algunos hostings).
        $fechasCandidatas = array_merge($this->diasAbsorbidos[$fechaStr . '|' . $maquinaFiscal] ?? [], [$fechaStr]);
        if (count($fechasCandidatas) > 1) {
            $this->line('  → absorbe también los días sin objetivo: ' . implode(', ', array_slice($fechasCandidatas, 0, -1)));
        }
        $pairs = $this->cargarCandidatos($fechasCandidatas);

        // Relleno: si con los pedidos del día no se puede llegar (faltan pedidos, o ni los N más baratos bajan al
        // objetivo, o ni los N más caros llegan), se suman los pedidos que sobraron de los días anteriores.
        if ($this->diasRelleno > 0 && $this->requiereRelleno($pairs, $cantidadObjetivo, (float) $montoObjetivo)) {
            $fechasRelleno = [];
            for ($d = 1; $d <= $this->diasRelleno; $d++) {
                $f = date('Y-m-d', strtotime($fechaStr . " -{$d} days"));
                if (!in_array($f, $fechasCandidatas, true)) {
                    $fechasRelleno[] = $f;
                }
            }
            $extra = empty($fechasRelleno) ? [] : $this->cargarCandidatos($fechasRelleno);
            if (!empty($extra)) {
                $this->line(sprintf('  → relleno con %d pedido(s) sobrantes de %s', count($extra), implode(', ', $fechasRelleno)));
                $pairs = array_merge($extra, $pairs);
            }
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
        // Si el libro trae los números exactos del grupo (p. ej. FISCAL UNITARIA no consecutivas), se usan esos.
        $numerosLibro = count($numeros) === $cantidadObjetivo ? array_values($numeros) : null;

        return DB::transaction(function () use ($selected, $fechaStr, $maquinaFiscal, $sumaSelected, $ajuste, $inicioNum, $numerosLibro, $montoObjetivo, $base) {
            foreach ($selected as $i => $ped) {
                $ped->numero_factura = (string) ($numerosLibro[$i] ?? ($inicioNum + $i));
                if ($this->tieneMaquinaFiscal) {
                    $ped->maquina_fiscal = $maquinaFiscal;
                }
                $ped->valido = true;
                $ped->save();
                $this->totalPedidosMarcados++;
            }

            $aplicado = '0';
            if (bccomp($ajuste, '0', $this->scale) !== 0) {
                $aplicado = $this->aplicarAjuste($selected, $ajuste, $montoObjetivo, $fechaStr, $maquinaFiscal);
            }
            $base['monto_real_dia'] = bcadd($sumaSelected, $aplicado, $this->scale);
            return $base;
        });
    }

    /**
     * Pedidos candidatos (no válidos, facturados y con monto > 0) de las fechas dadas, en orden cronológico.
     * @param string[] $fechas
     * @return array<int, array{pedido: pedidos, monto: string}>
     */
    protected function cargarCandidatos(array $fechas): array
    {
        $query = pedidos::whereIn(DB::raw('DATE(COALESCE(fecha_factura, created_at))'), $fechas)
            ->where(function ($q) {
                $q->whereNull('valido')->orWhere('valido', false);
            });
        if ($this->filtrarEstado && $this->tieneEstado) {
            $query->where('estado', 1);
        }
        $pedidos = $query->orderByRaw('COALESCE(fecha_factura, created_at) ASC')->orderBy('id')->get();
        if ($pedidos->isEmpty()) {
            return [];
        }

        // Una sola consulta para todos los montos por pedido.
        $montosPorPedido = [];
        foreach (array_chunk($pedidos->pluck('id')->all(), 1000) as $chunk) {
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
            if (bccomp($monto, '0.01', $this->scale) <= 0) { // incluye cambios que se compensan a 0 (quedan en ~0,01 Bs por redondeo)
                if (!isset($this->excluidosMontoCero[$ped->id])) {
                    $this->excluidosMontoCero[$ped->id] = true;
                    $this->pedidosExcluidosMontoCero++;
                }
                continue; // sin ítems, devolución o monto cero: no puede ser factura fiscal
            }
            $pairs[] = ['pedido' => $ped, 'monto' => $monto];
        }
        return $pairs;
    }

    /**
     * ¿Hace falta relleno? Sí cuando no hay N candidatos, o cuando ni los N más baratos bajan al objetivo, o ni los
     * N más caros llegan (con la tolerancia de búsqueda).
     */
    protected function requiereRelleno(array $pairs, int $cantidadObjetivo, float $montoObjetivo): bool
    {
        if (count($pairs) < $cantidadObjetivo) {
            return true;
        }
        $montos = array_map(fn ($p) => (float) $p['monto'], $pairs);
        sort($montos);
        $minimo = array_sum(array_slice($montos, 0, $cantidadObjetivo));
        $maximo = array_sum(array_slice($montos, -$cantidadObjetivo));
        return $minimo > $montoObjetivo + $this->toleranciaBs || $maximo < $montoObjetivo - $this->toleranciaBs;
    }

    /**
     * Greedy (20 semillas) + simulated annealing con límite de tiempo. Devuelve índices seleccionados.
     *
     * Las semillas alternan dos criterios: el greedy clásico (cada paso toma el pedido que deja la suma más cerca del
     * objetivo) y el greedy por promedio (cada paso toma el pedido más cercano a lo que falta / cupos restantes).
     * El clásico cae en una trampa cuando hay un pedido enorme parecido al objetivo: lo toma primero y el annealing
     * no puede sacarlo; el de promedio reparte el objetivo entre los N cupos y no lo elige.
     * @param float[] $montosFloat
     * @return int[]
     */
    protected function buscarSeleccion(array $montosFloat, int $total, int $cantidadObjetivo, float $montoObjFloat): array
    {
        $globalBestIndices = null;
        $globalBestDiff = PHP_FLOAT_MAX;
        $inicio = microtime(true);
        $deadline = $inicio + $this->maxSegundos;

        $tol = $this->toleranciaBs;
        for ($run = 0; $run < 20; $run++) {
            $result = $run % 2 === 0
                ? $this->greedyPorPromedio($montosFloat, $total, $cantidadObjetivo, $montoObjFloat, $run > 0)
                : $this->greedySeleccionRapida($montosFloat, $total, $cantidadObjetivo, $montoObjFloat);
            if ($result !== null && $result['abs_diff'] < $globalBestDiff) {
                $globalBestDiff = $result['abs_diff'];
                $globalBestIndices = $result['indices'];
            }
            if ($globalBestDiff <= $tol || microtime(true) >= $deadline) break;
        }

        if ($globalBestIndices !== null && $globalBestDiff > $tol) {
            $saRun = 0;
            while (microtime(true) < $deadline) {
                if ($saRun === 0) {
                    $semilla = $globalBestIndices;
                } else {
                    $reinicio = $saRun % 2 === 0
                        ? $this->greedyPorPromedio($montosFloat, $total, $cantidadObjetivo, $montoObjFloat, true)
                        : $this->greedySeleccionRapida($montosFloat, $total, $cantidadObjetivo, $montoObjFloat);
                    $semilla = $reinicio['indices'] ?? $globalBestIndices;
                }
                $semillaSuma = 0.0;
                foreach ($semilla as $idx) $semillaSuma += $montosFloat[$idx];

                $saResult = $this->simulatedAnnealingRapido($montosFloat, $total, $semilla, $semillaSuma, $cantidadObjetivo, $montoObjFloat, $deadline, $tol);
                if ($saResult['abs_diff'] < $globalBestDiff) {
                    $globalBestDiff = $saResult['abs_diff'];
                    $globalBestIndices = $saResult['indices'];
                }
                if ($globalBestDiff <= $tol) break;
                $saRun++;
            }
        }

        return $globalBestIndices ?? [];
    }

    /**
     * Aplica el ajuste en Bs cambiando el precio unitario de ítems de los pedidos elegidos, recalcula el pago de cada
     * pedido tocado y deja auditoría en cuadre_ajustes (una fila por ítem). Devuelve el ajuste realmente aplicado (Bs).
     *
     * - Si la diferencia ya está dentro de la tolerancia (1 Bs o 0,02 % del objetivo) no se toca nada.
     * - Cada ítem se prueba con el precio a 1 decimal en USD (precio "creíble"); si así no queda dentro de la
     *   tolerancia, con 2 y luego con 4 decimales. Entre los que quedan dentro se prefiere menos decimales, sin
     *   descuento, del último pedido y de mayor monto.
     * - Si un solo ítem no alcanza (p. ej. un ajuste negativo mayor que el ítem), se toma el que más acerca y se repite
     *   con el resto sobre otro ítem, hasta 20 ítems.
     * - Nunca se aplica un cambio que no acerque la suma al objetivo.
     */
    protected function aplicarAjuste($seleccionados, string $ajusteBs, string $montoObjetivo, string $fecha, string $maquinaFiscal): string
    {
        // 1 Bs o 0,02 % del objetivo, pero nunca más del 10 % (una factura de 1 Bs no puede quedar en 0).
        $tolerancia = min(max(1.0, abs((float) $montoObjetivo) * 0.0002), abs((float) $montoObjetivo) * 0.1);
        $restante = (float) $ajusteBs;
        if (abs($restante) <= $tolerancia) {
            return '0';
        }

        $ultimoId = (int) $seleccionados->last()->id;
        $pedidosPorId = $seleccionados->keyBy('id');
        $items = collect();
        foreach (array_chunk($pedidosPorId->keys()->all(), 1000) as $chunk) {
            $items = $items->merge(items_pedidos::whereIn('id_pedido', $chunk)->whereNotNull('id_producto')->where('cantidad', '>', 0)->get());
        }
        if ($items->isEmpty()) {
            \Log::warning('CuadrePedidosDiario: sin ítems para ajustar en el grupo', ['fecha' => $fecha, 'maquina' => $maquinaFiscal]);
            return '0';
        }

        $aplicadoTotal = '0';
        $usados = [];
        for ($paso = 0; $paso < 20 && abs($restante) > $tolerancia; $paso++) {
            $mejor = null;
            $mejorClave = null;
            foreach ($items as $item) {
                if (isset($usados[$item->id])) {
                    continue;
                }
                $tasa = (float) ($item->tasa ?? 0);
                if ($tasa <= 0) {
                    $tasa = $this->obtenerTasaGlobal();
                }
                $opcion = null;
                foreach ([1, 2, 4] as $decimales) {
                    $opcion = $this->calcularAjusteItem($item, $tasa, $restante, $decimales);
                    if ($opcion['error'] <= $tolerancia) {
                        break;
                    }
                }
                if ($opcion['error'] >= abs($restante) - 0.0001) {
                    continue; // no acerca la suma al objetivo
                }
                $dentro = $opcion['error'] <= $tolerancia;
                $clave = [
                    $dentro ? 0 : 1,
                    $dentro ? $opcion['decimales'] : 0,
                    $dentro ? 0 : $opcion['error'],
                    (float) ($item->descuento ?? 0) > 0 ? 1 : 0,
                    (int) $item->id_pedido === $ultimoId ? 0 : 1,
                    -$opcion['monto_actual_bs'],
                ];
                if ($mejorClave === null || $clave < $mejorClave) {
                    $mejorClave = $clave;
                    $mejor = [$item, $opcion];
                }
            }
            if ($mejor === null) {
                break;
            }
            [$item, $opcion] = $mejor;
            $aplicado = $this->guardarAjusteItem($item, $pedidosPorId[$item->id_pedido], $opcion, $restante, $fecha, $maquinaFiscal);
            $usados[$item->id] = true;
            $aplicadoTotal = bcadd($aplicadoTotal, $aplicado, $this->scale);
            $restante -= (float) $aplicado;
        }

        return $aplicadoTotal;
    }

    /**
     * Precio nuevo de un ítem para absorber $ajusteBs con el precio redondeado a $decimales en USD.
     * @return array{decimales: int, precio_unitario: float, monto_usd: float, monto_bs: float, monto_actual_bs: float, aplicado: float, error: float}
     */
    protected function calcularAjusteItem(items_pedidos $item, float $tasa, float $ajusteBs, int $decimales): array
    {
        $cantidad = (float) $item->cantidad;
        $montoActualBs = (float) ($item->monto_bs ?? ((float) $item->monto * $tasa));
        $nuevoMontoUsd = ($montoActualBs + $ajusteBs) / $tasa;
        $precio = $cantidad > 0 ? $nuevoMontoUsd / $cantidad : $nuevoMontoUsd;
        $precio = max(10 ** -$decimales, round($precio, $decimales));
        $montoUsd = round($cantidad * $precio, 4);
        $montoBs = round($montoUsd * $tasa, 4);
        $aplicado = $montoBs - $montoActualBs;
        return [
            'decimales'       => $decimales,
            'precio_unitario' => $precio,
            'monto_usd'       => $montoUsd,
            'monto_bs'        => $montoBs,
            'monto_actual_bs' => $montoActualBs,
            'aplicado'        => $aplicado,
            'error'           => abs($ajusteBs - $aplicado),
        ];
    }

    /**
     * Guarda el precio nuevo del ítem, mueve la diferencia al pago del pedido y deja la auditoría. Devuelve lo aplicado (Bs).
     */
    protected function guardarAjusteItem(items_pedidos $item, pedidos $pedido, array $opcion, float $ajusteBs, string $fecha, string $maquinaFiscal): string
    {
        $orig = [
            'precio_unitario' => $item->precio_unitario,
            'monto'           => $item->monto,
            'monto_bs'        => $item->monto_bs,
        ];
        $montoActualBs = $opcion['monto_actual_bs'];
        $nuevoPrecioUnitario = $opcion['precio_unitario'];
        $nuevoMontoUsd = $opcion['monto_usd'];
        $nuevoMontoBs = $opcion['monto_bs'];
        $ajusteBs = number_format($ajusteBs, 4, '.', '');

        $item->precio_unitario = $nuevoPrecioUnitario;
        $item->monto = $nuevoMontoUsd;
        $item->monto_bs = $nuevoMontoBs;
        $item->save();

        $aplicado = bcsub(number_format($nuevoMontoBs, 4, '.', ''), number_format($montoActualBs, 4, '.', ''), $this->scale);

        // Pago. Convención de la app: pago_pedidos.monto = USD, monto_original y monto_bs = Bs.
        // Se mueve la DIFERENCIA al primer pago de la venta (cuenta=1) para respetar pedidos con varios pagos;
        // si el pedido no tiene pagos se crea uno con la suma de ítems.
        $deltaUsd = round($nuevoMontoUsd - (float) ($orig['monto'] ?? 0), 4);
        $deltaBs = (float) $aplicado;
        $pago = pago_pedidos::where('id_pedido', $pedido->id)->where('cuenta', 1)->orderBy('id')->first()
            ?: pago_pedidos::where('id_pedido', $pedido->id)->orderBy('id')->first();
        $pagoOrig = null;
        $pagoCreado = false;
        if ($pago) {
            $pagoOrig = ['monto' => $pago->monto, 'monto_bs' => $pago->monto_bs ?? null, 'monto_original' => $pago->monto_original ?? null];
            $pago->monto = round((float) $pago->monto + $deltaUsd, 4);
            if ($pago->monto_original !== null) {
                $enDolar = strtolower((string) ($pago->moneda ?? '')) === 'dolar';
                $pago->monto_original = round((float) $pago->monto_original + ($enDolar ? $deltaUsd : $deltaBs), 4);
            }
            if ($this->pagoTieneMontoBs && $pago->monto_bs !== null) {
                $pago->monto_bs = round((float) $pago->monto_bs + $deltaBs, 4);
            }
            $pago->save();
        } else {
            $sumaUsdItems = (float) DB::table('items_pedidos')->where('id_pedido', $pedido->id)->sum(DB::raw('COALESCE(monto, 0)'));
            $sumaBsItems = (float) DB::table('items_pedidos')
                ->where('id_pedido', $pedido->id)
                ->sum(DB::raw('COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa, 0), 1), 0)'));
            $datosPago = [
                'id_pedido'      => $pedido->id,
                'tipo'           => '5',
                'cuenta'         => 1,
                'moneda'         => 'bs',
                'monto'          => round($sumaUsdItems, 4),
                'monto_original' => round($sumaBsItems, 4),
            ];
            if ($this->pagoTieneMontoBs) {
                $datosPago['monto_bs'] = round($sumaBsItems, 4);
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
        if ($this->tasaGlobal === null) {
            $tasaMoneda = DB::table('monedas')->where('tipo', 1)->orderBy('id', 'desc')->value('valor');
            $this->tasaGlobal = $tasaMoneda !== null && (float) $tasaMoneda > 0 ? (float) $tasaMoneda : 1.0;
        }
        return $this->tasaGlobal;
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
     * Greedy por promedio: en cada paso toma el pedido más cercano a (objetivo - suma) / cupos restantes, de modo que el
     * objetivo se reparte entre los N cupos. Con $ruido se desplaza ese ideal al azar (±30 %, salvo en el último cupo)
     * para que cada semilla sea distinta.
     */
    protected function greedyPorPromedio(array $montosFloat, int $total, int $cantidadObjetivo, float $montoObjetivo, bool $ruido): ?array
    {
        if ($total < $cantidadObjetivo || $cantidadObjetivo < 1) return null;
        $indices = range(0, $total - 1);
        shuffle($indices);
        $selectedSet = [];
        $selectedIndices = [];
        $suma = 0.0;
        for ($k = 0; $k < $cantidadObjetivo; $k++) {
            $restantes = $cantidadObjetivo - $k;
            $ideal = ($montoObjetivo - $suma) / $restantes;
            if ($ruido && $restantes > 1) {
                $ideal *= 0.7 + (mt_rand() / mt_getrandmax()) * 0.6;
            }
            $bestIdx = null;
            $bestDiff = PHP_FLOAT_MAX;
            foreach ($indices as $idx) {
                if (isset($selectedSet[$idx])) continue;
                $diff = abs($montosFloat[$idx] - $ideal);
                if ($diff < $bestDiff) {
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
    protected function simulatedAnnealingRapido(array $montosFloat, int $total, array $selectedIndices, float $currentSuma, int $cantidadObjetivo, float $montoObjetivo, float $deadline, float $tolerancia = 0.005): array
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
            if ($bestAbs <= $tolerancia) break;
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
