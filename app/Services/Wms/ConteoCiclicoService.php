<?php

namespace App\Services\Wms;

use App\Models\ConteoCiclico;
use App\Models\ConteoCiclicoDetalle;
use App\Models\WarehouseInventory;
use App\Models\WarehouseMovement;
use Illuminate\Support\Facades\DB;

/**
 * Conteo cíclico por ubicación.
 *
 * El WMS sólo vale lo que vale la confianza en sus cantidades. Sin recuentos
 * periódicos, el stock del sistema y el físico divergen y el motor de slotting
 * empieza a sugerir ubicaciones basándose en ocupaciones que no son reales.
 *
 * Dos reglas que definen si el conteo sirve o es teatro:
 *
 *   - CIEGO: el contador no ve la cantidad de sistema. Si la ve, la confirma.
 *   - RECUENTO ANTES DE AJUSTAR: una diferencia se vuelve a contar antes de
 *     tocar el inventario. Ajustar al primer conteo convierte un error de
 *     conteo en un error de inventario.
 *
 * La frecuencia sale del ABC: las ubicaciones con producto de clase A se cuentan
 * cada 30 días porque son las que más se manipulan y más se descuadran.
 */
class ConteoCiclicoService
{
    private array $frecuencias;
    private float $tolerancia;
    private string $criterioAbc;

    public function __construct()
    {
        $this->frecuencias = config('wms.conteo.frecuencia_dias');
        $this->tolerancia  = (float) config('wms.conteo.tolerancia_unidades');
        $this->criterioAbc = config('wms.abc.criterio_slotting', 'combinado');
    }

    /**
     * Genera un conteo con las ubicaciones que toca revisar.
     *
     * @param array $opts ['tipo','criterio_abc','zona','warehouse_ids','limite','ciego','usuario_id']
     */
    public function generar(array $opts = []): ConteoCiclico
    {
        $tipo  = $opts['tipo'] ?? 'abc';
        $clase = $opts['criterio_abc'] ?? null;
        $zona  = $opts['zona'] ?? null;
        $limite = (int) ($opts['limite'] ?? 50);

        return DB::transaction(function () use ($tipo, $clase, $zona, $limite, $opts) {
            $conteo = ConteoCiclico::create([
                'codigo'             => $this->generarCodigo(),
                'tipo'               => $tipo,
                'estado'             => 'planificado',
                'ciego'              => $opts['ciego'] ?? true,
                'exige_recuento'     => $opts['exige_recuento'] ?? true,
                'criterio_abc'       => $clase,
                'zona'               => $zona,
                'usuario_creador_id' => $opts['usuario_id'] ?? null,
                'fecha_programada'   => $opts['fecha_programada'] ?? now()->toDateString(),
                'observaciones'      => $opts['observaciones'] ?? null,
            ]);

            $lineas = $this->seleccionarLineas($tipo, $clase, $zona, $limite, $opts);

            foreach ($lineas as $linea) {
                ConteoCiclicoDetalle::create([
                    'conteo_id'        => $conteo->id,
                    'warehouse_id'     => $linea->warehouse_id,
                    'inventario_id'    => $linea->inventario_id,
                    'lote'             => $linea->lote,
                    // Se congela aquí: la diferencia debe medirse contra lo que el
                    // sistema decía al emitir la tarea, no contra lo de después.
                    'cantidad_sistema' => $linea->cantidad,
                    'estado'           => 'pendiente',
                ]);
            }

            $conteo->total_lineas = count($lineas);
            $conteo->save();

            return $conteo->fresh('detalles');
        });
    }

