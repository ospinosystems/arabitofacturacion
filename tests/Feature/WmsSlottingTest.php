<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

use App\Models\ProductoAbc;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\inventario;
use App\Services\Wms\SlottingService;

/**
 * Pruebas del motor de sugerencia de ubicación (slotting).
 *
 * Usa DatabaseTransactions: todo se revierte al terminar.
 *
 * Lo que se verifica son las reglas que definen el comportamiento del motor,
 * no números concretos de score (esos dependen de los pesos configurados):
 *
 *  - Los filtros duros son infranqueables (refrigeración, peligrosos, capacidad).
 *  - Un producto A prefiere ubicaciones cercanas; un C prefiere las lejanas.
 *  - La consolidación gana cuando ya hay stock del mismo producto.
 *  - Una cantidad que no cabe en un hueco se reparte entre varios.
 *  - Las decisiones del operario quedan registradas para poder medir el motor.
 */
class WmsSlottingTest extends TestCase
{
    use DatabaseTransactions;

    private function crearProducto(array $attrs = []): inventario
    {
        return inventario::create(array_merge([
            'codigo_barras' => 'SLOTTEST-' . uniqid(),
            'descripcion'   => 'Producto slotting de prueba',
            'cantidad'      => 100,
            'precio'        => 10,
            'precio_base'   => 8,
            'unidad'        => 'UND',
            'peso_kg'       => 1.0,
            'largo_cm'      => 20,
            'ancho_cm'      => 10,
            'alto_cm'       => 10,
            'apilable'      => 1,
            'datos_fisicos_fuente' => 'medido',
        ], $attrs));
    }

    private function crearUbicacion(array $attrs = []): Warehouse
    {
        static $n = 0;
        $n++;

        return Warehouse::create(array_merge([
            'pasillo'            => 'T',
            'cara'               => 1,
            'rack'               => $n,
            'nivel'              => 1,
            'codigo'             => 'TST' . $n . '-' . uniqid(),
            'tipo'               => 'almacenamiento',
            'estado'             => 'activa',
            'zona'               => 'T',
            'capacidad_peso'     => 1000,
            'capacidad_volumen'  => 2.0,
            'capacidad_unidades' => 500,
            'distancia_muelle_m' => 10,
            'accesibilidad'      => 'dorada',
            'clase_abc'          => 'A',
            'refrigerada'        => 0,
            'admite_peligrosos'  => 0,
            'permite_mezcla_productos' => 1,
            'bloqueada_para_putaway'   => 0,
            'prioridad_picking'  => 100,
        ], $attrs));
    }

    private function clasificar(inventario $p, string $clase): void
    {
        ProductoAbc::create([
            'inventario_id'  => $p->id,
            'criterio'       => config('wms.abc.criterio_slotting'),
            'periodo_inicio' => now()->subDays(30)->toDateString(),
            'periodo_fin'    => now()->toDateString(),
            'metrica'        => 1,
            'clase'          => $clase,
            'ranking'        => 1,
            'calculado_en'   => now(),
        ]);
    }

    /**
     * Restringe la evaluación a las ubicaciones creadas por el test.
     *
     * Se usa `solo_ids` (lista blanca) y no `excluir`: el almacén real tiene decenas
     * de miles de huecos y construir la lista de exclusión sería absurdo.
     */
    private function sugerirEntre(inventario $producto, float $cantidad, array $ubicaciones): array
    {
        $ids = collect($ubicaciones)->pluck('id')->all();

        return (new SlottingService())->sugerir($producto, $cantidad, ['solo_ids' => $ids, 'top_n' => 10]);
    }

    /** @test */
    public function un_producto_refrigerado_solo_se_sugiere_en_ubicacion_refrigerada()
    {
        $producto = $this->crearProducto(['requiere_refrigeracion' => 1]);
        $seca     = $this->crearUbicacion(['refrigerada' => 0]);
        $fria     = $this->crearUbicacion(['refrigerada' => 1]);

        $r = $this->sugerirEntre($producto, 5, [$seca, $fria]);

        $codigos = collect($r['candidatas'])->pluck('codigo')->all();

        $this->assertContains($fria->codigo, $codigos, 'Debe ofrecer la ubicación refrigerada');
        $this->assertNotContains($seca->codigo, $codigos, 'No debe ofrecer una ubicación seca');
    }

