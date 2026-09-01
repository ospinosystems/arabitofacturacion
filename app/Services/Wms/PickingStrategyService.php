<?php

namespace App\Services\Wms;

use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

/**
 * Estrategia de selección de stock para picking.
 *
 * El WMS venía eligiendo ubicación con orderBy('cantidad','desc'): se despachaba
 * del montón más grande. Eso ignora el vencimiento, y con ello la mercancía vieja
 * se queda en el almacén hasta que se vence. Es una fuente de merma silenciosa.
 *
 * FEFO (First Expired, First Out) ordena por:
 *
 *   1. fecha de vencimiento ascendente — lo que caduca antes sale primero;
 *      los lotes sin fecha van al final, porque no se puede afirmar que sean
 *      más viejos que uno con fecha próxima.
 *   2. fecha de entrada ascendente — desempate FIFO entre lotes equivalentes.
 *   3. prioridad de picking de la ubicación — a igualdad de todo lo anterior,
 *      se toma la ubicación que queda antes en el recorrido.
 *
 * Los lotes ya vencidos nunca se sugieren: se listan aparte para que alguien
 * decida qué hacer con ellos.
 */
class PickingStrategyService
{
    private string $estrategia;
    private int $diasMinimosVencimiento;
    private bool $permitirMultiubicacion;

    public function __construct(array $config = [])
    {
        $cfg = array_merge(config('wms.picking'), $config);

        $this->estrategia             = $cfg['estrategia'];
        $this->diasMinimosVencimiento = (int) $cfg['dias_minimos_vencimiento'];
        $this->permitirMultiubicacion = (bool) $cfg['permitir_multiubicacion'];
    }

    /**
     * Existencias disponibles del producto, ordenadas según la estrategia.
     *
     * @return \Illuminate\Database\Eloquent\Collection<WarehouseInventory>
     */
    public function existenciasOrdenadas(int $inventarioId)
    {
        // El join con warehouses es necesario para ordenar por recorrido, así que
        // todas las columnas van calificadas: `estado`, `cantidad` y
        // `fecha_vencimiento` existen en ambas tablas o serían ambiguas.
        $q = WarehouseInventory::with('warehouse')
            ->select('warehouse_inventory.*')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_inventory.warehouse_id')
            ->where('warehouse_inventory.inventario_id', $inventarioId)
            ->where('warehouse_inventory.estado', 'disponible')
            ->where('warehouses.estado', 'activa')
            ->whereRaw('(warehouse_inventory.cantidad - COALESCE(warehouse_inventory.cantidad_bloqueada, 0)) > 0');

        // Nunca ofrecer para despacho un lote vencido (ni dentro del margen mínimo).
        $q->where(function ($sub) {
            $sub->whereNull('warehouse_inventory.fecha_vencimiento')
                ->orWhereDate(
                    'warehouse_inventory.fecha_vencimiento',
                    '>=',
                    now()->addDays($this->diasMinimosVencimiento)->toDateString()
                );
        });

        return $this->aplicarOrden($q)->get();
    }

    private function aplicarOrden($q)
    {
        if ($this->estrategia === 'fifo') {
            $q->orderBy('warehouse_inventory.fecha_entrada', 'asc');
        } else {
            // FEFO. En MySQL, "IS NULL" ordena los nulos al final con ASC.
            $q->orderByRaw('warehouse_inventory.fecha_vencimiento IS NULL ASC')
              ->orderBy('warehouse_inventory.fecha_vencimiento', 'asc')
              ->orderBy('warehouse_inventory.fecha_entrada', 'asc');
        }

        return $q->orderBy('warehouses.prioridad_picking', 'asc')
                 ->orderBy('warehouse_inventory.id', 'asc');
    }

    /**
     * La existencia que debe tomarse primero. Reemplaza directamente al viejo
     * orderBy('cantidad','desc').
     */
    public function mejorExistencia(int $inventarioId): ?WarehouseInventory
    {
        return $this->existenciasOrdenadas($inventarioId)->first();
    }

    /**
     * Código de ubicación a mostrar para un producto, o null si no tiene stock.
     */
    public function codigoUbicacionSugerida(int $inventarioId): ?string
    {
        $existencia = $this->mejorExistencia($inventarioId);

        return $existencia && $existencia->warehouse ? $existencia->warehouse->codigo : null;
    }

