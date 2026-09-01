<?php

namespace App\Http\Controllers;

use App\Models\ProductoAbc;
use App\Models\PutawaySugerencia;
use App\Models\Warehouse;
use App\Models\inventario;
use App\Services\Wms\AbcClassificationService;
use App\Services\Wms\SlottingService;
use Illuminate\Http\Request;
use Response;

/**
 * Sugerencia de ubicación (putaway) y analítica del motor de slotting.
 */
class SlottingController extends Controller
{
    /**
     * Sugiere dónde guardar un producto.
     *
     * Acepta el producto por id o por código de barras / proveedor, porque desde el
     * terminal del pasillero lo que llega es un escaneo, no un id.
     */
    public function sugerir(Request $request)
    {
        $request->validate([
            'cantidad'      => 'required|numeric|min:0.0001',
            'inventario_id' => 'nullable|integer',
            'codigo'        => 'nullable|string',
            'zona'          => 'nullable|string',
            'top_n'         => 'nullable|integer|min:1|max:10',
            'distribuir'    => 'nullable|boolean',
        ]);

        $producto = $this->resolverProducto($request);
        if (!$producto) {
            return Response::json(['estado' => false, 'msj' => 'Producto no encontrado'], 404);
        }

        $slotting = new SlottingService();
        $cantidad = (float) $request->cantidad;

        $opts = array_filter([
            'zona'  => $request->zona,
            'top_n' => $request->top_n ? (int) $request->top_n : null,
        ], fn ($v) => $v !== null);

        // Modo distribución: para cuando la cantidad no cabe en un solo hueco.
        if ($request->boolean('distribuir')) {
            return Response::json($slotting->sugerirDistribucion($producto, $cantidad, $opts));
        }

        $resultado = $slotting->sugerir($producto, $cantidad, $opts);

        // Se registra la sugerencia para poder cerrarla con la decisión real y
        // así construir el historial de aciertos y correcciones.
        if (!empty($resultado['candidatas'])) {
            $resultado['sugerencia_id'] = $slotting->registrarSugerencia(
                $resultado,
                $request->contexto ?? 'manual',
                $request->referencia,
                session('id_usuario')
            );
        }

        return Response::json($resultado);
    }

    /**
     * Registra qué ubicación eligió finalmente el operario.
     *
     * Es la mitad que da valor a la otra: sin esto sólo se sabe qué propuso el
     * motor, nunca si acertó.
     */
    public function registrarDecision(Request $request)
    {
        $request->validate([
            'sugerencia_id'    => 'required|integer|exists:putaway_sugerencias,id',
            'codigo_ubicacion' => 'required_without:warehouse_id|string|nullable',
            'warehouse_id'     => 'required_without:codigo_ubicacion|integer|nullable',
            'motivo'           => 'nullable|string|max:255',
        ]);

        $warehouseId = $request->warehouse_id;

        if (!$warehouseId) {
            $warehouse = Warehouse::where('codigo', $this->normalizarCodigo($request->codigo_ubicacion))->first();
            if (!$warehouse) {
                return Response::json(['estado' => false, 'msj' => 'Ubicación no encontrada'], 404);
            }
            $warehouseId = $warehouse->id;
        }

        (new SlottingService())->registrarDecision(
            (int) $request->sugerencia_id,
            (int) $warehouseId,
            $request->motivo
        );

        return Response::json(['estado' => true, 'msj' => 'Decisión registrada']);
    }