    /**
     * Elige qué contar. Con tipo 'abc' se priorizan las ubicaciones cuya última
     * verificación quedó fuera de la frecuencia que le corresponde a su clase.
     */
    private function seleccionarLineas(string $tipo, ?string $clase, ?string $zona, int $limite, array $opts)
    {
        $q = DB::table('warehouse_inventory as wi')
            ->join('warehouses as w', 'w.id', '=', 'wi.warehouse_id')
            ->leftJoin('producto_abc as abc', function ($j) {
                $j->on('abc.inventario_id', '=', 'wi.inventario_id')
                  ->where('abc.criterio', '=', $this->criterioAbc);
            })
            ->where('w.estado', 'activa')
            ->select('wi.warehouse_id', 'wi.inventario_id', 'wi.lote', 'wi.cantidad', 'abc.clase');

        if ($zona) {
            $q->where('w.zona', $zona);
        }

        if (!empty($opts['warehouse_ids'])) {
            $q->whereIn('wi.warehouse_id', (array) $opts['warehouse_ids']);
        }

        if ($tipo === 'abc' && $clase) {
            $q->where('abc.clase', $clase);
        }

        if ($tipo === 'abc') {
            // Días desde el último conteo de esa ubicación, comparados con la
            // frecuencia que exige la clase del producto que contiene.
            $casos = [];
            foreach ($this->frecuencias as $c => $dias) {
                $casos[] = "WHEN abc.clase = '" . addslashes($c) . "' THEN " . (int) $dias;
            }
            $caseFrecuencia = 'CASE ' . implode(' ', $casos) . ' ELSE 180 END';

            $q->leftJoin(DB::raw('(
                    SELECT d.warehouse_id, MAX(d.contado_en) AS ultimo
                    FROM conteo_ciclico_detalles d
                    WHERE d.contado_en IS NOT NULL
                    GROUP BY d.warehouse_id
                ) uc'), 'uc.warehouse_id', '=', 'wi.warehouse_id')
              ->addSelect(DB::raw('uc.ultimo as ultimo_conteo'))
              // Nunca contada, o vencido su plazo.
              ->whereRaw("(uc.ultimo IS NULL OR DATEDIFF(NOW(), uc.ultimo) >= {$caseFrecuencia})")
              // Las más atrasadas primero; las nunca contadas al principio.
              ->orderByRaw('uc.ultimo IS NULL DESC')
              ->orderBy('uc.ultimo');
        } else {
            $q->orderBy('w.prioridad_picking');
        }

        return $q->limit($limite)->get();
    }

    /**
     * Registra la cantidad contada de una línea.
     *
     * Si hay diferencia y el conteo exige recuento, la línea pasa a 'en_recuento'
     * en vez de quedar lista para ajustar.
     */
    public function registrarConteo(int $detalleId, float $cantidad, ?int $usuarioId = null, ?string $observaciones = null): array
    {
        // Una cantidad contada nunca puede ser negativa: se cuenta lo que hay en el
        // estante, y no hay forma de tener menos que nada. Sin esta guarda, un error
        // de tipeo o una llamada interna mal formada deja el stock en negativo.
        if ($cantidad < 0) {
            return ['estado' => false, 'msj' => 'La cantidad contada no puede ser negativa'];
        }

        $detalle = ConteoCiclicoDetalle::with('conteo', 'inventario')->findOrFail($detalleId);
        $conteo  = $detalle->conteo;

        if (in_array($conteo->estado, ['ajustado', 'cancelado'], true)) {
            return ['estado' => false, 'msj' => 'El conteo ya está cerrado'];
        }

        $esRecuento = $detalle->estado === 'en_recuento';

        if ($esRecuento) {
            $detalle->cantidad_recuento = $cantidad;
        } else {
            $detalle->cantidad_contada = $cantidad;
        }

        $final = $detalle->cantidadFinal();
        $diferencia = $final - (float) $detalle->cantidad_sistema;

        $detalle->diferencia = $diferencia;
        $detalle->valor_diferencia = $this->valorizar($detalle, $diferencia);
        $detalle->usuario_id = $usuarioId;
        $detalle->contado_en = now();
        if ($observaciones) {
            $detalle->observaciones = $observaciones;
        }

        $hayDiferencia = abs($diferencia) > $this->tolerancia;

        if ($hayDiferencia && $conteo->exige_recuento && !$esRecuento) {
            $detalle->estado = 'en_recuento';
        } else {
            $detalle->estado = 'contado';
        }

        $detalle->save();

        if ($conteo->estado === 'planificado') {
            $conteo->estado = 'en_proceso';
            $conteo->iniciado_en = now();
            $conteo->save();
        }

        $conteo->recalcularResumen();

        return [
            'estado'          => true,
            'requiere_recuento' => $detalle->estado === 'en_recuento',
            'diferencia'      => $conteo->ciego && $detalle->estado === 'en_recuento'
                // En conteo ciego no se revela la diferencia: sólo se pide recontar.
                ? null
                : round($diferencia, 4),
            'msj'             => $detalle->estado === 'en_recuento'
                ? 'Se requiere un segundo conteo de esta ubicación'
                : 'Conteo registrado',
        ];
    }

    /**
     * Aplica los ajustes al inventario y cierra el conteo.
     *
     * Cada ajuste deja su movimiento en el kardex de ubicaciones: un cuadre sin
     * rastro es indistinguible de un robo.
     */
    public function ajustar(int $conteoId, ?int $usuarioId = null): array
    {
        $conteo = ConteoCiclico::with('detalles')->findOrFail($conteoId);

        if ($conteo->estado === 'ajustado') {
            return ['estado' => false, 'msj' => 'Este conteo ya fue ajustado'];
        }

        $pendientesRecuento = $conteo->detalles->where('estado', 'en_recuento')->count();
        if ($pendientesRecuento > 0) {
            return [
                'estado' => false,
                'msj'    => "Faltan {$pendientesRecuento} línea(s) por recontar antes de poder ajustar",
            ];
        }

        $ajustadas = 0;
        $valorTotal = 0.0;

        DB::transaction(function () use ($conteo, $usuarioId, &$ajustadas, &$valorTotal) {
            foreach ($conteo->detalles->where('estado', 'contado') as $detalle) {
                $diferencia = (float) $detalle->diferencia;

                if (abs($diferencia) <= $this->tolerancia) {
                    $detalle->estado = 'ajustado';
                    $detalle->save();
                    continue;
                }

                $wi = WarehouseInventory::where('warehouse_id', $detalle->warehouse_id)
                    ->where('inventario_id', $detalle->inventario_id)
                    ->where('lote', $detalle->lote)
                    ->first();

                $cantidadFinal = $detalle->cantidadFinal();

                if ($wi) {
                    $wi->cantidad = $cantidadFinal;
                    $wi->save();
                } elseif ($cantidadFinal > 0) {
                    // Hallazgo: había producto donde el sistema no registraba nada.
                    $wi = WarehouseInventory::create([
                        'warehouse_id'  => $detalle->warehouse_id,
                        'inventario_id' => $detalle->inventario_id,
                        'cantidad'      => $cantidadFinal,
                        'lote'          => $detalle->lote,
                        'fecha_entrada' => now()->toDateString(),
                        'estado'        => 'disponible',
                        'observaciones' => 'Alta por conteo cíclico ' . $conteo->codigo,
                    ]);
                    $detalle->es_hallazgo = true;
                }

                $movimiento = WarehouseMovement::create([
                    'tipo'                 => 'ajuste',
                    'inventario_id'        => $detalle->inventario_id,
                    // Un ajuste positivo entra a la ubicación; uno negativo sale de ella.
                    'warehouse_origen_id'  => $diferencia < 0 ? $detalle->warehouse_id : null,
                    'warehouse_destino_id' => $diferencia > 0 ? $detalle->warehouse_id : null,
                    'cantidad'             => abs($diferencia),
                    'lote'                 => $detalle->lote,
                    'usuario_id'           => $usuarioId,
                    'documento_referencia' => $conteo->codigo,
                    'observaciones'        => 'Ajuste por conteo cíclico. Sistema: '
                        . $detalle->cantidad_sistema . ', contado: ' . $cantidadFinal,
                    'fecha_movimiento'     => now(),
                ]);

                $detalle->warehouse_movement_id = $movimiento->id;
                $detalle->estado = 'ajustado';
                $detalle->save();

                $ajustadas++;
                $valorTotal += (float) $detalle->valor_diferencia;
            }

            $conteo->estado = 'ajustado';
            $conteo->ajustado_en = now();
            $conteo->finalizado_en = $conteo->finalizado_en ?: now();
            $conteo->save();
        });

        $conteo->recalcularResumen();

        return [
            'estado'           => true,
            'msj'              => "Conteo ajustado. {$ajustadas} línea(s) corregidas.",
            'lineas_ajustadas' => $ajustadas,
            'valor_diferencia' => round($valorTotal, 4),
            'exactitud_pct'    => $conteo->fresh()->exactitud_pct,
        ];
    }

    /**
     * Valoriza la diferencia a costo, para poder medir el impacto en dinero.
     */
    private function valorizar(ConteoCiclicoDetalle $detalle, float $diferencia): float
    {
        $costo = optional($detalle->inventario)->precio_base ?? 0;

        return round($diferencia * (float) $costo, 4);
    }

    private function generarCodigo(): string
    {
        $ultimo = ConteoCiclico::orderByDesc('id')->value('id') ?? 0;

        return 'CC-' . now()->format('Ymd') . '-' . str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Exactitud de inventario del periodo: el KPI que resume si el almacén es fiable.
     */
    public function exactitudPeriodo(int $dias = 90): array
    {
        $conteos = ConteoCiclico::where('estado', 'ajustado')
            ->where('ajustado_en', '>=', now()->subDays($dias))
            ->get();

        $contadas = $conteos->sum('lineas_contadas');
        $conDif   = $conteos->sum('lineas_con_diferencia');

        $meta = (float) config('wms.conteo.meta_exactitud_pct');
        $exactitud = $contadas > 0 ? round((($contadas - $conDif) / $contadas) * 100, 2) : null;

        return [
            'dias'                  => $dias,
            'conteos'               => $conteos->count(),
            'lineas_contadas'       => $contadas,
            'lineas_con_diferencia' => $conDif,
            'exactitud_pct'         => $exactitud,
            'meta_pct'              => $meta,
            'cumple_meta'           => $exactitud !== null ? $exactitud >= $meta : null,
            'valor_diferencia'      => round($conteos->sum('valor_diferencia'), 2),
        ];
    }
}