    /**
     * Plan de recolección: de qué ubicaciones y lotes tomar para completar la cantidad.
     *
     * @return array{
     *   completo: bool, cantidad_solicitada: float, cantidad_planificada: float,
     *   pendiente: float, lineas: array, alertas: array
     * }
     */
    public function planPicking(int $inventarioId, float $cantidad): array
    {
        $existencias = $this->existenciasOrdenadas($inventarioId);

        $restante = $cantidad;
        $lineas   = [];
        $alertas  = [];

        foreach ($existencias as $e) {
            if ($restante <= 0.0001) {
                break;
            }

            $disponible = (float) $e->cantidad - (float) ($e->cantidad_bloqueada ?? 0);
            if ($disponible <= 0) {
                continue;
            }

            $tomar = min($restante, $disponible);

            $lineas[] = [
                'warehouse_inventory_id' => $e->id,
                'warehouse_id'           => $e->warehouse_id,
                'codigo_ubicacion'       => optional($e->warehouse)->codigo,
                'prioridad_picking'      => optional($e->warehouse)->prioridad_picking,
                'lote'                   => $e->lote,
                'fecha_vencimiento'      => $e->fecha_vencimiento ? $e->fecha_vencimiento->toDateString() : null,
                'dias_para_vencer'       => $e->fecha_vencimiento
                    ? (int) now()->startOfDay()->diffInDays($e->fecha_vencimiento, false) : null,
                'disponible'             => round($disponible, 4),
                'cantidad'               => round($tomar, 4),
            ];

            $restante -= $tomar;

            if (!$this->permitirMultiubicacion) {
                break;
            }
        }

        // Avisos que el operario debe ver antes de recolectar.
        $vencidos = $this->existenciasVencidas($inventarioId);
        if ($vencidos->isNotEmpty()) {
            $alertas[] = [
                'tipo' => 'lotes_vencidos',
                'msj'  => 'Hay ' . $vencidos->count() . ' lote(s) vencido(s) de este producto que fueron excluidos del picking',
                'detalle' => $vencidos->map(fn ($v) => [
                    'codigo_ubicacion'  => optional($v->warehouse)->codigo,
                    'lote'              => $v->lote,
                    'cantidad'          => (float) $v->cantidad,
                    'fecha_vencimiento' => $v->fecha_vencimiento ? $v->fecha_vencimiento->toDateString() : null,
                ])->values()->all(),
            ];
        }

        $proximo = collect($lineas)->first(fn ($l) => $l['dias_para_vencer'] !== null && $l['dias_para_vencer'] <= 30);
        if ($proximo) {
            $alertas[] = [
                'tipo' => 'proximo_vencer',
                'msj'  => "El lote {$proximo['lote']} vence en {$proximo['dias_para_vencer']} días. Despachar con prioridad.",
            ];
        }

        if (count($lineas) > 1) {
            $alertas[] = [
                'tipo' => 'multiubicacion',
                'msj'  => 'La cantidad requiere recolectar de ' . count($lineas) . ' ubicaciones',
            ];
        }

        return [
            'completo'             => $restante <= 0.0001,
            'estrategia'           => $this->estrategia,
            'cantidad_solicitada'  => round($cantidad, 4),
            'cantidad_planificada' => round($cantidad - max(0, $restante), 4),
            'pendiente'            => round(max(0, $restante), 4),
            'lineas'               => $lineas,
            'alertas'              => $alertas,
        ];
    }

    /**
     * Lotes vencidos con stock. No se despachan, pero alguien tiene que verlos.
     */
    public function existenciasVencidas(int $inventarioId = null)
    {
        $q = WarehouseInventory::with('warehouse', 'inventario')
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->where('cantidad', '>', 0);

        if ($inventarioId) {
            $q->where('inventario_id', $inventarioId);
        }

        return $q->orderBy('fecha_vencimiento')->get();
    }

    /**
     * Ordena una lista de líneas de recolección por recorrido de almacén.
     *
     * Con varias líneas, recolectar en el orden en que fueron pedidas hace que el
     * pasillero cruce el almacén de ida y vuelta. Ordenar por prioridad_picking
     * convierte el recorrido en un solo barrido.
     */
    public function ordenarPorRecorrido(array $lineas): array
    {
        usort($lineas, function ($a, $b) {
            $pa = $a['prioridad_picking'] ?? PHP_INT_MAX;
            $pb = $b['prioridad_picking'] ?? PHP_INT_MAX;

            return $pa <=> $pb;
        });

        return $lineas;
    }

    /**
     * Mapa inventario_id => código de ubicación FEFO, para muchos productos a la vez.
     *
     * Evita el N+1 cuando hay que pintar una lista de items de pedido con su
     * ubicación sugerida.
     */
    public function mapaUbicacionesSugeridas(array $inventarioIds): array
    {
        $ids = array_values(array_unique(array_filter($inventarioIds)));
        if (empty($ids)) {
            return [];
        }

        $orden = $this->estrategia === 'fifo'
            ? 'wi.fecha_entrada ASC'
            : 'wi.fecha_vencimiento IS NULL ASC, wi.fecha_vencimiento ASC, wi.fecha_entrada ASC';

        // Se resuelve con una sola consulta: por cada producto, la primera fila
        // según el orden de la estrategia.
        $filas = DB::select("
            SELECT t.inventario_id, t.codigo, t.lote, t.fecha_vencimiento, t.disponible
            FROM (
                SELECT wi.inventario_id,
                       w.codigo,
                       wi.lote,
                       wi.fecha_vencimiento,
                       (wi.cantidad - COALESCE(wi.cantidad_bloqueada, 0)) AS disponible,
                       ROW_NUMBER() OVER (PARTITION BY wi.inventario_id ORDER BY {$orden}, w.prioridad_picking ASC, wi.id ASC) AS rn
                FROM warehouse_inventory wi
                JOIN warehouses w ON w.id = wi.warehouse_id
                WHERE wi.inventario_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                  AND wi.estado = 'disponible'
                  AND w.estado = 'activa'
                  AND (wi.cantidad - COALESCE(wi.cantidad_bloqueada, 0)) > 0
                  AND (wi.fecha_vencimiento IS NULL OR wi.fecha_vencimiento >= CURDATE())
            ) t
            WHERE t.rn = 1
        ", $ids);

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f->inventario_id] = [
                'codigo'            => $f->codigo,
                'lote'              => $f->lote,
                'fecha_vencimiento' => $f->fecha_vencimiento,
                'disponible'        => (float) $f->disponible,
            ];
        }

        return $mapa;
    }
}
