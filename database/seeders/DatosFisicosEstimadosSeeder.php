<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rellena peso y dimensiones ESTIMADOS para poder probar el flujo del WMS
 * antes de tener las medidas reales.
 *
 * Dos decisiones importantes:
 *
 * 1. Los valores no son aleatorios puros: se derivan del perfil de la categoría
 *    y se escalan por el precio del producto. Un rollo de alambre pesa como un
 *    rollo de alambre y una nevera ocupa como una nevera. Si los números fueran
 *    ruido, el motor de slotting produciría sugerencias sin sentido y no habría
 *    forma de saber si la lógica está bien o mal.
 *
 * 2. La aleatoriedad es determinista (semilla = id del producto): correr el
 *    seeder dos veces da el mismo resultado. Sin esto, cada ejecución cambiaría
 *    las ubicaciones sugeridas y sería imposible comparar.
 *
 * Todo queda marcado con datos_fisicos_fuente = 'estimado'. El día que se midan
 * de verdad, basta filtrar por ese campo para saber qué falta.
 */
class DatosFisicosEstimadosSeeder extends Seeder
{
    /**
     * Perfil físico por categoría: rangos de peso (kg) y dimensiones (cm) de
     * UNA unidad de venta, más características de manejo.
     *
     * [peso_min, peso_max, largo, ancho, alto, unidades_por_bulto, apilable, fragil]
     */
    private array $perfiles = [
        // id_categoria => perfil
        1  => ['nombre' => 'AGRICOLA',               'peso' => [0.4, 6.0],   'dim' => [25, 18, 12], 'bulto' => 12, 'apilable' => true,  'fragil' => false],
        2  => ['nombre' => 'ALAMBRE',                'peso' => [5.0, 25.0],  'dim' => [32, 32, 14], 'bulto' => 4,  'apilable' => true,  'fragil' => false],
        3  => ['nombre' => 'BATERIA',                'peso' => [9.0, 26.0],  'dim' => [26, 17, 21], 'bulto' => 2,  'apilable' => false, 'fragil' => false],
        4  => ['nombre' => 'CONSTRUCCION',           'peso' => [8.0, 50.0],  'dim' => [45, 30, 20], 'bulto' => 4,  'apilable' => true,  'fragil' => false],
        6  => ['nombre' => 'CUIDADO DEL HOGAR',      'peso' => [0.3, 3.0],   'dim' => [18, 12, 22], 'bulto' => 12, 'apilable' => true,  'fragil' => false],
        8  => ['nombre' => 'ELECTRICIDAD',           'peso' => [0.1, 1.8],   'dim' => [15, 10, 6],  'bulto' => 24, 'apilable' => true,  'fragil' => false],
        9  => ['nombre' => 'ELECTRODOMESTICO',       'peso' => [2.0, 18.0],  'dim' => [45, 35, 30], 'bulto' => 1,  'apilable' => false, 'fragil' => true],
        11 => ['nombre' => 'FONTANERIA',             'peso' => [0.3, 5.0],   'dim' => [40, 12, 12], 'bulto' => 10, 'apilable' => true,  'fragil' => false],
        13 => ['nombre' => 'GRIFERIA',               'peso' => [0.4, 2.5],   'dim' => [22, 12, 9],  'bulto' => 12, 'apilable' => true,  'fragil' => false],
        14 => ['nombre' => 'HERRAMIENTAS',           'peso' => [0.2, 3.5],   'dim' => [30, 12, 7],  'bulto' => 12, 'apilable' => true,  'fragil' => false],
        18 => ['nombre' => 'MOTOS',                  'peso' => [0.5, 10.0],  'dim' => [28, 20, 15], 'bulto' => 6,  'apilable' => true,  'fragil' => false],
        21 => ['nombre' => 'PINTURA',                'peso' => [1.0, 20.0],  'dim' => [22, 22, 26], 'bulto' => 4,  'apilable' => true,  'fragil' => false],
        22 => ['nombre' => 'PLOMERIA',               'peso' => [0.3, 5.0],   'dim' => [40, 12, 12], 'bulto' => 10, 'apilable' => true,  'fragil' => false],
        24 => ['nombre' => 'REPUESTOS',              'peso' => [0.2, 4.0],   'dim' => [24, 16, 10], 'bulto' => 10, 'apilable' => true,  'fragil' => false],
        26 => ['nombre' => 'TELEFONIA',              'peso' => [0.1, 0.7],   'dim' => [16, 9, 5],   'bulto' => 20, 'apilable' => true,  'fragil' => true],
        28 => ['nombre' => 'TORNILLERIA',            'peso' => [0.02, 1.2],  'dim' => [10, 8, 5],   'bulto' => 50, 'apilable' => true,  'fragil' => false],
        31 => ['nombre' => 'CERRADURA',              'peso' => [0.4, 2.2],   'dim' => [18, 12, 7],  'bulto' => 12, 'apilable' => true,  'fragil' => false],
        32 => ['nombre' => 'HERRAMIENTAS ELECTRICAS','peso' => [1.5, 9.0],   'dim' => [38, 26, 18], 'bulto' => 4,  'apilable' => true,  'fragil' => true],
        34 => ['nombre' => 'LINEA BLANCA',           'peso' => [22.0, 85.0], 'dim' => [62, 62, 150],'bulto' => 1,  'apilable' => false, 'fragil' => true],
        36 => ['nombre' => 'HOGAR',                  'peso' => [0.3, 6.0],   'dim' => [26, 20, 16], 'bulto' => 8,  'apilable' => true,  'fragil' => false],
    ];

