<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use App\Http\Controllers\sendCentral;
use App\Models\pedidos;
use App\Models\items_pedidos;

/**
 * Verifica el guard: no se puede EXPORTAR a central un pedido sin ítems o con
 * ítems en cantidad 0. El guard debe cortar ANTES de llamar a central.
 */
class ExportGuardItemCeroTest extends TestCase
{
    use DatabaseTransactions;

    private function crearPedido(): pedidos
    {
        $idCliente  = DB::table('clientes')->value('id');
        $idVendedor = DB::table('usuarios')->value('id');
        $this->assertNotNull($idCliente, 'Se requiere un cliente en la BD de pruebas');
        $this->assertNotNull($idVendedor, 'Se requiere un usuario en la BD de pruebas');

        return pedidos::create([
            'estado'      => 0,
            'export'      => 0,
            'id_cliente'  => $idCliente,
            'id_vendedor' => $idVendedor,
        ]);
    }

    /** @return array{0:bool,1:string} estado y msj de la respuesta */
    private function exportar(int $id): array
    {
        $res = (new sendCentral)->setPedidoInCentralFromMaster($id, null, 'add');
        $data = $res instanceof \Illuminate\Http\JsonResponse ? $res->getData(true) : (array) $res;
        return [(bool) ($data['estado'] ?? false), (string) ($data['msj'] ?? '')];
    }

    /** @test */
    public function no_exporta_pedido_con_item_cantidad_cero()
    {
        Http::fake(); // si por error llamara a central, no sale a la red

        $pedido = $this->crearPedido();
        items_pedidos::create([
            'id_pedido'   => $pedido->id,
            'id_producto' => null,
            'cantidad'    => 0,
            'monto'       => 0,
        ]);

        [$estado, $msj] = $this->exportar($pedido->id);

        $this->assertFalse($estado, 'No debería permitir exportar con ítem en cantidad 0');
        $this->assertStringContainsString('cantidad 0', $msj);
        Http::assertNothingSent(); // cortó antes de central
    }

    /** @test */
    public function no_exporta_pedido_sin_items()
    {
        Http::fake();

        $pedido = $this->crearPedido();

        [$estado, $msj] = $this->exportar($pedido->id);

        $this->assertFalse($estado, 'No debería permitir exportar un pedido sin ítems');
        $this->assertStringContainsString('no tiene ítems', $msj);
        Http::assertNothingSent();
    }
}
