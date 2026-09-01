<?php

namespace App\Services\Wms;

use App\Models\inventario;
use App\Models\ProductoAbc;
use App\Models\PutawaySugerencia;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Motor de sugerencia de ubicación (slotting / putaway).
 *
 * Responde a "¿dónde debe ir este producto?" con un ranking explicado, no con una
 * caja negra. Funciona en dos pasos:
 *
 *   1. FILTROS DUROS: descartan lo imposible. Un producto refrigerado no puede ir
 *      a una ubicación seca por más que el resto encaje. Aquí no hay puntaje: se
 *      pasa o no se pasa, y siempre queda registrado el motivo del descarte.
 *
 *   2. SCORE PONDERADO: entre las que sí sirven, se puntúa cada factor de 0 a 1 y
 *      se pondera según config('wms.slotting.pesos'). Los factores son los que un
 *      jefe de almacén usaría a ojo:
 *
 *        consolidación   ¿ya hay stock de ese producto ahí? Fragmentar un producto
 *                        en cinco ubicaciones multiplica los recorridos.
 *        afinidad ABC    ¿la clase de rotación del producto coincide con la de la
 *                        ubicación? Es el corazón del slotting por rotación.
 *        cercanía        Distancia al muelle, interpretada según la clase: para un
 *                        producto A cerca es bueno; para un C, ocupar una ubicación
 *                        cercana es DESPERDICIARLA, y el score lo penaliza.
 *        ergonomía       Altura de la ubicación contra frecuencia de picking.
 *        ajuste cubicaje Premia el hueco donde la mercancía queda justa, dejando algo
 *                        de holgura. Evita gastar una ubicación grande en algo pequeño.
 *        afinidad familia Productos de la misma categoría cerca entre sí.
 *
 * Deliberadamente NO es machine learning. Con cero historial, un modelo aprendería
 * ruido. Lo que sí hace es registrar cada sugerencia y cada corrección del operario
 * (ver registrarDecision), que es el dataset con el que después se pueden reajustar
 * estos pesos contra lo que la gente realmente hace.
 */
class SlottingService
{
    private array $pesos;
    private int $topN;
    private float $holguraMinima;
    private float $scoreMinimo;
    private int $limiteCandidatas;
    private string $criterioAbc;

    public function __construct(array $config = [])
    {
        $cfg = array_merge(config('wms.slotting'), $config);

        $this->pesos         = $cfg['pesos'];
        $this->topN          = (int) $cfg['top_n'];
        $this->holguraMinima = (float) $cfg['holgura_minima_pct'];
        $this->scoreMinimo   = (float) $cfg['score_minimo'];
        $this->limiteCandidatas = (int) ($cfg['limite_candidatas'] ?? 400);
        $this->criterioAbc   = config('wms.abc.criterio_slotting', 'combinado');
    }

