<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\TCDController;
use App\Models\inventario;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\TCDOrden;
use App\Models\TCDOrdenItem;
use App\Models\TCDAsignacion;
use App\Models\TCDTicketDespacho;

/**
 * Pruebas del flujo de ENVÍO / DESPACHO (TCD).
 *
 * Usa DatabaseTransactions: cada test corre dentro de una transacción que se
 * revierte al terminar, así NO se altera la data de la BD local de pruebas.
 *
 * Invariantes que verifica:
 *  - confirmarOrden descuenta del inventario general y de la ubicación, y bloquea.
 *  - escanearTicketDespacho libera el bloqueo y cambia el estado de la orden.
 */
class TCDDespachoTest extends TestCase
{
    use DatabaseTransactions;

    /** id de usuario válido para chequeador/pasillero (no debe romper FKs) */
    private function usuarioId(): int
    {
        return (int) (DB::table('usuarios')->value('id') ?? 1);
    }

    private function crearProducto(float $cantidad): inventario
    {
        return inventario::create([
            'codigo_barras' => 'TCDTEST-' . uniqid(),
            'codigo_proveedor' => 'CPTEST-' . uniqid(),
            'descripcion'   => 'Producto TCD de prueba',
            'cantidad'      => $cantidad,
            'precio'        => 10,
            'precio_base'   => 8,
        ]);
    }

    private function crearUbicacion(): Warehouse
    {
        return Warehouse::create([
            'pasillo'  => 'Z',
            'cara'     => 9,
            'rack'     => 9,
            'nivel'    => 9,
            'codigo'   => 'ZTEST-' . uniqid(),
            'nombre'   => 'Ubicación de prueba',
            'descripcion' => 'fixture test',
            'zona'     => 'Z',
            'tipo'     => 'almacenamiento',
            'estado'   => 'activa',
        ]);
    }

    /**
     * Arma una orden TCD "completada" lista para confirmar, con:
     *  - producto con `invGeneral` en inventario general
     *  - ubicación con `enUbicacion` disponible
     *  - asignación de `procesada` unidades ya procesadas por el pasillero
     */
    private function armarOrdenCompletada(float $invGeneral, float $enUbicacion, float $procesada): array
    {
        $uid = $this->usuarioId();
        $producto  = $this->crearProducto($invGeneral);
        $ubicacion = $this->crearUbicacion();

        $wi = WarehouseInventory::create([
            'warehouse_id'      => $ubicacion->id,
            'inventario_id'     => $producto->id,
            'cantidad'          => $enUbicacion,
            'cantidad_bloqueada' => 0,
            'estado'            => 'disponible',
            'fecha_entrada'     => date('Y-m-d'),
        ]);

        $orden = TCDOrden::create([
            'numero_orden'  => 'TCD #TEST-' . uniqid(),
            'chequeador_id' => $uid,
            'estado'        => 'completada',
        ]);

        $item = TCDOrdenItem::create([
            'tcd_orden_id'       => $orden->id,
            'inventario_id'      => $producto->id,
            'codigo_barras'      => $producto->codigo_barras,
            'codigo_proveedor'   => $producto->codigo_proveedor,
            'descripcion'        => $producto->descripcion,
            'precio'             => 10,
            'precio_base'        => 8,
            'ubicacion'          => $ubicacion->codigo,
            'cantidad'           => $procesada,
            'cantidad_descontada' => 0,
            'cantidad_bloqueada' => 0,
        ]);

        TCDAsignacion::create([
            'tcd_orden_id'      => $orden->id,
            'tcd_orden_item_id' => $item->id,
            'pasillero_id'      => $uid,
            'cantidad'          => $procesada,
            'cantidad_procesada' => $procesada,
            'estado'            => 'completada',
            'warehouse_codigo'  => $ubicacion->codigo,
            'warehouse_id'      => $ubicacion->id,
        ]);

        session(['id_usuario' => $uid]);

        return compact('producto', 'ubicacion', 'wi', 'orden', 'item');
    }

    /** @test */
    public function confirmar_orden_descuenta_inventario_general_y_bloquea_en_ubicacion()
    {
        ['producto' => $p, 'wi' => $wi, 'orden' => $orden, 'item' => $item]
            = $this->armarOrdenCompletada(invGeneral: 100, enUbicacion: 50, procesada: 10);

        $resp = (new TCDController)->confirmarOrden(
            Request::create('/tcd/confirmar', 'POST', ['orden_id' => $orden->id])
        );
        $data = $resp->getData(true);

        $this->assertTrue($data['estado'] ?? false, $data['msj'] ?? 'confirmarOrden falló');

        // Inventario general: 100 - 10 = 90
        $this->assertEquals(90, (float) $p->fresh()->cantidad, 'inventario general mal descontado');

        // Ubicación: cantidad 50 -> 40, bloqueada 0 -> 10
        $wiFresh = $wi->fresh();
        $this->assertEquals(40, (float) $wiFresh->cantidad, 'cantidad en ubicación mal descontada');
        $this->assertEquals(10, (float) $wiFresh->cantidad_bloqueada, 'cantidad bloqueada incorrecta');

        // Item de la orden refleja lo descontado/bloqueado
        $itemFresh = $item->fresh();
        $this->assertEquals(10, (float) $itemFresh->cantidad_descontada);
        $this->assertEquals(10, (float) $itemFresh->cantidad_bloqueada);

        // Se generó el ticket de despacho en estado 'generado'
        $this->assertNotEmpty($data['ticket_codigo_barras'] ?? null);
        $this->assertDatabaseHas('tcd_tickets_despacho', [
            'codigo_barras' => $data['ticket_codigo_barras'],
            'estado' => 'generado',
        ]);
    }

    /** @test */
    public function escanear_ticket_libera_bloqueo_y_marca_orden_despachada()
    {
        ['wi' => $wi, 'orden' => $orden]
            = $this->armarOrdenCompletada(invGeneral: 100, enUbicacion: 50, procesada: 10);

        // Paso 1: confirmar (deja 10 bloqueadas, ticket 'generado')
        $confirm = (new TCDController)->confirmarOrden(
            Request::create('/tcd/confirmar', 'POST', ['orden_id' => $orden->id])
        )->getData(true);
        $this->assertTrue($confirm['estado'] ?? false, $confirm['msj'] ?? '');
        $codigo = $confirm['ticket_codigo_barras'];

        $this->assertEquals(10, (float) $wi->fresh()->cantidad_bloqueada);

        // Paso 2: escanear el ticket de despacho
        $resp = (new TCDController)->escanearTicketDespacho(
            Request::create('/tcd/escanear', 'POST', ['codigo_barras' => $codigo])
        );
        $data = $resp->getData(true);
        $this->assertTrue($data['estado'] ?? false, $data['msj'] ?? 'escanear falló');

        // Bloqueo liberado: 10 -> 0 ; cantidad real intacta (40)
        $wiFresh = $wi->fresh();
        $this->assertEquals(0, (float) $wiFresh->cantidad_bloqueada, 'no se liberó el bloqueo');
        $this->assertEquals(40, (float) $wiFresh->cantidad, 'la cantidad real no debe cambiar al despachar');

        // Estado de la orden -> despachada ; ticket -> escaneado
        $this->assertEquals('despachada', $orden->fresh()->estado);
        $this->assertDatabaseHas('tcd_tickets_despacho', [
            'codigo_barras' => $codigo,
            'estado' => 'escaneado',
        ]);
    }
}
