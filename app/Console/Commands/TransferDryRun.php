<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use App\Models\inventario;
use App\Models\movimientosInventariounitario;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Valida end-to-end el flujo de transferencias premontadas SIN hacer commits.
 *
 * Pasos:
 *  1) Pide premontadas a central via /getPremontadasFromOrdenDistribucion.
 *  2) Toma la primera (o la indicada con --orden=ID) y simula el descuento
 *     local + llamada a central, todo dentro de una transacción que SIEMPRE
 *     se hace rollback al final. La BD real no queda modificada.
 *  3) Imprime un reporte detallado: items, vínculos, stock antes/después,
 *     movimientos que se habrían creado, payload exacto que se mandaría
 *     a setPedidoInCentralFromMasters.
 *
 * Modos:
 *   php artisan transfer:dry-run                # primera premontada disponible
 *   php artisan transfer:dry-run --orden=42     # OD específica
 *   php artisan transfer:dry-run --execute      # ¡COMMIT REAL! úsalo solo cuando el dry-run se ve bien
 */
class TransferDryRun extends Command
{
    protected $signature = 'transfer:dry-run
                            {--orden= : id_orden_distribucion específica}
                            {--execute : Ejecutar de verdad (commit). Por defecto solo simula.}';

    protected $description = 'Simula (o ejecuta) la transferencia de una premontada y reporta paso a paso.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $idOrden = $this->option('orden') ? (int) $this->option('orden') : null;

        $this->info('=== TRANSFER DRY-RUN ===');
        $this->line($execute ? '<comment>MODO EJECUCIÓN: se hará commit real.</comment>' : 'MODO SIMULACIÓN: rollback garantizado.');
        $this->newLine();

        // Paso 1: pedir premontadas a central.
        $this->line('[1/4] Consultando premontadas en central...');
        $sc = new sendCentral();
        $resp = $sc->getPremontadasFromOrdenDistribucion(Request::create('', 'POST', ['limit' => 50]));
        $data = $resp->getData(true);
        if (empty($data['estado'])) {
            $this->error('Central rechazó la consulta: ' . ($data['msj'] ?? 'sin mensaje'));
            return self::FAILURE;
        }
        $premontadas = $data['premontadas'] ?? [];
        $this->info('  → ' . count($premontadas) . ' premontadas disponibles');

        if (count($premontadas) === 0) {
            $this->warn('No hay OD aprobadas para esta sucursal. Crea una en central (estado=Aprobada) y vuelve.');
            return self::SUCCESS;
        }

        // Paso 2: seleccionar OD.
        $pm = null;
        if ($idOrden) {
            foreach ($premontadas as $p) {
                if ((int) $p['id_orden_distribucion'] === $idOrden) {
                    $pm = $p;
                    break;
                }
            }
            if (!$pm) {
                $this->error("OD #$idOrden no aparece en premontadas (¿no está aprobada? ¿ya fue transferida?).");
                return self::FAILURE;
            }
        } else {
            $pm = $premontadas[0];
        }

        $this->line('[2/4] OD seleccionada: #' . $pm['id_orden_distribucion'] .
            ' → destino ' . ($pm['sucursal_destino']['codigo'] ?? '?') .
            ' (' . count($pm['items']) . ' items)');

        // Paso 3: resolver vínculos locales y stock.
        $this->line('[3/4] Resolviendo vínculos locales y stock...');
        $itemsPayload = [];
        $reporte = [];
        $hayErrores = false;

        foreach ($pm['items'] as $idx => $it) {
            $masterId = (int) $it['producto_id_master'];
            $cantidad = (float) $it['cantidad'];
            $local = inventario::where('id_vinculacion', $masterId)->first();

            $row = [
                'master_id' => $masterId,
                'desc' => $it['producto']['descripcion'] ?? '?',
                'cant_solicitada' => $cantidad,
                'vinculo_local' => $local ? $local->id : null,
                'stock_actual' => $local ? (float) $local->cantidad : null,
                'stock_despues' => null,
                'origen_mov' => null,
                'status' => 'OK',
                'nota' => '',
            ];

            if (!$local) {
                $row['status'] = 'ERROR';
                $row['nota'] = "Sin vínculo local (id_vinculacion=$masterId).";
                $hayErrores = true;
            } elseif ($cantidad > (float) $local->cantidad) {
                $row['status'] = 'ERROR';
                $row['nota'] = "Stock insuficiente. Hay {$local->cantidad}, piden $cantidad.";
                $hayErrores = true;
            } else {
                $row['stock_despues'] = (float) $local->cantidad - $cantidad;
                $row['origen_mov'] = "TRANS.SAL OD#" . $pm['id_orden_distribucion'];
                $itemsPayload[] = [
                    'producto_id_master' => $masterId,
                    'cantidad' => $cantidad,
                ];
            }

            $reporte[] = $row;
        }

