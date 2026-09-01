<?php

namespace App\Services\Wms;

use App\Models\ProductoAbc;
use App\Models\ProductoAbcHistorial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Clasificación ABC del inventario por análisis de Pareto.
 *
 * El método ABC parte de la observación de Pareto: una minoría de los artículos
 * concentra la mayoría de la actividad. Se ordenan los productos de mayor a menor
 * según una métrica, se acumula el porcentaje y se corta:
 *
 *   A -> hasta el 80% acumulado  (pocos artículos, casi toda la actividad)
 *   B -> del 80% al 95%
 *   C -> el resto                 (muchos artículos, poca actividad)
 *
 * Lo que cambia el resultado es la métrica elegida, y por eso se calculan varias:
 *
 *   valor       Consumo valorizado (unidades x costo). Es el ABC clásico de gestión
 *               de stock: dice dónde está inmovilizado el dinero. Sirve para decidir
 *               políticas de reposición y a qué se le hace seguimiento fino.
 *
 *   unidades    Volumen de salida. Dice qué mueve masa por el almacén.
 *
 *   popularidad Número de líneas de pedido, es decir cuántas veces hubo que ir a
 *               buscar el producto. Para ubicar mercancía esta es la métrica que
 *               manda: un artículo barato que se pide 40 veces al día cuesta más
 *               recorridos que uno caro que se pide una vez al mes.
 *
 *   combinado   Mezcla ponderada de las tres. Es la que usa el motor de slotting.
 *
 * Fuente de demanda: líneas de pedido reales (items_pedidos + pedidos). Se excluyen
 * pedidos anulados y devoluciones — una devolución genera trabajo de reubicación,
 * pero no es demanda y no debe subir la clase de un producto.
 */
