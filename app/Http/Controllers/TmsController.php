<?php

namespace App\Http\Controllers;

use App\Models\TmsConductor;
use App\Models\TmsParada;
use App\Models\TmsParadaItem;
use App\Models\TmsRuta;
use App\Models\TmsVehiculo;
use App\Services\Wms\PlanificacionCargaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Response;

/**
 * TMS: flota, planificación de carga, rutas y prueba de entrega.
 */
class TmsController extends Controller
{
    // ---------------------------------------------------------------- Flota

    public function vehiculos(Request $request)
    {
        $vehiculos = TmsVehiculo::with('conductorHabitual')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('placa')
            ->get()
            ->map(function ($v) {
                $libre = $v->capacidadLibre(now()->toDateString());

                return array_merge($v->toArray(), [
                    'capacidad_libre_hoy' => $libre,
                ]);
            });

        return Response::json(['estado' => true, 'vehiculos' => $vehiculos]);
    }

    public function guardarVehiculo(Request $request)
    {
        $datos = $request->validate([
            'id'                   => 'nullable|integer|exists:tms_vehiculos,id',
            'placa'                => 'required|string|max:20',
            'nombre'               => 'nullable|string|max:191',
            'tipo'                 => 'required|in:moto,camioneta,camion,furgon,trailer',
            'capacidad_peso_kg'    => 'required|numeric|min:0',
            'capacidad_volumen_m3' => 'required|numeric|min:0',
            'capacidad_bultos'     => 'nullable|integer|min:0',
            'refrigerado'          => 'nullable|boolean',
            'costo_km'             => 'nullable|numeric|min:0',
            'costo_fijo_viaje'     => 'nullable|numeric|min:0',
            'estado'               => 'nullable|in:disponible,en_ruta,mantenimiento,inactivo',
            'conductor_habitual_id' => 'nullable|integer|exists:tms_conductores,id',
            'observaciones'        => 'nullable|string',
        ]);

        // La placa identifica al vehículo: no puede repetirse en otro registro.
        $duplicada = TmsVehiculo::where('placa', $datos['placa'])
            ->when(!empty($datos['id']), fn ($q) => $q->where('id', '!=', $datos['id']))
            ->exists();

        if ($duplicada) {
            return Response::json(['estado' => false, 'msj' => 'Ya existe un vehículo con esa placa'], 422);
        }

        $vehiculo = TmsVehiculo::updateOrCreate(['id' => $datos['id'] ?? null], $datos);

        return Response::json(['estado' => true, 'vehiculo' => $vehiculo, 'msj' => 'Vehículo guardado']);
    }

    public function conductores(Request $request)
    {
        return Response::json([
            'estado'      => true,
            'conductores' => TmsConductor::when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
                ->orderBy('nombre')->get()
                ->map(fn ($c) => array_merge($c->toArray(), ['licencia_vigente' => $c->licenciaVigente()])),
        ]);
    }

    public function guardarConductor(Request $request)
    {
        $datos = $request->validate([
            'id'             => 'nullable|integer|exists:tms_conductores,id',
            'nombre'         => 'required|string|max:191',
            'documento'      => 'nullable|string|max:40',
            'telefono'       => 'nullable|string|max:40',
            'licencia'       => 'nullable|string|max:40',
            'licencia_vence' => 'nullable|date',
            'usuario_id'     => 'nullable|integer',
            'estado'         => 'nullable|in:disponible,en_ruta,inactivo',
            'observaciones'  => 'nullable|string',
        ]);

        $conductor = TmsConductor::updateOrCreate(['id' => $datos['id'] ?? null], $datos);

        return Response::json(['estado' => true, 'conductor' => $conductor, 'msj' => 'Conductor guardado']);
    }

    // -------------------------------------------------- Planificación de carga

