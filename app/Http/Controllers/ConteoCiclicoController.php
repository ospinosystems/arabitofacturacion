<?php

namespace App\Http\Controllers;

use App\Models\ConteoCiclico;
use App\Models\ConteoCiclicoDetalle;
use App\Services\Wms\ConteoCiclicoService;
use Illuminate\Http\Request;
use Response;

/**
 * Conteo cíclico por ubicación.
 *
 * Ojo con la diferencia respecto al "inventario cíclico" que ya existe contra
 * arabitocentral: aquél cuenta productos contra el stock general de la sucursal;
 * éste cuenta ubicaciones físicas contra warehouse_inventory.
 */
class ConteoCiclicoController extends Controller
{
    public function index(Request $request)
    {
        $conteos = ConteoCiclico::query()
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->withCount('detalles')
            ->orderByDesc('id')
            ->limit((int) ($request->limite ?? 50))
            ->get();

        return Response::json([
            'estado'    => true,
            'conteos'   => $conteos,
            'exactitud' => (new ConteoCiclicoService())->exactitudPeriodo(90),
        ]);
    }

    /**
     * Genera un nuevo conteo. Por defecto, tipo ABC: selecciona las ubicaciones
     * cuyo plazo de recuento ya venció según la clase del producto que contienen.
     */
    public function generar(Request $request)
    {
        $request->validate([
            'tipo'         => 'nullable|in:abc,zona,ubicaciones,producto',
            'criterio_abc' => 'nullable|in:A,B,C',
            'zona'         => 'nullable|string',
            'limite'       => 'nullable|integer|min:1|max:500',
            'ciego'        => 'nullable|boolean',
        ]);

        $conteo = (new ConteoCiclicoService())->generar([
            'tipo'           => $request->tipo ?? 'abc',
            'criterio_abc'   => $request->criterio_abc,
            'zona'           => $request->zona,
            'limite'         => (int) ($request->limite ?? 50),
            'ciego'          => $request->boolean('ciego', true),
            'exige_recuento' => $request->boolean('exige_recuento', true),
            'warehouse_ids'  => $request->warehouse_ids,
            'usuario_id'     => session('id_usuario'),
            'observaciones'  => $request->observaciones,
        ]);

        if ($conteo->total_lineas === 0) {
            return Response::json([
                'estado' => true,
                'conteo' => $conteo,
                'msj'    => 'No hay ubicaciones que cumplan el criterio (puede que ya estén todas dentro de su plazo de recuento).',
            ]);
        }

        return Response::json([
            'estado' => true,
            'conteo' => $conteo,
            'msj'    => "Conteo {$conteo->codigo} generado con {$conteo->total_lineas} línea(s).",
        ]);
    }

    /**
     * Tareas de conteo. En modo ciego NUNCA se envía cantidad_sistema al cliente:
     * si viaja al navegador, alguien puede verla y el conteo deja de ser ciego.
     */
    public function tareas(Request $request, $id)
    {
        $conteo = ConteoCiclico::findOrFail($id);

        $detalles = ConteoCiclicoDetalle::with(['warehouse', 'inventario'])
            ->where('conteo_id', $conteo->id)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('warehouse_id')
            ->get()
            ->map(function ($d) use ($conteo) {
                $fila = [
                    'id'               => $d->id,
                    'codigo_ubicacion' => optional($d->warehouse)->codigo,
                    'inventario_id'    => $d->inventario_id,
                    'descripcion'      => optional($d->inventario)->descripcion,
                    'codigo_barras'    => optional($d->inventario)->codigo_barras,
                    'lote'             => $d->lote,
                    'estado'           => $d->estado,
                    'es_recuento'      => $d->estado === 'en_recuento',
                ];

                if (!$conteo->ciego) {
                    $fila['cantidad_sistema'] = (float) $d->cantidad_sistema;
                    $fila['cantidad_contada'] = $d->cantidad_contada !== null ? (float) $d->cantidad_contada : null;
                    $fila['diferencia']       = $d->diferencia !== null ? (float) $d->diferencia : null;
                }

                return $fila;
            });

        return Response::json([
            'estado'   => true,
            'conteo'   => $conteo,
            'ciego'    => (bool) $conteo->ciego,
            'detalles' => $detalles,
        ]);
    }

    public function registrar(Request $request)
    {
        $request->validate([
            'detalle_id' => 'required|integer|exists:conteo_ciclico_detalles,id',
            'cantidad'   => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $resultado = (new ConteoCiclicoService())->registrarConteo(
            (int) $request->detalle_id,
            (float) $request->cantidad,
            session('id_usuario'),
            $request->observaciones
        );

        return Response::json($resultado, $resultado['estado'] ? 200 : 422);
    }

    public function ajustar(Request $request, $id)
    {
        $resultado = (new ConteoCiclicoService())->ajustar((int) $id, session('id_usuario'));

        return Response::json($resultado, $resultado['estado'] ? 200 : 422);
    }

    public function reporte($id)
    {
        $conteo = ConteoCiclico::with(['detalles.warehouse', 'detalles.inventario'])->findOrFail($id);

        $diferencias = $conteo->detalles
            ->filter(fn ($d) => $d->diferencia !== null && (float) $d->diferencia != 0.0)
            ->map(fn ($d) => [
                'codigo_ubicacion' => optional($d->warehouse)->codigo,
                'descripcion'      => optional($d->inventario)->descripcion,
                'lote'             => $d->lote,
                'sistema'          => (float) $d->cantidad_sistema,
                'contado'          => $d->cantidadFinal(),
                'diferencia'       => (float) $d->diferencia,
                'valor'            => (float) $d->valor_diferencia,
                'hallazgo'         => (bool) $d->es_hallazgo,
            ])->values();

        return Response::json([
            'estado'      => true,
            'conteo'      => $conteo->only([
                'codigo', 'tipo', 'estado', 'criterio_abc', 'zona',
                'total_lineas', 'lineas_contadas', 'lineas_con_diferencia',
                'valor_diferencia', 'exactitud_pct', 'iniciado_en', 'ajustado_en',
            ]),
            'diferencias' => $diferencias,
            'meta_pct'    => (float) config('wms.conteo.meta_exactitud_pct'),
        ]);
    }
}
