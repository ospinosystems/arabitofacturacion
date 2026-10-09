<?php

namespace App\Services\Cuadre;

use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Exportación detallada de ventas para auditoría en Excel: una fila por línea de factura (producto vendido), día por día,
 * indicando por qué máquina fiscal salió, con código, cantidad, precio cobrado, tasa e importes en USD y Bs.
 * El mismo generador alimenta la descarga web (streaming) y el comando cuadre:ventas-detalle-csv (archivo completo).
 */
class VentasDetalleExporter
{
    public const CABECERA = [
        'FECHA', 'MAQUINA_FISCAL', 'NUMERO_FACTURA', 'ID_PEDIDO', 'HORA', 'TIPO',
        'CODIGO_BARRAS', 'CODIGO_PROVEEDOR', 'DESCRIPCION',
        'CANTIDAD', 'PRECIO_UNIT_USD', 'IMPORTE_USD', 'TASA_BS_USD', 'PRECIO_UNIT_BS', 'IMPORTE_BS',
        'TOTAL_FACTURA_USD', 'TOTAL_FACTURA_BS', 'LINEAS_FACTURA',
    ];

    public const FECHA_EXPR = 'DATE(COALESCE(p.fecha_factura, p.created_at))';

    public function consulta(?string $desde, ?string $hasta, ?string $maquina = null): Builder
    {
        $fecha = self::FECHA_EXPR;
        $q = DB::table('items_pedidos as i')
            ->join('pedidos as p', 'p.id', '=', 'i.id_pedido')
            ->leftJoin('inventarios as inv', 'inv.id', '=', 'i.id_producto')
            ->where('p.valido', 1)
            ->selectRaw("$fecha as fecha, COALESCE(p.fecha_factura, p.created_at) as fecha_hora, p.maquina_fiscal, p.numero_factura, p.id as id_pedido, i.id as id_item, i.id_producto, inv.codigo_barras, inv.codigo_proveedor, inv.descripcion, i.cantidad, i.monto, i.tasa, i.monto_bs, i.precio_unitario")
            ->orderByRaw("$fecha, p.maquina_fiscal, LENGTH(p.numero_factura), p.numero_factura, p.id, i.id");
        if ($desde) {
            $q->whereRaw("$fecha >= ?", [$desde]);
        }
        if ($hasta) {
            $q->whereRaw("$fecha <= ?", [$hasta]);
        }
        if ($maquina !== null && $maquina !== '') {
            $q->where('p.maquina_fiscal', $maquina);
        }
        return $q;
    }

    /** Rango de fechas con ventas cuadradas (para nombrar el archivo completo). */
    public function rango(): array
    {
        $fecha = self::FECHA_EXPR;
        $r = DB::table('pedidos as p')->where('p.valido', 1)->selectRaw("MIN($fecha) d1, MAX($fecha) d2")->first();
        return [$r->d1 ?? null, $r->d2 ?? null];
    }

    /** Generador de filas: recorre las líneas con cursor y las agrupa por factura para repetir en cada fila el total de la factura. */
    public function filas(?string $desde, ?string $hasta, ?string $maquina = null): Generator
    {
        $grupo = [];
        $actual = null;
        foreach ($this->consulta($desde, $hasta, $maquina)->cursor() as $r) {
            if ($actual !== null && $r->id_pedido !== $actual) {
                yield from $this->emitir($grupo);
                $grupo = [];
            }
            $actual = $r->id_pedido;
            $grupo[] = $r;
        }
        if ($grupo) {
            yield from $this->emitir($grupo);
        }
    }

    protected function emitir(array $lineas): Generator
    {
        $tasaFactura = null;
        foreach ($lineas as $l) {
            if ((float) $l->tasa > 0) {
                $tasaFactura = (float) $l->tasa;
                break;
            }
        }
        $totUsd = 0.0;
        $totBs = 0.0;
        $calc = [];
        foreach ($lineas as $l) {
            $tasa = (float) $l->tasa > 0 ? (float) $l->tasa : $tasaFactura;
            $usd = (float) $l->monto;
            $bs = $l->monto_bs !== null ? (float) $l->monto_bs : ($tasa ? $usd * $tasa : null);
            $totUsd += $usd;
            $totBs += (float) $bs;
            $calc[] = [$tasa, $usd, $bs];
        }
        foreach ($lineas as $k => $l) {
            [$tasa, $usd, $bs] = $calc[$k];
            $cant = (float) $l->cantidad;
            $pu = $cant != 0.0 ? $usd / $cant : (float) $l->precio_unitario;
            yield [
                'fecha'             => $l->fecha,
                'maquina'           => $l->maquina_fiscal,
                'factura'           => $l->numero_factura,
                'pedido'            => $l->id_pedido,
                'hora'              => substr((string) $l->fecha_hora, 11, 8),
                'tipo'              => $cant < 0 ? 'DEVOLUCION' : 'VENTA',
                'codigo_barras'     => $l->codigo_barras,
                'codigo_proveedor'  => $l->codigo_proveedor,
                'descripcion'       => $l->descripcion ?? ($l->id_producto ? '(producto sin ficha #' . $l->id_producto . ')' : ''),
                'cantidad'          => $cant,
                'precio_usd'        => $pu,
                'importe_usd'       => $usd,
                'tasa'              => $tasa,
                'precio_bs'         => $tasa ? $pu * $tasa : null,
                'importe_bs'        => $bs,
                'total_factura_usd' => $totUsd,
                'total_factura_bs'  => $totBs,
                'lineas_factura'    => count($lineas),
            ];
        }
    }

    /**
     * Escribe el CSV (BOM UTF-8 + cabecera + filas) en un recurso abierto. Formato "excel": separador ";" y decimal coma
     * (Excel en español lo abre con doble clic); "plano": separador "," y punto decimal. Devuelve los totales escritos.
     */
    public function escribir($out, iterable $filas, string $formato = 'excel'): array
    {
        $excel = $formato !== 'plano';
        $sep = $excel ? ';' : ',';
        $dec = $excel ? ',' : '.';
        $n = fn ($v, int $d) => $v === null ? '' : number_format((float) $v, $d, $dec, '');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::CABECERA, $sep);
        $tot = ['filas' => 0, 'facturas' => 0, 'usd' => 0.0, 'bs' => 0.0];
        $ultimo = null;
        foreach ($filas as $f) {
            fputcsv($out, [
                $f['fecha'], $f['maquina'], $f['factura'], $f['pedido'], $f['hora'], $f['tipo'],
                $f['codigo_barras'], $f['codigo_proveedor'], $f['descripcion'],
                $n($f['cantidad'], 4), $n($f['precio_usd'], 4), $n($f['importe_usd'], 4), $n($f['tasa'], 4), $n($f['precio_bs'], 4), $n($f['importe_bs'], 4),
                $n($f['total_factura_usd'], 4), $n($f['total_factura_bs'], 4), $f['lineas_factura'],
            ], $sep);
            $tot['filas']++;
            $tot['usd'] += $f['importe_usd'];
            $tot['bs'] += (float) $f['importe_bs'];
            if ($f['pedido'] !== $ultimo) {
                $tot['facturas']++;
                $ultimo = $f['pedido'];
            }
            if ($tot['filas'] % 2000 === 0) {
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }
        return $tot;
    }
}
