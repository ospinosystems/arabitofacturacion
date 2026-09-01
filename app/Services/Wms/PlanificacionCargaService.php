<?php

namespace App\Services\Wms;

use App\Models\TmsParada;
use App\Models\TmsParadaItem;
use App\Models\TmsRuta;
use App\Models\TmsVehiculo;
use App\Models\inventario;
use Illuminate\Support\Facades\DB;

/**
 * Planificación de carga y rutas (TMS).
 *
 * Usa los mismos datos físicos que el slotting. La diferencia es el contenedor:
 * allí el hueco de un rack, aquí la caja de un camión.
 *
 * El reparto es un bin packing con dos restricciones simultáneas (peso y volumen).
 * Se resuelve con heurística First Fit Decreasing: se ordenan los envíos de mayor
 * a menor y cada uno se mete en el primer vehículo donde quepa. No da el óptimo
 * matemático, pero para flotas de decenas de vehículos queda a un pequeño margen
 * del óptimo y es instantáneo, que es lo que hace falta a las 5 de la mañana.
 *
 * El límite real casi nunca es el mismo: la mercancía densa satura el peso mucho
 * antes que el volumen, y la voluminosa al revés. Por eso se controlan las dos.
 */
class PlanificacionCargaService
{
    private float $factorSeguridad;
    private int $minutosPorParada;
    private float $velocidadKmh;

    public function __construct()
    {
        $this->factorSeguridad  = (float) config('wms.tms.factor_seguridad_carga');
        $this->minutosPorParada = (int) config('wms.tms.minutos_por_parada');
        $this->velocidadKmh     = (float) config('wms.tms.velocidad_media_kmh');
    }

    /**
     * Calcula peso y volumen de una lista de items.
     *
     * @param  array $items  [['inventario_id' => int, 'cantidad' => float], ...]
     * @return array ['peso_kg','volumen_m3','bultos','sin_datos' => [ids]]
     */
    public function cubicar(array $items): array
    {
        $ids = array_values(array_unique(array_column($items, 'inventario_id')));
        if (empty($ids)) {
            return ['peso_kg' => 0.0, 'volumen_m3' => 0.0, 'bultos' => 0, 'sin_datos' => []];
        }

        $productos = inventario::whereIn('id', $ids)
            ->get(['id', 'peso_kg', 'volumen_m3', 'unidades_por_bulto', 'datos_fisicos_fuente'])
            ->keyBy('id');

        $peso = 0.0;
        $volumen = 0.0;
        $bultos = 0;
        $sinDatos = [];
        $estimados = false;

        foreach ($items as $item) {
            $p = $productos[$item['inventario_id']] ?? null;
            $cantidad = (float) $item['cantidad'];

            if (!$p || $p->peso_kg === null || $p->volumen_m3 === null) {
                $sinDatos[] = $item['inventario_id'];
                continue;
            }

            $peso    += (float) $p->peso_kg * $cantidad;
            $volumen += (float) $p->volumen_m3 * $cantidad;

            $upb = (int) ($p->unidades_por_bulto ?: 1);
            $bultos += (int) ceil($cantidad / max(1, $upb));

            if ($p->datos_fisicos_fuente === 'estimado') {
                $estimados = true;
            }
        }

        return [
            'peso_kg'    => round($peso, 4),
            'volumen_m3' => round($volumen, 6),
            'bultos'     => $bultos,
            'sin_datos'  => array_values(array_unique($sinDatos)),
            'estimado'   => $estimados,
        ];
    }

