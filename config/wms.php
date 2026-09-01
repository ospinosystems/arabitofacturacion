<?php

/**
 * Parámetros del WMS.
 *
 * Todo lo que un jefe de almacén querría ajustar sin tocar código vive aquí:
 * umbrales del ABC, pesos del motor de slotting y frecuencias de conteo.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Clasificación ABC
    |--------------------------------------------------------------------------
    |
    | Umbrales de Pareto sobre el porcentaje acumulado. Con los valores por
    | defecto: clase A hasta el 80% acumulado, B hasta el 95%, C el resto.
    |
    */
    'abc' => [
        'dias_analisis' => 180,

        'umbral_a' => 80.0,
        'umbral_b' => 95.0,

        // Productos sin ninguna salida en el periodo. Se clasifican aparte para no
        // ensuciar el Pareto: son candidatos a obsolescencia, no "C normales".
        'clase_sin_movimiento' => 'C',

        // Pesos del criterio 'combinado'. La popularidad pesa más porque para decidir
        // dónde va un producto importa cuántas veces hay que ir a buscarlo, no su precio.
        'pesos_combinado' => [
            'popularidad' => 0.50,
            'valor'       => 0.30,
            'unidades'    => 0.20,
        ],

        // Criterio que usa el motor de slotting para decidir ubicación.
        'criterio_slotting' => 'combinado',
    ],

    /*
    |--------------------------------------------------------------------------
    | Motor de slotting (sugerencia de ubicación / putaway)
    |--------------------------------------------------------------------------
    |
    | Pesos de cada factor del score. Se normalizan internamente, así que lo que
    | importa es la proporción entre ellos, no que sumen 1.
    |
    */
    'slotting' => [
        'pesos' => [
            // Ya hay stock del mismo producto ahí: consolidar evita fragmentar el
            // inventario y ahorra recorridos. Es el factor más fuerte.
            'consolidacion'   => 30.0,

            // La clase ABC del producto coincide con la clase de la ubicación.
            'afinidad_abc'    => 25.0,

            // Cercanía al muelle, ponderada por rotación: las A cerca, las C al fondo.
            'cercania'        => 15.0,

            // Ergonomía: producto de alta rotación en zona dorada (a la altura de las manos).
            'ergonomia'       => 10.0,

            // Ajuste de cubicaje: premia el hueco donde la mercancía queda justa.
            // Evita gastar una ubicación grande en algo pequeño.
            'ajuste_cubicaje' => 12.0,

            // Productos de la misma categoría/proveedor cerca entre sí.
            'afinidad_familia' => 8.0,
        ],

        // Cuántas candidatas devolver al operario.
        'top_n' => 3,

        // Cuántas ubicaciones se puntúan por sugerencia.
        //
        // Un almacén real tiene decenas de miles de huecos y evaluarlos todos agota
        // la memoria del proceso. Se preseleccionan en SQL por clase, distancia y
        // recorrido, y sólo este bloque pasa al scoring completo. Las ubicaciones
        // que ya contienen el producto se incluyen siempre, aparte de este límite.
        'limite_candidatas' => 400,

        // Si la ubicación queda con menos de este % libre tras el ingreso, se
        // considera saturada y pierde atractivo (dejar holgura para reposición).
        'holgura_minima_pct' => 5.0,

        // Score por debajo del cual no se sugiere nada y se pide decisión manual.
        'score_minimo' => 10.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Estrategia de picking
    |--------------------------------------------------------------------------
    |
    | fefo : primero lo que vence antes (First Expired, First Out).
    | fifo : primero lo que entró antes.
    |
    | El WMS usa FEFO por defecto. Con productos perecederos cualquier otra cosa
    | genera merma. Los lotes sin fecha de vencimiento caen al final del orden.
    |
    */
    'picking' => [
        'estrategia' => 'fefo',

        // No sugerir para picking un lote que vence dentro de este margen: hay que
        // revisarlo antes de despacharlo.
        'dias_minimos_vencimiento' => 0,

        // Permitir completar una línea tomando de varias ubicaciones.
        'permitir_multiubicacion' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conteo cíclico
    |--------------------------------------------------------------------------
    |
    | Cada cuántos días debe recontarse una ubicación según la clase ABC del
    | producto que contiene. Las A se descuadran más porque se tocan más.
    |
    */
    'conteo' => [
        'frecuencia_dias' => [
            'A' => 30,
            'B' => 90,
            'C' => 180,
        ],

        // Diferencia en unidades por debajo de la cual se ajusta sin recuento.
        'tolerancia_unidades' => 0,

        // Meta de exactitud de inventario. Por debajo de esto hay que investigar.
        'meta_exactitud_pct' => 97.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | TMS
    |--------------------------------------------------------------------------
    */
    'tms' => [
        // Margen de seguridad al planificar carga: no llenar el vehículo al 100%.
        'factor_seguridad_carga' => 0.95,

        // Minutos estimados de servicio por parada, para el tiempo de ruta.
        'minutos_por_parada' => 15,

        // Velocidad media urbana para estimar tiempos, km/h.
        'velocidad_media_kmh' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Datos físicos estimados
    |--------------------------------------------------------------------------
    |
    | Mientras un producto tenga datos_fisicos_fuente = 'estimado', las sugerencias
    | que dependan de su peso/volumen deben advertirlo en la respuesta.
    |
    */
    'advertir_datos_estimados' => true,

];