    /** Perfil de respaldo para categorías sin definir. */
    private array $perfilPorDefecto = [
        'nombre' => 'GENERICO', 'peso' => [0.3, 3.0], 'dim' => [22, 16, 11],
        'bulto' => 12, 'apilable' => true, 'fragil' => false,
    ];

    /**
     * Palabras en la descripción que implican manejo especial, con independencia
     * de la categoría. Es lo que haría un jefe de almacén revisando el catálogo.
     */
    private array $palabrasPeligrosas = [
        'HERBICIDA', 'INSECTICIDA', 'FUNGICIDA', 'PLAGUICIDA', 'PESTICIDA',
        'GLIFOSATO', 'ACIDO', 'SOLVENTE', 'THINNER', 'GASOLINA', 'GAS ',
        'INFLAMABLE', 'CLORO', 'SODA CAUSTICA', 'BATERIA', 'PILA',
        'VENENO', 'RATICIDA', 'MATAMALEZA', 'AGROQUIMICO',
    ];

    private array $palabrasRefrigeracion = [
        'VACUNA', 'BIOLOGICO', 'SEMEN', 'REFRIGERAD', 'CADENA DE FRIO',
    ];

    public function run()
    {
        $this->command->info('Estimando datos físicos del catálogo...');

        $productos = DB::table('inventarios')
            ->select('id', 'id_categoria', 'descripcion', 'unidad', 'precio_base', 'precio', 'bulto')
            // Nunca se pisa un dato medido de verdad.
            ->where('datos_fisicos_fuente', 'estimado')
            ->orderBy('id')
            ->get();

        if ($productos->isEmpty()) {
            $this->command->warn('No hay productos pendientes de estimar.');
            return;
        }

        // Mediana de precio por categoría: sirve de referencia para escalar el tamaño.
        $medianas = $this->medianasPorCategoria();

        $actualizados = 0;
        $peligrosos = 0;
        $refrigerados = 0;
        $ahora = now();

        foreach ($productos->chunk(500) as $lote) {
            $updates = [];

            foreach ($lote as $p) {
                $perfil = $this->perfiles[$p->id_categoria] ?? $this->perfilPorDefecto;

                // Semilla determinista: mismo producto, mismo resultado siempre.
                mt_srand((int) $p->id);

                $factor = $this->factorPrecio($p, $medianas);

                [$pesoMin, $pesoMax] = $perfil['peso'];
                $peso = $this->entre($pesoMin, $pesoMax) * $factor;

                // Las dimensiones escalan con la raíz cúbica del factor: si algo pesa
                // 8 veces más, es ~2 veces más grande en cada lado, no 8.
                $escalaLineal = pow($factor, 1 / 3);
                [$l, $a, $h] = $perfil['dim'];
                $largo = $l * $escalaLineal * $this->entre(0.85, 1.15);
                $ancho = $a * $escalaLineal * $this->entre(0.85, 1.15);
                $alto  = $h * $escalaLineal * $this->entre(0.85, 1.15);

                $descripcion = strtoupper($p->descripcion ?? '');
                $esPeligroso = $this->contiene($descripcion, $this->palabrasPeligrosas)
                               || in_array($p->id_categoria, [3], true); // baterías
                $esRefrigerado = $this->contiene($descripcion, $this->palabrasRefrigeracion);

                // Si el producto ya traía `bulto`, se respeta: es un dato del negocio.
                $unidadesPorBulto = (int) ($p->bulto > 0 ? $p->bulto : $perfil['bulto']);

                $peso  = round(max(0.005, $peso), 4);
                $largo = round(max(1, $largo), 2);
                $ancho = round(max(1, $ancho), 2);
                $alto  = round(max(1, $alto), 2);
                $volumen = round(($largo * $ancho * $alto) / 1000000, 8);

                $updates[] = [
                    'id'                     => $p->id,
                    'peso_kg'                => $peso,
                    'largo_cm'               => $largo,
                    'ancho_cm'               => $ancho,
                    'alto_cm'                => $alto,
                    'volumen_m3'             => $volumen,
                    'unidades_por_bulto'     => $unidadesPorBulto,
                    'peso_bulto_kg'          => round($peso * $unidadesPorBulto, 4),
                    'volumen_bulto_m3'       => round($volumen * $unidadesPorBulto, 8),
                    'bultos_por_capa'        => $this->bultosPorCapa($volumen),
                    'capas_por_paleta'       => $perfil['apilable'] ? mt_rand(3, 6) : 1,
                    'apilable'               => $perfil['apilable'] ? 1 : 0,
                    'max_apilamiento'        => $perfil['apilable'] ? mt_rand(3, 8) : 1,
                    'fragil'                 => $perfil['fragil'] ? 1 : 0,
                    'requiere_refrigeracion' => $esRefrigerado ? 1 : 0,
                    'peligroso'              => $esPeligroso ? 1 : 0,
                    'datos_fisicos_fuente'   => 'estimado',
                    'updated_at'             => $ahora,
                ];

                if ($esPeligroso)   { $peligrosos++; }
                if ($esRefrigerado) { $refrigerados++; }
            }

            $this->aplicarLote($updates);
            $actualizados += count($updates);
            $this->command->getOutput()->write('.');
        }

        $this->command->newLine();
        $this->command->info("Productos estimados: {$actualizados}");
        $this->command->line("  Marcados como peligrosos:   {$peligrosos}");
        $this->command->line("  Requieren refrigeración:    {$refrigerados}");
        $this->command->warn('Todos quedaron con datos_fisicos_fuente = "estimado".');
    }