    /**
     * Panel del motor: qué tan seguido se acepta la sugerencia y dónde falla.
     */
    public function metricas(Request $request)
    {
        $dias = (int) ($request->dias ?? 30);
        $slotting = new SlottingService();

        $tasa = $slotting->tasaAceptacion($dias);

        // Los rechazos agrupados por motivo dicen qué factor del scoring está mal
        // calibrado. Es el insumo directo para reajustar los pesos.
        $rechazos = PutawaySugerencia::rechazadas()
            ->where('decidido_en', '>=', now()->subDays($dias))
            ->whereNotNull('motivo_override')
            ->selectRaw('motivo_override, COUNT(*) as n')
            ->groupBy('motivo_override')
            ->orderByDesc('n')
            ->limit(20)
            ->get();

        return Response::json([
            'estado'           => true,
            'dias'             => $dias,
            'aceptacion'       => $tasa,
            'motivos_rechazo'  => $rechazos,
            'listo_para_ajuste' => ($tasa['total'] ?? 0) >= 500,
            'nota' => ($tasa['total'] ?? 0) < 500
                ? 'Aún hay pocas decisiones registradas. Con unos cientos de casos se pueden reajustar los pesos del scoring contra el comportamiento real.'
                : 'Hay suficientes decisiones registradas para reajustar los pesos del scoring.',
        ]);
    }

    /**
     * Clasificación ABC: distribución y consulta por producto.
     */
    public function abc(Request $request)
    {
        $criterio = $request->criterio ?? config('wms.abc.criterio_slotting');
        $servicio = new AbcClassificationService();

        if ($request->filled('inventario_id') || $request->filled('codigo')) {
            $producto = $this->resolverProducto($request);
            if (!$producto) {
                return Response::json(['estado' => false, 'msj' => 'Producto no encontrado'], 404);
            }

            return Response::json([
                'estado'   => true,
                'producto' => ['id' => $producto->id, 'descripcion' => $producto->descripcion],
                'clases'   => ProductoAbc::where('inventario_id', $producto->id)->get(),
            ]);
        }

        return Response::json([
            'estado'       => true,
            'criterio'     => $criterio,
            'distribucion' => $servicio->distribucion($criterio),
            'reubicar'     => $servicio->candidatosReubicacion($criterio, 25)->map(fn ($h) => [
                'inventario_id'  => $h->inventario_id,
                'descripcion'    => optional($h->inventario)->descripcion,
                'clase_anterior' => $h->clase_anterior,
                'clase_nueva'    => $h->clase_nueva,
            ]),
        ]);
    }

    /**
     * Productos sin datos físicos cargados. Es la lista de trabajo para medir.
     */
    public function pendientesDeMedir(Request $request)
    {
        $q = inventario::where('activo', 1)
            ->where(function ($sub) {
                $sub->whereNull('peso_kg')
                    ->orWhereNull('volumen_m3')
                    ->orWhere('datos_fisicos_fuente', 'estimado');
            });

        // Priorizar por rotación: medir primero lo que más se mueve.
        $criterio = config('wms.abc.criterio_slotting');
        $q->leftJoin('producto_abc as abc', function ($j) use ($criterio) {
            $j->on('abc.inventario_id', '=', 'inventarios.id')->where('abc.criterio', '=', $criterio);
        })
        ->select('inventarios.id', 'inventarios.descripcion', 'inventarios.codigo_barras',
                 'inventarios.peso_kg', 'inventarios.volumen_m3', 'inventarios.datos_fisicos_fuente',
                 'abc.clase', 'abc.ranking')
        ->orderByRaw('abc.ranking IS NULL ASC')
        ->orderBy('abc.ranking');

        return Response::json([
            'estado'    => true,
            'total'     => (clone $q)->count(),
            'productos' => $q->limit((int) ($request->limite ?? 100))->get(),
            'nota'      => 'Ordenados por rotación: medir primero los de clase A rinde más.',
        ]);
    }

