<?php

namespace App\Http\Controllers;

use App\Models\inventario;
use App\Models\TransferenciaAsignacion;
use App\Models\TransferenciaBulto;
use App\Models\TransferenciaBultoItem;
use App\Models\transferencias_inventario;
use App\Models\transferencias_inventario_items;
use App\Models\usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

/**
 * Flujo de despacho con recolección y bultos para el TCD (módulo React).
 *
 * Ciclo:
 *   guardarOrden (estado=0, NO descuenta)  →  asignarLineas a pasilleros  →
 *   el pasillero recolecta (recolectarLinea) → el checkeador reconcuenta →
 *   crear/cerrar BULTOS con su mercancía → despachar escaneando bulto por bulto
 *   (descontarInventario atómico por bulto) → finalizarDespacho (crea el espejo en
 *   central con lo realmente despachado) → imprimir orden/factura.
 *
 * La mercancía mandada a recolectar pero no empacada queda EXCLUIDA (no se descuenta
 * ni se manda a central) y se muestra en reporteExcluidos.
 *
 * Estados de la orden (transferencias_inventarios.estado):
 *   0 = EN PREPARACIÓN (local, sin descontar, sin espejo)
 *   1 = DESPACHADA (espejo creado en central; sigue el ciclo normal 1→3→4→2)
 */
class TransferenciaDespachoController extends Controller
{
    // ───────────────────────────── helpers ─────────────────────────────

    private function uid()
    {
        return session('id_usuario');
    }

    private function esDici(): bool
    {
        return (int) session('tipo_usuario') === 7 || in_array((int) session('tipo_usuario'), [1, 6]);
    }

    /** Cantidad total empacada en bultos para una línea (item) de la orden. */
    private function empacadoPorItem($idItem): float
    {
        return (float) TransferenciaBultoItem::where('id_transferencia_item', $idItem)->sum('cantidad');
    }

    /** Cantidad total recolectada por los pasilleros para una línea. */
    private function recolectadoPorItem($idItem): float
    {
        return (float) TransferenciaAsignacion::where('id_transferencia_item', $idItem)->sum('cantidad_recolectada');
    }

    /** Cuánto se puede empacar todavía de una línea = (recolectado o solicitado) − ya empacado. */
    private function empacableRestante(transferencias_inventario_items $item): float
    {
        $tieneAsignaciones = TransferenciaAsignacion::where('id_transferencia_item', $item->id)->exists();
        $tope = $tieneAsignaciones ? $this->recolectadoPorItem($item->id) : (float) $item->cantidad;
        return max(0, $tope - $this->empacadoPorItem($item->id));
    }

    // ───────────────────── Fase 0: orden pendiente ─────────────────────