    /**
     * Escala el tamaño según qué tan caro es el producto respecto a la mediana de
     * su categoría. Se usa logaritmo y se acota entre 0.5x y 3x para que un
     * producto carísimo no termine del tamaño de un camión.
     */
    private function factorPrecio($producto, array $medianas): float
    {
        $categoria = $producto->id_categoria ?? 0;
        $mediana = $medianas[$categoria] ?? 0;
        $precio = (float) ($producto->precio_base ?: $producto->precio ?: 0);

        if ($mediana <= 0 || $precio <= 0) {
            return 1.0;
        }

        $ratio = $precio / $mediana;
        $factor = 1 + (log10(max(0.1, $ratio)) * 0.8);

        return max(0.5, min(3.0, $factor));
    }

    /** Mediana de precio_base por categoría. */
    private function medianasPorCategoria(): array
    {
        $medianas = [];

        $porCategoria = DB::table('inventarios')
            ->select('id_categoria', 'precio_base')
            ->where('precio_base', '>', 0)
            ->orderBy('id_categoria')
            ->get()
            ->groupBy('id_categoria');

        foreach ($porCategoria as $categoria => $filas) {
            $precios = $filas->pluck('precio_base')->map(fn ($v) => (float) $v)->sort()->values();
            $n = $precios->count();
            if ($n === 0) {
                continue;
            }
            $medianas[(int) $categoria] = $n % 2
                ? $precios[intdiv($n, 2)]
                : ($precios[intdiv($n, 2) - 1] + $precios[intdiv($n, 2)]) / 2;
        }

        return $medianas;
    }

    /** Cuántos bultos caben en una capa de paleta estándar (1.2 x 1.0 m). */
    private function bultosPorCapa(float $volumenUnidad): int
    {
        if ($volumenUnidad <= 0) {
            return 1;
        }

        // Aproximación gruesa por área ocupada; suficiente para un dato estimado.
        $estimado = (int) floor(1.2 / max(0.05, pow($volumenUnidad, 1 / 3)));

        return max(1, min(40, $estimado));
    }

    private function entre(float $min, float $max): float
    {
        return $min + (mt_rand(0, 10000) / 10000) * ($max - $min);
    }

    private function contiene(string $texto, array $palabras): bool
    {
        foreach ($palabras as $palabra) {
            if (strpos($texto, $palabra) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * UPDATE masivo con CASE, para no hacer 6900 consultas sueltas.
     */
    private function aplicarLote(array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $campos = [
            'peso_kg', 'largo_cm', 'ancho_cm', 'alto_cm', 'volumen_m3',
            'unidades_por_bulto', 'peso_bulto_kg', 'volumen_bulto_m3',
            'bultos_por_capa', 'capas_por_paleta', 'apilable', 'max_apilamiento',
            'fragil', 'requiere_refrigeracion', 'peligroso',
        ];

        $ids = array_column($updates, 'id');
        $sql = 'UPDATE inventarios SET ';
        $bindings = [];
        $partes = [];

        foreach ($campos as $campo) {
            $caso = "{$campo} = CASE id";
            foreach ($updates as $u) {
                $caso .= ' WHEN ? THEN ?';
                $bindings[] = $u['id'];
                $bindings[] = $u[$campo];
            }
            $caso .= " ELSE {$campo} END";
            $partes[] = $caso;
        }

        $partes[] = 'updated_at = ?';
        $bindings[] = $updates[0]['updated_at'];

        $sql .= implode(', ', $partes);
        $sql .= ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $bindings = array_merge($bindings, $ids);

        DB::update($sql, $bindings);
    }
}
