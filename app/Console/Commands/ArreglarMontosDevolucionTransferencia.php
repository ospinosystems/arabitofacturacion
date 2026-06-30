<?php

namespace App\Console\Commands;

use App\Models\pago_pedidos;
use App\Models\pagos_referencias;
use App\Models\items_pedidos;
use App\Support\BancoMoneda;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ARREGLO MONTOS DEVOLUCIÓN TRANSFERENCIA 2026-06-27
 *
 * Corrige los pagos históricos del bug donde, en una DEVOLUCIÓN sin items por
 * transferencia, el pago tipo=1 guardó el monto EN BS de la referencia como si fuera
 * USD (1:1), en vez de convertirlo. El fix de código ya evita los nuevos; este comando
 * arregla los que ya quedaron mal.
 *
 * Enfoque (según el flujo real):
 *   1. Parte de `pagos_referencias` (tipo=1) cargadas en la fecha indicada.
 *   2. Por cada pedido de esas refs: confirma que NO tiene items (devolución pura) y
 *      que SÍ tiene un pago registrado (pago_pedidos tipo=1).
 *   3. Mira la MONEDA del banco de cada ref (BancoMoneda::esDivisa, del catálogo central):
 *      - banco en Bs  → el USD correcto = monto_ref / tasa.
 *      - banco en USD → el USD correcto = monto_ref (directo).
 *   4. Si el pago está guardado 1:1 con el Bs (sin convertir), lo corrige al USD real,
 *      conservando el signo y seteando monto_original (moneda del banco) + moneda.
 *
 * SEGURO: dry-run por defecto; solo toca pagos cuya firma es EXACTAMENTE la del bug
 * (monto == suma Bs de las refs SIN convertir, y distinto del USD correcto).
 */
class ArreglarMontosDevolucionTransferencia extends Command
{
    protected $signature = 'transferencia:arreglar-montos-devolucion
                            {--fecha= : Fecha (YYYY-MM-DD) de las referencias a revisar. OBLIGATORIA.}
                            {--hasta= : Fecha fin (YYYY-MM-DD). Default = --fecha (un solo día).}
                            {--tasa= : Tasa Bs/USD a usar. Si no se da, se toma de `monedas` (tipo=1) para la fecha.}
                            {--aplicar : Ejecuta la corrección. Sin esta bandera solo lista (dry-run).}';

    protected $description = 'Corrige pagos de transferencia (tipo=1) de DEVOLUCIONES sin items donde el Bs quedó guardado como USD (1:1). Recalcula el USD según la moneda del banco.';