    /** @test */
    public function la_camara_fria_no_se_ofrece_para_mercancia_seca()
    {
        // El frío es caro y escaso: gastarlo en mercancía seca es un desperdicio.
        $producto = $this->crearProducto(['requiere_refrigeracion' => 0]);
        $seca     = $this->crearUbicacion(['refrigerada' => 0]);
        $fria     = $this->crearUbicacion(['refrigerada' => 1]);

        $r = $this->sugerirEntre($producto, 5, [$seca, $fria]);
        $codigos = collect($r['candidatas'])->pluck('codigo')->all();

        $this->assertContains($seca->codigo, $codigos);
        $this->assertNotContains($fria->codigo, $codigos);
    }

    /** @test */
    public function la_mercancia_peligrosa_solo_va_a_zona_que_la_admite()
    {
        $producto = $this->crearProducto(['peligroso' => 1]);
        $normal   = $this->crearUbicacion(['admite_peligrosos' => 0]);
        $zonaPel  = $this->crearUbicacion(['admite_peligrosos' => 1]);

        $r = $this->sugerirEntre($producto, 5, [$normal, $zonaPel]);
        $codigos = collect($r['candidatas'])->pluck('codigo')->all();

        $this->assertContains($zonaPel->codigo, $codigos);
        $this->assertNotContains($normal->codigo, $codigos);
    }

    /** @test */
    public function se_descarta_la_ubicacion_sin_capacidad_de_peso()
    {
        $producto = $this->crearProducto(['peso_kg' => 50]); // 10 ud = 500 kg
        $chica    = $this->crearUbicacion(['capacidad_peso' => 100, 'capacidad_volumen' => 99]);
        $grande   = $this->crearUbicacion(['capacidad_peso' => 2000, 'capacidad_volumen' => 99]);

        $r = $this->sugerirEntre($producto, 10, [$chica, $grande]);
        $codigos = collect($r['candidatas'])->pluck('codigo')->all();

        $this->assertContains($grande->codigo, $codigos);
        $this->assertNotContains($chica->codigo, $codigos, 'No debe caber en la de 100 kg');
    }

    /** @test */
    public function no_se_sugiere_una_ubicacion_bloqueada_para_putaway()
    {
        $producto  = $this->crearProducto();
        $abierta   = $this->crearUbicacion(['bloqueada_para_putaway' => 0]);
        $bloqueada = $this->crearUbicacion(['bloqueada_para_putaway' => 1]);

        $r = $this->sugerirEntre($producto, 5, [$abierta, $bloqueada]);
        $codigos = collect($r['candidatas'])->pluck('codigo')->all();

        $this->assertContains($abierta->codigo, $codigos);
        $this->assertNotContains($bloqueada->codigo, $codigos);
    }

    /** @test */
    public function un_producto_de_clase_a_prefiere_la_ubicacion_cercana_al_muelle()
    {
        $producto = $this->crearProducto();
        $this->clasificar($producto, 'A');

        $cerca = $this->crearUbicacion(['distancia_muelle_m' => 3,  'clase_abc' => 'A', 'accesibilidad' => 'dorada']);
        $lejos = $this->crearUbicacion(['distancia_muelle_m' => 90, 'clase_abc' => 'C', 'accesibilidad' => 'altura']);

        $r = $this->sugerirEntre($producto, 5, [$cerca, $lejos]);

        $this->assertSame($cerca->codigo, $r['candidatas'][0]['codigo'],
            'Un producto de alta rotación debe ir cerca del muelle');
    }

    /** @test */
    public function un_producto_de_clase_c_no_desperdicia_la_ubicacion_cercana()
    {
        // El matiz clave del slotting por rotación: para un producto C, estar cerca
        // no es un premio. Esa ubicación le hace falta a un producto A.
        $producto = $this->crearProducto();
        $this->clasificar($producto, 'C');

        $cerca = $this->crearUbicacion(['distancia_muelle_m' => 3,  'clase_abc' => 'A', 'accesibilidad' => 'dorada']);
        $lejos = $this->crearUbicacion(['distancia_muelle_m' => 90, 'clase_abc' => 'C', 'accesibilidad' => 'altura']);

        $r = $this->sugerirEntre($producto, 5, [$cerca, $lejos]);

        $this->assertSame($lejos->codigo, $r['candidatas'][0]['codigo'],
            'Un producto de baja rotación debe ir al fondo, no ocupar espacio privilegiado');
    }

