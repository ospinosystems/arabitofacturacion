<?php

namespace App\Services\Inventario;

use Illuminate\Support\Facades\DB;

/**
 * Libro (registro) de entradas y salidas de inventario, reconstruido únicamente con documentos:
 *  - ENTRADAS: facturas fiscales de compra recibidas en la sucursal (tabla inventario_entradas, importada de central);
 *    y también las notas (CxP sin factura fiscal) y los traslados entre sucursales (criterio oficial desde el 09-oct-2026).
 *  - SALIDAS: las facturas de venta (pedidos con valido = 1 y número de factura).
 * No parte de ningún stock inicial ni actual: la existencia es la suma algebraica de entradas y salidas desde el
 * primer documento. Las salidas se valoran al costo promedio ponderado móvil (USD) de las entradas de cada producto;
 * los Bs de cada movimiento usan la tasa del propio documento (tasa de la factura de compra / tasa de la venta).
 * Un ítem de venta con cantidad negativa (devolución dentro de un cambio) vuelve al inventario al costo promedio.
 */
class LibroInventarioService
{
    public const TIPOS = ['FACTURA', 'NOTA', 'TRANSFERENCIA'];

    /** Criterio oficial (decisión del usuario, 09-oct-2026): facturas fiscales, notas y traslados cuentan como entradas. */
    public const TIPOS_DEFECTO = ['FACTURA', 'NOTA', 'TRANSFERENCIA'];