class AbcClassificationService
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('wms.abc'), $config);
    }

    /**
     * Recalcula la clasificación completa y la persiste.
     *
     * @return array Resumen por criterio: conteo de A/B/C y totales.
     */
    public function recalcular(?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $hasta = $hasta ?: Carbon::now();
        $desde = $desde ?: (clone $hasta)->subDays((int) $this->config['dias_analisis']);

        $demanda = $this->obtenerDemanda($desde, $hasta);

        if (empty($demanda)) {
            return [
                'error'   => 'No hay demanda en el periodo analizado',
                'periodo' => [$desde->toDateString(), $hasta->toDateString()],
            ];
        }

        $resumen = [];
        foreach (['valor', 'unidades', 'popularidad', 'combinado'] as $criterio) {
            $resumen[$criterio] = $this->clasificarPorCriterio($criterio, $demanda, $desde, $hasta);
        }

        return [
            'periodo'          => [$desde->toDateString(), $hasta->toDateString()],
            'productos'        => count($demanda),
            'dias_analizados'  => $desde->diffInDays($hasta),
            'resumen'          => $resumen,
        ];
    }

    /**
     * Demanda agregada por producto en el periodo.
     *
     * Se toman sólo líneas con cantidad positiva: las negativas son correcciones o
     * devoluciones y restarían frecuencia de picking que sí ocurrió.
     *
     * @return array<int,array{unidades:float,valor:float,lineas:int}>
     */
    private function obtenerDemanda(Carbon $desde, Carbon $hasta): array
    {
        $filas = DB::table('items_pedidos as ip')
            ->join('pedidos as p', 'p.id', '=', 'ip.id_pedido')
            ->join('inventarios as i', 'i.id', '=', 'ip.id_producto')
            ->where('p.estado', 1)
            ->where(function ($q) {
                $q->whereNull('p.isdevolucionOriginalid')
                  ->orWhere('p.isdevolucionOriginalid', 0);
            })
            ->whereBetween('p.created_at', [$desde, $hasta])
            ->where('ip.cantidad', '>', 0)
            ->groupBy('ip.id_producto')
            ->select([
                'ip.id_producto',
                DB::raw('SUM(ip.cantidad) as unidades'),
                // Valorizado a costo: el ABC de valor mide capital inmovilizado,
                // no facturación, así que se usa precio_base y no el precio de venta.
                DB::raw('SUM(ip.cantidad * COALESCE(i.precio_base, 0)) as valor'),
                DB::raw('COUNT(*) as lineas'),
            ])
            ->get();

        $demanda = [];
        foreach ($filas as $f) {
            $demanda[(int) $f->id_producto] = [
                'unidades' => (float) $f->unidades,
                'valor'    => (float) $f->valor,
                'lineas'   => (int) $f->lineas,
            ];
        }

        return $demanda;
    }

    /**
     * Aplica Pareto para un criterio y persiste el resultado.
     */
    private function clasificarPorCriterio(string $criterio, array $demanda, Carbon $desde, Carbon $hasta): array
    {
        $metricas = $this->calcularMetricas($criterio, $demanda);

        // Orden descendente por métrica: el Pareto se construye de mayor a menor.
        arsort($metricas);

        $total = array_sum($metricas);
        if ($total <= 0) {
            return ['A' => 0, 'B' => 0, 'C' => 0, 'total' => 0];
        }

        $umbralA = (float) $this->config['umbral_a'];
        $umbralB = (float) $this->config['umbral_b'];

        $acumulado = 0.0;
        $ranking   = 0;
        $conteo    = ['A' => 0, 'B' => 0, 'C' => 0];
        $ahora     = Carbon::now();

        // Clasificaciones previas, para detectar cambios de clase en una sola consulta.
        $previas = ProductoAbc::where('criterio', $criterio)
            ->pluck('clase', 'inventario_id')
            ->toArray();

        $filas       = [];
        $cambios     = [];

        foreach ($metricas as $inventarioId => $metrica) {
            $ranking++;
            $participacion = ($metrica / $total) * 100;
            $acumulado    += $participacion;

            // El corte se evalúa sobre el acumulado *incluyendo* este producto: el
            // artículo que cruza el 80% pertenece todavía a la clase A.
            if ($acumulado <= $umbralA) {
                $clase = 'A';
            } elseif ($acumulado <= $umbralB) {
                $clase = 'B';
            } else {
                $clase = 'C';
            }

            $conteo[$clase]++;

            $filas[] = [
                'inventario_id'     => $inventarioId,
                'criterio'          => $criterio,
                'periodo_inicio'    => $desde->toDateString(),
                'periodo_fin'       => $hasta->toDateString(),
                'unidades'          => $demanda[$inventarioId]['unidades'],
                'valor'             => $demanda[$inventarioId]['valor'],
                'lineas'            => $demanda[$inventarioId]['lineas'],
                'metrica'           => round($metrica, 4),
                'participacion_pct' => round($participacion, 6),
                'acumulado_pct'     => round(min($acumulado, 100), 6),
                'clase'             => $clase,
                'ranking'           => $ranking,
                'calculado_en'      => $ahora,
                'created_at'        => $ahora,
                'updated_at'        => $ahora,
            ];

            $claseAnterior = $previas[$inventarioId] ?? null;
            if ($claseAnterior !== $clase) {
                $cambios[] = [
                    'inventario_id'  => $inventarioId,
                    'criterio'       => $criterio,
                    'clase_anterior' => $claseAnterior,
                    'clase_nueva'    => $clase,
                    'metrica'        => round($metrica, 4),
                    'periodo_inicio' => $desde->toDateString(),
                    'periodo_fin'    => $hasta->toDateString(),
                    'calculado_en'   => $ahora,
                    'created_at'     => $ahora,
                    'updated_at'     => $ahora,
                ];
            }
        }

        $this->persistir($criterio, $filas, $cambios);

        return [
            'A'      => $conteo['A'],
            'B'      => $conteo['B'],
            'C'      => $conteo['C'],
            'total'  => $ranking,
            'cambios' => count($cambios),
        ];
    }

    /**
     * Métrica de ordenamiento según el criterio.
     *
     * Para 'combinado' no se puede sumar directamente unidades con bolívares: se
     * normaliza cada métrica a su participación porcentual y se ponderan.
     *
     * @return array<int,float> inventario_id => métrica
     */
    private function calcularMetricas(string $criterio, array $demanda): array
    {
        if ($criterio !== 'combinado') {
            $campo = $criterio === 'popularidad' ? 'lineas' : $criterio;

            return array_map(fn ($d) => (float) $d[$campo], $demanda);
        }

        $pesos = $this->config['pesos_combinado'];

        $totales = [
            'unidades'    => array_sum(array_column($demanda, 'unidades')),
            'valor'       => array_sum(array_column($demanda, 'valor')),
            'popularidad' => array_sum(array_column($demanda, 'lineas')),
        ];

        $metricas = [];
        foreach ($demanda as $id => $d) {
            $pUnidades    = $totales['unidades']    > 0 ? $d['unidades'] / $totales['unidades'] : 0;
            $pValor       = $totales['valor']       > 0 ? $d['valor']    / $totales['valor']    : 0;
            $pPopularidad = $totales['popularidad'] > 0 ? $d['lineas']   / $totales['popularidad'] : 0;

            $metricas[$id] = ($pPopularidad * $pesos['popularidad'])
                           + ($pValor       * $pesos['valor'])
                           + ($pUnidades    * $pesos['unidades']);
        }

        return $metricas;
    }

    /**
     * Reemplaza la clasificación vigente del criterio y registra los cambios de clase.
     */
    private function persistir(string $criterio, array $filas, array $cambios): void
    {
        DB::transaction(function () use ($criterio, $filas, $cambios) {
            // Se recalcula el criterio completo: lo viejo deja de ser válido.
            ProductoAbc::where('criterio', $criterio)->delete();

            foreach (array_chunk($filas, 500) as $lote) {
                DB::table('producto_abc')->insert($lote);
            }

            foreach (array_chunk($cambios, 500) as $lote) {
                DB::table('producto_abc_historial')->insert($lote);
            }
        });
    }

    /**
     * Productos que subieron de clase desde el último cálculo: candidatos a reubicar
     * más cerca del muelle. Es el disparador natural del re-slotting.
     */
    public function candidatosReubicacion(string $criterio = null, int $limite = 50)
    {
        $criterio = $criterio ?: $this->config['criterio_slotting'];

        return ProductoAbcHistorial::with('inventario')
            ->where('criterio', $criterio)
            ->ascensos()
            ->orderByDesc('calculado_en')
            ->limit($limite)
            ->get();
    }

    /**
     * Distribución actual: cuántos productos y qué % de la métrica cae en cada clase.
     * Sirve para verificar que el Pareto se está comportando (una A gorda es señal
     * de que el umbral está mal puesto o de que el catálogo es muy plano).
     */
    public function distribucion(string $criterio = 'combinado'): array
    {
        $filas = ProductoAbc::where('criterio', $criterio)
            ->select('clase', DB::raw('COUNT(*) as productos'), DB::raw('SUM(participacion_pct) as participacion'))
            ->groupBy('clase')
            ->orderBy('clase')
            ->get();

        $totalProductos = $filas->sum('productos');

        return $filas->map(fn ($f) => [
            'clase'             => $f->clase,
            'productos'         => (int) $f->productos,
            'productos_pct'     => $totalProductos > 0 ? round(($f->productos / $totalProductos) * 100, 2) : 0,
            'participacion_pct' => round((float) $f->participacion, 2),
        ])->toArray();
    }
}