    public function handle(): int
    {
        $fecha = $this->option('fecha');
        if (!$fecha) {
            $this->error('Falta --fecha=YYYY-MM-DD');
            return self::FAILURE;
        }
        $hasta = $this->option('hasta') ?: $fecha;
        $aplicar = (bool) $this->option('aplicar');

        // Guard: el catálogo de bancos (de central) es necesario para clasificar la moneda.
        // Sin él, esDivisa cae a false y podría malinterpretar un banco en dólares.
        $catCount = $this->contarCatalogoBancos();
        if ($catCount === 0) {
            $this->error('No se pudo cargar el catálogo de bancos desde central (0 bancos). ' .
                'Es necesario para clasificar la moneda del banco. Verificá que central esté accesible y reintentá.');
            return self::FAILURE;
        }

        // Tasa Bs/USD: --tasa o, si no, la activa en `monedas` para la fecha.
        $tasa = $this->option('tasa') ? (float) $this->option('tasa') : null;
        if (!$tasa) {
            $row = DB::table('monedas')->where('tipo', 1)
                ->where('created_at', '<=', $hasta . ' 23:59:59')
                ->orderByDesc('created_at')->first();
            if (!$row) {
                $row = DB::table('monedas')->where('tipo', 1)->orderByDesc('id')->first();
            }
            $tasa = $row ? (float) $row->valor : 0;
        }
        if ($tasa <= 1) {
            $this->error("Tasa Bs/USD inválida ({$tasa}). Pasá --tasa=XXX explícitamente.");
            return self::FAILURE;
        }

        $this->info("Rango: {$fecha} → {$hasta} | Tasa Bs/USD: {$tasa} | Catálogo bancos: {$catCount}");

        // 1) Pedidos que tienen referencias de transferencia (tipo=1) cargadas en el rango.
        $pedidoIds = pagos_referencias::where('tipo', 1)
            ->whereBetween('created_at', [$fecha . ' 00:00:00', $hasta . ' 23:59:59'])
            ->whereNotNull('id_pedido')
            ->distinct()
            ->pluck('id_pedido');

        $aCorregir = [];
        $reporte = [];

        foreach ($pedidoIds as $idPedido) {
            // 2a) Debe ser devolución SIN items (sin productos reales).
            $nItems = items_pedidos::where('id_pedido', $idPedido)->whereNotNull('id_producto')->count();
            if ($nItems > 0) {
                continue;
            }
            // 2b) Debe tener un pago de transferencia registrado.
            $pago = pago_pedidos::where('id_pedido', $idPedido)->where('tipo', 1)->first();
            if (!$pago) {
                continue;
            }

            $refs = pagos_referencias::where('id_pedido', $idPedido)->where('tipo', 1)->get(['monto', 'banco']);
            if ($refs->isEmpty()) {
                continue;
            }

            // 3) Moneda del banco de cada ref → USD correcto.
            $bsSum = 0.0;       // suma de las refs en su moneda (lo que el bug guardó como USD)
            $correctUsd = 0.0;  // USD correcto según la moneda del banco
            $monedas = [];
            foreach ($refs as $r) {
                $m = (float) $r->monto;
                $esDiv = BancoMoneda::esDivisa($r->banco);
                $bsSum += $m;
                $monedas[] = $esDiv ? 'dolar' : 'bs';
                $correctUsd += $esDiv ? $m : ($m / $tasa);
            }
            $monedaUnica = (count(array_unique($monedas)) === 1) ? $monedas[0] : null;

            $cur = (float) $pago->monto;
            $signo = $cur < 0 ? -1 : 1;

            // 4) Firma EXACTA del bug: el pago está 1:1 con el Bs (== suma de refs sin convertir)
            // Y distinto del USD correcto. NO matchea pagos ya correctos ni bancos en dólares
            // (cuyo USD correcto == el monto guardado).
            $esBuggy = abs(abs($cur) - abs($bsSum)) < 0.5
                    && abs(abs($cur) - abs($correctUsd)) > 0.5;
            if (!$esBuggy) {
                continue;
            }

            $aCorregir[] = [
                'pago'   => $pago,
                'usd'    => $signo * abs($correctUsd),
                'orig'   => $signo * abs($bsSum),
                'moneda' => $monedaUnica,
            ];
            $reporte[] = [
                $idPedido,
                $pago->id,
                round($cur, 2),
                round($signo * abs($correctUsd), 4),
                $monedaUnica ?? 'mix',
                round($bsSum, 2),
            ];
        }

        $this->table(
            ['id_pedido', 'id_pago', 'monto_actual($)', 'monto_correcto($)', 'moneda', 'bs_ref'],
            $reporte
        );
        $this->info('Pagos a corregir: ' . count($aCorregir));

        if (empty($aCorregir)) {
            $this->info('Nada que corregir en ese rango.');
            return self::SUCCESS;
        }
        if (!$aplicar) {
            $this->warn('DRY-RUN. Reintentá con --aplicar para corregir.');
            return self::SUCCESS;
        }

        $n = 0;
        foreach ($aCorregir as $c) {
            $c['pago']->update([
                'monto'          => $c['usd'],
                'monto_original' => $c['orig'],
                'moneda'         => $c['moneda'],
            ]);
            $n++;
        }
        $this->info("Corregidos {$n} pagos.");
        return self::SUCCESS;
    }

    /** Cuenta los bancos del catálogo (de central vía el proxy local). */
    private function contarCatalogoBancos(): int
    {
        try {
            $resp = (new \App\Http\Controllers\BancosListController())->getBancos(new \Illuminate\Http\Request());
            $payload = json_decode($resp->getContent(), true);
            return is_array($payload['bancos'] ?? null) ? count($payload['bancos']) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
