<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Models\ConteoCiclicoDetalle;
use App\Models\ProductoAbc;
use App\Models\TmsConductor;
use App\Models\TmsVehiculo;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseMovement;
use App\Models\inventario;
use App\Services\Wms\ConteoCiclicoService;
use App\Services\Wms\PlanificacionCargaService;

/**
 * Pruebas del conteo cíclico por ubicación y de la planificación de carga (TMS).
 */
class WmsConteoYTmsTest extends TestCase
{
    use DatabaseTransactions;

    private function crearProducto(array $attrs = []): inventario
    {
        return inventario::create(array_merge([
            'codigo_barras' => 'CCTEST-' . uniqid(),
            'descripcion'   => 'Producto conteo de prueba',
            'cantidad'      => 100,
            'precio'        => 10,
            'precio_base'   => 4,
            'unidad'        => 'UND',
            'peso_kg'       => 2.0,
            'largo_cm'      => 20, 'ancho_cm' => 20, 'alto_cm' => 25, // 0.01 m3
            'unidades_por_bulto' => 5,
            'datos_fisicos_fuente' => 'medido',
        ], $attrs));
    }

    private function crearUbicacion(): Warehouse
    {
        static $n = 0;
        $n++;

        return Warehouse::create([
            'pasillo' => 'K', 'cara' => 1, 'rack' => $n, 'nivel' => 1,
            'codigo'  => 'CC' . $n . '-' . uniqid(),
            'tipo'    => 'almacenamiento', 'estado' => 'activa',
        ]);
    }

    /** Prepara una ubicación con stock y su clasificación ABC. */
    private function escenarioConteo(float $cantidad, string $clase = 'A'): array
    {
        $p = $this->crearProducto();
        $u = $this->crearUbicacion();

        ProductoAbc::create([
            'inventario_id' => $p->id, 'criterio' => config('wms.abc.criterio_slotting'),
            'periodo_inicio' => now()->subDays(30)->toDateString(),
            'periodo_fin' => now()->toDateString(),
            'metrica' => 1, 'clase' => $clase, 'ranking' => 1, 'calculado_en' => now(),
        ]);

        $wi = WarehouseInventory::create([
            'warehouse_id' => $u->id, 'inventario_id' => $p->id,
            'cantidad' => $cantidad, 'fecha_entrada' => now()->toDateString(),
            'estado' => 'disponible',
        ]);

        return [$p, $u, $wi];
    }

    // -------------------------------------------------------- Conteo cíclico

    /** @test */
    public function una_diferencia_exige_recuento_antes_de_poder_ajustar()
    {
        [$p, $u] = $this->escenarioConteo(50);

        $svc = new ConteoCiclicoService();
        $conteo = $svc->generar(['tipo' => 'ubicaciones', 'warehouse_ids' => [$u->id], 'limite' => 5]);

        $detalle = ConteoCiclicoDetalle::where('conteo_id', $conteo->id)->first();
        $this->assertNotNull($detalle);

        $r = $svc->registrarConteo($detalle->id, 47); // faltan 3
        $this->assertTrue($r['requiere_recuento'], 'Una diferencia debe disparar recuento');

        $ajuste = $svc->ajustar($conteo->id);
        $this->assertFalse($ajuste['estado'], 'No se puede ajustar con recuentos pendientes');
    }

    /** @test */
    public function el_ajuste_corrige_el_stock_y_deja_rastro_en_el_kardex()
    {
        [$p, $u, $wi] = $this->escenarioConteo(50);

        $svc = new ConteoCiclicoService();
        $conteo = $svc->generar(['tipo' => 'ubicaciones', 'warehouse_ids' => [$u->id], 'limite' => 5]);
        $detalle = ConteoCiclicoDetalle::where('conteo_id', $conteo->id)->first();

        $svc->registrarConteo($detalle->id, 47);  // primer conteo
        $svc->registrarConteo($detalle->id, 47);  // recuento confirma

        $r = $svc->ajustar($conteo->id);

        $this->assertTrue($r['estado']);
        $this->assertEquals(47, (float) $wi->fresh()->cantidad, 'El stock debe quedar en lo contado');

        // Un cuadre sin rastro es indistinguible de un faltante no reportado.
        $mov = WarehouseMovement::where('documento_referencia', $conteo->codigo)->first();
        $this->assertNotNull($mov, 'Todo ajuste debe generar un movimiento');
        $this->assertSame('ajuste', $mov->tipo);
        $this->assertEquals(3, (float) $mov->cantidad);
        $this->assertEquals($u->id, $mov->warehouse_origen_id, 'Un faltante sale de la ubicación');
    }