    /** @test */
    public function consolidar_gana_sobre_una_ubicacion_vacia_equivalente()
    {
        $producto = $this->crearProducto();
        $this->clasificar($producto, 'B');

        $conStock = $this->crearUbicacion(['distancia_muelle_m' => 20, 'clase_abc' => 'B']);
        $vacia    = $this->crearUbicacion(['distancia_muelle_m' => 20, 'clase_abc' => 'B']);

        WarehouseInventory::create([
            'warehouse_id'  => $conStock->id,
            'inventario_id' => $producto->id,
            'cantidad'      => 25,
            'fecha_entrada' => now()->toDateString(),
            'estado'        => 'disponible',
        ]);

        $r = $this->sugerirEntre($producto, 5, [$conStock, $vacia]);

        $this->assertSame($conStock->codigo, $r['candidatas'][0]['codigo'],
            'Fragmentar un producto entre ubicaciones multiplica los recorridos');
    }

    /** @test */
    public function una_cantidad_que_no_cabe_en_un_hueco_se_reparte_entre_varios()
    {
        // Cada ubicación aguanta 10 unidades por volumen (0.2 m3 / 0.02 m3).
        $producto = $this->crearProducto([
            'largo_cm' => 20, 'ancho_cm' => 20, 'alto_cm' => 50, // 0.02 m3
            'peso_kg'  => 0.1,
        ]);

        $u1 = $this->crearUbicacion(['capacidad_volumen' => 0.2, 'capacidad_unidades' => null, 'capacidad_peso' => null]);
        $u2 = $this->crearUbicacion(['capacidad_volumen' => 0.2, 'capacidad_unidades' => null, 'capacidad_peso' => null]);
        $u3 = $this->crearUbicacion(['capacidad_volumen' => 0.2, 'capacidad_unidades' => null, 'capacidad_peso' => null]);

        $r = (new SlottingService())->sugerirDistribucion($producto, 25, [
            'solo_ids' => [$u1->id, $u2->id, $u3->id],
        ]);

        $this->assertTrue($r['completo'], 'Debe ubicar las 25 unidades');
        $this->assertGreaterThan(1, count($r['asignaciones']), 'Debe usar más de una ubicación');
        $this->assertEquals(25, array_sum(array_column($r['asignaciones'], 'cantidad')));

        // Un producto que se vende por unidad no admite fracciones.
        foreach ($r['asignaciones'] as $a) {
            $this->assertEquals(floor($a['cantidad']), $a['cantidad'],
                'Las cantidades sugeridas deben ser enteras para unidades discretas');
        }
    }

    /** @test */
    public function la_sugerencia_advierte_cuando_los_datos_fisicos_son_estimados()
    {
        $producto = $this->crearProducto(['datos_fisicos_fuente' => 'estimado']);
        $u = $this->crearUbicacion();

        $r = $this->sugerirEntre($producto, 5, [$u]);

        $this->assertTrue($r['datos_estimados']);
        $this->assertNotNull($r['advertencia'], 'Debe advertir que el cubicaje es estimado');
    }

    /** @test */
    public function la_decision_del_operario_queda_registrada_para_medir_el_motor()
    {
        $producto = $this->crearProducto();
        $u1 = $this->crearUbicacion(['distancia_muelle_m' => 5]);
        $u2 = $this->crearUbicacion(['distancia_muelle_m' => 50]);

        $slotting = new SlottingService();
        $r = $this->sugerirEntre($producto, 5, [$u1, $u2]);

        $id = $slotting->registrarSugerencia($r, 'tcr', 'test-ref', null);
        $this->assertNotNull($id);

        // El operario ignora la sugerencia y usa la segunda opción.
        $otra = collect($r['candidatas'])->firstWhere('codigo', '!=', $r['candidatas'][0]['codigo']);
        $slotting->registrarDecision($id, $otra['warehouse_id'], 'Prueba de override');

        $fila = DB::table('putaway_sugerencias')->find($id);

        $this->assertEquals(0, $fila->fue_aceptada, 'Debe marcarse como no aceptada');
        $this->assertEquals('Prueba de override', $fila->motivo_override);
        $this->assertNotNull($fila->posicion_elegida, 'Debe saberse en qué puesto del ranking quedó');
        $this->assertNotNull($fila->decidido_en);
    }
}