    /**
     * Guarda/actualiza una orden de transferencia en estado preparación (0) SIN descontar
     * inventario ni crear espejo en central. Reemplaza al "Crear Transferencia" inmediato.
     */
    public function guardarOrden(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador (DICI) puede armar órdenes.']);
        }
        DB::beginTransaction();
        try {
            $items = $req->items ?? [];
            if (!count($items)) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'La orden no tiene ítems.']);
            }

            if ($req->id) {
                $orden = transferencias_inventario::find($req->id);
                if (!$orden) {
                    DB::rollBack();
                    return Response::json(['estado' => false, 'msj' => 'Orden no encontrada.']);
                }
                if ((int) $orden->estado !== 0) {
                    DB::rollBack();
                    return Response::json(['estado' => false, 'msj' => 'Solo se puede editar una orden en preparación.']);
                }
                // al reeditar, los ítems viejos (y sus asignaciones/bultos por cascade) se rehacen
                transferencias_inventario_items::where('id_transferencia', $orden->id)->delete();
                $orden->id_destino = $req->id_destino;
                $orden->observacion = $req->observaciones;
                // preservar el vínculo con la redistribución si ya lo tenía; solo setear si viene
                if ($req->filled('id_orden_distribucion')) {
                    $orden->id_orden_distribucion = (int) $req->id_orden_distribucion;
                }
                $orden->save();
            } else {
                $orden = transferencias_inventario::create([
                    'id_transferencia_central' => null,
                    'id_orden_distribucion' => $req->filled('id_orden_distribucion') ? (int) $req->id_orden_distribucion : null,
                    'id_destino' => $req->id_destino,
                    'id_usuario' => $this->uid(),
                    'estado' => 0,
                    'observacion' => $req->observaciones,
                ]);
            }

            foreach ($items as $it) {
                $idProd = (int) ($it['id_producto_insucursal'] ?? $it['id_producto'] ?? 0);
                $producto = inventario::find($idProd);
                if (!$producto) {
                    continue;
                }
                transferencias_inventario_items::create([
                    'id_transferencia' => $orden->id,
                    'id_producto' => $producto->id,
                    'cantidad' => $it['cantidad'],
                    'cantidad_original_stock_inventario' => $producto->cantidad,
                    'revisado' => !empty($it['revisado']),
                ]);
            }

            DB::commit();
            return Response::json([
                'estado' => true,
                'msj' => 'Orden guardada (en preparación). No se descontó inventario.',
                'orden' => $this->ordenConDetalle($orden->id),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return Response::json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage() . ' L' . $e->getLine()]);
        }
    }

    /** Lista las órdenes locales de despacho (por estado, default preparación=0). */
    public function getOrdenes(Request $req)
    {
        $estado = $req->has('estado') && $req->estado !== '' ? (int) $req->estado : 0;
        $q = transferencias_inventario::where('estado', $estado);
        if ($req->q) {
            $q->where('id', $req->q);
        }
        $ordenes = $q->orderByDesc('id')->limit($req->limit ? (int) $req->limit : 50)->get()
            ->map(fn ($o) => $this->ordenConDetalle($o->id));
        return Response::json(['estado' => true, 'ordenes' => $ordenes]);
    }

    /** Elimina una orden en preparación (estado 0). No tocó inventario, así que solo se borra. */
    public function eliminarOrden(Request $req)
    {
        $orden = transferencias_inventario::find($req->id);
        if (!$orden) {
            return Response::json(['estado' => false, 'msj' => 'Orden no encontrada.']);
        }
        if ((int) $orden->estado !== 0) {
            return Response::json(['estado' => false, 'msj' => 'Solo se puede eliminar una orden en preparación.']);
        }
        $orden->delete(); // cascade: items, asignaciones, bultos, bulto_items
        return Response::json(['estado' => true, 'msj' => 'Orden eliminada.']);
    }

    /** Arma el detalle completo de una orden para el front. */
    private function ordenConDetalle($idOrden): array
    {
        $orden = transferencias_inventario::with(['items.producto', 'asignaciones.pasillero', 'bultos.items'])->find($idOrden);
        if (!$orden) {
            return [];
        }
        // Ubicación de almacén por producto (una consulta para toda la orden).
        $ubic = \App\Models\WarehouseInventory::ubicacionesPorProductos($orden->items->pluck('id_producto')->all());
        $items = $orden->items->map(function ($it) use ($ubic) {
            return [
                'id' => $it->id,
                'id_producto' => $it->id_producto,
                'descripcion' => $it->producto->descripcion ?? null,
                'codigo_barras' => $it->producto->codigo_barras ?? null,
                'codigo_proveedor' => $it->producto->codigo_proveedor ?? null,
                'ubicacion' => $ubic[$it->id_producto] ?? null,
                'cantidad' => (float) $it->cantidad,
                'revisado' => (bool) $it->revisado,
                'recolectado' => $this->recolectadoPorItem($it->id),
                'empacado' => $this->empacadoPorItem($it->id),
            ];
        });
        return [
            'id' => $orden->id,
            'estado' => (int) $orden->estado,
            'id_destino' => $orden->id_destino,
            'id_transferencia_central' => $orden->id_transferencia_central,
            'id_orden_distribucion' => $orden->id_orden_distribucion,
            'observacion' => $orden->observacion,
            'created_at' => (string) $orden->created_at,
            'items' => $items,
            'asignaciones' => $orden->asignaciones->map(fn ($a) => [
                'id' => $a->id,
                'id_transferencia_item' => $a->id_transferencia_item,
                'id_producto' => $a->id_producto,
                'pasillero_id' => $a->pasillero_id,
                'pasillero' => $a->pasillero->nombre ?? null,
                'cantidad' => (float) $a->cantidad,
                'cantidad_recolectada' => (float) $a->cantidad_recolectada,
                'estado' => $a->estado,
                'warehouse_codigo' => $a->warehouse_codigo,
            ]),
            'bultos' => $orden->bultos->map(fn ($b) => [
                'id' => $b->id,
                'numero' => $b->numero,
                'codigo_barras' => $b->codigo_barras,
                'estado' => $b->estado,
                'items' => $b->items->map(fn ($bi) => [
                    'id' => $bi->id,
                    'id_producto' => $bi->id_producto,
                    'id_transferencia_item' => $bi->id_transferencia_item,
                    'cantidad' => (float) $bi->cantidad,
                ]),
            ]),
        ];
    }

    // ──────────────── Fase 1: asignación a pasilleros ────────────────

    /** Pasilleros disponibles (usuarios tipo 8). */
    public function getPasilleros()
    {
        $pasilleros = usuarios::where('tipo_usuario', 8)->orderBy('nombre')->get(['id', 'nombre', 'usuario']);
        return Response::json(['estado' => true, 'pasilleros' => $pasilleros]);
    }

    /**
     * Reparte líneas a pasilleros. Reemplaza el set de asignaciones de la orden.
     * Rechaza si ya hay recolección en curso (para no perder trabajo): primero hay que revertir.
     * Input: id_transferencia, asignaciones[{id_transferencia_item, pasillero_id, cantidad}]
     */
    public function asignarLineas(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador asigna.']);
        }
        DB::beginTransaction();
        try {
            $orden = transferencias_inventario::find($req->id_transferencia);
            if (!$orden || (int) $orden->estado !== 0) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Orden no editable.']);
            }
            $yaRecolectado = TransferenciaAsignacion::where('id_transferencia', $orden->id)->where('cantidad_recolectada', '>', 0)->exists();
            if ($yaRecolectado) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Ya hay recolección en curso. Revertí antes de reasignar.']);
            }

            // validar que por ítem no se reparta más que lo solicitado
            $porItem = [];
            foreach (($req->asignaciones ?? []) as $a) {
                $porItem[$a['id_transferencia_item']] = ($porItem[$a['id_transferencia_item']] ?? 0) + (float) $a['cantidad'];
            }
            foreach ($porItem as $idItem => $suma) {
                $item = transferencias_inventario_items::find($idItem);
                if (!$item || $item->id_transferencia != $orden->id) {
                    DB::rollBack();
                    return Response::json(['estado' => false, 'msj' => 'Ítem inválido en la asignación.']);
                }
                if ($suma - (float) $item->cantidad > 0.0001) {
                    DB::rollBack();
                    return Response::json(['estado' => false, 'msj' => "Se asignó más de lo solicitado en el ítem #{$idItem}."]);
                }
            }

            TransferenciaAsignacion::where('id_transferencia', $orden->id)->delete();
            foreach (($req->asignaciones ?? []) as $a) {
                if ((float) $a['cantidad'] <= 0) {
                    continue;
                }
                $item = transferencias_inventario_items::find($a['id_transferencia_item']);
                TransferenciaAsignacion::create([
                    'id_transferencia' => $orden->id,
                    'id_transferencia_item' => $item->id,
                    'id_producto' => $item->id_producto,
                    'pasillero_id' => (int) $a['pasillero_id'],
                    'cantidad' => (float) $a['cantidad'],
                    'cantidad_recolectada' => 0,
                    'estado' => 'pendiente',
                ]);
            }

            DB::commit();
            return Response::json(['estado' => true, 'msj' => 'Líneas asignadas.', 'orden' => $this->ordenConDetalle($orden->id)]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return Response::json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage() . ' L' . $e->getLine()]);
        }
    }

    /** Asignaciones de una orden agrupadas por pasillero (vista del checkeador). */
    public function getAsignaciones(Request $req)
    {
        $orden = transferencias_inventario::find($req->id_transferencia);
        if (!$orden) {
            return Response::json(['estado' => false, 'msj' => 'Orden no encontrada.']);
        }
        return Response::json(['estado' => true, 'orden' => $this->ordenConDetalle($orden->id)]);
    }

    // ──────────── Fase 2: recolección + recuento ────────────

    /** Recolecciones del pasillero logueado, agrupadas por orden. */
    public function misRecolecciones(Request $req)
    {
        $pid = $this->uid();
        $asigs = TransferenciaAsignacion::with(['producto', 'transferencia'])
            ->where('pasillero_id', $pid)
            ->whereHas('transferencia', fn ($q) => $q->where('estado', 0))
            ->orderByDesc('id_transferencia')->get();

        $porOrden = [];
        foreach ($asigs as $a) {
            $porOrden[$a->id_transferencia]['id_transferencia'] = $a->id_transferencia;
            $porOrden[$a->id_transferencia]['lineas'][] = [
                'id' => $a->id,
                'id_producto' => $a->id_producto,
                'descripcion' => $a->producto->descripcion ?? null,
                'codigo_barras' => $a->producto->codigo_barras ?? null,
                'codigo_proveedor' => $a->producto->codigo_proveedor ?? null,
                'cantidad' => (float) $a->cantidad,
                'cantidad_recolectada' => (float) $a->cantidad_recolectada,
                'estado' => $a->estado,
                'warehouse_codigo' => $a->warehouse_codigo,
            ];
        }
        return Response::json(['estado' => true, 'ordenes' => array_values($porOrden)]);
    }

    /**
     * El pasillero (o el checkeador, recontando) fija lo recolectado de una asignación.
     * Input: id_asignacion, cantidad_recolectada, warehouse_codigo?
     */
    public function recolectarLinea(Request $req)
    {
        DB::beginTransaction();
        try {
            $a = TransferenciaAsignacion::lockForUpdate()->find($req->id_asignacion);
            if (!$a) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Asignación no encontrada.']);
            }
            // el pasillero solo toca lo suyo; el checkeador (DICI) puede recontar cualquiera
            if (!$this->esDici() && $a->pasillero_id != $this->uid()) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'No es tu asignación.']);
            }
            $cant = (float) $req->cantidad_recolectada;
            if ($cant < 0) {
                $cant = 0;
            }
            if ($cant - (float) $a->cantidad > 0.0001) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'No puede recolectar más de lo asignado.']);
            }
            $a->cantidad_recolectada = $cant;
            if ($req->has('warehouse_codigo')) {
                $a->warehouse_codigo = $req->warehouse_codigo;
            }
            $a->estado = $cant <= 0 ? 'pendiente' : ($cant >= (float) $a->cantidad ? 'recolectada' : 'en_proceso');
            $a->save();
            DB::commit();
            return Response::json(['estado' => true, 'msj' => 'Recolección registrada.', 'asignacion' => $a]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return Response::json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage()]);
        }
    }

    // ──────────────── Fase 3: bultos + mini-panel ────────────────

    /** Crea un bulto abierto con su correlativo y código de barras. */
    public function crearBulto(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador arma bultos.']);
        }
        DB::beginTransaction();
        try {
            $orden = transferencias_inventario::lockForUpdate()->find($req->id_transferencia);
            if (!$orden || (int) $orden->estado !== 0) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Orden no válida para armar bultos.']);
            }
            $numero = (int) TransferenciaBulto::where('id_transferencia', $orden->id)->max('numero') + 1;
            $bulto = TransferenciaBulto::create([
                'id_transferencia' => $orden->id,
                'numero' => $numero,
                'codigo_barras' => 'BUL-' . $orden->id . '-' . $numero,
                'estado' => 'abierto',
            ]);
            DB::commit();
            return Response::json(['estado' => true, 'msj' => 'Bulto creado.', 'bulto' => $bulto]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return Response::json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Agrega (o incrementa) mercancía en un bulto abierto. Valida que no se empaque más de lo
     * recolectado (o solicitado si no hubo recolección) para esa línea.
     * Input: id_bulto, id_transferencia_item, cantidad
     */
    public function agregarItemBulto(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador empaca.']);
        }
        DB::beginTransaction();
        try {
            $bulto = TransferenciaBulto::lockForUpdate()->find($req->id_bulto);
            if (!$bulto || $bulto->estado !== 'abierto') {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Bulto no está abierto.']);
            }
            $item = transferencias_inventario_items::find($req->id_transferencia_item);
            if (!$item || $item->id_transferencia != $bulto->id_transferencia) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Ítem no pertenece a esta orden.']);
            }
            $cant = (float) $req->cantidad;
            if ($cant <= 0) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Cantidad inválida.']);
            }
            $restante = $this->empacableRestante($item);
            if ($cant - $restante > 0.0001) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => "Solo quedan {$restante} por empacar de ese producto (recolectado − ya empacado)."]);
            }

            $bi = TransferenciaBultoItem::where('id_bulto', $bulto->id)->where('id_transferencia_item', $item->id)->first();
            if ($bi) {
                $bi->cantidad = (float) $bi->cantidad + $cant;
                $bi->save();
            } else {
                $bi = TransferenciaBultoItem::create([
                    'id_bulto' => $bulto->id,
                    'id_transferencia_item' => $item->id,
                    'id_producto' => $item->id_producto,
                    'cantidad' => $cant,
                ]);
            }
            DB::commit();
            return Response::json(['estado' => true, 'msj' => 'Mercancía agregada al bulto.', 'item' => $bi]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return Response::json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage()]);
        }
    }

    /** Quita una línea de un bulto abierto. */
    public function quitarItemBulto(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador.']);
        }
        $bi = TransferenciaBultoItem::find($req->id_bulto_item);
        if (!$bi) {
            return Response::json(['estado' => false, 'msj' => 'Línea de bulto no encontrada.']);
        }
        $bulto = TransferenciaBulto::find($bi->id_bulto);
        if (!$bulto || $bulto->estado !== 'abierto') {
            return Response::json(['estado' => false, 'msj' => 'El bulto no está abierto.']);
        }
        $bi->delete();
        return Response::json(['estado' => true, 'msj' => 'Línea quitada del bulto.']);
    }

    /** Cierra un bulto (debe tener al menos 1 ítem). Su código de barras queda firme para la etiqueta. */
    public function cerrarBulto(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador.']);
        }
        DB::beginTransaction();
        try {
            $bulto = TransferenciaBulto::lockForUpdate()->find($req->id_bulto);
            if (!$bulto) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Bulto no encontrado.']);
            }
            if ($bulto->estado !== 'abierto') {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'El bulto ya está cerrado.']);
            }
            if (TransferenciaBultoItem::where('id_bulto', $bulto->id)->count() === 0) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'El bulto está vacío.']);
            }
            $bulto->estado = 'cerrado';
            $bulto->cerrado_por = $this->uid();
            $bulto->cerrado_at = now();
            $bulto->save();
            DB::commit();
            return Response::json(['estado' => true, 'msj' => 'Bulto cerrado.', 'bulto' => $bulto]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return Response::json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage()]);
        }
    }

    /** Mini-panel: escanear el código de un bulto y ver su contenido. */
    public function consultarBulto(Request $req)
    {
        $codigo = trim((string) $req->codigo);
        $bulto = TransferenciaBulto::with(['items.producto', 'transferencia'])->where('codigo_barras', $codigo)->first();
        if (!$bulto) {
            return Response::json(['estado' => false, 'msj' => 'Bulto no encontrado: ' . $codigo]);
        }
        return Response::json([
            'estado' => true,
            'bulto' => [
                'id' => $bulto->id,
                'numero' => $bulto->numero,
                'codigo_barras' => $bulto->codigo_barras,
                'estado' => $bulto->estado,
                'id_transferencia' => $bulto->id_transferencia,
                'id_destino' => $bulto->transferencia->id_destino ?? null,
                'items' => $bulto->items->map(fn ($bi) => [
                    'id_producto' => $bi->id_producto,
                    'descripcion' => $bi->producto->descripcion ?? null,
                    'codigo_barras' => $bi->producto->codigo_barras ?? null,
                    'cantidad' => (float) $bi->cantidad,
                ]),
            ],
        ]);
    }

    /** Lista de bultos de una orden. */
    public function getBultos(Request $req)
    {
        $bultos = TransferenciaBulto::with('items.producto')->where('id_transferencia', $req->id_transferencia)->orderBy('numero')->get();
        return Response::json(['estado' => true, 'bultos' => $bultos]);
    }

    // ──────────────── Fase 4: despacho + exclusión ────────────────

    /**
     * Despacha UN bulto (escaneo): valida cerrado, descuenta el inventario de su contenido de
     * forma atómica (reusa descontarInventario, ordenando por id_producto para evitar deadlock)
     * y marca el bulto como despachado. Cada bulto da salida solo a SU mercancía.
     */
    public function despacharBulto(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador despacha.']);
        }
        DB::beginTransaction();
        try {
            $bulto = TransferenciaBulto::lockForUpdate()
                ->when($req->codigo, fn ($q) => $q->where('codigo_barras', trim((string) $req->codigo)))
                ->when($req->id_bulto, fn ($q) => $q->where('id', $req->id_bulto))
                ->first();
            if (!$bulto) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Bulto no encontrado.']);
            }
            if ($bulto->estado === 'despachado') {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Ese bulto ya fue despachado.']);
            }
            if ($bulto->estado !== 'cerrado') {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'El bulto debe estar cerrado para despacharse.']);
            }

            $inv = new InventarioController();
            // ordenar por id_producto → locks en orden consistente (sin deadlock bajo concurrencia)
            $itemsBulto = TransferenciaBultoItem::where('id_bulto', $bulto->id)->orderBy('id_producto')->get();
            foreach ($itemsBulto as $bi) {
                $producto = inventario::find($bi->id_producto);
                if (!$producto) {
                    throw new \Exception('Producto #' . $bi->id_producto . ' no existe.');
                }
                $actual = (float) $producto->cantidad;
                $ok = $inv->descontarInventario(
                    $producto->id,
                    $actual - (float) $bi->cantidad,
                    $actual,
                    null,
                    'TRANS.SAL BULTO #' . $bulto->codigo_barras
                );
                if (!$ok) {
                    throw new \Exception('No se pudo descontar el producto #' . $bi->id_producto . '.');
                }
            }

            $bulto->estado = 'despachado';
            $bulto->despachado_por = $this->uid();
            $bulto->despachado_at = now();
            $bulto->save();

            DB::commit();
            return Response::json([
                'estado' => true,
                'msj' => 'Bulto ' . $bulto->codigo_barras . ' despachado (inventario descontado).',
                'bulto' => $bulto,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return Response::json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Finaliza el despacho de la orden: con todos los bultos despachados, crea el espejo en
     * central con las cantidades REALMENTE despachadas (lo no empacado queda excluido). El
     * inventario ya se descontó por bulto, así que aquí solo notificamos a central; si falla, la
     * mercancía ya salió y se puede reintentar (dirección de fallo recuperable).
     */
    public function finalizarDespacho(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador.']);
        }
        $orden = transferencias_inventario::find($req->id_transferencia);
        if (!$orden) {
            return Response::json(['estado' => false, 'msj' => 'Orden no encontrada.']);
        }
        if ((int) $orden->estado === 1 && $orden->id_transferencia_central) {
            return Response::json(['estado' => false, 'msj' => 'La orden ya fue despachada a central.']);
        }
        $bultos = TransferenciaBulto::where('id_transferencia', $orden->id)->get();
        if ($bultos->isEmpty()) {
            return Response::json(['estado' => false, 'msj' => 'La orden no tiene bultos.']);
        }
        $pendientes = $bultos->whereIn('estado', ['abierto', 'cerrado'])->count();
        if ($pendientes > 0) {
            return Response::json(['estado' => false, 'msj' => "Faltan {$pendientes} bulto(s) por despachar (escaneá todos)."]);
        }

        // Cantidades realmente despachadas, agregadas por producto.
        $despachadoPorProducto = [];
        foreach ($bultos as $b) {
            foreach (TransferenciaBultoItem::where('id_bulto', $b->id)->get() as $bi) {
                $despachadoPorProducto[$bi->id_producto] = ($despachadoPorProducto[$bi->id_producto] ?? 0) + (float) $bi->cantidad;
            }
        }
        if (!count($despachadoPorProducto)) {
            return Response::json(['estado' => false, 'msj' => 'No hay mercancía despachada.']);
        }

        // items_clean para central (mismo shape que settransferenciaDici).
        $itemsClean = [];
        foreach ($despachadoPorProducto as $idProd => $cant) {
            $producto = inventario::find($idProd);
            if (!$producto) {
                continue;
            }
            $itemsClean[] = [
                'producto' => $producto,
                'descuento' => 0,
                'monto' => $producto->precio_base * $cant,
                'cantidad' => $cant,
            ];
        }

        try {
            $sc = new sendCentral();
            $codigoOrigen = $sc->getOrigen();
            $resp = $sc->requestToCentral('post', '/setPedidoInCentralFromMasters', [
                'codigo_origen' => $codigoOrigen,
                'id_sucursal' => $orden->id_destino,
                'observaciones' => $orden->observacion,
                'type' => 'add',
                'id_transferencia_central' => null,
                'pedidos' => [[
                    'id' => $orden->id,
                    'items' => $itemsClean,
                ]],
            ]);
            $res = $resp->json();
            if (isset($res['estado']) && $res['estado'] === true) {
                $orden->id_transferencia_central = $res['id'];
                $orden->estado = 1;
                $orden->save();
                return Response::json(['estado' => true, 'msj' => 'Despacho finalizado y enviado a central.', 'orden' => $this->ordenConDetalle($orden->id)]);
            }
            return Response::json(['estado' => false, 'msj' => 'Central no aceptó el despacho. La mercancía ya salió; reintentá. Detalle: ' . json_encode($res)]);
        } catch (\Throwable $e) {
            return Response::json(['estado' => false, 'msj' => 'Mercancía ya despachada localmente; falló el envío a central (reintentá): ' . $e->getMessage()]);
        }
    }

    /**
     * SALIDA SIMPLE (Plan B, sin bultos): da salida a TODA la orden en preparación de una vez.
     * Descuenta el inventario de forma atómica (todo-o-nada) y crea el espejo en central con las
     * cantidades que quedaron en la orden (el DICI ya las editó/ajustó a lo que consiguió). Si la
     * orden nació de una redistribución, reenvía id_orden_distribucion para que central la marque
     * "En Tránsito" y ancle el pedido.
     *
     * Idempotente/recuperable:
     *   estado 0                       → descuenta + notifica central.
     *   estado 1 sin central           → NO vuelve a descontar; solo reintenta el envío a central.
     *   estado 1 con central           → ya despachada, no hace nada.
     */
    public function darSalidaSimple(Request $req)
    {
        if (!$this->esDici()) {
            return Response::json(['estado' => false, 'msj' => 'Solo el checkeador (DICI) da salida.']);
        }

        $orden = transferencias_inventario::with('items.producto')->find($req->id_transferencia ?? $req->id);
        if (!$orden) {
            return Response::json(['estado' => false, 'msj' => 'Orden no encontrada.']);
        }
        if ((int) $orden->estado === 1 && $orden->id_transferencia_central) {
            return Response::json(['estado' => false, 'msj' => 'La orden ya fue despachada a central.']);
        }

        // Ítems con cantidad > 0 (lo que el DICI dejó confirmado en la orden).
        $itemsValidos = $orden->items->filter(fn ($it) => (float) $it->cantidad > 0)->values();
        if ($itemsValidos->isEmpty()) {
            return Response::json(['estado' => false, 'msj' => 'La orden no tiene ítems con cantidad para despachar.']);
        }

        // ── Fase A: descontar inventario (solo si aún no se descontó: estado 0) ──
        if ((int) $orden->estado === 0) {
            DB::beginTransaction();
            try {
                // reclamar la orden dentro de la misma transacción para bloquear dobles salidas
                $lock = transferencias_inventario::lockForUpdate()->find($orden->id);
                if (!$lock || (int) $lock->estado !== 0) {
                    DB::rollBack();
                    return Response::json(['estado' => false, 'msj' => 'La orden ya no está en preparación (otra salida en curso).']);
                }

                $inv = new InventarioController();
                // ordenar por id_producto → locks en orden consistente (sin deadlock bajo concurrencia)
                foreach ($itemsValidos->sortBy('id_producto') as $it) {
                    $producto = inventario::find($it->id_producto);
                    if (!$producto) {
                        throw new \Exception('Producto #' . $it->id_producto . ' ya no existe.');
                    }
                    $actual = (float) $producto->cantidad;
                    $ok = $inv->descontarInventario(
                        $producto->id,
                        $actual - (float) $it->cantidad,
                        $actual,
                        $orden->id,
                        'TRANS.SAL ORDEN'
                    );
                    if (!$ok) {
                        throw new \Exception('No se pudo descontar el producto #' . $it->id_producto . '.');
                    }
                }

                $lock->estado = 1; // DESPACHADA localmente (inventario ya salió)
                $lock->save();
                DB::commit();
                $orden->estado = 1;
            } catch (\Throwable $e) {
                DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'No se dio salida (no se descontó nada): ' . $e->getMessage()]);
            }
        }

        // ── Fase B: notificar a central (crear el espejo). El inventario ya salió; si falla, se
        //    puede reintentar esta misma llamada (entra por estado=1 sin central, sin re-descontar). ──
        $itemsClean = [];
        foreach ($itemsValidos as $it) {
            $producto = $it->producto ?: inventario::find($it->id_producto);
            if (!$producto) {
                continue;
            }
            $itemsClean[] = [
                'producto' => $producto,
                'descuento' => 0,
                'monto' => (float) $producto->precio_base * (float) $it->cantidad,
                'cantidad' => (float) $it->cantidad,
            ];
        }

        try {
            $sc = new sendCentral();
            $resp = $sc->requestToCentral('post', '/setPedidoInCentralFromMasters', [
                'codigo_origen' => $sc->getOrigen(),
                'id_sucursal' => $orden->id_destino,
                'observaciones' => $orden->observacion,
                'type' => 'add',
                'id_transferencia_central' => null,
                'id_orden_distribucion' => $orden->id_orden_distribucion,
                'pedidos' => [[
                    'id' => $orden->id,
                    'items' => $itemsClean,
                ]],
            ]);
            $res = $resp->json();
            if (isset($res['estado']) && $res['estado'] === true) {
                $orden->id_transferencia_central = $res['id'];
                $orden->estado = 1;
                $orden->save();
                return Response::json([
                    'estado' => true,
                    'msj' => 'Salida dada. Inventario descontado y enviado a central.',
                    'orden' => $this->ordenConDetalle($orden->id),
                ]);
            }
            return Response::json(['estado' => false, 'msj' => 'La mercancía ya salió, pero central no aceptó el envío. Reintentá "Dar salida". Detalle: ' . json_encode($res)]);
        } catch (\Throwable $e) {
            return Response::json(['estado' => false, 'msj' => 'La mercancía ya salió localmente; falló el envío a central (reintentá "Dar salida"): ' . $e->getMessage()]);
        }
    }

    /** Reporte de mercancía excluida = solicitado − empacado (lo no encontrado/recolectado). */
    public function reporteExcluidos(Request $req)
    {
        $orden = transferencias_inventario::with('items.producto')->find($req->id_transferencia);
        if (!$orden) {
            return Response::json(['estado' => false, 'msj' => 'Orden no encontrada.']);
        }
        $filas = [];
        foreach ($orden->items as $it) {
            $solicitado = (float) $it->cantidad;
            $recolectado = $this->recolectadoPorItem($it->id);
            $empacado = $this->empacadoPorItem($it->id);
            $excluido = $solicitado - $empacado;
            if ($excluido > 0.0001) {
                $filas[] = [
                    'id_producto' => $it->id_producto,
                    'descripcion' => $it->producto->descripcion ?? null,
                    'codigo_barras' => $it->producto->codigo_barras ?? null,
                    'solicitado' => $solicitado,
                    'recolectado' => $recolectado,
                    'empacado' => $empacado,
                    'excluido' => $excluido,
                ];
            }
        }
        return Response::json(['estado' => true, 'excluidos' => $filas]);
    }

    // ──────────────── Fase 5: impresión ────────────────

    /** Orden de recolección imprimible para un pasillero (líneas a buscar). */
    public function imprimirOrdenRecoleccion(Request $req)
    {
        $orden = transferencias_inventario::with('items.producto')->findOrFail($req->id_transferencia);
        $pasilleroId = (int) $req->pasillero_id;
        $pasillero = usuarios::find($pasilleroId);
        $asignaciones = TransferenciaAsignacion::with('producto')
            ->where('id_transferencia', $orden->id)->where('pasillero_id', $pasilleroId)->get();
        return view('transferencias.orden-recoleccion', compact('orden', 'pasillero', 'asignaciones'));
    }

    /** Etiqueta de un bulto (código de barras CODE128). */
    public function etiquetaBulto(Request $req)
    {
        $bulto = TransferenciaBulto::with(['items.producto', 'transferencia'])->findOrFail($req->id_bulto);
        return view('transferencias.etiqueta-bulto', compact('bulto'));
    }

    /** Orden de despacho: resumen de bultos y totales. */
    public function imprimirOrdenDespacho(Request $req)
    {
        $orden = transferencias_inventario::with(['items.producto', 'bultos.items.producto'])->findOrFail($req->id_transferencia);
        $excluidos = $this->excluidosArray($orden);
        return view('transferencias.orden-despacho', compact('orden', 'excluidos'));
    }

    /** Factura/lista de despacho: cada producto con su número de bulto. */
    public function imprimirFacturaBultos(Request $req)
    {
        $orden = transferencias_inventario::with(['bultos.items.producto'])->findOrFail($req->id_transferencia);
        // filas: producto + nº de bulto + cantidad (un producto puede ir en varios bultos)
        $filas = [];
        foreach ($orden->bultos->sortBy('numero') as $b) {
            foreach ($b->items as $bi) {
                $filas[] = [
                    'bulto' => $b->numero,
                    'codigo_bulto' => $b->codigo_barras,
                    'descripcion' => $bi->producto->descripcion ?? null,
                    'codigo_barras' => $bi->producto->codigo_barras ?? null,
                    'cantidad' => (float) $bi->cantidad,
                ];
            }
        }
        return view('transferencias.factura-bultos', compact('orden', 'filas'));
    }

    /** Helper para la vista: array de excluidos (solicitado − empacado). */
    private function excluidosArray(transferencias_inventario $orden): array
    {
        $filas = [];
        foreach ($orden->items as $it) {
            $excluido = (float) $it->cantidad - $this->empacadoPorItem($it->id);
            if ($excluido > 0.0001) {
                $filas[] = [
                    'descripcion' => $it->producto->descripcion ?? null,
                    'codigo_barras' => $it->producto->codigo_barras ?? null,
                    'solicitado' => (float) $it->cantidad,
                    'empacado' => $this->empacadoPorItem($it->id),
                    'excluido' => $excluido,
                ];
            }
        }
        return $filas;
    }
}
