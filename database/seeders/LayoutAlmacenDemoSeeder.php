<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Layout de almacén de demostración, para probar slotting y conteo sin datos reales.
 *
 * Modela un almacén rectangular con el muelle en el origen (0,0):
 *
 *   - Pasillos A..F, cada uno con 2 caras y 12 racks de 4 niveles.
 *   - La distancia al muelle crece con la letra del pasillo y el número de rack.
 *   - La accesibilidad depende del nivel: el nivel 2 es la "zona dorada", a la
 *     altura de las manos; el nivel 4 exige escalera.
 *   - La clase ABC de la ubicación se asigna por distancia: lo cerca es para
 *     productos A, lo lejano para C. Ese es el principio del slotting por rotación.
 *
 * No se ejecuta si ya hay ubicaciones cargadas.
 */
class LayoutAlmacenDemoSeeder extends Seeder
{
    private const PASILLOS = ['A', 'B', 'C', 'D', 'E', 'F'];
    private const CARAS    = [1, 2];
    private const RACKS    = 12;
    private const NIVELES  = 4;

    /** Separación entre pasillos y entre racks, en metros. */
    private const ANCHO_PASILLO = 4.0;
    private const LARGO_RACK    = 1.5;

    public function run()
    {
        if (DB::table('warehouses')->count() > 0) {
            $this->command->warn('Ya existen ubicaciones. Seeder omitido.');
            return;
        }

        $this->command->info('Generando layout de almacén de demostración...');

        $ahora = now();
        $filas = [];

        foreach (self::PASILLOS as $iPasillo => $pasillo) {
            foreach (self::CARAS as $cara) {
                for ($rack = 1; $rack <= self::RACKS; $rack++) {
                    for ($nivel = 1; $nivel <= self::NIVELES; $nivel++) {
                        $filas[] = $this->construirUbicacion($iPasillo, $pasillo, $cara, $rack, $nivel, $ahora);
                    }
                }
            }
        }

        // Muelles: recepción y despacho, pegados al origen.
        $filas[] = $this->ubicacionEspecial('REC', 1, 1, 1, 'RECEPCION', 'recepcion', 0.0, 2.0, $ahora);
        $filas[] = $this->ubicacionEspecial('DES', 1, 1, 1, 'DESPACHO', 'despacho', 0.0, -2.0, $ahora);
        // Zona refrigerada, para poder probar la regla de compatibilidad.
        $filas[] = $this->ubicacionEspecial('FRIO', 1, 1, 1, 'CAMARA FRIA', 'almacenamiento', 8.0, 6.0, $ahora, true);
        // Zona de mercancía peligrosa, aislada.
        $filas[] = $this->ubicacionEspecial('PEL', 1, 1, 1, 'ZONA PELIGROSOS', 'almacenamiento', 30.0, 20.0, $ahora, false, true);

        foreach (array_chunk($filas, 200) as $lote) {
            DB::table('warehouses')->insert($lote);
        }

        $this->command->info('Ubicaciones creadas: ' . count($filas));

        $resumen = DB::table('warehouses')
            ->select('clase_abc', DB::raw('COUNT(*) as n'))
            ->groupBy('clase_abc')->orderBy('clase_abc')->get();

        foreach ($resumen as $r) {
            $this->command->line('  Clase ' . ($r->clase_abc ?: '-') . ': ' . $r->n . ' ubicaciones');
        }
    }

