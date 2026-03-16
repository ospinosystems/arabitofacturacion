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
 * Reemplaza los ítems del pedido por una combinación de 3-8 productos reales del inventario
 * cuyos precios (USD) × tasa ≈ monto_bs objetivo del pedido.
 * Usa fuerza bruta con poda para encontrar combinaciones válidas.
 */
class CuadreFixUnitarias extends Command
{
    protected $signature = 'cuadre:fix-unitarias
                            {--dry-run : Solo mostrar qué se haría, sin escribir}
                            {--umbral=3 : Factor multiplicador: un ítem se considera irreal si precio_unitario > precio_inventario × umbral}
                            {--tolerancia=0.02 : Tolerancia en Bs para aceptar una combinación (default 0.02 Bs)}';

    protected $description = 'Reemplaza ítems con precios irreales en pedidos específicos por combinaciones de productos reales del inventario.';

    protected const PEDIDOS_OBJETIVO = [
        271122, 269613, 267833, 270150, 272689,
        265875, 268846, 272023, 271498, 262731,
        270450, 266862, 268578, 272556, 269322,
    ];

    protected float $tolerancia;
    protected float $umbral;
    protected int $fixedCount = 0;
    protected int $skippedCount = 0;
    protected int $failedCount = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->umbral = (float) $this->option('umbral');
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

        $pedidosIrreales = $this->buscarPedidosObjetivo();
        if (empty($pedidosIrreales)) {
            $this->info('No se encontraron pedidos FISCAL UNITARIA con precios irreales.');
            return Command::SUCCESS;
        }
        $this->info('Pedidos con precios irreales encontrados: ' . count($pedidosIrreales));
        $this->newLine();