    /**
     * Simula el reparto de envíos entre vehículos sin persistir nada.
     * Sirve para ver cuántos viajes hacen falta antes de comprometer la flota.
     */
    public function planificar(Request $request)
    {
        $request->validate([
            'envios'                 => 'required|array|min:1',
            'envios.*.items'         => 'required|array|min:1',
            'envios.*.items.*.inventario_id' => 'required|integer',
            'envios.*.items.*.cantidad'      => 'required|numeric|min:0.0001',
            'fecha'                  => 'nullable|date',
            'vehiculo_ids'           => 'nullable|array',
        ]);

        $plan = (new PlanificacionCargaService())->planificar($request->envios, [
            'fecha'        => $request->fecha,
            'vehiculo_ids' => $request->vehiculo_ids,
        ]);

        return Response::json($plan);
    }

    /**
     * Planifica y además crea las rutas y paradas.
     */
    public function crearRutas(Request $request)
    {
        $request->validate([
            'envios'       => 'required|array|min:1',
            'fecha'        => 'nullable|date',
            'conductor_id' => 'nullable|integer|exists:tms_conductores,id',
        ]);

        $servicio = new PlanificacionCargaService();
        $plan = $servicio->planificar($request->envios, [
            'fecha'        => $request->fecha,
            'vehiculo_ids' => $request->vehiculo_ids,
        ]);

        if (empty($plan['rutas'])) {
            return Response::json([
                'estado' => false,
                'msj'    => $plan['msj'] ?? 'No se pudo asignar ningún envío a un vehículo',
                'sin_asignar' => $plan['sin_asignar'] ?? [],
            ], 422);
        }

        $rutas = $servicio->crearRutas($plan['rutas'], [
            'fecha'        => $request->fecha,
            'conductor_id' => $request->conductor_id,
            'usuario_id'   => session('id_usuario'),
            'observaciones' => $request->observaciones,
        ]);

        return Response::json([
            'estado'      => true,
            'rutas'       => $rutas,
            'sin_asignar' => $plan['sin_asignar'],
            'aviso'       => $plan['aviso'],
            'msj'         => count($rutas) . ' ruta(s) creada(s)',
        ]);
    }

    /**
     * Convierte órdenes TCD ya despachadas en paradas de ruta.
     *
     * Esta es la costura entre el WMS y el TMS. El TCD termina cuando la mercancía
     * sale del muelle ('despachada'); a partir de ahí el problema es de transporte.
     * Sin este puente había que volver a teclear los items para planificar la ruta.
     *
     * Se puede crear ruta nueva o agregar a una existente (`ruta_id`), porque en la
     * práctica un camión sale con varias órdenes.
     */
    public function rutasDesdeTcd(Request $request)
    {
        $request->validate([
            'orden_ids'   => 'required|array|min:1',
            'orden_ids.*' => 'integer|exists:tcd_ordenes,id',
            'ruta_id'     => 'nullable|integer|exists:tms_rutas,id',
            'fecha'       => 'nullable|date',
            'conductor_id' => 'nullable|integer|exists:tms_conductores,id',
        ]);

        $ordenes = \App\Models\TCDOrden::with('items')
            ->whereIn('id', $request->orden_ids)
            ->get();

        // Sólo tiene sentido transportar lo que ya salió del almacén.
        $noDespachadas = $ordenes->whereNotIn('estado', ['completada', 'despachada']);
        if ($noDespachadas->isNotEmpty()) {
            return Response::json([
                'estado' => false,
                'msj'    => 'Estas órdenes aún no están completadas ni despachadas: '
                            . $noDespachadas->pluck('numero_orden')->implode(', '),
            ], 422);
        }

        // Una orden ya montada en una ruta no se vuelve a montar.
        $yaEnRuta = TmsParada::whereIn('tcd_orden_id', $ordenes->pluck('id'))
            ->whereHas('ruta', fn ($q) => $q->whereNotIn('estado', ['cancelada']))
            ->pluck('tcd_orden_id')->all();

        if (!empty($yaEnRuta)) {
            return Response::json([
                'estado' => false,
                'msj'    => 'Ya hay ruta asignada para la(s) orden(es): '
                            . $ordenes->whereIn('id', $yaEnRuta)->pluck('numero_orden')->implode(', '),
            ], 422);
        }

        $envios = [];
        foreach ($ordenes as $orden) {
            $items = $orden->items
                // La cantidad que viaja es la realmente recolectada, no la pedida.
                ->map(fn ($i) => [
                    'inventario_id' => $i->inventario_id,
                    'cantidad'      => (float) ($i->cantidad_descontada > 0 ? $i->cantidad_descontada : $i->cantidad),
                ])
                ->filter(fn ($i) => $i['cantidad'] > 0)
                ->values()->all();

            if (empty($items)) {
                continue;
            }

            // El TCD sólo guarda el código de la sucursal destino (el nombre vive en
            // central). Si no hay destino, se rotula con el número de orden para que
            // el conductor pueda identificar la parada en el manifiesto.
            $envios[] = [
                'tcd_orden_id'   => $orden->id,
                'destino_nombre' => $orden->sucursal_destino_codigo
                    ? 'Sucursal ' . $orden->sucursal_destino_codigo
                    : $orden->numero_orden,
                'direccion'      => null, // se completa al editar la parada
                'items'          => $items,
            ];
        }

        if (empty($envios)) {
            return Response::json(['estado' => false, 'msj' => 'Las órdenes no tienen items con cantidad'], 422);
        }

        $servicio = new PlanificacionCargaService();

        // Agregar a una ruta existente: no se replanifica, se anexan paradas.
        if ($request->filled('ruta_id')) {
            return $this->anexarARuta((int) $request->ruta_id, $envios, $servicio);
        }

        $plan = $servicio->planificar($envios, [
            'fecha'        => $request->fecha,
            'vehiculo_ids' => $request->vehiculo_ids,
        ]);

        if (empty($plan['rutas'])) {
            return Response::json([
                'estado'      => false,
                'msj'         => $plan['msj'] ?? 'No hay vehículo con capacidad para esta carga',
                'sin_asignar' => $plan['sin_asignar'] ?? [],
            ], 422);
        }

        $rutas = $servicio->crearRutas($plan['rutas'], [
            'fecha'         => $request->fecha,
            'conductor_id'  => $request->conductor_id,
            'usuario_id'    => session('id_usuario'),
            'observaciones' => 'Generada desde TCD: ' . $ordenes->pluck('numero_orden')->implode(', '),
        ]);

        return Response::json([
            'estado'      => true,
            'rutas'       => $rutas,
            'sin_asignar' => $plan['sin_asignar'],
            'aviso'       => $plan['aviso'],
            'msj'         => count($rutas) . ' ruta(s) creada(s) desde ' . count($envios) . ' orden(es) TCD',
        ]);
    }

