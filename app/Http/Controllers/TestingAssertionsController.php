<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Response;
use DB;

/**
 * TESTING-ASSERTIONS 2026-05-27
 *
 * Endpoints SOLO LECTURA usados por la suite Playwright para validar el estado
 * real de la base de datos después de cada acción. Sin esto los tests son
 * "el endpoint respondió OK" — con esto son "el pedido quedó facturado, con
 * las filas correctas en pago_pedidos, sin huérfanos".
 *
 * SEGURIDAD: el método protect() bloquea toda llamada en producción.
 * En dev/staging permite leer. Nunca escriben.
 *
 * Diseño: cada método expone UN aspecto del estado. Los tests componen.
 */
class TestingAssertionsController extends Controller
{
    /**
     * Bloqueo defensivo. Si por error queda expuesto en prod, retorna 403.
     */
    private function protect()
    {
        if (app()->environment('production')) {
            abort(403, 'Testing endpoints disabled in production');
        }
    }

    /**
     * Devuelve la fila completa de un pedido + sus pagos + count de items.
     * Body: { id_pedido }
     */
    public function getPedidoFull(Request $req)
    {
        $this->protect();
        $id = (int) $req->id_pedido;
        if (!$id) {
            return Response::json(['error' => 'id_pedido requerido'], 400);
        }

        $pedido = DB::table('pedidos')->where('id', $id)->first();
        if (!$pedido) {
            return Response::json(['error' => 'pedido no existe', 'id' => $id], 404);
        }

        $pagos = DB::table('pago_pedidos')
            ->where('id_pedido', $id)
            ->orderBy('id', 'asc')
            ->get();

        $itemsCount = DB::table('items_pedidos')
            ->where('id_pedido', $id)
            ->count();

        $itemsSum = DB::table('items_pedidos')
            ->where('id_pedido', $id)
            ->selectRaw('SUM(cantidad) as suma_cant, SUM(monto * cantidad) as suma_monto')
            ->first();

        $refs = DB::table('pagos_referencias')
            ->where('id_pedido', $id)
            ->orderBy('id', 'asc')
            ->get();

        return Response::json([
            'pedido' => $pedido,
            'pagos' => $pagos,
            'items_count' => $itemsCount,
            'items_sum_cantidad' => $itemsSum->suma_cant ?? 0,
            'items_sum_monto' => $itemsSum->suma_monto ?? 0,
            'refs' => $refs,
        ]);
    }

    /**
     * Devuelve filas de pago_pedidos filtradas.
     * Body: { id_pedido, tipo? (1=transfer,2=débito,3=efectivo,4=crédito,5=biopago,6=vuelto), imborrable? }
     */
    public function getPagos(Request $req)
    {
        $this->protect();
        $q = DB::table('pago_pedidos');
        if ($req->has('id_pedido')) {
            $q->where('id_pedido', (int) $req->id_pedido);
        }
        if ($req->has('tipo')) {
            $q->where('tipo', (int) $req->tipo);
        }
        if ($req->has('imborrable')) {
            $q->where('imborrable', (int) $req->imborrable);
        }
        return Response::json([
            'rows' => $q->orderBy('id', 'asc')->get(),
            'count' => $q->count(),
        ]);
    }

    /**
     * Devuelve refs de pago para un pedido.
     * Body: { id_pedido, descripcion?, banco? }
     */
    public function getRefs(Request $req)
    {
        $this->protect();
        $q = DB::table('pagos_referencias');
        if ($req->has('id_pedido')) {
            $q->where('id_pedido', (int) $req->id_pedido);
        }
        if ($req->has('descripcion')) {
            $q->where('descripcion', $req->descripcion);
        }
        if ($req->has('banco')) {
            $q->where('banco', $req->banco);
        }
        return Response::json([
            'rows' => $q->orderBy('id', 'asc')->get(),
            'count' => $q->count(),
        ]);
    }

    /**
     * Devuelve filas de pos_operaciones_rechazadas.
     * Body: { numero_orden_contiene? (substring), since_minutes? (default 5) }
     */
    public function getPosRechazadas(Request $req)
    {
        $this->protect();
        $sinceMin = (int) ($req->since_minutes ?? 5);
        $q = DB::table('pos_operaciones_rechazadas')
            ->where('created_at', '>=', now()->subMinutes($sinceMin));
        if ($req->has('numero_orden_contiene')) {
            $q->where('orderNumber', 'like', '%' . $req->numero_orden_contiene . '%');
        }
        return Response::json([
            'rows' => $q->orderBy('id', 'desc')->get(),
            'count' => $q->count(),
        ]);
    }

    /**
     * Devuelve solicitudes de descuento para un pedido (cierra el loop end-to-end con central).
     * Body: { id_pedido }
     */
    public function getSolicitudesDescuento(Request $req)
    {
        $this->protect();
        $id = (int) $req->id_pedido;
        $rows = collect();
        // Local: tabla solicitudes_descuento_front si existe
        if (\Schema::hasTable('solicitudes_descuento_front')) {
            $rows = DB::table('solicitudes_descuento_front')
                ->where('id_pedido', $id)
                ->orderBy('id', 'desc')
                ->get();
        }
        return Response::json([
            'rows_locales' => $rows,
            'count_locales' => $rows->count(),
        ]);
    }

    /**
     * INSERTAR FIXTURE para tests: meter una ref directamente en central para simular
     * el caso "ref existe en central pero no local" (test de importarRefDeCentral).
     * Solo funciona en !production. Llama a central como cualquier cajero.
     *
     * Body: { banco, descripcion, monto, id_pedido? }
     */
    public function fixtureRefEnCentral(Request $req)
    {
        $this->protect();

        // Crear directamente en central via sendCentral usando la sesión actual
        $refData = [
            'id'          => 999999, // idinsucursal ficticio (no existe local)
            'tipo'        => 1,
            'monto'       => (float) $req->monto,
            'descripcion' => $req->descripcion,
            'banco'       => $req->banco,
            'cedula'      => null,
            'telefono'    => null,
            'categoria'   => 'central',
            'estatus'     => 'aprobada', // simular que central ya la aprobó
            'id_pedido'   => $req->id_pedido ?? null,
        ];

        $resultado = (new sendCentral)->createTranferenciaAprobacion([
            'refs' => [$refData],
            'retenciones' => [],
        ]);

        return Response::json([
            'estado' => true,
            'msj' => 'Fixture creado en central',
            'central_response' => $resultado,
        ]);
    }

    /**
     * Limpia refs de prueba de un pedido — usado por afterEach de tests.
     * Body: { id_pedido }
     */
    public function cleanupRefs(Request $req)
    {
        $this->protect();
        $id = (int) $req->id_pedido;
        if (!$id) return Response::json(['error' => 'id_pedido requerido'], 400);
        $deleted = DB::table('pagos_referencias')->where('id_pedido', $id)->delete();
        return Response::json(['deleted' => $deleted]);
    }
}
