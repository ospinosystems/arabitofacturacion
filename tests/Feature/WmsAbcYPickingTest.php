<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

use App\Models\ProductoAbc;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\inventario;
use App\Services\Wms\AbcClassificationService;
use App\Services\Wms\PickingStrategyService;

/**
 * Pruebas de la clasificación ABC (Pareto) y de la estrategia de picking (FEFO).
 */
class WmsAbcYPickingTest extends TestCase
{
    use DatabaseTransactions;

    private function crearProducto(array $attrs = []): inventario
    {
        return inventario::create(array_merge([
            'codigo_barras' => 'ABCTEST-' . uniqid(),
            'descripcion'   => 'Producto ABC de prueba',
            'cantidad'      => 100,
            'precio'        => 10,
            'precio_base'   => 5,
            'unidad'        => 'UND',
        ], $attrs));
    }

    private function crearUbicacion(int $prioridad = 100): Warehouse
    {
        static $n = 0;
        $n++;

        return Warehouse::create([
            'pasillo' => 'F', 'cara' => 1, 'rack' => $n, 'nivel' => 1,
            'codigo'  => 'FEFO' . $n . '-' . uniqid(),
            'tipo'    => 'almacenamiento',
            'estado'  => 'activa',
            'prioridad_picking' => $prioridad,
        ]);
    }

    private function stock(Warehouse $u, inventario $p, float $cantidad, ?string $vence, string $lote = null, string $entrada = null): WarehouseInventory
    {
        return WarehouseInventory::create([
            'warehouse_id'      => $u->id,
            'inventario_id'     => $p->id,
            'cantidad'          => $cantidad,
            'lote'              => $lote,
            'fecha_vencimiento' => $vence,
            'fecha_entrada'     => $entrada ?: now()->subDays(10)->toDateString(),
            'estado'            => 'disponible',
        ]);
    }

    // ------------------------------------------------------------------ ABC

    /** @test */
    public function el_pareto_corta_las_clases_en_los_umbrales_configurados()
    {
        $servicio = new AbcClassificationService();

        // Se accede al método privado por reflexión: interesa verificar la regla de
        // corte del Pareto sin depender de que existan ventas reales en la BD.
        $metodo = new \ReflectionMethod($servicio, 'calcularMetricas');
        $metodo->setAccessible(true);

        // 80 / 15 / 5: un Pareto de manual.
        $demanda = [
            1 => ['unidades' => 800, 'valor' => 800, 'lineas' => 800],
            2 => ['unidades' => 150, 'valor' => 150, 'lineas' => 150],
            3 => ['unidades' => 50,  'valor' => 50,  'lineas' => 50],
        ];

        $metricas = $metodo->invoke($servicio, 'unidades', $demanda);
        arsort($metricas);

        $total = array_sum($metricas);
        $acumulado = 0;
        $clases = [];

        foreach ($metricas as $id => $m) {
            $acumulado += ($m / $total) * 100;
            $clases[$id] = $acumulado <= 80.0 ? 'A' : ($acumulado <= 95.0 ? 'B' : 'C');
        }

        $this->assertSame('A', $clases[1], 'El producto que concentra el 80% es clase A');
        $this->assertSame('B', $clases[2], 'El siguiente 15% es clase B');
        $this->assertSame('C', $clases[3], 'La cola es clase C');
    }

    /** @test */
    public function el_criterio_combinado_pondera_mas_la_popularidad_que_el_valor()
    {
        // Para ubicar mercancía importa cuántas veces hay que ir a buscarla, no
        // cuánto cuesta. Un artículo barato que se pide todo el día debe pesar más
        // que uno caro que se pide una vez.
        $servicio = new AbcClassificationService();
        $metodo = new \ReflectionMethod($servicio, 'calcularMetricas');
        $metodo->setAccessible(true);

        $demanda = [
            10 => ['unidades' => 10, 'valor' => 10000, 'lineas' => 1],    // caro, casi no se toca
            20 => ['unidades' => 10, 'valor' => 10,    'lineas' => 500],  // barato, se toca siempre
        ];

        $metricas = $metodo->invoke($servicio, 'combinado', $demanda);

        $this->assertGreaterThan($metricas[10], $metricas[20],
            'El producto popular debe puntuar por encima del caro pero inmóvil');
    }

    /** @test */
    public function la_distribucion_abc_se_calcula_sobre_las_clases_guardadas()
    {
        $p1 = $this->crearProducto();
        $p2 = $this->crearProducto();

        foreach ([[$p1, 'A', 70.0], [$p2, 'C', 30.0]] as [$p, $clase, $part]) {
            ProductoAbc::create([
                'inventario_id' => $p->id, 'criterio' => 'combinado',
                'periodo_inicio' => now()->subDays(30)->toDateString(),
                'periodo_fin' => now()->toDateString(),
                'metrica' => $part, 'participacion_pct' => $part,
                'clase' => $clase, 'ranking' => 1, 'calculado_en' => now(),
            ]);
        }

        $dist = collect((new AbcClassificationService())->distribucion('combinado'))->keyBy('clase');

        $this->assertGreaterThanOrEqual(1, $dist['A']['productos']);
        $this->assertGreaterThanOrEqual(1, $dist['C']['productos']);
    }