    /**
     * Reparte envíos entre los vehículos disponibles.
     *
     * @param array $envios   [['referencia','destino_nombre','direccion','cliente_id',
     *                          'tcd_orden_id','items' => [...]], ...]
     * @param array $opts     ['fecha','vehiculo_ids']
     */
    public function planificar(array $envios, array $opts = []): array
    {
        $fecha = $opts['fecha'] ?? now()->toDateString();

        $vehiculos = TmsVehiculo::disponibles()
            ->when(!empty($opts['vehiculo_ids']), fn ($q) => $q->whereIn('id', (array) $opts['vehiculo_ids']))
            ->orderByDesc('capacidad_volumen_m3')
            ->get();

        if ($vehiculos->isEmpty()) {
            return ['estado' => false, 'msj' => 'No hay vehículos disponibles', 'rutas' => [], 'sin_asignar' => $envios];
        }

        // Cubicar cada envío antes de repartir.
        $preparados = [];
        $avisosSinDatos = [];

        foreach ($envios as $i => $envio) {
            $cubicaje = $this->cubicar($envio['items'] ?? []);

            if (!empty($cubicaje['sin_datos'])) {
                $avisosSinDatos = array_merge($avisosSinDatos, $cubicaje['sin_datos']);
            }

            $preparados[] = array_merge($envio, [
                'indice'     => $i,
                'peso_kg'    => $cubicaje['peso_kg'],
                'volumen_m3' => $cubicaje['volumen_m3'],
                'bultos'     => $cubicaje['bultos'],
                'estimado'   => $cubicaje['estimado'] ?? false,
            ]);
        }

        // First Fit Decreasing: primero los envíos grandes. Si se empieza por los
        // pequeños, los grandes se quedan sin sitio y hacen falta más viajes.
        usort($preparados, fn ($a, $b) => $b['volumen_m3'] <=> $a['volumen_m3']);

        // Capacidad utilizable de cada vehículo, con el margen de seguridad aplicado.
        $capacidades = [];
        foreach ($vehiculos as $v) {
            $capacidades[$v->id] = [
                'vehiculo'   => $v,
                'peso_kg'    => (float) $v->capacidad_peso_kg * $this->factorSeguridad,
                'volumen_m3' => (float) $v->capacidad_volumen_m3 * $this->factorSeguridad,
                'envios'     => [],
            ];
        }

        $sinAsignar = [];

        foreach ($preparados as $envio) {
            $asignado = false;

            foreach ($capacidades as $vid => &$cap) {
                if ($envio['peso_kg'] <= $cap['peso_kg'] && $envio['volumen_m3'] <= $cap['volumen_m3']) {
                    $cap['peso_kg']    -= $envio['peso_kg'];
                    $cap['volumen_m3'] -= $envio['volumen_m3'];
                    $cap['envios'][]   = $envio;
                    $asignado = true;
                    break;
                }
            }
            unset($cap);

            if (!$asignado) {
                $sinAsignar[] = $envio;
            }
        }

        // Ajuste al vehículo más pequeño que sirva.
        //
        // El empaquetado llena primero los vehículos grandes, que es lo correcto para
        // minimizar viajes, pero deja situaciones absurdas: 500 kg en un camión de
        // 3.500 cuando había una camioneta libre. Despachar el vehículo más pequeño
        // que aguante la carga cuesta menos por kilómetro y libera el grande.
        $this->reducirVehiculos($capacidades, $vehiculos);

        $plan = [];
        foreach ($capacidades as $cap) {
            if (empty($cap['envios'])) {
                continue;
            }

            $v = $cap['vehiculo'];
            $pesoTotal = array_sum(array_column($cap['envios'], 'peso_kg'));
            $volTotal  = array_sum(array_column($cap['envios'], 'volumen_m3'));

            $plan[] = [
                'vehiculo_id'   => $v->id,
                'placa'         => $v->placa,
                'tipo'          => $v->tipo,
                'paradas'       => count($cap['envios']),
                'peso_kg'       => round($pesoTotal, 2),
                'volumen_m3'    => round($volTotal, 4),
                'bultos'        => array_sum(array_column($cap['envios'], 'bultos')),
                'utilizacion_peso_pct'    => (float) $v->capacidad_peso_kg > 0
                    ? round(($pesoTotal / (float) $v->capacidad_peso_kg) * 100, 2) : null,
                'utilizacion_volumen_pct' => (float) $v->capacidad_volumen_m3 > 0
                    ? round(($volTotal / (float) $v->capacidad_volumen_m3) * 100, 2) : null,
                'limitante'     => $this->limitante($v, $pesoTotal, $volTotal),
                'envios'        => $cap['envios'],
            ];
        }

        return [
            'estado'      => !empty($plan),
            'fecha'       => $fecha,
            'rutas'       => $plan,
            'sin_asignar' => $sinAsignar,
            'aviso'       => !empty($avisosSinDatos)
                ? 'Hay ' . count(array_unique($avisosSinDatos)) . ' producto(s) sin peso/volumen: la carga calculada está subestimada.'
                : null,
        ];
    }