    /**
     * Anexa envíos a una ruta ya planificada, verificando que quepan.
     */
    private function anexarARuta(int $rutaId, array $envios, PlanificacionCargaService $servicio)
    {
        $ruta = TmsRuta::with('vehiculo', 'paradas')->findOrFail($rutaId);

        if (!in_array($ruta->estado, ['planificada', 'cargando'], true)) {
            return Response::json([
                'estado' => false,
                'msj'    => 'Sólo se puede agregar carga a una ruta en planificación o cargando',
            ], 422);
        }

        $vehiculo = $ruta->vehiculo;
        $factor = (float) config('wms.tms.factor_seguridad_carga');

        $pesoExtra = 0.0;
        $volumenExtra = 0.0;
        foreach ($envios as $e) {
            $c = $servicio->cubicar($e['items']);
            $pesoExtra    += $c['peso_kg'];
            $volumenExtra += $c['volumen_m3'];
        }

        if ($vehiculo) {
            $pesoFinal    = (float) $ruta->peso_total_kg + $pesoExtra;
            $volumenFinal = (float) $ruta->volumen_total_m3 + $volumenExtra;

            if ($pesoFinal > (float) $vehiculo->capacidad_peso_kg * $factor) {
                return Response::json(['estado' => false, 'msj' => 'La carga adicional supera la capacidad de peso del vehículo'], 422);
            }
            if ($volumenFinal > (float) $vehiculo->capacidad_volumen_m3 * $factor) {
                return Response::json(['estado' => false, 'msj' => 'La carga adicional supera la capacidad de volumen del vehículo'], 422);
            }
        }

        $orden = (int) $ruta->paradas->max('orden');

        DB::transaction(function () use ($ruta, $envios, $servicio, &$orden) {
            foreach ($envios as $envio) {
                $orden++;
                $cubicaje = $servicio->cubicar($envio['items']);

                $parada = TmsParada::create([
                    'ruta_id'        => $ruta->id,
                    'orden'          => $orden,
                    'tipo'           => 'entrega',
                    'tcd_orden_id'   => $envio['tcd_orden_id'] ?? null,
                    'destino_nombre' => $envio['destino_nombre'] ?? null,
                    'direccion'      => $envio['direccion'] ?? null,
                    'peso_kg'        => $cubicaje['peso_kg'],
                    'volumen_m3'     => $cubicaje['volumen_m3'],
                    'bultos'         => $cubicaje['bultos'],
                    'estado'         => 'pendiente',
                ]);

                $ids = array_column($envio['items'], 'inventario_id');
                $productos = \App\Models\inventario::whereIn('id', $ids)
                    ->get(['id', 'descripcion', 'peso_kg', 'volumen_m3'])->keyBy('id');

                foreach ($envio['items'] as $item) {
                    $p = $productos[$item['inventario_id']] ?? null;
                    $cantidad = (float) $item['cantidad'];

                    TmsParadaItem::create([
                        'parada_id'     => $parada->id,
                        'inventario_id' => $item['inventario_id'],
                        'descripcion'   => $p->descripcion ?? null,
                        'cantidad'      => $cantidad,
                        'peso_kg'       => $p && $p->peso_kg !== null ? round((float) $p->peso_kg * $cantidad, 4) : 0,
                        'volumen_m3'    => $p && $p->volumen_m3 !== null ? round((float) $p->volumen_m3 * $cantidad, 6) : 0,
                    ]);
                }
            }
        });

        $ruta->recalcularTotales();
        $servicio->estimarTiempos($ruta->fresh());

        return Response::json([
            'estado' => true,
            'ruta'   => $ruta->fresh('paradas'),
            'msj'    => count($envios) . ' parada(s) agregada(s) a la ruta ' . $ruta->codigo,
        ]);
    }

