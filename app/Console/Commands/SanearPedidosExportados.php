<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use App\Models\pedidos;
use App\Models\transferencias_inventario;
use Illuminate\Console\Command;

/**
 * SANEAR EXPORT 2026-06-09 — detecta y retira espejos de exportación huérfanos en
 * central: pedidos espejo (estado 1/3/4, pendientes de extraer por la sucursal
 * destino) cuyo pedido original en esta sucursal ya no existe, fue anulado o perdió
 * la marca export. Un espejo huérfano es peligroso: la sucursal destino aún puede
 * extraerlo e incorporar inventario que el origen ya repuso al eliminar el pedido.
 *
 * Por defecto solo reporta (dry-run). Con --aplicar borra los huérfanos en central.
 *
 * NO toca espejos que pertenecen a otros flujos que comparten la tabla pedidos en
 * central y no tienen pedido local en esta tabla:
 *   - TCD: idinsucursal = 9000000 + orden_id
 *   - Premontadas/OD: id_orden_distribucion != null
 *   - Transferencias DICI: idinsucursal = transferencias_inventario.id
 * Tampoco toca espejos recientes (--dias, default 2) para no chocar con exportaciones
 * en vuelo.
 *
 * Uso:
 *   php artisan pedidos:sanear-exportados            # solo reporte
 *   php artisan pedidos:sanear-exportados --aplicar  # borra huérfanos en central
 */
class SanearPedidosExportados extends Command
{
    protected $signature = 'pedidos:sanear-exportados
                            {--aplicar : Borrar en central los espejos huérfanos detectados}
                            {--dias=2 : Ignorar espejos creados hace menos de N días}';

    protected $description = 'Detecta (y con --aplicar retira) pedidos espejo huérfanos en central cuyo pedido local ya no existe o fue anulado.';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $dias = max(0, (int) $this->option('dias'));
        $limiteReciente = now()->subDays($dias);

        $this->info('=== SANEAR PEDIDOS EXPORTADOS ===');
        $this->line($aplicar ? '<comment>MODO APLICAR: los huérfanos se borrarán en central.</comment>' : 'MODO REPORTE: no se borra nada (usa --aplicar para ejecutar).');
        $this->newLine();

        $sc = new sendCentral();
        $res = $sc->getPedidosEspejoCentral();
        if (empty($res['estado'])) {
            $this->error('Central rechazó la consulta: ' . ($res['msj'] ?? 'sin mensaje'));
            return self::FAILURE;
        }

        $espejos = $res['espejos'] ?? [];
        $this->info('Espejos no extraídos en central (estados 1/3/4): ' . count($espejos));

        $huerfanos = [];
        $reporte = [];

        foreach ($espejos as $espejo) {
            $idCentral = $espejo['id'];
            $idLocal = (int) $espejo['idinsucursal'];
            $destino = $espejo['destino']['codigo'] ?? $espejo['id_destino'] ?? '?';
            $fila = [
                'id_central' => $idCentral,
                'idinsucursal' => $idLocal,
                'estado_central' => $espejo['estado'],
                'destino' => $destino,
                'creado' => $espejo['created_at'] ?? '?',
            ];

            // ── Flujos ajenos al export de pedidos: nunca tocar ──
            if (!empty($espejo['id_orden_distribucion'])) {
                $fila['veredicto'] = 'SKIP: premontada/OD #' . $espejo['id_orden_distribucion'];
                $reporte[] = $fila;
                continue;
            }
            if ($idLocal >= 9000000) {
                $fila['veredicto'] = 'SKIP: orden TCD';
                $reporte[] = $fila;
                continue;
            }
            $esTransferenciaDici = transferencias_inventario::where('id_transferencia_central', $idCentral)
                ->orWhere('id', $idLocal)
                ->exists();
            if ($esTransferenciaDici) {
                $fila['veredicto'] = 'SKIP: transferencia DICI';
                $reporte[] = $fila;
                continue;
            }

            // ── Margen para exportaciones en vuelo ──
            if (!empty($espejo['created_at']) && $espejo['created_at'] >= $limiteReciente->format('Y-m-d H:i:s')) {
                $fila['veredicto'] = "SKIP: reciente (< $dias días)";
                $reporte[] = $fila;
                continue;
            }

            // ── Cruce contra el pedido local ──
            $local = pedidos::find($idLocal);
            if (!$local) {
                $fila['veredicto'] = 'HUÉRFANO: pedido local no existe';
                $huerfanos[$idLocal] = $fila;
            } elseif (!$local->export) {
                // El espejo existe pero el pedido local no está marcado como exportado:
                // respuesta de "add" perdida o desexport a medias. El espejo sobra y
                // además bloquea re-exportar (índice único idinsucursal+id_origen).
                $fila['veredicto'] = 'HUÉRFANO: pedido local sin marca export (estado local ' . $local->estado . ')';
                $huerfanos[$idLocal] = $fila;
            } elseif ($local->estado == 2) {
                $fila['veredicto'] = 'HUÉRFANO: pedido local anulado';
                $huerfanos[$idLocal] = $fila;
            } elseif ($local->estado == 0) {
                $fila['veredicto'] = 'HUÉRFANO: pedido local pendiente con espejo colgado';
                $huerfanos[$idLocal] = $fila;
            } else {
                $fila['veredicto'] = 'OK: export en tránsito';
            }
            $reporte[] = $fila;
        }

        $this->table(
            ['id central', 'id local', 'estado central', 'destino', 'creado', 'veredicto'],
            array_map(function ($r) {
                return [$r['id_central'], $r['idinsucursal'], $r['estado_central'], $r['destino'], $r['creado'], $r['veredicto']];
            }, $reporte)
        );

        if (empty($huerfanos)) {
            $this->info('Sin espejos huérfanos. Nada que sanear.');
            return self::SUCCESS;
        }

        $this->warn('Espejos huérfanos detectados: ' . count($huerfanos));
        if (!$aplicar) {
            $this->warn('Reintenta con --aplicar para retirarlos de central.');
            return self::SUCCESS;
        }

        // Borrado en central por lotes chicos (misma política que operaciones masivas:
        // requests cortos, sin transacciones eternas).
        $errores = 0;
        foreach (array_chunk(array_keys($huerfanos), 25) as $lote) {
            $resDel = $sc->deletePedidosEspejoCentral($lote);
            if (empty($resDel['estado'])) {
                $errores++;
                $this->error('Lote falló: ' . ($resDel['msj'] ?? 'sin mensaje'));
                continue;
            }
            $this->info($resDel['msj'] ?? 'Lote procesado');

            // Consistencia local: el pedido (si existe) ya no tiene espejo en central.
            $idsConfirmados = array_merge($resDel['eliminados'] ?? [], $resDel['sin_espejo'] ?? []);
            foreach ($idsConfirmados as $idLocal) {
                $local = pedidos::find($idLocal);
                if ($local && $local->export) {
                    $local->export = 0;
                    $local->save();
                }
            }
            if (!empty($resDel['extraidos'])) {
                $this->warn('Central no retiró (ya extraídos): ' . implode(', ', $resDel['extraidos']));
            }
        }

        if ($errores) {
            $this->error("Terminó con $errores lote(s) fallido(s). Revisa y reintenta.");
            return self::FAILURE;
        }
        $this->info('Saneamiento completado.');
        return self::SUCCESS;
    }
}