        $this->table(
            ['#', 'master', 'descripción', 'pide', 'stock', 'después', 'origen_mov', 'status', 'nota'],
            array_map(function ($r, $i) {
                return [
                    $i + 1,
                    $r['master_id'],
                    mb_strimwidth($r['desc'], 0, 30, '…'),
                    $r['cant_solicitada'],
                    $r['stock_actual'] ?? '-',
                    $r['stock_despues'] ?? '-',
                    $r['origen_mov'] ?? '-',
                    $r['status'],
                    $r['nota'],
                ];
            }, $reporte, array_keys($reporte))
        );

        if ($hayErrores) {
            $this->error('Hay errores en los items. Aborto.');
            return self::FAILURE;
        }

        // Paso 4: ejecutar real o solo mostrar payload.
        // NOTA: en modo simulación NO llamamos a settransferenciaDici, porque ese método
        // hace POST real a central y crearía el pedido espejo aunque hagamos rollback local
        // (la llamada HTTP no está en transacción). Para simular sin tocar nada, solo
        // imprimimos el payload exacto y los efectos que tendría.
        if (!$execute) {
            $this->newLine();
            $this->warn('[4/4] SIMULACIÓN — no se llama a central. Este es el payload que se enviaría:');
            $payload = [
                'id_destino' => $pm['sucursal_destino']['id'],
                'observaciones' => 'transfer:dry-run',
                'id_orden_distribucion' => $pm['id_orden_distribucion'],
                'items' => $itemsPayload,
            ];
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->newLine();
            $this->info('Efectos que tendría si --execute:');
            foreach ($reporte as $r) {
                if ($r['status'] !== 'OK') continue;
                $this->line(sprintf(
                    '  • inventario #%d "%s": %s → %s | movimiento origen "%s"',
                    $r['vinculo_local'],
                    mb_strimwidth($r['desc'], 0, 40, '…'),
                    $r['stock_actual'],
                    $r['stock_despues'],
                    $r['origen_mov']
                ));
            }
            $this->line('  • central /setPedidoInCentralFromMasters: crea pedidos+items y marca OD #' . $pm['id_orden_distribucion'] . ' como "En Tránsito"');
            $this->newLine();
            $this->warn('Reintenta con --execute para hacerlo de verdad.');
            return self::SUCCESS;
        }

        // Modo execute: llamamos real a settransferenciaDici, que descuenta y llama a central.
        $this->line('[4/4] EJECUTANDO settransferenciaDici (commit real)...');
        $countMovsAntes = movimientosInventariounitario::count();

        try {
            $payload = [
                'id_destino' => $pm['sucursal_destino']['id'],
                'observaciones' => 'transfer:dry-run --execute',
                'id_orden_distribucion' => $pm['id_orden_distribucion'],
                'items' => $itemsPayload,
            ];
            $req = Request::create('', 'POST', $payload);
            $resp = $sc->settransferenciaDici($req);
            $resData = $resp->getData(true);

            $this->newLine();
            if (empty($resData['estado'])) {
                $this->error('settransferenciaDici devolvió error: ' . ($resData['msj'] ?? 'sin mensaje'));
                if (isset($resData['line'])) {
                    $this->error('Línea: ' . $resData['line']);
                }
                return self::FAILURE;
            }

            $this->info('Respuesta de settransferenciaDici:');
            $this->line(json_encode($resData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $countMovsDespues = movimientosInventariounitario::count();
            $this->newLine();
            $this->info('Movimientos de inventario creados: ' . ($countMovsDespues - $countMovsAntes));
            $this->info('Verifica:');
            $this->line('  • Tu inventario local: las cantidades reportadas arriba deben coincidir con la BD.');
            $this->line('  • La OD #' . $pm['id_orden_distribucion'] . ' en central: debe estar en "En Tránsito".');
            $this->line('  • La tabla pedidos en central: nuevo row con id_orden_distribucion = ' . $pm['id_orden_distribucion'] . '.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Excepción: ' . $e->getMessage());
            $this->error('Línea: ' . $e->getLine() . ' Archivo: ' . $e->getFile());
            return self::FAILURE;
        }
    }
}