    /**
     * Órdenes TCD listas para transporte y aún sin ruta.
     * Es la bandeja de entrada del planificador.
     */
    public function tcdPendientesDeRuta(Request $request)
    {
        $conRuta = TmsParada::whereNotNull('tcd_orden_id')
            ->whereHas('ruta', fn ($q) => $q->where('estado', '!=', 'cancelada'))
            ->pluck('tcd_orden_id');

        $ordenes = \App\Models\TCDOrden::with('items')
            ->whereIn('estado', ['completada', 'despachada'])
            ->whereNotIn('id', $conRuta)
            ->orderByDesc('id')
            ->limit((int) ($request->limite ?? 50))
            ->get();

        $servicio = new PlanificacionCargaService();

        return Response::json([
            'estado'  => true,
            'ordenes' => $ordenes->map(function ($o) use ($servicio) {
                $items = $o->items->map(fn ($i) => [
                    'inventario_id' => $i->inventario_id,
                    'cantidad'      => (float) ($i->cantidad_descontada > 0 ? $i->cantidad_descontada : $i->cantidad),
                ])->filter(fn ($i) => $i['cantidad'] > 0)->values()->all();

                $c = $servicio->cubicar($items);

                return [
                    'id'           => $o->id,
                    'numero_orden' => $o->numero_orden,
                    'estado'       => $o->estado,
                    'fecha'        => optional($o->created_at)->toDateString(),
                    'items'        => count($items),
                    'peso_kg'      => $c['peso_kg'],
                    'volumen_m3'   => $c['volumen_m3'],
                    'bultos'       => $c['bultos'],
                    'sin_ficha'    => count($c['sin_datos']),
                ];
            }),
        ]);
    }

    // ------------------------------------------------------------------ Rutas