    /** @test */
    public function no_se_acepta_una_cantidad_contada_negativa()
    {
        // Contar no puede dar menos que cero; aceptarlo dejaría stock negativo.
        [$p, $u] = $this->escenarioConteo(10);

        $svc = new ConteoCiclicoService();
        $conteo = $svc->generar(['tipo' => 'ubicaciones', 'warehouse_ids' => [$u->id], 'limite' => 5]);
        $detalle = ConteoCiclicoDetalle::where('conteo_id', $conteo->id)->first();

        $r = $svc->registrarConteo($detalle->id, -5);

        $this->assertFalse($r['estado']);
        $this->assertSame('pendiente', $detalle->fresh()->estado, 'La línea no debe alterarse');
    }

    /** @test */
    public function un_conteo_exacto_no_genera_movimiento_de_ajuste()
    {
        [$p, $u, $wi] = $this->escenarioConteo(30);

        $svc = new ConteoCiclicoService();
        $conteo = $svc->generar(['tipo' => 'ubicaciones', 'warehouse_ids' => [$u->id], 'limite' => 5]);
        $detalle = ConteoCiclicoDetalle::where('conteo_id', $conteo->id)->first();

        $svc->registrarConteo($detalle->id, 30);
        $r = $svc->ajustar($conteo->id);

        $this->assertTrue($r['estado']);
        $this->assertEquals(0, $r['lineas_ajustadas']);
        $this->assertEquals(30, (float) $wi->fresh()->cantidad);
        $this->assertEquals(100.0, (float) $conteo->fresh()->exactitud_pct);
    }

    // -------------------------------------------------------------------- TMS

    private function flota(): array
    {
        $conductor = TmsConductor::create([
            'nombre' => 'Conductor Prueba',
            'licencia_vence' => now()->addYear()->toDateString(),
        ]);

        $grande = TmsVehiculo::create([
            'placa' => 'TST-' . substr(uniqid(), -6), 'tipo' => 'camion',
            'capacidad_peso_kg' => 3000, 'capacidad_volumen_m3' => 20,
            'conductor_habitual_id' => $conductor->id,
        ]);
        $chico = TmsVehiculo::create([
            'placa' => 'TST-' . substr(uniqid(), -6), 'tipo' => 'camioneta',
            'capacidad_peso_kg' => 600, 'capacidad_volumen_m3' => 3,
        ]);

        return [$grande, $chico, $conductor];
    }

    /** @test */
    public function el_cubicaje_suma_peso_y_volumen_de_los_items()
    {
        $p = $this->crearProducto(); // 2 kg, 0.01 m3, 5 ud por bulto

        $c = (new PlanificacionCargaService())->cubicar([
            ['inventario_id' => $p->id, 'cantidad' => 10],
        ]);

        $this->assertEquals(20.0, $c['peso_kg']);
        $this->assertEquals(0.1, $c['volumen_m3']);
        $this->assertEquals(2, $c['bultos'], '10 unidades a 5 por bulto son 2 bultos');
    }

    /** @test */
    public function el_cubicaje_reporta_los_productos_sin_ficha_fisica()
    {
        // Si un producto no tiene peso, la carga calculada va corta y hay que decirlo.
        $sinFicha = $this->crearProducto(['peso_kg' => null, 'largo_cm' => null, 'ancho_cm' => null, 'alto_cm' => null]);

        $c = (new PlanificacionCargaService())->cubicar([
            ['inventario_id' => $sinFicha->id, 'cantidad' => 10],
        ]);

        $this->assertContains($sinFicha->id, $c['sin_datos']);
        $this->assertEquals(0.0, $c['peso_kg']);
    }