    /**
     * Carga las medidas reales de un producto y lo saca del estado "estimado".
     */
    public function guardarMedidas(Request $request)
    {
        $request->validate([
            'inventario_id' => 'required|integer|exists:inventarios,id',
            'peso_kg'       => 'required|numeric|min:0',
            'largo_cm'      => 'required|numeric|min:0',
            'ancho_cm'      => 'required|numeric|min:0',
            'alto_cm'       => 'required|numeric|min:0',
            'unidades_por_bulto' => 'nullable|integer|min:1',
            'fuente'        => 'nullable|in:medido,proveedor',
        ]);

        $producto = inventario::findOrFail($request->inventario_id);

        $producto->peso_kg  = $request->peso_kg;
        $producto->largo_cm = $request->largo_cm;
        $producto->ancho_cm = $request->ancho_cm;
        $producto->alto_cm  = $request->alto_cm;

        if ($request->filled('unidades_por_bulto')) {
            $producto->unidades_por_bulto = $request->unidades_por_bulto;
        }

        $producto->datos_fisicos_fuente   = $request->fuente ?? 'medido';
        $producto->datos_fisicos_medido_en = now();
        // volumen_m3 lo recalcula el modelo al guardar.
        $producto->save();

        return Response::json([
            'estado'   => true,
            'msj'      => 'Medidas guardadas',
            'producto' => [
                'id'         => $producto->id,
                'peso_kg'    => (float) $producto->peso_kg,
                'volumen_m3' => (float) $producto->volumen_m3,
                'fuente'     => $producto->datos_fisicos_fuente,
            ],
        ]);
    }

    /**
     * Ocupación física del almacén: dónde queda espacio y dónde no.
     */
    public function ocupacion(Request $request)
    {
        $ubicaciones = Warehouse::query()
            ->where('estado', 'activa')
            ->when($request->filled('zona'), fn ($q) => $q->where('zona', $request->zona))
            ->orderBy('prioridad_picking')
            ->get();

        $resumen = ['A' => 0, 'B' => 0, 'C' => 0, 'sin_clase' => 0];
        $detalle = [];
        $ocupadas = 0;

        foreach ($ubicaciones as $u) {
            $ocup = $u->ocupacionFisica();
            $tieneStock = $ocup['unidades'] > 0;
            if ($tieneStock) {
                $ocupadas++;
            }

            $clave = $u->clase_abc ?: 'sin_clase';
            $resumen[$clave] = ($resumen[$clave] ?? 0) + 1;

            $detalle[] = [
                'codigo'       => $u->codigo,
                'zona'         => $u->zona,
                'clase_abc'    => $u->clase_abc,
                'unidades'     => $ocup['unidades'],
                'peso_kg'      => $ocup['peso_kg'],
                'volumen_m3'   => $ocup['volumen_m3'],
                'pct_volumen'  => $u->capacidad_volumen > 0
                    ? round(($ocup['volumen_m3'] / (float) $u->capacidad_volumen) * 100, 2) : null,
                'pct_peso'     => $u->capacidad_peso > 0
                    ? round(($ocup['peso_kg'] / (float) $u->capacidad_peso) * 100, 2) : null,
                'datos_completos' => $ocup['completa'],
            ];
        }

        return Response::json([
            'estado'             => true,
            'total_ubicaciones'  => $ubicaciones->count(),
            'ocupadas'           => $ocupadas,
            'vacias'             => $ubicaciones->count() - $ocupadas,
            'ocupacion_pct'      => $ubicaciones->count() > 0
                ? round(($ocupadas / $ubicaciones->count()) * 100, 2) : 0,
            'por_clase'          => $resumen,
            'ubicaciones'        => $detalle,
        ]);
    }

    private function resolverProducto(Request $request): ?inventario
    {
        if ($request->filled('inventario_id')) {
            return inventario::find($request->inventario_id);
        }

        $codigo = trim((string) $request->codigo);
        if ($codigo === '') {
            return null;
        }

        return inventario::where('codigo_barras', $codigo)
            ->orWhere('codigo_proveedor', $codigo)
            ->first();
    }

    /** Mismo criterio de normalización que usa el TCR al escanear una ubicación. */
    private function normalizarCodigo(?string $codigo): ?string
    {
        if (!$codigo) {
            return $codigo;
        }

        $n = preg_replace('/[^a-zA-Z0-9]/', '-', $codigo);
        $n = preg_replace('/-+/', '-', $n);

        return trim($n, '-');
    }
}