    /**
     * Sugiere las mejores ubicaciones para guardar una cantidad de un producto.
     *
     * @param  inventario|int $producto
     * @param  float          $cantidad
     * @param  array          $opts  ['zona' => 'A', 'excluir' => [ids], 'top_n' => 5]
     */
    public function sugerir($producto, float $cantidad, array $opts = []): array
    {
        $producto = $producto instanceof inventario ? $producto : inventario::findOrFail($producto);
        $topN     = (int) ($opts['top_n'] ?? $this->topN);

        $claseProducto = $this->claseAbcDe($producto->id);

        $candidatas = $this->candidatasBase($producto, $opts);
        if ($candidatas->isEmpty()) {
            return $this->respuestaVacia($producto, $claseProducto, $cantidad, 'No hay ubicaciones activas que admitan este producto');
        }

        $ocupacion  = $this->ocupacionPorUbicacion($producto, $candidatas->pluck('id')->all());
        $distancias = $this->rangoDistancias($candidatas);

        $evaluadas  = [];
        $descartadas = [];

        foreach ($candidatas as $ubicacion) {
            $ocup = $ocupacion[$ubicacion->id] ?? $this->ocupacionVacia();

            $veto = $this->filtrosDuros($ubicacion, $producto, $cantidad, $ocup);
            if ($veto !== null) {
                $descartadas[] = ['codigo' => $ubicacion->codigo, 'motivo' => $veto];
                continue;
            }

            $evaluadas[] = $this->puntuar($ubicacion, $producto, $cantidad, $claseProducto, $ocup, $distancias);
        }

        if (empty($evaluadas)) {
            return $this->respuestaVacia(
                $producto, $claseProducto, $cantidad,
                'Ninguna ubicación pasó los filtros de compatibilidad y capacidad',
                $descartadas
            );
        }

        usort($evaluadas, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($evaluadas, 0, $topN);

        return [
            'estado'            => true,
            'producto'          => $this->resumenProducto($producto),
            'cantidad'          => $cantidad,
            'clase_abc'         => $claseProducto,
            'criterio_abc'      => $this->criterioAbc,
            'datos_estimados'   => $producto->datosFisicosEstimados(),
            'advertencia'       => $this->advertencia($producto),
            'candidatas'        => $top,
            'evaluadas'         => count($evaluadas),
            'descartadas'       => count($descartadas),
            'descartadas_muestra' => array_slice($descartadas, 0, 10),
            'confiable'         => ($top[0]['score'] ?? 0) >= $this->scoreMinimo,
        ];
    }

    /**
     * Ubicaciones candidatas antes de puntuar.
     *
     * Un almacén real tiene decenas de miles de huecos (en producción: 73.000).
     * Traerlos todos a memoria para puntuarlos agota el proceso, así que la
     * selección se hace en dos partes:
     *
     *   1. Las ubicaciones que YA tienen este producto. Son pocas, están indexadas
     *      por inventario_id y no pueden faltar nunca: son las que ganan por
     *      consolidación, que es el factor de mayor peso.
     *
     *   2. Un bloque acotado del resto, prefiltrado y preordenado en SQL por los
     *      criterios baratos (clase de la ubicación, distancia, recorrido). Sobre
     *      ese bloque se aplica después el scoring completo en PHP.
     *
     * Es lo que hace cualquier WMS: no se evalúan 73.000 huecos para guardar una
     * caja, se evalúa el vecindario razonable.
     */
    private function candidatasBase(inventario $producto, array $opts)
    {
        $limite = (int) ($opts['limite'] ?? $this->limiteCandidatas);

        // --- Parte 1: donde ya está el producto (consolidación) ---
        $idsConsolidacion = DB::table('warehouse_inventory')
            ->where('inventario_id', $producto->id)
            ->distinct()
            ->pluck('warehouse_id')
            ->all();

        $consolidacion = collect();
        if (!empty($idsConsolidacion)) {
            $consolidacion = $this->filtrosSql(Warehouse::query(), $producto, $opts)
                ->whereIn('id', $idsConsolidacion)
                ->limit(50)
                ->get();
        }

        // --- Parte 2: bloque acotado del resto ---
        $q = $this->filtrosSql(Warehouse::query(), $producto, $opts);

        if ($consolidacion->isNotEmpty()) {
            $q->whereNotIn('id', $consolidacion->pluck('id')->all());
        }

        // Preorden barato, sólo con columnas indexadas. La afinidad con la clase
        // del producto es el criterio más discriminante que se puede resolver en SQL.
        $clase = $this->claseAbcDe($producto->id);
        if ($clase) {
            $q->orderByRaw('CASE WHEN clase_abc = ? THEN 0 WHEN clase_abc IS NULL THEN 1 ELSE 2 END', [$clase]);
        }

        // Para un producto A conviene lo cercano; para un C, lo lejano.
        if ($clase === 'C') {
            $q->orderByRaw('distancia_muelle_m IS NULL ASC')->orderByDesc('distancia_muelle_m');
        } else {
            $q->orderByRaw('distancia_muelle_m IS NULL ASC')->orderBy('distancia_muelle_m');
        }

        $resto = $q->orderBy('prioridad_picking')
                   ->limit(max(1, $limite - $consolidacion->count()))
                   ->get();

        return $consolidacion->concat($resto);
    }

    /**
     * Filtros duros expresables en SQL, compartidos por ambas partes de la selección.
     */
    private function filtrosSql($q, inventario $producto, array $opts)
    {
        $q->disponiblesParaPutaway()
          ->where('tipo', 'almacenamiento');

        // Un producto refrigerado sólo tiene sentido en ubicación refrigerada.
        // A la inversa también: la cámara fría es cara y escasa, así que no se
        // ofrece para mercancía seca aunque físicamente quepa.
        $q->where('refrigerada', (bool) $producto->requiere_refrigeracion);

        // La mercancía peligrosa va aislada; el resto no debe ocupar esa zona.
        $q->where('admite_peligrosos', (bool) $producto->peligroso);

        if (!empty($opts['zona'])) {
            $q->where('zona', $opts['zona']);
        }

        // Restringir a un conjunto concreto (reubicaciones dirigidas, pruebas).
        if (!empty($opts['solo_ids'])) {
            $q->whereIn('id', (array) $opts['solo_ids']);
        }

        if (!empty($opts['excluir'])) {
            $q->whereNotIn('id', (array) $opts['excluir']);
        }

        return $q;
    }

    /**
     * Ocupación actual de TODAS las ubicaciones en una sola consulta.
     *
     * Hacerlo por ubicación sería N+1 y con un almacén real (decenas de miles de
     * huecos) la sugerencia tardaría segundos.
     *
     * @return array<int,array>
     */
    private function ocupacionPorUbicacion(inventario $producto, array $warehouseIds = []): array
    {
        $filas = DB::table('warehouse_inventory as wi')
            ->leftJoin('inventarios as i', 'i.id', '=', 'wi.inventario_id')
            // Sólo las ubicaciones que se van a puntuar: agregar la tabla completa
            // es innecesario y crece con todo el inventario del almacén.
            ->when(!empty($warehouseIds), fn ($q) => $q->whereIn('wi.warehouse_id', $warehouseIds))
            ->groupBy('wi.warehouse_id')
            ->select([
                'wi.warehouse_id',
                DB::raw('SUM(wi.cantidad) as unidades'),
                DB::raw('SUM(wi.cantidad * COALESCE(i.peso_kg, 0)) as peso_kg'),
                DB::raw('SUM(wi.cantidad * COALESCE(i.volumen_m3, 0)) as volumen_m3'),
                DB::raw('COUNT(DISTINCT wi.inventario_id) as productos_distintos'),
                // ¿Cuánto hay ya de ESTE producto aquí? Base de la consolidación.
                DB::raw('SUM(CASE WHEN wi.inventario_id = ' . (int) $producto->id . ' THEN wi.cantidad ELSE 0 END) as cantidad_producto'),
                // ¿La ubicación está reservada al producto aunque hoy tenga 0?
                DB::raw('SUM(CASE WHEN wi.inventario_id = ' . (int) $producto->id . ' THEN 1 ELSE 0 END) as filas_producto'),
                // Vecindad de familia: unidades de la misma categoría.
                DB::raw('SUM(CASE WHEN i.id_categoria = ' . (int) ($producto->id_categoria ?: 0) . ' THEN wi.cantidad ELSE 0 END) as cantidad_familia'),
                // ¿Hay productos sin ficha física? Entonces la ocupación está subestimada.
                DB::raw('SUM(CASE WHEN i.volumen_m3 IS NULL AND wi.cantidad > 0 THEN 1 ELSE 0 END) as sin_ficha'),
            ])
            ->get();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f->warehouse_id] = [
                'unidades'            => (float) $f->unidades,
                'peso_kg'             => (float) $f->peso_kg,
                'volumen_m3'          => (float) $f->volumen_m3,
                'productos_distintos' => (int) $f->productos_distintos,
                'cantidad_producto'   => (float) $f->cantidad_producto,
                'filas_producto'      => (int) $f->filas_producto,
                'cantidad_familia'    => (float) $f->cantidad_familia,
                'sin_ficha'           => (int) $f->sin_ficha,
            ];
        }