    /** @test */
    public function la_carga_se_asigna_al_vehiculo_mas_pequeno_que_la_admita()
    {
        // Mandar 40 kg en un camión de 3 toneladas cuesta de más y bloquea el camión.
        [$grande, $chico] = $this->flota();
        $p = $this->crearProducto();

        $plan = (new PlanificacionCargaService())->planificar([
            ['destino_nombre' => 'Cliente 1', 'items' => [['inventario_id' => $p->id, 'cantidad' => 20]]],
        ], ['vehiculo_ids' => [$grande->id, $chico->id]]);

        $this->assertCount(1, $plan['rutas']);
        $this->assertSame($chico->placa, $plan['rutas'][0]['placa'],
            'Debe elegir la camioneta, no el camión');
    }

    /** @test */
    public function una_carga_que_excede_la_capacidad_no_se_asigna()
    {
        [$grande, $chico] = $this->flota();
        $pesado = $this->crearProducto(['peso_kg' => 500]); // 20 ud = 10 000 kg

        $plan = (new PlanificacionCargaService())->planificar([
            ['destino_nombre' => 'Cliente pesado', 'items' => [['inventario_id' => $pesado->id, 'cantidad' => 20]]],
        ], ['vehiculo_ids' => [$grande->id, $chico->id]]);

        $this->assertCount(0, $plan['rutas']);
        $this->assertCount(1, $plan['sin_asignar'], 'Debe reportarse como no asignable');
    }

    /** @test */
    public function las_rutas_creadas_calculan_totales_y_utilizacion()
    {
        [$grande, $chico] = $this->flota();
        $p = $this->crearProducto();

        $svc = new PlanificacionCargaService();
        $plan = $svc->planificar([
            ['destino_nombre' => 'A', 'latitud' => 10.24, 'longitud' => -67.59,
             'items' => [['inventario_id' => $p->id, 'cantidad' => 10]]],
            ['destino_nombre' => 'B', 'latitud' => 10.22, 'longitud' => -67.47,
             'items' => [['inventario_id' => $p->id, 'cantidad' => 10]]],
        ], ['vehiculo_ids' => [$grande->id, $chico->id]]);

        $rutas = $svc->crearRutas($plan['rutas'], ['fecha' => now()->toDateString()]);

        $this->assertCount(1, $rutas);
        $ruta = $rutas[0];

        $this->assertEquals(2, $ruta->paradas->count());
        $this->assertEquals(40.0, (float) $ruta->peso_total_kg, '20 unidades a 2 kg');
        $this->assertNotNull($ruta->utilizacion_peso_pct);
        $this->assertGreaterThan(0, $ruta->distancia_estimada_km, 'Con coordenadas debe estimar distancia');
        $this->assertGreaterThan(0, $ruta->tiempo_estimado_min);
    }

    // ------------------------------------------------------- Costura TCD -> TMS

    /** Orden TCD completada, con cantidad pedida distinta de la recolectada. */
    private function ordenTcd(int $items = 2, float $pedida = 10, float $recolectada = 8): \App\Models\TCDOrden
    {
        $orden = \App\Models\TCDOrden::create([
            'numero_orden'   => 'TCD-TEST-' . uniqid(),
            'chequeador_id'  => (int) (\Illuminate\Support\Facades\DB::table('usuarios')->value('id') ?? 1),
            'estado'         => 'completada',
        ]);

        for ($i = 0; $i < $items; $i++) {
            $p = $this->crearProducto();
            \App\Models\TCDOrdenItem::create([
                'tcd_orden_id'       => $orden->id,
                'inventario_id'      => $p->id,
                'codigo_barras'      => $p->codigo_barras,
                'descripcion'        => $p->descripcion,
                'precio'             => $p->precio,
                'cantidad'           => $pedida,
                'cantidad_descontada' => $recolectada,
                'cantidad_bloqueada' => 0,
            ]);
        }

        return $orden;
    }