    /**
     * @param string[] $tipos tipos de entrada a considerar
     * @return array{productos: array<int|string, array>, movimientos: array<int, array>, totales: array, tasa_cierre: float, desde: string, hasta: string}
     */
    public function construir(?string $desde, ?string $hasta, array $tipos = self::TIPOS_DEFECTO, $soloProducto = null, bool $conMovimientos = true): array
    {
        $hasta = $hasta ?: date('Y-m-d');
        $tipos = array_values(array_intersect(self::TIPOS, array_map('strtoupper', $tipos))) ?: self::TIPOS_DEFECTO;

        // ── Entradas ───────────────────────────────────────────────────────────────────────────────
        $qe = DB::table('inventario_entrada_items as ei')->join('inventario_entradas as e', 'e.id', '=', 'ei.entrada_id')
            ->where('e.anulado', 0)->whereIn('e.tipo', $tipos)->where('e.fecha_recepcion', '<=', $hasta)
            ->select('ei.id as item_id', 'e.id as entrada_id', 'e.tipo', 'e.numero_documento', 'e.numero_nota', 'e.proveedor', 'e.origen_sucursal',
                'e.fecha_recepcion as fecha', 'e.tasa_bs', 'ei.central_id_producto', 'ei.id_producto', 'ei.cantidad', 'ei.costo_unitario_usd', 'ei.descripcion_origen', 'ei.codigo_barras_origen', 'ei.mapeo');
        $sinFicha = is_string($soloProducto) && str_starts_with($soloProducto, 'c'); // clave 'c<id central>' = sin ficha local
        if ($soloProducto !== null) {
            if ($sinFicha) {
                $qe->whereNull('ei.id_producto')->where('ei.central_id_producto', (int) substr($soloProducto, 1));
            } else {
                $qe->where('ei.id_producto', (int) $soloProducto);
            }
        }
        $entradas = $qe->orderBy('e.fecha_recepcion')->orderBy('e.id')->orderBy('ei.id')->get();

        // ── Salidas ────────────────────────────────────────────────────────────────────────────────
        $qs = DB::table('items_pedidos as i')->join('pedidos as p', 'p.id', '=', 'i.id_pedido')
            ->where('p.valido', 1)->whereNotNull('p.numero_factura')
            ->whereRaw('DATE(COALESCE(p.fecha_factura, p.created_at)) <= ?', [$hasta])
            ->selectRaw('i.id as item_id, i.id_pedido, i.id_producto, i.cantidad, i.monto, i.tasa, DATE(COALESCE(p.fecha_factura, p.created_at)) as fecha, TIME(COALESCE(p.fecha_factura, p.created_at)) as hora, p.numero_factura, p.maquina_fiscal');
        if ($soloProducto !== null) {
            $qs->where('i.id_producto', $sinFicha ? -1 : (int) $soloProducto);
        }
        $salidas = $qs->orderByRaw('COALESCE(p.fecha_factura, p.created_at), p.id, i.id')->get();

        // ── Un solo flujo cronológico (las entradas de un día van antes que las ventas de ese día) ──
        $movs = [];
        $seq = 0;
        foreach ($entradas as $e) {
            $k = $e->id_producto !== null ? (int) $e->id_producto : 'c' . $e->central_id_producto;
            $movs[] = ['k' => $k, 'orden' => sprintf('%s|0|00:00:00|%010d', $e->fecha, $seq++), 'fecha' => $e->fecha, 'tipo' => 'ENTRADA', 'subtipo' => $e->tipo,
                'documento' => $this->docEntrada($e), 'tercero' => $e->proveedor ?: ($e->origen_sucursal ? 'Sucursal ' . $e->origen_sucursal : ''),
                'cantidad' => (float) $e->cantidad, 'costo' => (float) $e->costo_unitario_usd, 'tasa' => (float) ($e->tasa_bs ?: 0),
                'desc_origen' => $e->descripcion_origen, 'cb_origen' => $e->codigo_barras_origen, 'mapeo' => $e->mapeo];
        }
        foreach ($salidas as $s) {
            $cant = (float) $s->cantidad;
            $movs[] = ['k' => (int) $s->id_producto, 'orden' => sprintf('%s|1|%s|%010d', $s->fecha, $s->hora, $seq++), 'fecha' => $s->fecha, 'tipo' => $cant < 0 ? 'DEVOLUCION' : 'SALIDA', 'subtipo' => 'VENTA',
                'documento' => 'Factura ' . trim((string) $s->maquina_fiscal) . ' ' . $s->numero_factura, 'tercero' => '',
                'cantidad' => abs($cant), 'costo' => null, 'tasa' => (float) ($s->tasa ?: 0), 'venta_usd' => (float) $s->monto];
        }
        usort($movs, fn ($a, $b) => strcmp($a['orden'], $b['orden']));

        // ── Fichas de producto ─────────────────────────────────────────────────────────────────────
        $ids = array_values(array_unique(array_filter(array_column($movs, 'k'), 'is_int')));
        $fichas = [];
        foreach (array_chunk($ids, 2000) as $chunk) {
            foreach (DB::table('inventarios')->whereIn('id', $chunk)->get(['id', 'codigo_barras', 'codigo_proveedor', 'descripcion', 'unidad']) as $f) {
                $fichas[(int) $f->id] = $f;
            }
        }

        // ── Kardex por producto: promedio ponderado móvil ───────────────────────────────────────────
        $prod = [];
        $out = [];
        $tasaCierre = 0.0;
        $nuevo = fn () => ['qty' => 0.0, 'valor' => 0.0, 'prom' => 0.0, 'ini_qty' => 0.0, 'ini_valor' => 0.0, 'ent_qty' => 0.0, 'ent_valor' => 0.0, 'sal_qty' => 0.0, 'sal_valor' => 0.0, 'dev_qty' => 0.0, 'dev_valor' => 0.0, 'venta_usd' => 0.0, 'negativo' => false, 'movs' => 0];
        foreach ($movs as $m) {
            $k = $m['k'];
            $p = &$prod[$k];
            if ($p === null) $p = $nuevo();
            $enPeriodo = $desde === null || $m['fecha'] >= $desde;
            if ($m['tasa'] > 0) $tasaCierre = $m['tasa'];
            if ($m['tipo'] === 'ENTRADA') {
                $costo = $m['costo'];
                $p['qty'] += $m['cantidad'];
                $p['valor'] += $m['cantidad'] * $costo;
                if ($p['qty'] > 0) $p['prom'] = $p['valor'] / $p['qty'];
                $total = $m['cantidad'] * $costo;
                if ($enPeriodo) { $p['ent_qty'] += $m['cantidad']; $p['ent_valor'] += $total; }
            } elseif ($m['tipo'] === 'DEVOLUCION') {
                $costo = $p['prom'];
                $p['qty'] += $m['cantidad'];
                $p['valor'] += $m['cantidad'] * $costo;
                $total = $m['cantidad'] * $costo;
                if ($enPeriodo) { $p['dev_qty'] += $m['cantidad']; $p['dev_valor'] += $total; $p['venta_usd'] -= abs($m['venta_usd'] ?? 0); }
            } else {
                $costo = $p['prom'];
                $p['qty'] -= $m['cantidad'];
                $p['valor'] -= $m['cantidad'] * $costo;
                $total = $m['cantidad'] * $costo;
                if ($p['qty'] < -0.00001) $p['negativo'] = true;
                if ($enPeriodo) { $p['sal_qty'] += $m['cantidad']; $p['sal_valor'] += $total; $p['venta_usd'] += $m['venta_usd'] ?? 0; }
            }
            if (!$enPeriodo) {
                $p['ini_qty'] = $p['qty'];
                $p['ini_valor'] = $p['valor'];
                continue;
            }
            $p['movs']++;
            if ($conMovimientos) {
                $out[] = ['producto' => $k, 'fecha' => $m['fecha'], 'tipo' => $m['tipo'], 'subtipo' => $m['subtipo'], 'documento' => $m['documento'], 'tercero' => $m['tercero'],
                    'entrada' => $m['tipo'] === 'SALIDA' ? 0.0 : $m['cantidad'], 'salida' => $m['tipo'] === 'SALIDA' ? $m['cantidad'] : 0.0,
                    'costo_unit' => $costo, 'total_usd' => $total, 'tasa' => $m['tasa'], 'total_bs' => $total * $m['tasa'],
                    'saldo_qty' => $p['qty'], 'saldo_prom' => $p['prom'], 'saldo_valor' => $p['qty'] * $p['prom']];
            }
            unset($p);
        }
        unset($p);

        $productos = [];
        $tot = ['productos' => 0, 'ini_valor' => 0.0, 'ent_qty' => 0.0, 'ent_valor' => 0.0, 'sal_qty' => 0.0, 'sal_valor' => 0.0, 'dev_qty' => 0.0, 'dev_valor' => 0.0, 'fin_qty' => 0.0, 'fin_valor' => 0.0, 'venta_usd' => 0.0, 'negativos' => 0, 'sin_ficha' => 0];
        foreach ($prod as $k => $p) {
            $f = is_int($k) ? ($fichas[$k] ?? null) : null;
            $origen = null;
            if (!$f) {
                foreach ($movs as $m) { if ($m['k'] === $k && $m['tipo'] === 'ENTRADA') { $origen = $m; break; } }
            }
            $productos[$k] = [
                'id' => $k, 'codigo' => $f->codigo_barras ?? ($origen['cb_origen'] ?? ''), 'codigo_proveedor' => $f->codigo_proveedor ?? '',
                'descripcion' => $f->descripcion ?? ($origen['desc_origen'] ?? '(sin ficha local)'), 'unidad' => $f->unidad ?? 'UND', 'sin_ficha' => !$f,
                'ini_qty' => $p['ini_qty'], 'ini_valor' => $p['ini_valor'], 'ent_qty' => $p['ent_qty'], 'ent_valor' => $p['ent_valor'],
                'sal_qty' => $p['sal_qty'], 'sal_valor' => $p['sal_valor'], 'dev_qty' => $p['dev_qty'], 'dev_valor' => $p['dev_valor'],
                'fin_qty' => $p['qty'], 'prom' => $p['prom'], 'fin_valor' => $p['qty'] * $p['prom'], 'fin_valor_bs' => $p['qty'] * $p['prom'] * $tasaCierre,
                'venta_usd' => $p['venta_usd'], 'negativo' => $p['negativo'], 'movs' => $p['movs'],
            ];
            $tot['productos']++;
            foreach (['ini_valor', 'ent_qty', 'ent_valor', 'sal_qty', 'sal_valor', 'dev_qty', 'dev_valor', 'venta_usd'] as $c) $tot[$c] += $productos[$k][$c];
            $tot['fin_qty'] += $p['qty'];
            $tot['fin_valor'] += $p['qty'] * $p['prom'];
            if ($p['negativo']) $tot['negativos']++;
            if (!$f) $tot['sin_ficha']++;
        }
        $tot['fin_valor_bs'] = $tot['fin_valor'] * $tasaCierre;
        uasort($productos, fn ($a, $b) => strcmp((string) $a['descripcion'], (string) $b['descripcion']));

        return ['productos' => $productos, 'movimientos' => $out, 'totales' => $tot, 'tasa_cierre' => $tasaCierre,
            'desde' => $desde ?: ($movs[0]['fecha'] ?? $hasta), 'hasta' => $hasta, 'tipos' => $tipos];
    }

    protected function docEntrada($e): string
    {
        if ($e->tipo === 'FACTURA') return 'Factura ' . $e->numero_documento . ($e->numero_nota ? ' / N.E. ' . $e->numero_nota : '');
        if ($e->tipo === 'NOTA') return 'Nota ' . ($e->numero_nota ?: $e->numero_documento);
        return 'Traslado ' . ($e->origen_sucursal ?: '') . ' #' . $e->entrada_id;
    }

    /** Primer y último movimiento disponibles (para los filtros). */
    public function rangoDisponible(): array
    {
        $e1 = DB::table('inventario_entradas')->where('anulado', 0)->min('fecha_recepcion');
        $s1 = DB::table('pedidos')->where('valido', 1)->selectRaw('MIN(DATE(COALESCE(fecha_factura, created_at))) d')->value('d');
        $s2 = DB::table('pedidos')->where('valido', 1)->selectRaw('MAX(DATE(COALESCE(fecha_factura, created_at))) d')->value('d');
        $e2 = DB::table('inventario_entradas')->where('anulado', 0)->max('fecha_recepcion');
        return [min(array_filter([$e1, $s1])) ?: date('Y-m-d'), max(array_filter([$e2, $s2])) ?: date('Y-m-d')];
    }
}