        return $mapa;
    }

    private function ocupacionVacia(): array
    {
        return [
            'unidades' => 0.0, 'peso_kg' => 0.0, 'volumen_m3' => 0.0,
            'productos_distintos' => 0, 'cantidad_producto' => 0.0,
            'filas_producto' => 0, 'cantidad_familia' => 0.0, 'sin_ficha' => 0,
        ];
    }

    /**
     * Filtros duros. Devuelve el motivo del descarte, o null si la ubicación sirve.
     */
    private function filtrosDuros(Warehouse $u, inventario $producto, float $cantidad, array $ocup): ?string
    {
        if ($producto->requiere_refrigeracion && !$u->refrigerada) {
            return 'No refrigerada';
        }

        if ($producto->peligroso && !$u->admite_peligrosos) {
            return 'No admite mercancía peligrosa';
        }

        if (!$producto->apilable && $u->accesibilidad === 'altura') {
            return 'Producto no apilable en ubicación de altura';
        }

        if (!$u->permite_mezcla_productos
            && $ocup['productos_distintos'] > 0
            && $ocup['filas_producto'] === 0) {
            return 'No permite mezclar productos y ya está ocupada';
        }

        // Capacidad en unidades
        if ($u->capacidad_unidades !== null
            && ($ocup['unidades'] + $cantidad) > (float) $u->capacidad_unidades) {
            return 'Supera capacidad en unidades';
        }

        // Capacidad en peso y volumen: sólo aplicables si el producto tiene ficha.
        if ($producto->tieneDatosFisicos()) {
            $pesoNuevo    = (float) $producto->peso_kg * $cantidad;
            $volumenNuevo = (float) $producto->volumen_m3 * $cantidad;

            if ($u->capacidad_peso !== null
                && ($ocup['peso_kg'] + $pesoNuevo) > (float) $u->capacidad_peso) {
                return 'Supera capacidad de peso';
            }

            if ($u->capacidad_volumen !== null
                && ($ocup['volumen_m3'] + $volumenNuevo) > (float) $u->capacidad_volumen) {
                return 'Supera capacidad de volumen';
            }

            // La altura del producto tiene que caber en el hueco.
            if ($u->alto_util_cm !== null && $producto->alto_cm !== null
                && (float) $producto->alto_cm > (float) $u->alto_util_cm) {
                return 'El producto no cabe en alto';
            }
        }

        return null;
    }

    /**
     * Calcula el score de una ubicación y arma su explicación.
     */
    private function puntuar(Warehouse $u, inventario $producto, float $cantidad, ?string $claseProducto, array $ocup, array $distancias): array
    {
        $factores = [
            'consolidacion'    => $this->factorConsolidacion($ocup),
            'afinidad_abc'     => $this->factorAfinidadAbc($claseProducto, $u->clase_abc),
            'cercania'         => $this->factorCercania($u, $claseProducto, $distancias),
            'ergonomia'        => $this->factorErgonomia($u, $claseProducto, $producto),
            'ajuste_cubicaje'  => $this->factorCubicaje($u, $producto, $cantidad, $ocup),
            'afinidad_familia' => $this->factorFamilia($ocup),
        ];

        $score = 0.0;
        $desglose = [];
        foreach ($factores as $nombre => $valor) {
            $peso = (float) ($this->pesos[$nombre] ?? 0);
            $aporte = $valor * $peso;
            $score += $aporte;
            $desglose[$nombre] = [
                'valor'  => round($valor, 4),
                'peso'   => $peso,
                'aporte' => round($aporte, 4),
            ];
        }

        return [
            'warehouse_id'  => $u->id,
            'codigo'        => $u->codigo,
            'zona'          => $u->zona,
            'clase_abc'     => $u->clase_abc,
            'accesibilidad' => $u->accesibilidad,
            'distancia_m'   => (float) $u->distancia_muelle_m,
            'score'         => round($score, 4),
            'desglose'      => $desglose,
            'motivos'       => $this->motivos($factores, $u, $ocup, $claseProducto),
            'ocupacion'     => [
                'unidades'   => round($ocup['unidades'], 2),
                'peso_kg'    => round($ocup['peso_kg'], 2),
                'volumen_m3' => round($ocup['volumen_m3'], 5),
                'estimada'   => $ocup['sin_ficha'] > 0,
            ],
        ];
    }

    /**
     * Consolidación: máximo si el producto ya está ahí con stock; alto si la
     * ubicación le está asignada aunque esté en cero (es "su" sitio).
     */
    private function factorConsolidacion(array $ocup): float
    {
        if ($ocup['cantidad_producto'] > 0) {
            return 1.0;
        }

        if ($ocup['filas_producto'] > 0) {
            return 0.7;
        }

        // Ubicación vacía: no consolida, pero tampoco estorba.
        return $ocup['productos_distintos'] === 0 ? 0.35 : 0.0;
    }

    /**
     * Afinidad ABC: coincidencia exacta vale 1, clases adyacentes 0.5, extremos 0.
     * Poner un producto A en zona C (o al revés) es exactamente lo que el slotting
     * por rotación busca evitar.
     */
    private function factorAfinidadAbc(?string $claseProducto, ?string $claseUbicacion): float
    {
        if (!$claseProducto || !$claseUbicacion) {
            return 0.5; // sin información, neutro
        }

        if ($claseProducto === $claseUbicacion) {
            return 1.0;
        }

        $orden = ['A' => 1, 'B' => 2, 'C' => 3];
        $distancia = abs(($orden[$claseProducto] ?? 2) - ($orden[$claseUbicacion] ?? 2));

        return $distancia === 1 ? 0.5 : 0.0;
    }

    /**
     * Cercanía al muelle, interpretada según la rotación del producto.
     *
     * El matiz importante: para un producto C, estar cerca NO es bueno. Ocupar una
     * ubicación privilegiada con algo que se pide dos veces al año le quita ese
     * espacio a un producto A. Por eso la curva se invierte.
     */
    private function factorCercania(Warehouse $u, ?string $claseProducto, array $distancias): float
    {
        if ($u->distancia_muelle_m === null || $distancias['rango'] <= 0) {
            return 0.5;
        }

        // 0 = la más cercana del almacén, 1 = la más lejana
        $norm = ((float) $u->distancia_muelle_m - $distancias['min']) / $distancias['rango'];
        $norm = max(0.0, min(1.0, $norm));

        switch ($claseProducto) {
            case 'A':
                return 1.0 - $norm;           // cuanto más cerca, mejor
            case 'C':
                return $norm;                 // cuanto más lejos, mejor
            case 'B':
            default:
                // Zona intermedia: el óptimo está a media distancia.
                return 1.0 - abs($norm - 0.5) * 2;
        }
    }

    /**
     * Ergonomía: la "zona dorada" (a la altura de las manos) debe reservarse para
     * lo que más se toca. Lo pesado va al suelo aunque rote poco, porque levantarlo
     * a un nivel alto es un riesgo.
     */
    private function factorErgonomia(Warehouse $u, ?string $claseProducto, inventario $producto): float
    {
        $esPesado = $producto->peso_kg !== null && (float) $producto->peso_kg >= 20.0;

        if ($esPesado) {
            return [
                'suelo'  => 1.0,
                'dorada' => 0.6,
                'media'  => 0.2,
                'altura' => 0.0,
            ][$u->accesibilidad] ?? 0.5;
        }

        $tabla = [
            'A' => ['dorada' => 1.0, 'suelo' => 0.7, 'media' => 0.45, 'altura' => 0.1],
            'B' => ['dorada' => 0.7, 'suelo' => 0.6, 'media' => 0.8,  'altura' => 0.4],
            'C' => ['dorada' => 0.2, 'suelo' => 0.4, 'media' => 0.7,  'altura' => 1.0],
        ];

        $fila = $tabla[$claseProducto] ?? $tabla['B'];

        return $fila[$u->accesibilidad] ?? 0.5;
    }

    /**
     * Ajuste de cubicaje: qué tan bien aprovecha el hueco la mercancía que entra.
     *
     * Una campana centrada en "casi lleno" sería un error: un ingreso normal de 20
     * unidades pequeñas nunca llena un rack, y penalizarlo por eso castigaría la
     * operación cotidiana. La curva por tanto:
     *
     *   - nunca baja de 0.5 por estar poco llena (aprovechar poco no es un pecado),
     *   - sube hasta 1.0 a medida que la ubicación se usa mejor,
     *   - cae fuerte sólo cuando queda sin holgura para la próxima reposición.
     */
    private function factorCubicaje(Warehouse $u, inventario $producto, float $cantidad, array $ocup): float
    {
        if (!$producto->tieneDatosFisicos() || $u->capacidad_volumen === null || (float) $u->capacidad_volumen <= 0) {
            return 0.5; // sin datos, neutro: no se premia ni se castiga
        }

        $volumenNuevo = (float) $producto->volumen_m3 * $cantidad;
        $ocupacionFinal = ($ocup['volumen_m3'] + $volumenNuevo) / (float) $u->capacidad_volumen;

        if ($ocupacionFinal > 1.0) {
            return 0.0; // no cabe (el filtro duro ya debió descartarla)
        }

        // Sin holgura mínima para reponer: sirve, pero es la peor opción utilizable.
        $holguraFinal = (1.0 - $ocupacionFinal) * 100;
        if ($holguraFinal < $this->holguraMinima) {
            return 0.25;
        }

        // Meseta: el aprovechamiento ideal es usar bien el hueco sin agotarlo.
        $optimo = 0.85;

        if ($ocupacionFinal <= $optimo) {
            // De 0.5 (vacía) a 1.0 (aprovechada). Nunca punitivo.
            return 0.5 + 0.5 * ($ocupacionFinal / $optimo);
        }

        // Entre el óptimo y el tope, decae de 1.0 a 0.4.
        $exceso = ($ocupacionFinal - $optimo) / (1.0 - $optimo);

        return max(0.4, 1.0 - ($exceso * 0.6));
    }

    /**
     * Afinidad de familia: si en la ubicación ya hay productos de la misma
     * categoría, agruparlos facilita el picking y el conteo.
     */
    private function factorFamilia(array $ocup): float
    {
        if ($ocup['unidades'] <= 0) {
            return 0.3; // vacía: no aporta ni resta
        }

        $proporcion = $ocup['cantidad_familia'] / $ocup['unidades'];

        return max(0.0, min(1.0, $proporcion));
    }

    /**
     * Traduce los factores a frases que el operario pueda leer en el terminal.
     */
    private function motivos(array $factores, Warehouse $u, array $ocup, ?string $claseProducto): array
    {
        $motivos = [];

        if ($factores['consolidacion'] >= 1.0) {
            $motivos[] = 'Ya hay ' . rtrim(rtrim(number_format($ocup['cantidad_producto'], 2, ',', '.'), '0'), ',') . ' unidades de este producto aquí';
        } elseif ($factores['consolidacion'] >= 0.7) {
            $motivos[] = 'Ubicación ya asignada a este producto';
        } elseif ($factores['consolidacion'] >= 0.35) {
            $motivos[] = 'Ubicación vacía';
        }

        if ($factores['afinidad_abc'] >= 1.0 && $claseProducto) {
            $motivos[] = "Zona {$u->clase_abc} acorde a rotación {$claseProducto}";
        } elseif ($factores['afinidad_abc'] <= 0.0 && $claseProducto) {
            $motivos[] = "Zona {$u->clase_abc} no ideal para rotación {$claseProducto}";
        }

        if ($factores['cercania'] >= 0.8) {
            $motivos[] = $claseProducto === 'C'
                ? 'Lejos del muelle, correcto para baja rotación'
                : 'Cerca del muelle (' . (float) $u->distancia_muelle_m . ' m)';
        }

        if ($factores['ergonomia'] >= 0.9) {
            $motivos[] = 'Altura adecuada (' . $u->accesibilidad . ')';
        } elseif ($factores['ergonomia'] <= 0.2) {
            $motivos[] = 'Altura poco práctica (' . $u->accesibilidad . ')';
        }

        // Un score bajo de cubicaje significa cosas opuestas segun el lado de la curva,
        // asi que el mensaje se decide por la ocupacion resultante, no por el score.
        if ($factores['ajuste_cubicaje'] <= 0.25) {
            $motivos[] = 'Quedaría sin holgura para reponer';
        } elseif ($factores['ajuste_cubicaje'] >= 0.85) {
            $motivos[] = 'El volumen aprovecha bien el hueco';
        }

        if ($factores['afinidad_familia'] >= 0.6) {
            $motivos[] = 'Junto a productos de la misma categoría';
        }

        return $motivos;
    }

    private function rangoDistancias($candidatas): array
    {
        $valores = $candidatas->pluck('distancia_muelle_m')
            ->filter(fn ($d) => $d !== null)
            ->map(fn ($d) => (float) $d);

        if ($valores->isEmpty()) {
            return ['min' => 0.0, 'max' => 0.0, 'rango' => 0.0];
        }

        $min = $valores->min();
        $max = $valores->max();

        return ['min' => $min, 'max' => $max, 'rango' => $max - $min];
    }

    private function claseAbcDe(int $inventarioId): ?string
    {
        return ProductoAbc::where('inventario_id', $inventarioId)
            ->where('criterio', $this->criterioAbc)
            ->value('clase');
    }

    private function resumenProducto(inventario $p): array
    {
        return [
            'id'          => $p->id,
            'descripcion' => $p->descripcion,
            'codigo'      => $p->codigo_barras,
            'peso_kg'     => $p->peso_kg !== null ? (float) $p->peso_kg : null,
            'volumen_m3'  => $p->volumen_m3 !== null ? (float) $p->volumen_m3 : null,
            'refrigerado' => (bool) $p->requiere_refrigeracion,
            'peligroso'   => (bool) $p->peligroso,
            'apilable'    => (bool) $p->apilable,
        ];
    }

    private function advertencia(inventario $producto): ?string
    {
        if (!config('wms.advertir_datos_estimados')) {
            return null;
        }

        if (!$producto->tieneDatosFisicos()) {
            return 'Este producto no tiene peso ni volumen cargados: la sugerencia ignora el cubicaje.';
        }

        if ($producto->datosFisicosEstimados()) {
            return 'Peso y volumen son ESTIMADOS, no medidos. La ubicación sugerida puede cambiar al cargar las medidas reales.';
        }

        return null;
    }

    private function respuestaVacia(inventario $producto, ?string $clase, float $cantidad, string $motivo, array $descartadas = []): array
    {
        return [
            'estado'              => false,
            'msj'                 => $motivo,
            'producto'            => $this->resumenProducto($producto),
            'cantidad'            => $cantidad,
            'clase_abc'           => $clase,
            'datos_estimados'     => $producto->datosFisicosEstimados(),
            'advertencia'         => $this->advertencia($producto),
            'candidatas'          => [],
            'evaluadas'           => 0,
            'descartadas'         => count($descartadas),
            'descartadas_muestra' => array_slice($descartadas, 0, 10),
            'confiable'           => false,
        ];
    }

    /**
     * Reparte una cantidad entre varias ubicaciones cuando no cabe en una sola.
     *
     * Un ingreso de una paleta completa rara vez entra en un solo hueco. Sin esto,
     * el motor simplemente respondería "no hay ubicación" y el operario tendría que
     * resolverlo a mano, que es justo lo que se quiere evitar.
     *
     * Va tomando la mejor ubicación disponible, calcula cuánto cabe realmente ahí,
     * la reserva y repite con el remanente.
     *
     * @return array ['asignaciones' => [...], 'pendiente' => float, 'completo' => bool]
     */
    public function sugerirDistribucion($producto, float $cantidad, array $opts = []): array
    {
        $producto = $producto instanceof inventario ? $producto : inventario::findOrFail($producto);

        $restante    = $cantidad;
        $excluir     = (array) ($opts['excluir'] ?? []);
        $asignaciones = [];
        $maxUbicaciones = (int) ($opts['max_ubicaciones'] ?? 10);

        $claseProducto = $this->claseAbcDe($producto->id);

        for ($i = 0; $i < $maxUbicaciones && $restante > 0.0001; $i++) {
            $candidatas = $this->candidatasBase($producto, ['excluir' => $excluir] + $opts);
            if ($candidatas->isEmpty()) {
                break;
            }

            // Se recalcula por iteración: la ocupación cambia con lo ya asignado.
            $ocupacion  = $this->ocupacionPorUbicacion($producto, $candidatas->pluck('id')->all());
            $distancias = $this->rangoDistancias($candidatas);
            $mejor = null;

            foreach ($candidatas as $u) {
                $ocup = $ocupacion[$u->id] ?? $this->ocupacionVacia();

                $cabe = $this->cantidadQueCabe($u, $producto, $ocup);
                if ($cabe <= 0.0001) {
                    continue;
                }

                // Se puntúa con la cantidad que realmente va a entrar, no con el total.
                $porAsignar = min($restante, $cabe);
                $evaluada = $this->puntuar($u, $producto, $porAsignar, $claseProducto, $ocup, $distancias);
                $evaluada['cantidad'] = round($porAsignar, 4);

                if ($mejor === null || $evaluada['score'] > $mejor['score']) {
                    $mejor = $evaluada;
                }
            }

            if ($mejor === null) {
                break;
            }

            $asignaciones[] = $mejor;
            $restante -= $mejor['cantidad'];
            $excluir[] = $mejor['warehouse_id'];
        }

        return [
            'estado'          => !empty($asignaciones),
            'producto'        => $this->resumenProducto($producto),
            'cantidad'        => $cantidad,
            'clase_abc'       => $claseProducto,
            'datos_estimados' => $producto->datosFisicosEstimados(),
            'advertencia'     => $this->advertencia($producto),
            'asignaciones'    => $asignaciones,
            'pendiente'       => round(max(0, $restante), 4),
            'completo'        => $restante <= 0.0001,
            'msj'             => $restante > 0.0001
                ? 'No hay capacidad para la cantidad completa; queda un remanente sin ubicar'
                : null,
        ];
    }

    /**
     * Cuántas unidades caben todavía en la ubicación, mirando las tres capacidades.
     * Devuelve 0 si la ubicación no es apta.
     */
    private function cantidadQueCabe(Warehouse $u, inventario $producto, array $ocup): float
    {
        // Filtros que no dependen de la cantidad.
        if ($producto->requiere_refrigeracion && !$u->refrigerada) {
            return 0.0;
        }
        if ($producto->peligroso && !$u->admite_peligrosos) {
            return 0.0;
        }
        if (!$producto->apilable && $u->accesibilidad === 'altura') {
            return 0.0;
        }
        if (!$u->permite_mezcla_productos && $ocup['productos_distintos'] > 0 && $ocup['filas_producto'] === 0) {
            return 0.0;
        }
        if ($u->alto_util_cm !== null && $producto->alto_cm !== null
            && (float) $producto->alto_cm > (float) $u->alto_util_cm) {
            return 0.0;
        }

        $limites = [];

        if ($u->capacidad_unidades !== null) {
            $limites[] = max(0, (float) $u->capacidad_unidades - $ocup['unidades']);
        }

        if ($producto->tieneDatosFisicos()) {
            if ($u->capacidad_peso !== null && (float) $producto->peso_kg > 0) {
                $limites[] = max(0, ((float) $u->capacidad_peso - $ocup['peso_kg']) / (float) $producto->peso_kg);
            }
            if ($u->capacidad_volumen !== null && (float) $producto->volumen_m3 > 0) {
                $limites[] = max(0, ((float) $u->capacidad_volumen - $ocup['volumen_m3']) / (float) $producto->volumen_m3);
            }
        }

        // Manda la capacidad más restrictiva. Sin límites definidos, no se acota.
        if (empty($limites)) {
            return INF;
        }

        $cabe = min($limites);

        // Un producto que se vende por pieza no admite fracciones: sugerir "5,87
        // unidades" es inaplicable en el terminal. Las unidades de medida continuas
        // (peso, longitud, volumen) sí las admiten.
        if (!$this->unidadEsContinua($producto)) {
            return floor($cabe);
        }

        return floor($cabe * 10000) / 10000;
    }

    /**
     * ¿La unidad de venta admite decimales? KG, MTR, LTR sí; UND, PAR, JUEGO no.
     */
    private function unidadEsContinua(inventario $producto): bool
    {
        $unidad = strtoupper(trim((string) $producto->unidad));

        $continuas = ['KG', 'KGS', 'GR', 'GRS', 'MTR', 'MT', 'M', 'METRO', 'METROS',
                      'LTR', 'LT', 'L', 'LITRO', 'LITROS', 'M2', 'M3', 'CM'];

        return in_array($unidad, $continuas, true);
    }

    /**
     * Guarda la sugerencia emitida. Devuelve el id para poder cerrarla después con
     * la decisión real del operario.
     */
    public function registrarSugerencia(array $resultado, string $contexto = 'manual', ?string $referencia = null, ?int $usuarioId = null): ?int
    {
        if (empty($resultado['candidatas'])) {
            return null;
        }

        $top = $resultado['candidatas'][0];

        $sugerencia = PutawaySugerencia::create([
            'inventario_id'           => $resultado['producto']['id'],
            'cantidad'                => $resultado['cantidad'],
            'contexto'                => $contexto,
            'referencia'              => $referencia,
            // Se guarda el desglose completo: sin él no se puede auditar por qué
            // el motor propuso lo que propuso.
            'candidatas'              => $resultado['candidatas'],
            'warehouse_sugerido_id'   => $top['warehouse_id'],
            'score_sugerido'          => $top['score'],
            'clase_abc'               => $resultado['clase_abc'],
            'datos_fisicos_estimados' => $resultado['datos_estimados'],
            'usuario_id'              => $usuarioId,
        ]);

        return $sugerencia->id;
    }

    /**
     * Cierra una sugerencia con lo que el operario realmente hizo.
     *
     * Cada override es una etiqueta de entrenamiento: dice que el motor se equivocó
     * y en qué dirección. Es la razón de ser de esta tabla.
     */
    public function registrarDecision(int $sugerenciaId, int $warehouseElegidoId, ?string $motivoOverride = null): void
    {
        $sugerencia = PutawaySugerencia::find($sugerenciaId);
        if (!$sugerencia) {
            return;
        }

        $posicion = null;
        $scoreElegido = null;

        foreach ((array) $sugerencia->candidatas as $i => $c) {
            if ((int) ($c['warehouse_id'] ?? 0) === $warehouseElegidoId) {
                $posicion = $i + 1;
                $scoreElegido = $c['score'] ?? null;
                break;
            }
        }

        $sugerencia->update([
            'warehouse_elegido_id' => $warehouseElegidoId,
            'score_elegido'        => $scoreElegido,
            'posicion_elegida'     => $posicion,
            'fue_aceptada'         => $warehouseElegidoId === (int) $sugerencia->warehouse_sugerido_id,
            'motivo_override'      => $motivoOverride,
            'decidido_en'          => now(),
        ]);
    }

    /**
     * Tasa de aceptación del motor. Es el KPI que dice si el scoring sirve:
     * si los pasilleros ignoran la sugerencia el 70% de las veces, los pesos están mal.
     */
    public function tasaAceptacion(int $dias = 30): array
    {
        $desde = now()->subDays($dias);

        $total = PutawaySugerencia::decididas()->where('decidido_en', '>=', $desde)->count();
        if ($total === 0) {
            return ['total' => 0, 'aceptadas' => 0, 'tasa_pct' => null, 'en_top3_pct' => null];
        }

        $aceptadas = PutawaySugerencia::decididas()
            ->where('decidido_en', '>=', $desde)
            ->where('fue_aceptada', true)->count();

        // Que la elegida esté en el top 3 ya indica que el motor "iba bien encaminado".
        $enTop3 = PutawaySugerencia::decididas()
            ->where('decidido_en', '>=', $desde)
            ->whereNotNull('posicion_elegida')
            ->where('posicion_elegida', '<=', 3)->count();

        return [
            'total'       => $total,
            'aceptadas'   => $aceptadas,
            'tasa_pct'    => round(($aceptadas / $total) * 100, 2),
            'en_top3_pct' => round(($enTop3 / $total) * 100, 2),
        ];
    }
}