    public function rutas(Request $request)
    {
        $rutas = TmsRuta::with(['vehiculo', 'conductor'])
            ->withCount('paradas')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->fecha))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit((int) ($request->limite ?? 50))
            ->get();

        return Response::json(['estado' => true, 'rutas' => $rutas]);
    }

    public function ruta($id)
    {
        $ruta = TmsRuta::with(['vehiculo', 'conductor', 'paradas.items'])->findOrFail($id);

        return Response::json([
            'estado'    => true,
            'ruta'      => $ruta,
            'limitante' => $ruta->limitante(),
        ]);
    }

    /**
     * Reordena las paradas por vecino más cercano.
     */
    public function optimizarRuta($id)
    {
        $ruta = TmsRuta::findOrFail($id);

        if ($ruta->estado !== 'planificada') {
            return Response::json(['estado' => false, 'msj' => 'Sólo se puede reordenar una ruta en estado planificada'], 422);
        }

        $resultado = (new PlanificacionCargaService())->optimizarOrden($ruta);

        return Response::json($resultado, $resultado['estado'] ? 200 : 422);
    }

    /**
     * Cambia el estado de la ruta y arrastra el del vehículo y el conductor.
     */
    public function cambiarEstadoRuta(Request $request, $id)
    {
        $request->validate(['estado' => 'required|in:planificada,cargando,en_ruta,completada,cancelada']);

        $ruta = TmsRuta::with('vehiculo', 'conductor')->findOrFail($id);
        $nuevo = $request->estado;

        // No se despacha con licencia vencida.
        if ($nuevo === 'en_ruta' && $ruta->conductor && !$ruta->conductor->licenciaVigente()) {
            return Response::json([
                'estado' => false,
                'msj'    => 'El conductor tiene la licencia vencida (' . $ruta->conductor->licencia_vence->toDateString() . ')',
            ], 422);
        }

        DB::transaction(function () use ($ruta, $nuevo) {
            $ruta->estado = $nuevo;

            if ($nuevo === 'en_ruta' && !$ruta->salida_real) {
                $ruta->salida_real = now();
            }
            if (in_array($nuevo, ['completada', 'cancelada'], true) && !$ruta->retorno_real) {
                $ruta->retorno_real = now();
            }
            $ruta->save();

            $estadoRecurso = in_array($nuevo, ['cargando', 'en_ruta'], true) ? 'en_ruta' : 'disponible';

            if ($ruta->vehiculo && $ruta->vehiculo->estado !== 'mantenimiento') {
                $ruta->vehiculo->update(['estado' => $estadoRecurso]);
            }
            if ($ruta->conductor && $ruta->conductor->estado !== 'inactivo') {
                $ruta->conductor->update(['estado' => $estadoRecurso]);
            }
        });

        return Response::json(['estado' => true, 'ruta' => $ruta->fresh(), 'msj' => 'Estado actualizado']);
    }

    // ---------------------------------------------------- Entrega (POD)

    /**
     * Registra la entrega de una parada, con prueba de entrega.
     */
    public function registrarEntrega(Request $request, $paradaId)
    {
        $request->validate([
            'recibido_por'          => 'required|string|max:191',
            'documento'             => 'nullable|string|max:40',
            'items'                 => 'nullable|array',
            'items.*.id'            => 'required_with:items|integer',
            'items.*.cantidad_entregada' => 'required_with:items|numeric|min:0',
            'observaciones'         => 'nullable|string',
        ]);

        $parada = TmsParada::with('items')->findOrFail($paradaId);

        if (in_array($parada->estado, ['entregada', 'fallida'], true)) {
            return Response::json(['estado' => false, 'msj' => 'Esta parada ya fue cerrada'], 422);
        }

        DB::transaction(function () use ($request, $parada) {
            if ($request->filled('items')) {
                foreach ($request->items as $item) {
                    TmsParadaItem::where('id', $item['id'])
                        ->where('parada_id', $parada->id)
                        ->update(['cantidad_entregada' => $item['cantidad_entregada']]);
                }
            } else {
                // Sin detalle, se asume entrega completa.
                TmsParadaItem::where('parada_id', $parada->id)
                    ->update(['cantidad_entregada' => DB::raw('cantidad')]);
            }

            $parada->refresh()->load('items');

            $parada->estado           = $parada->esParcial() ? 'parcial' : 'entregada';
            $parada->pod_recibido_por = $request->recibido_por;
            $parada->pod_documento    = $request->documento;
            $parada->pod_at           = now();
            $parada->llegada_real     = $parada->llegada_real ?: now();
            $parada->salida_real      = now();
            $parada->observaciones    = $request->observaciones;
            $parada->save();

            $this->cerrarRutaSiTerminada($parada->ruta_id);
        });

        return Response::json([
            'estado' => true,
            'parada' => $parada->fresh('items'),
            'msj'    => 'Entrega registrada',
        ]);
    }

    /**
     * Marca una parada como fallida (nadie en el sitio, dirección errada, rechazo).
     */
    public function registrarFallo(Request $request, $paradaId)
    {
        $request->validate([
            'motivo' => 'required|string|max:255',
            'reprogramar' => 'nullable|boolean',
        ]);

        $parada = TmsParada::findOrFail($paradaId);

        $parada->estado       = $request->boolean('reprogramar') ? 'reprogramada' : 'fallida';
        $parada->motivo_fallo = $request->motivo;
        $parada->llegada_real = $parada->llegada_real ?: now();
        $parada->salida_real  = now();
        $parada->save();

        $this->cerrarRutaSiTerminada($parada->ruta_id);

        return Response::json(['estado' => true, 'parada' => $parada, 'msj' => 'Novedad registrada']);
    }

    /**
     * Manifiesto de carga: lo que va en el vehículo, para el conductor.
     */
    public function manifiesto($rutaId)
    {
        $ruta = TmsRuta::with(['vehiculo', 'conductor', 'paradas.items'])->findOrFail($rutaId);

        return view('tms.manifiesto', compact('ruta'));
    }

    /**
     * Indicadores de la operación de transporte.
     */
    public function indicadores(Request $request)
    {
        $dias  = (int) ($request->dias ?? 30);
        $desde = now()->subDays($dias);

        $rutas = TmsRuta::where('fecha', '>=', $desde->toDateString())->get();
        $paradas = TmsParada::whereIn('ruta_id', $rutas->pluck('id'))->get();

        $entregadas = $paradas->whereIn('estado', ['entregada', 'parcial'])->count();
        $cerradas   = $paradas->whereIn('estado', ['entregada', 'parcial', 'fallida'])->count();

        return Response::json([
            'estado' => true,
            'dias'   => $dias,
            'rutas'  => [
                'total'      => $rutas->count(),
                'completadas' => $rutas->where('estado', 'completada')->count(),
            ],
            'paradas' => [
                'total'      => $paradas->count(),
                'entregadas' => $entregadas,
                'fallidas'   => $paradas->where('estado', 'fallida')->count(),
                // Entregas cumplidas sobre entregas intentadas.
                'efectividad_pct' => $cerradas > 0 ? round(($entregadas / $cerradas) * 100, 2) : null,
            ],
            'utilizacion' => [
                'peso_promedio_pct'    => round((float) $rutas->avg('utilizacion_peso_pct'), 2),
                'volumen_promedio_pct' => round((float) $rutas->avg('utilizacion_volumen_pct'), 2),
                'nota' => 'Una utilización baja en ambos indica que se están despachando camiones a medio llenar.',
            ],
            'distancia_km' => round((float) $rutas->sum('distancia_estimada_km'), 2),
            'costo'        => round((float) $rutas->sum('costo_estimado'), 2),
        ]);
    }

    /**
     * Cierra la ruta cuando ya no queda ninguna parada pendiente.
     */
    private function cerrarRutaSiTerminada($rutaId): void
    {
        $pendientes = TmsParada::where('ruta_id', $rutaId)
            ->whereIn('estado', ['pendiente', 'en_sitio'])
            ->count();

        if ($pendientes > 0) {
            return;
        }

        $ruta = TmsRuta::with('vehiculo', 'conductor')->find($rutaId);
        if (!$ruta || in_array($ruta->estado, ['completada', 'cancelada'], true)) {
            return;
        }

        $ruta->estado = 'completada';
        $ruta->retorno_real = $ruta->retorno_real ?: now();
        $ruta->save();

        if ($ruta->vehiculo && $ruta->vehiculo->estado === 'en_ruta') {
            $ruta->vehiculo->update(['estado' => 'disponible']);
        }
        if ($ruta->conductor && $ruta->conductor->estado === 'en_ruta') {
            $ruta->conductor->update(['estado' => 'disponible']);
        }
    }
}