    private function construirUbicacion(int $iPasillo, string $pasillo, int $cara, int $rack, int $nivel, $ahora): array
    {
        // Coordenadas: X avanza por pasillo, Y avanza por rack.
        $x = round($iPasillo * self::ANCHO_PASILLO + ($cara === 2 ? 1.2 : 0), 2);
        $y = round($rack * self::LARGO_RACK, 2);

        // Distancia recorrida real (Manhattan): el pasillero no camina en diagonal.
        $distancia = round($x + $y, 2);

        // Nivel 1 = suelo (pesado, poco frecuente), 2 = zona dorada (lo que más se toca),
        // 3 = media, 4 = altura (requiere escalera).
        $accesibilidad = [1 => 'suelo', 2 => 'dorada', 3 => 'media', 4 => 'altura'][$nivel];

        // Clase de la ubicación por cercanía al muelle. El nivel también pesa:
        // una ubicación cercana pero en altura no es tan buena como una cercana a la mano.
        $penalizacionNivel = in_array($nivel, [1, 2], true) ? 0 : 6;
        $distanciaEfectiva = $distancia + $penalizacionNivel;

        if ($distanciaEfectiva <= 14) {
            $clase = 'A';
        } elseif ($distanciaEfectiva <= 26) {
            $clase = 'B';
        } else {
            $clase = 'C';
        }

        // El nivel de suelo aguanta más peso; los niveles altos, menos.
        $capacidadPeso = [1 => 1500.0, 2 => 1000.0, 3 => 700.0, 4 => 400.0][$nivel];

        return [
            'pasillo'                  => $pasillo,
            'cara'                     => $cara,
            'rack'                     => $rack,
            'nivel'                    => $nivel,
            'codigo'                   => "{$pasillo}{$cara}-{$rack}-{$nivel}",
            'nombre'                   => null,
            'descripcion'              => null,
            'tipo'                     => 'almacenamiento',
            'estado'                   => 'activa',
            'zona'                     => $pasillo,
            'capacidad_peso'           => $capacidadPeso,
            'capacidad_volumen'        => 2.4,   // hueco de 1.2 x 1.0 x 2.0 m aprox
            'capacidad_unidades'       => 800,
            'coord_x'                  => $x,
            'coord_y'                  => $y,
            'distancia_muelle_m'       => $distancia,
            'accesibilidad'            => $accesibilidad,
            'clase_abc'                => $clase,
            'alto_util_cm'             => 200.0,
            'ancho_util_cm'            => 120.0,
            'profundidad_util_cm'      => 100.0,
            'permite_mezcla_productos' => 1,
            'permite_mezcla_lotes'     => 1,
            'refrigerada'              => 0,
            'admite_peligrosos'        => 0,
            'bloqueada_para_putaway'   => 0,
            // Orden de recorrido: se visita pasillo por pasillo, rack por rack.
            'prioridad_picking'        => ($iPasillo * 1000) + ($cara * 100) + $rack,
            'created_at'               => $ahora,
            'updated_at'               => $ahora,
        ];
    }

    private function ubicacionEspecial(
        string $pasillo, int $cara, int $rack, int $nivel, string $nombre, string $tipo,
        float $x, float $y, $ahora, bool $refrigerada = false, bool $peligrosos = false
    ): array {
        return [
            'pasillo'                  => $pasillo,
            'cara'                     => $cara,
            'rack'                     => $rack,
            'nivel'                    => $nivel,
            'codigo'                   => "{$pasillo}{$cara}-{$rack}-{$nivel}",
            'nombre'                   => $nombre,
            'descripcion'              => null,
            'tipo'                     => $tipo,
            'estado'                   => 'activa',
            'zona'                     => $pasillo,
            'capacidad_peso'           => 3000.0,
            'capacidad_volumen'        => 20.0,
            'capacidad_unidades'       => 5000,
            'coord_x'                  => $x,
            'coord_y'                  => $y,
            'distancia_muelle_m'       => round(abs($x) + abs($y), 2),
            'accesibilidad'            => 'suelo',
            'clase_abc'                => $tipo === 'almacenamiento' ? 'B' : null,
            'alto_util_cm'             => 250.0,
            'ancho_util_cm'            => 400.0,
            'profundidad_util_cm'      => 300.0,
            'permite_mezcla_productos' => 1,
            'permite_mezcla_lotes'     => 1,
            'refrigerada'              => $refrigerada ? 1 : 0,
            'admite_peligrosos'        => $peligrosos ? 1 : 0,
            'bloqueada_para_putaway'   => in_array($tipo, ['recepcion', 'despacho'], true) ? 1 : 0,
            'prioridad_picking'        => 1,
            'created_at'               => $ahora,
            'updated_at'               => $ahora,
        ];
    }
}