    /**
     * Cambia cada carga al vehículo más pequeño (y por tanto más barato) donde quepa.
     *
     * Recorre las cargas de mayor a menor para que las grandes reserven primero los
     * vehículos que necesitan, y sólo reasigna a un vehículo libre: nunca desplaza
     * una carga ya colocada.
     *
     * @param array $capacidades  referencia al mapa vehiculo_id => estado de carga
     */
    private function reducirVehiculos(array &$capacidades, $vehiculos): void
    {
        // Candidatos de menor a mayor capacidad de volumen.
        $porTamano = $vehiculos->sortBy(fn ($v) => (float) $v->capacidad_volumen_m3)->values();

        $ocupados = [];
        foreach ($capacidades as $vid => $cap) {
            if (!empty($cap['envios'])) {
                $ocupados[$vid] = true;
            }
        }

        $cargas = [];
        foreach ($capacidades as $vid => $cap) {
            if (empty($cap['envios'])) {
                continue;
            }
            $cargas[$vid] = [
                'peso'    => array_sum(array_column($cap['envios'], 'peso_kg')),
                'volumen' => array_sum(array_column($cap['envios'], 'volumen_m3')),
            ];
        }

        arsort($cargas); // las cargas mayores eligen primero

        foreach ($cargas as $vidActual => $carga) {
            foreach ($porTamano as $candidato) {
                if ((int) $candidato->id === (int) $vidActual) {
                    break; // ya se llegó al vehículo actual: no hay nada más pequeño que sirva
                }

                if (!empty($ocupados[$candidato->id])) {
                    continue; // ese vehículo ya lleva carga
                }

                $capPeso = (float) $candidato->capacidad_peso_kg * $this->factorSeguridad;
                $capVol  = (float) $candidato->capacidad_volumen_m3 * $this->factorSeguridad;

                if ($carga['peso'] <= $capPeso && $carga['volumen'] <= $capVol) {
                    // Traspaso completo de la carga al vehículo menor.
                    $capacidades[$candidato->id]['envios'] = $capacidades[$vidActual]['envios'];
                    $capacidades[$vidActual]['envios'] = [];

                    unset($ocupados[$vidActual]);
                    $ocupados[$candidato->id] = true;
                    break;
                }
            }
        }
    }

    /**
     * Qué recurso se agota primero en este vehículo con esta carga.
     * Saberlo dice si conviene un camión más grande o uno con más tara útil.
     */
    private function limitante(TmsVehiculo $v, float $peso, float $volumen): ?string
    {
        $pctPeso = (float) $v->capacidad_peso_kg > 0 ? $peso / (float) $v->capacidad_peso_kg : 0;
        $pctVol  = (float) $v->capacidad_volumen_m3 > 0 ? $volumen / (float) $v->capacidad_volumen_m3 : 0;

        if ($pctPeso < 0.5 && $pctVol < 0.5) {
            return null; // holgado
        }

        return $pctPeso >= $pctVol ? 'peso' : 'volumen';
    }

    /**
     * Materializa un plan en rutas y paradas persistidas.
     */
    public function crearRutas(array $plan, array $opts = []): array
    {
        $creadas = [];

        DB::transaction(function () use ($plan, $opts, &$creadas) {
            foreach ($plan as $r) {
                $ruta = TmsRuta::create([
                    'codigo'                  => $this->generarCodigoRuta(),
                    'fecha'                   => $opts['fecha'] ?? now()->toDateString(),
                    'estado'                  => 'planificada',
                    'vehiculo_id'             => $r['vehiculo_id'],
                    'conductor_id'            => $opts['conductor_id'] ?? optional(TmsVehiculo::find($r['vehiculo_id']))->conductor_habitual_id,
                    'usuario_planificador_id' => $opts['usuario_id'] ?? null,
                    'observaciones'           => $opts['observaciones'] ?? null,
                ]);

                foreach (array_values($r['envios']) as $orden => $envio) {
                    $parada = TmsParada::create([
                        'ruta_id'        => $ruta->id,
                        'orden'          => $orden + 1,
                        'tipo'           => $envio['tipo'] ?? 'entrega',
                        'tcd_orden_id'   => $envio['tcd_orden_id'] ?? null,
                        'pedido_id'      => $envio['pedido_id'] ?? null,
                        'cliente_id'     => $envio['cliente_id'] ?? null,
                        'destino_nombre' => $envio['destino_nombre'] ?? null,
                        'direccion'      => $envio['direccion'] ?? null,
                        'latitud'        => $envio['latitud'] ?? null,
                        'longitud'       => $envio['longitud'] ?? null,
                        'peso_kg'        => $envio['peso_kg'],
                        'volumen_m3'     => $envio['volumen_m3'],
                        'bultos'         => $envio['bultos'],
                        'estado'         => 'pendiente',
                    ]);

                    $this->crearItems($parada, $envio['items'] ?? []);
                }

                $ruta->recalcularTotales();
                $this->estimarTiempos($ruta);

                $creadas[] = $ruta->fresh('paradas');
            }
        });

        return $creadas;
    }

    private function crearItems(TmsParada $parada, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $ids = array_column($items, 'inventario_id');
        $productos = inventario::whereIn('id', $ids)
            ->get(['id', 'descripcion', 'peso_kg', 'volumen_m3'])->keyBy('id');

        foreach ($items as $item) {
            $p = $productos[$item['inventario_id']] ?? null;
            $cantidad = (float) $item['cantidad'];

            TmsParadaItem::create([
                'parada_id'     => $parada->id,
                'inventario_id' => $item['inventario_id'],
                'descripcion'   => $p->descripcion ?? null,
                'cantidad'      => $cantidad,
                // Congelados: el manifiesto no debe cambiar si mañana se corrige la ficha.
                'peso_kg'       => $p && $p->peso_kg !== null ? round((float) $p->peso_kg * $cantidad, 4) : 0,
                'volumen_m3'    => $p && $p->volumen_m3 !== null ? round((float) $p->volumen_m3 * $cantidad, 6) : 0,
            ]);
        }
    }