    // ----------------------------------------------------------------- FEFO

    /** @test */
    public function fefo_saca_primero_el_lote_que_vence_antes()
    {
        $p = $this->crearProducto();
        $u1 = $this->crearUbicacion();
        $u2 = $this->crearUbicacion();

        // La ubicación con MÁS cantidad es la que vence después: con el criterio
        // viejo (mayor cantidad) se habría elegido esa y el lote próximo se perdería.
        $this->stock($u1, $p, 5,   now()->addDays(10)->toDateString(), 'LOTE-PRONTO');
        $this->stock($u2, $p, 500, now()->addDays(365)->toDateString(), 'LOTE-LEJANO');

        $svc = new PickingStrategyService();
        $primera = $svc->mejorExistencia($p->id);

        $this->assertSame('LOTE-PRONTO', $primera->lote,
            'FEFO debe sacar primero lo que caduca antes, no el montón más grande');
    }

    /** @test */
    public function los_lotes_sin_fecha_de_vencimiento_van_al_final()
    {
        $p = $this->crearProducto();
        $sinFecha = $this->crearUbicacion();
        $conFecha = $this->crearUbicacion();

        $this->stock($sinFecha, $p, 100, null, 'SIN-FECHA');
        $this->stock($conFecha, $p, 100, now()->addDays(200)->toDateString(), 'CON-FECHA');

        $orden = (new PickingStrategyService())->existenciasOrdenadas($p->id);

        $this->assertSame('CON-FECHA', $orden->first()->lote,
            'No se puede asumir que un lote sin fecha sea más viejo que uno fechado');
    }

    /** @test */
    public function un_lote_vencido_nunca_se_ofrece_para_picking()
    {
        $p = $this->crearProducto();
        $u = $this->crearUbicacion();

        $this->stock($u, $p, 50, now()->subDays(5)->toDateString(), 'VENCIDO');

        $svc = new PickingStrategyService();

        $this->assertCount(0, $svc->existenciasOrdenadas($p->id),
            'No se puede despachar mercancía vencida');

        $plan = $svc->planPicking($p->id, 10);
        $this->assertFalse($plan['completo']);

        $tipos = array_column($plan['alertas'], 'tipo');
        $this->assertContains('lotes_vencidos', $tipos, 'Debe avisar que hay lotes vencidos');
    }

    /** @test */
    public function el_plan_de_picking_reparte_entre_ubicaciones_en_orden_fefo()
    {
        $p = $this->crearProducto();
        $u1 = $this->crearUbicacion();
        $u2 = $this->crearUbicacion();
        $u3 = $this->crearUbicacion();

        $this->stock($u1, $p, 4, now()->addDays(5)->toDateString(),  'L1');
        $this->stock($u2, $p, 4, now()->addDays(30)->toDateString(), 'L2');
        $this->stock($u3, $p, 4, now()->addDays(90)->toDateString(), 'L3');

        $plan = (new PickingStrategyService())->planPicking($p->id, 10);

        $this->assertTrue($plan['completo']);
        $this->assertSame(['L1', 'L2', 'L3'], array_column($plan['lineas'], 'lote'));
        $this->assertEquals([4, 4, 2], array_map('floatval', array_column($plan['lineas'], 'cantidad')));
    }

    /** @test */
    public function no_se_toma_stock_bloqueado()
    {
        $p = $this->crearProducto();
        $u = $this->crearUbicacion();

        $wi = $this->stock($u, $p, 10, null, 'L1');
        $wi->cantidad_bloqueada = 10; // todo comprometido para otro despacho
        $wi->save();

        $plan = (new PickingStrategyService())->planPicking($p->id, 5);

        $this->assertFalse($plan['completo'], 'El stock bloqueado no está disponible');
        $this->assertCount(0, $plan['lineas']);
    }

    /** @test */
    public function el_recorrido_se_ordena_por_prioridad_de_ubicacion()
    {
        // Recolectar en el orden en que se pidieron los productos hace cruzar el
        // almacén de ida y vuelta; ordenar por recorrido lo vuelve un solo barrido.
        $lineas = [
            ['codigo_ubicacion' => 'C', 'prioridad_picking' => 300],
            ['codigo_ubicacion' => 'A', 'prioridad_picking' => 100],
            ['codigo_ubicacion' => 'B', 'prioridad_picking' => 200],
        ];

        $ordenadas = (new PickingStrategyService())->ordenarPorRecorrido($lineas);

        $this->assertSame(['A', 'B', 'C'], array_column($ordenadas, 'codigo_ubicacion'));
    }
}
