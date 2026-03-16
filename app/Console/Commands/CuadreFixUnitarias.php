<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;

/**
 * Corrige pedidos FISCAL UNITARIA cuyo ajuste dejó precios irreales.
 * Reemplaza los ítems del pedido por 3-8 productos DISTINTOS del inventario (cantidad 1-3 cada uno)
 * cuyos precios (USD) × tasa ≈ monto_bs objetivo del pedido.
 */
class CuadreFixUnitarias extends Command
{
    protected $signature = 'cuadre:fix-unitarias
                            {--dry-run : Solo mostrar qué se haría, sin escribir}
                            {--tolerancia=0.02 : Tolerancia en Bs para aceptar una combinación (default 0.02 Bs)}';

    protected $description = 'Reemplaza ítems con precios irreales en pedidos específicos por combinaciones de productos reales del inventario.';

    protected const PEDIDOS_OBJETIVO = [
        271122, 269613, 267833, 270150, 272689,
        265875, 268846, 272023, 271498, 262731,
        270450, 266862, 268578, 272556, 269322,
    ];

    protected float $tolerancia;
    protected int $fixedCount = 0;
    protected int $failedCount = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->tolerancia = (float) $this->option('tolerancia');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirá en la base de datos.');
        }

        $this->info('Pedidos objetivo: ' . implode(', ', self::PEDIDOS_OBJETIVO));

        $productosInventario = $this->cargarProductosInventario();
        if (empty($productosInventario)) {
            $this->error('No se encontraron productos con precio > 0 en el inventario.');
            return Command::FAILURE;
        }
        $this->info('Productos del inventario con precio > 0: ' . count($productosInventario));

        $pedidosObjetivo = $this->buscarPedidosObjetivo();
        if (empty($pedidosObjetivo)) {
            $this->info('No se encontraron los pedidos objetivo.');
            return Command::SUCCESS;
        }
        $this->info('Pedidos a procesar: ' . count($pedidosObjetivo));
        $this->newLine();

        foreach ($pedidosObjetivo as $info) {
            $pedido = $info['pedido'];
            $montoBsObjetivo = $info['monto_bs_total'];
            $tasa = $info['tasa'];

            $this->line("Pedido <comment>#{$pedido->id}</comment> | Factura <comment>{$pedido->numero_factura}</comment> | Objetivo: <comment>" . number_format($montoBsObjetivo, 2) . " Bs</comment> (tasa: " . number_format($tasa, 4) . ")");

            $montoUsdObjetivo = $tasa > 0 ? $montoBsObjetivo / $tasa : $montoBsObjetivo;
            $combinacion = $this->buscarCombinacion($productosInventario, $montoUsdObjetivo, $tasa, $montoBsObjetivo);

            if ($combinacion === null) {
                $this->line("  → <fg=red>No se encontró combinación válida</>");
                $this->failedCount++;
                continue;
            }

            $sumaUsd = array_sum(array_column($combinacion, 'monto'));
            $sumaBs = array_sum(array_column($combinacion, 'monto_bs'));
            $diff = abs($montoBsObjetivo - $sumaBs);
            $this->line("  → <info>" . count($combinacion) . " ítems</info>, total USD: " . number_format($sumaUsd, 4) . ", total Bs: " . number_format($sumaBs, 4) . " (diff: " . number_format($diff, 4) . " Bs)");

            foreach ($combinacion as $item) {
                $this->line("    - {$item['descripcion']} × {$item['cantidad']} @ " . number_format($item['precio_unitario'], 4) . " USD = " . number_format($item['monto_bs'], 4) . " Bs");
            }

            if (!$dryRun) {
                $this->reemplazarItems($pedido, $combinacion, $tasa);
                $this->line("  → <info>Corregido</info>");
            } else {
                $this->line("  → <comment>(dry-run, no se modificó)</comment>");
            }
            $this->fixedCount++;
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('Corregidos: ' . $this->fixedCount);
        $this->info('Sin combinación encontrada: ' . $this->failedCount);
        $this->info('═══════════════════════════════════════');

        return Command::SUCCESS;
    }

    protected function cargarProductosInventario(): array
    {
        return DB::table('inventarios')
            ->where('precio', '>', 0)
            ->where(function ($q) {
                $q->whereNull('activo')->orWhere('activo', 1);
            })
            ->select('id', 'precio', 'descripcion', 'codigo_barras', 'codigo_proveedor')
            ->orderBy('precio')
            ->get()
            ->map(fn ($p) => [
                'id'               => $p->id,
                'precio'           => (float) $p->precio,
                'descripcion'      => $p->descripcion ?? '',
                'codigo_barras'    => $p->codigo_barras ?? '',
                'codigo_proveedor' => $p->codigo_proveedor ?? '',
            ])
            ->all();
    }

    protected function buscarPedidosObjetivo(): array
    {
        $pedidosList = pedidos::whereIn('id', self::PEDIDOS_OBJETIVO)->orderBy('id')->get();
        $result = [];

        foreach ($pedidosList as $pedido) {
            $items = items_pedidos::where('id_pedido', $pedido->id)->get();
            $totalBs = 0.0;
            $tasa = null;

            foreach ($items as $item) {
                $totalBs += (float) ($item->monto_bs ?? 0);
                if ($tasa === null && isset($item->tasa) && (float) $item->tasa > 0) {
                    $tasa = (float) $item->tasa;
                }
            }

            if ($tasa === null || $tasa <= 0) {
                $tasa = $this->obtenerTasaGlobal();
            }

            $result[] = [
                'pedido'         => $pedido,
                'monto_bs_total' => $totalBs,
                'tasa'           => $tasa,
            ];
        }

        return $result;
    }

    /**
     * Busca combinación de 3-8 productos DISTINTOS (cantidad 1-3 cada uno).
     * Cada producto aparece UNA sola vez. El último se ajusta para cuadrar al centavo.
     */
    protected function buscarCombinacion(array $productos, float $montoUsdObjetivo, float $tasa, float $montoBsObjetivo): ?array
    {
        $totalProductos = count($productos);
        $bestLineas = null;
        $bestDiff = PHP_FLOAT_MAX;
        $maxIntentos = 100000;

        for ($numItems = 3; $numItems <= 20; $numItems++) {
            if ($numItems > $totalProductos) break;

            for ($intento = 0; $intento < $maxIntentos; $intento++) {
                $usados = [];
                $lineas = [];
                $sumaUsd = 0.0;
                $ok = true;

                for ($slot = 0; $slot < $numItems - 1; $slot++) {
                    $intentosSlot = 0;
                    do {
                        $idx = mt_rand(0, $totalProductos - 1);
                        $intentosSlot++;
                    } while (isset($usados[$idx]) && $intentosSlot < 30);
                    if (isset($usados[$idx])) { $ok = false; break; }

                    $usados[$idx] = true;
                    $precio = $productos[$idx]['precio'];
                    $cant = mt_rand(1, 3);

                    if ($sumaUsd + $precio * $cant >= $montoUsdObjetivo * 1.1) {
                        $cant = 1;
                    }

                    $sumaUsd += $precio * $cant;
                    $lineas[] = ['idx' => $idx, 'cantidad' => $cant];

                    if ($sumaUsd >= $montoUsdObjetivo * 1.1) { $ok = false; break; }
                }
                if (!$ok) continue;

                $residuoUsd = $montoUsdObjetivo - $sumaUsd;
                if ($residuoUsd <= 0) continue;

                $mejorIdx = null;
                $mejorDiffLocal = PHP_FLOAT_MAX;
                for ($i = 0; $i < $totalProductos; $i++) {
                    if (isset($usados[$i])) continue;
                    $p = $productos[$i]['precio'];
                    if ($p <= 0) continue;
                    $d = abs($residuoUsd - $p);
                    if ($d < $mejorDiffLocal) {
                        $mejorDiffLocal = $d;
                        $mejorIdx = $i;
                    }
                }
                if ($mejorIdx === null) continue;

                $lineas[] = ['idx' => $mejorIdx, 'cantidad' => 1];
                $sumaUsdFinal = $sumaUsd + $productos[$mejorIdx]['precio'];
                $sumaBsFinal = $sumaUsdFinal * $tasa;
                $diff = abs($montoBsObjetivo - $sumaBsFinal);

                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestLineas = $lineas;
                }

                if ($diff <= $this->tolerancia) break;
            }

            if ($bestDiff <= $this->tolerancia) break;
        }

        if ($bestLineas === null) {
            return null;
        }

        return $this->formatearResultado($bestLineas, $productos, $tasa, $montoBsObjetivo);
    }

    /**
     * Formatea la combinación y ajusta el último ítem para cuadrar al centavo exacto.
     */
    protected function formatearResultado(array $lineas, array $productos, float $tasa, float $montoBsObjetivo): array
    {
        $result = [];
        foreach ($lineas as $l) {
            $prod = $productos[$l['idx']];
            $montoUsd = round($prod['precio'] * $l['cantidad'], 4);
            $montoBs = round($montoUsd * $tasa, 4);
            $result[] = [
                'id_producto'      => $prod['id'],
                'cantidad'         => $l['cantidad'],
                'precio_unitario'  => $prod['precio'],
                'monto'            => $montoUsd,
                'monto_bs'         => $montoBs,
                'descripcion'      => $prod['descripcion'],
                'codigo_barras'    => $prod['codigo_barras'],
                'codigo_proveedor' => $prod['codigo_proveedor'],
            ];
        }

        $sumaBs = array_sum(array_column($result, 'monto_bs'));
        $diff = $montoBsObjetivo - $sumaBs;

        if (abs($diff) > 0.001) {
            $lastIdx = count($result) - 1;
            $last = &$result[$lastIdx];
            $nuevoMontoBs = $last['monto_bs'] + $diff;
            $nuevoMontoUsd = $tasa > 0 ? $nuevoMontoBs / $tasa : $nuevoMontoBs;
            $nuevoPrecio = $last['cantidad'] > 0 ? $nuevoMontoUsd / $last['cantidad'] : $nuevoMontoUsd;
            $last['precio_unitario'] = round($nuevoPrecio, 4);
            $last['monto'] = round($last['cantidad'] * $last['precio_unitario'], 4);
            $last['monto_bs'] = round($last['monto'] * $tasa, 4);
        }

        return $result;
    }

    protected function reemplazarItems(pedidos $pedido, array $combinacion, float $tasa): void
    {
        DB::transaction(function () use ($pedido, $combinacion, $tasa) {
            items_pedidos::where('id_pedido', $pedido->id)->delete();

            foreach ($combinacion as $item) {
                items_pedidos::create([
                    'id_pedido'       => $pedido->id,
                    'id_producto'     => $item['id_producto'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'monto'           => $item['monto'],
                    'monto_bs'        => $item['monto_bs'],
                    'tasa'            => $tasa,
                    'descuento'       => 0,
                    'abono'           => 0,
                ]);
            }

            $sumaBs = array_sum(array_column($combinacion, 'monto_bs'));
            $pago = pago_pedidos::where('id_pedido', $pedido->id)->first();
            if ($pago) {
                $pago->monto = $sumaBs;
                if (Schema::hasColumn('pago_pedidos', 'monto_bs')) {
                    $pago->monto_bs = $sumaBs;
                }
                $pago->save();
            }
        });
    }

    protected function obtenerTasaGlobal(): float
    {
        $tasaMoneda = DB::table('monedas')->where('tipo', 1)->orderBy('id', 'desc')->value('valor');
        return $tasaMoneda !== null && (float) $tasaMoneda > 0 ? (float) $tasaMoneda : 1.0;
    }
}