    /**
     * Estimación gruesa de distancia y tiempo.
     *
     * Con coordenadas usa distancia haversine entre paradas consecutivas y le aplica
     * un factor de sinuosidad (las calles no son líneas rectas). Sin coordenadas
     * sólo estima el tiempo de servicio. No sustituye a un motor de ruteo real.
     */
    public function estimarTiempos(TmsRuta $ruta): void
    {
        $paradas = $ruta->paradas()->get();

        $distancia = 0.0;
        $anterior = null;

        foreach ($paradas as $p) {
            if ($anterior && $p->latitud && $p->longitud && $anterior->latitud && $anterior->longitud) {
                $distancia += $this->haversine(
                    (float) $anterior->latitud, (float) $anterior->longitud,
                    (float) $p->latitud, (float) $p->longitud
                );
            }
            $anterior = $p;
        }

        // Factor de sinuosidad: recorrido real sobre distancia en línea recta.
        $distancia *= 1.35;

        $minutosViaje   = $this->velocidadKmh > 0 ? ($distancia / $this->velocidadKmh) * 60 : 0;
        $minutosServicio = $paradas->count() * $this->minutosPorParada;

        $ruta->distancia_estimada_km = $distancia > 0 ? round($distancia, 2) : null;
        $ruta->tiempo_estimado_min   = (int) round($minutosViaje + $minutosServicio);

        $vehiculo = $ruta->vehiculo;
        if ($vehiculo && $distancia > 0) {
            $ruta->costo_estimado = round(
                ((float) ($vehiculo->costo_km ?? 0) * $distancia) + (float) ($vehiculo->costo_fijo_viaje ?? 0),
                4
            );
        }

        $ruta->save();
    }

    /** Distancia en km entre dos coordenadas. */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Reordena las paradas de una ruta por vecino más cercano.
     *
     * Heurística simple para el problema del viajante: desde el depósito, ir
     * siempre a la parada más cercana no visitada. No es óptimo, pero recorta
     * bastante frente al orden en que llegaron los pedidos.
     */
    public function optimizarOrden(TmsRuta $ruta): array
    {
        $paradas = $ruta->paradas()->get()
            ->filter(fn ($p) => $p->latitud !== null && $p->longitud !== null)
            ->values();

        if ($paradas->count() < 3) {
            return ['estado' => false, 'msj' => 'Se necesitan al menos 3 paradas con coordenadas'];
        }

        $distanciaOriginal = $this->distanciaTotal($paradas->all());

        $pendientes = $paradas->all();
        $ordenadas = [];
        // Se parte del depósito, aproximado por la primera parada cargada.
        $actual = array_shift($pendientes);
        $ordenadas[] = $actual;

        while (!empty($pendientes)) {
            $mejorIdx = 0;
            $mejorDist = PHP_FLOAT_MAX;

            foreach ($pendientes as $idx => $p) {
                $d = $this->haversine(
                    (float) $actual->latitud, (float) $actual->longitud,
                    (float) $p->latitud, (float) $p->longitud
                );
                if ($d < $mejorDist) {
                    $mejorDist = $d;
                    $mejorIdx = $idx;
                }
            }

            $actual = $pendientes[$mejorIdx];
            $ordenadas[] = $actual;
            array_splice($pendientes, $mejorIdx, 1);
        }

        DB::transaction(function () use ($ordenadas) {
            foreach ($ordenadas as $i => $p) {
                $p->orden = $i + 1;
                $p->save();
            }
        });

        $this->estimarTiempos($ruta->fresh());
        $distanciaNueva = $this->distanciaTotal($ordenadas);

        return [
            'estado'             => true,
            'distancia_original' => round($distanciaOriginal * 1.35, 2),
            'distancia_nueva'    => round($distanciaNueva * 1.35, 2),
            'ahorro_km'          => round(($distanciaOriginal - $distanciaNueva) * 1.35, 2),
            'orden'              => array_map(fn ($p) => $p->destino_nombre ?? $p->id, $ordenadas),
        ];
    }

    private function distanciaTotal(array $paradas): float
    {
        $total = 0.0;
        for ($i = 1; $i < count($paradas); $i++) {
            $total += $this->haversine(
                (float) $paradas[$i - 1]->latitud, (float) $paradas[$i - 1]->longitud,
                (float) $paradas[$i]->latitud, (float) $paradas[$i]->longitud
            );
        }

        return $total;
    }

    private function generarCodigoRuta(): string
    {
        $ultimo = TmsRuta::orderByDesc('id')->value('id') ?? 0;

        return 'RT-' . now()->format('Ymd') . '-' . str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }
}