        foreach ($pedidosIrreales as $info) {
            $pedido = $info['pedido'];
            $montoBsObjetivo = $info['monto_bs_total'];
            $tasa = $info['tasa'];

            $this->line("Pedido <comment>#{$pedido->id}</comment> | Factura <comment>{$pedido->numero_factura}</comment> | Objetivo: <comment>" . number_format($montoBsObjetivo, 2) . " Bs</comment> (tasa: " . number_format($tasa, 4) . ")");

            $montoUsdObjetivo = $tasa > 0 ? $montoBsObjetivo / $tasa : $montoBsObjetivo;
            $combinacion = $this->buscarCombinacion($productosInventario, $montoUsdObjetivo, $tasa, $montoBsObjetivo);

            if ($combinacion === null) {
                $this->line("  → <fg=red>No se encontró combinación válida (tolerancia: {$this->tolerancia} Bs)</>");
                $this->failedCount++;
                continue;
            }

            $sumaUsd = array_sum(array_column($combinacion, 'monto_usd'));
            $sumaBs = array_sum(array_column($combinacion, 'monto_bs'));
            $diff = abs($montoBsObjetivo - $sumaBs);
            $this->line("  → <info>" . count($combinacion) . " ítems</info>, total USD: " . number_format($sumaUsd, 4) . ", total Bs: " . number_format($sumaBs, 4) . " (diff: " . number_format($diff, 4) . " Bs)");

            foreach ($combinacion as $item) {
                $this->line("    - {$item['descripcion']} × {$item['cantidad']} @ {$item['precio_unitario']} USD = " . number_format($item['monto_bs'], 4) . " Bs");
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

    /**
     * Carga productos del inventario con precio > 0.
     * Devuelve array de ['id', 'precio', 'descripcion', 'codigo_barras', 'codigo_proveedor'].
     */
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

    /**
     * Carga los 15 pedidos objetivo y sus montos/tasa actuales.
     */
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
     * Busca una combinación de 3-8 productos reales cuyo total en Bs ≈ objetivo.
     * Estrategia: fuerza bruta con poda inteligente.
     *  1. Ordena productos por precio.
     *  2. Para cada cantidad (3..8), intenta construir combinaciones:
     *     - Llena N-1 slots con productos aleatorios.
     *     - El último slot intenta cubrir el residuo exacto (busca producto con precio ≈ residuo).
     *  3. Permite cantidades > 1 del mismo producto.
     */
    protected function buscarCombinacion(array $productos, float $montoUsdObjetivo, float $tasa, float $montoBsObjetivo): ?array
    {
        $preciosUsd = array_column($productos, 'precio');
        $totalProductos = count($productos);
        $bestCombinacion = null;
        $bestDiff = PHP_FLOAT_MAX;
        $maxIntentos = 50000;

        for ($numItems = 3; $numItems <= 8; $numItems++) {
            for ($intento = 0; $intento < $maxIntentos; $intento++) {
                $seleccion = [];
                $sumaUsd = 0.0;

                for ($slot = 0; $slot < $numItems - 1; $slot++) {
                    $idx = mt_rand(0, $totalProductos - 1);
                    $precio = $preciosUsd[$idx];
                    $maxCant = max(1, (int) floor(($montoUsdObjetivo - $sumaUsd) / max($precio, 0.01)));
                    $maxCant = min($maxCant, 5);
                    if ($maxCant < 1) $maxCant = 1;
                    $cant = mt_rand(1, $maxCant);
                    $sumaUsd += $precio * $cant;
                    $seleccion[] = ['idx' => $idx, 'cantidad' => $cant, 'precio' => $precio];

                    if ($sumaUsd >= $montoUsdObjetivo * 1.1) break;
                }

                if ($sumaUsd >= $montoUsdObjetivo * 1.1) continue;

                $residuoUsd = $montoUsdObjetivo - $sumaUsd;
                if ($residuoUsd <= 0) continue;

                $mejorIdx = null;
                $mejorCant = 1;
                $mejorDiffLocal = PHP_FLOAT_MAX;

                for ($i = 0; $i < $totalProductos; $i++) {
                    $p = $preciosUsd[$i];
                    if ($p <= 0) continue;
                    $cantMax = max(1, (int) round($residuoUsd / $p));
                    for ($c = max(1, $cantMax - 2); $c <= min($cantMax + 2, 10); $c++) {
                        $d = abs($residuoUsd - $p * $c);
                        $dBs = $d * $tasa;
                        if ($dBs < $mejorDiffLocal) {
                            $mejorDiffLocal = $dBs;
                            $mejorIdx = $i;
                            $mejorCant = $c;
                        }
                    }
                }

                if ($mejorIdx === null) continue;

                $seleccion[] = ['idx' => $mejorIdx, 'cantidad' => $mejorCant, 'precio' => $preciosUsd[$mejorIdx]];
                $sumaUsdFinal = $sumaUsd + $preciosUsd[$mejorIdx] * $mejorCant;
                $sumaBsFinal = $sumaUsdFinal * $tasa;
                $diff = abs($montoBsObjetivo - $sumaBsFinal);

                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestCombinacion = $seleccion;
                }

                if ($diff <= $this->tolerancia) {
                    return $this->formatearCombinacion($bestCombinacion, $productos, $tasa);
                }
            }
        }

        if ($bestCombinacion !== null && $bestDiff <= $this->tolerancia * 50) {
            return $this->ajustarUltimoItem($bestCombinacion, $productos, $tasa, $montoBsObjetivo);
        }

        return null;
    }

    /**
     * Formatea la combinación para inserción.
     */
    protected function formatearCombinacion(array $seleccion, array $productos, float $tasa): array
    {
        $result = [];
        foreach ($seleccion as $s) {
            $prod = $productos[$s['idx']];
            $montoUsd = round($s['precio'] * $s['cantidad'], 4);
            $montoBs = round($montoUsd * $tasa, 4);
            $result[] = [
                'id_producto'      => $prod['id'],
                'cantidad'         => $s['cantidad'],
                'precio_unitario'  => $s['precio'],
                'monto'            => $montoUsd,
                'monto_bs'         => $montoBs,
                'descripcion'      => $prod['descripcion'],
                'codigo_barras'    => $prod['codigo_barras'],
                'codigo_proveedor' => $prod['codigo_proveedor'],
            ];
        }
        return $result;
    }

    /**
     * Si la combinación no es exacta, ajusta el precio del último ítem para cuadrar al centavo.
     */
    protected function ajustarUltimoItem(array $seleccion, array $productos, float $tasa, float $montoBsObjetivo): array
    {
        $result = $this->formatearCombinacion($seleccion, $productos, $tasa);

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

    /**
     * Reemplaza los ítems del pedido por la nueva combinación y actualiza el pago.
     */
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