    /** @test */
    public function una_orden_tcd_despachada_se_convierte_en_parada_de_ruta()
    {
        $this->flota();
        $orden = $this->ordenTcd(2, 10, 8);

        $req = new \Illuminate\Http\Request();
        $req->merge(['orden_ids' => [$orden->id]]);

        $r = (new \App\Http\Controllers\TmsController())->rutasDesdeTcd($req)->getData(true);

        $this->assertTrue($r['estado'], $r['msj'] ?? '');

        $ruta = \App\Models\TmsRuta::with('paradas.items')->find($r['rutas'][0]['id']);
        $parada = $ruta->paradas->first();

        $this->assertEquals($orden->id, $parada->tcd_orden_id, 'La parada debe quedar ligada a la orden');
        $this->assertCount(2, $parada->items);

        // Lo que viaja es lo que se recolectó, no lo que se pidió.
        $this->assertEquals(8.0, (float) $parada->items->first()->cantidad);
    }

    /** @test */
    public function una_orden_tcd_no_completada_no_se_puede_rutear()
    {
        $this->flota();
        $orden = $this->ordenTcd();
        $orden->update(['estado' => 'en_proceso']);

        $req = new \Illuminate\Http\Request();
        $req->merge(['orden_ids' => [$orden->id]]);

        $r = (new \App\Http\Controllers\TmsController())->rutasDesdeTcd($req)->getData(true);

        $this->assertFalse($r['estado'], 'No se transporta lo que aún no salió del almacén');
    }

    /** @test */
    public function una_orden_tcd_no_se_puede_montar_en_dos_rutas()
    {
        $this->flota();
        $orden = $this->ordenTcd();

        $req = new \Illuminate\Http\Request();
        $req->merge(['orden_ids' => [$orden->id]]);

        $ctrl = new \App\Http\Controllers\TmsController();
        $primera = $ctrl->rutasDesdeTcd($req)->getData(true);
        $this->assertTrue($primera['estado']);

        $segunda = $ctrl->rutasDesdeTcd($req)->getData(true);
        $this->assertFalse($segunda['estado'], 'La misma carga no puede ir en dos camiones');
    }

    /** @test */
    public function la_bandeja_de_planificacion_excluye_las_ordenes_ya_ruteadas()
    {
        $this->flota();
        $orden = $this->ordenTcd();

        $ctrl = new \App\Http\Controllers\TmsController();

        $antes = collect($ctrl->tcdPendientesDeRuta(new \Illuminate\Http\Request())->getData(true)['ordenes']);
        $this->assertTrue($antes->contains('numero_orden', $orden->numero_orden));

        $req = new \Illuminate\Http\Request();
        $req->merge(['orden_ids' => [$orden->id]]);
        $ctrl->rutasDesdeTcd($req);

        $despues = collect($ctrl->tcdPendientesDeRuta(new \Illuminate\Http\Request())->getData(true)['ordenes']);
        $this->assertFalse($despues->contains('numero_orden', $orden->numero_orden),
            'Una orden ya ruteada no debe seguir en la bandeja');
    }

    /** @test */
    public function el_conductor_con_licencia_vencida_no_esta_vigente()
    {
        $vencido = TmsConductor::create([
            'nombre' => 'Licencia vencida',
            'licencia_vence' => now()->subDay()->toDateString(),
        ]);
        $vigente = TmsConductor::create([
            'nombre' => 'Licencia al día',
            'licencia_vence' => now()->addMonth()->toDateString(),
        ]);
        $sinDato = TmsConductor::create(['nombre' => 'Sin licencia registrada']);

        $this->assertFalse($vencido->licenciaVigente());
        $this->assertTrue($vigente->licenciaVigente());
        $this->assertTrue($sinDato->licenciaVigente(), 'Sin dato no se bloquea la operación');
    }
}
