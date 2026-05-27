<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Diagnóstico: pide a central la lista de bancos asignados a ESTA sucursal y
 * valida que todo esté coherente.
 *
 * Sirve para:
 *   - Confirmar que el sync auth funciona (API key, codigo_origen).
 *   - Ver la versión que central registra (X-Sucursal-Version) ↔ el SHA actual.
 *   - Listar los bancos vigentes para la caja, con sus 4 flags efectivos.
 *   - Detectar inconsistencias: bancos inactivos, sin ningún flag, etc.
 *   - Reportar cuántos bancos hay por escenario (T-Ing, T-Dev, P-Ing, P-Dev).
 *
 * Uso:
 *   php artisan bancos:check                # usa cache local de 2h si hay
 *   php artisan bancos:check --refresh      # bustea cache y reconsulta central
 */
class BancosCheckCommand extends Command
{
    protected $signature = 'bancos:check {--refresh : Bustea cache local de 2h y reconsulta central}';

    protected $description = 'Trae la lista de bancos asignados a esta sucursal desde central y valida coherencia.';

    public function handle(): int
    {
        $refresh = (bool) $this->option('refresh');

        $this->info('═══════════════════════════════════════════════════════');
        $this->info(' DIAGNÓSTICO DE BANCOS — SUCURSAL ↔ CENTRAL');
        $this->info('═══════════════════════════════════════════════════════');

        // 1) Versión del código de esta sucursal
        $versionLocal = \App\Support\AppVersion::current();
        $this->line('');
        $this->line('<fg=cyan>Versión del código (SHA git HEAD):</fg=cyan> ' . $versionLocal . ' (corto: ' . \App\Support\AppVersion::short() . ')');

        // 2) Datos básicos de la sucursal local
        try {
            $suc = \App\Models\sucursal::first();
            if ($suc) {
                $this->line('<fg=cyan>Sucursal local:</fg=cyan> id=' . $suc->id . ' codigo=' . $suc->codigo . ' nombre=' . ($suc->nombre ?? '(s/n)'));
            } else {
                $this->warn('No se encontró registro de sucursal local en la tabla `sucursals`.');
            }
        } catch (\Throwable $e) {
            $this->warn('No se pudo leer sucursal local: ' . $e->getMessage());
        }

        // 3) API key de central
        try {
            $sc = new \App\Http\Controllers\sendCentral();
            $apiKey = $sc->getCentralApiKey();
            if (!$apiKey) {
                $this->error('⚠ NO HAY API KEY de central configurado. Sin esto las consultas a central van a fallar.');
            } else {
                $this->line('<fg=cyan>API key central:</fg=cyan> ' . substr($apiKey, 0, 8) . '… (longitud ' . strlen($apiKey) . ')');
            }
        } catch (\Throwable $e) {
            $this->error('Error leyendo API key: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('');
        $this->info('─── Consultando central ───');
        $this->line('GET /bancos-central' . ($refresh ? '?refresh=1' : '') . ' (proxy local con cache de 2h)');

        // 4) Llamar al proxy local (igual que el frontend)
        try {
            $req = new \Illuminate\Http\Request();
            if ($refresh) $req->merge(['refresh' => '1']);
            $controller = new \App\Http\Controllers\BancosListController();
            $resp = $controller->getBancos($req);
            $payload = json_decode($resp->getContent(), true);
        } catch (\Throwable $e) {
            $this->error('Excepción al consultar: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!is_array($payload)) {
            $this->error('Respuesta no JSON. Body crudo: ' . substr($resp->getContent(), 0, 300));
            return self::FAILURE;
        }

        if (($payload['estado'] ?? null) === false) {
            $this->error('Central respondió con error: ' . ($payload['msj'] ?? '(sin mensaje)'));
            $this->line('Output completo:');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
            return self::FAILURE;
        }

        // 5) Metadata recibida
        $bancos = $payload['bancos'] ?? [];
        $sucId = $payload['sucursal_id'] ?? null;
        $empId = $payload['empresa_id'] ?? null;
        $bypassGlobal = $payload['bypass_pivote_caja_global'] ?? null;
        $bypassAplicado = $payload['bypass_pivote_caja_aplicado'] ?? null;
        $sucTieneAsig = $payload['sucursal_tiene_asignaciones'] ?? null;
        $cached = $payload['cached'] ?? null;

        $this->line('');
        $this->line('<fg=cyan>Sucursal en central:</fg=cyan> id=' . ($sucId ?? '<NULL>') . ' empresa_id=' . ($empId ?? '<NULL>'));
        $this->line('<fg=cyan>Bypass pivote (config):</fg=cyan> ' . ($bypassGlobal ? 'ON' : 'off') . ' · <fg=cyan>aplicado a esta caja:</fg=cyan> ' . ($bypassAplicado ? 'SÍ (caja sin asignaciones manuales)' : 'NO (modo estricto)'));
        $this->line('<fg=cyan>Caja tiene asignaciones en pivote:</fg=cyan> ' . ($sucTieneAsig ? 'SÍ' : 'NO'));
        $this->line('<fg=cyan>Respuesta servida desde cache local:</fg=cyan> ' . ($cached ? 'SÍ (use --refresh para bustear)' : 'NO (fresh fetch)'));

        if ($empId === null) {
            $this->error('⚠ La sucursal NO tiene empresa_id en central. Sin esto no hay bancos visibles. Asigna empresa a la sucursal desde central.');
            return self::FAILURE;
        }

        // 6) Resumen de bancos
        $this->line('');
        $this->info('─── Bancos asignados (' . count($bancos) . ') ───');

        if (empty($bancos)) {
            $this->warn('LISTA VACÍA. Causas posibles:');
            $this->line('  - La caja no tiene bancos asignados en `bancos_list_sucursal` y bypass está apagado.');
            $this->line('  - Todos los bancos de la empresa están inactivos (activo=false).');
            $this->line('  - La empresa de la caja no tiene bancos creados.');
            $this->line('Acción: panel Gastos → BANCOS → "Por sucursal" → revisa esta caja.');
            return self::SUCCESS;
        }

        $rows = [];
        $sumIngreso = 0;
        $sumDev = 0;
        $sumPosIngreso = 0;
        $sumPosDev = 0;
        $inactivos = 0;
        $sinNingunFlag = 0;

        foreach ($bancos as $b) {
            $tIng = !empty($b['disponible_transferencia_ingreso']);
            $tDev = !empty($b['disponible_transferencia_devolucion']);
            $pIng = !empty($b['disponible_pos_ingreso']);
            $pDev = !empty($b['disponible_pos_devolucion']);
            $activo = $b['activo'] ?? null;
            $activoBool = $activo === null ? true : (bool) $activo;

            $sumIngreso += $tIng ? 1 : 0;
            $sumDev += $tDev ? 1 : 0;
            $sumPosIngreso += $pIng ? 1 : 0;
            $sumPosDev += $pDev ? 1 : 0;
            if (!$activoBool) $inactivos++;
            if (!$tIng && !$tDev && !$pIng && !$pDev) $sinNingunFlag++;

            $warns = [];
            if (!$activoBool) $warns[] = 'INACTIVO';
            if (!$tIng && !$tDev && !$pIng && !$pDev) $warns[] = 'sin flags';

            $rows[] = [
                $b['id'] ?? '?',
                $b['codigo'] ?? '?',
                substr($b['descripcion'] ?? '', 0, 40),
                strtoupper($b['moneda'] ?? ''),
                $tIng ? '✓' : '',
                $tDev ? '✓' : '',
                $pIng ? '✓' : '',
                $pDev ? '✓' : '',
                implode(', ', $warns),
            ];
        }

        $this->table(
            ['id', 'codigo', 'descripcion', 'mon', 'T-Ing', 'T-Dev', 'P-Ing', 'P-Dev', 'warn'],
            $rows
        );

        // 7) Totales por escenario
        $this->line('');
        $this->info('─── Totales por escenario ───');
        $this->line('Transferencia Ingreso:    ' . $sumIngreso);
        $this->line('Transferencia Devolución: ' . $sumDev);
        $this->line('POS Ingreso:              ' . $sumPosIngreso);
        $this->line('POS Devolución:           ' . $sumPosDev);

        // 8) Warnings / inconsistencias
        $this->line('');
        $hayWarnings = false;
        if ($inactivos > 0) {
            $this->warn('⚠ ' . $inactivos . ' banco(s) marcados INACTIVOS en central — no deberían venir en la lista. Si los ves: error en endpoint o en el filtro `activo`.');
            $hayWarnings = true;
        }
        if ($sinNingunFlag > 0) {
            $this->warn('⚠ ' . $sinNingunFlag . ' banco(s) sin NINGÚN flag disponible. Asignados pero invisibles en cualquier select del cajero. Edítalos en Catálogo.');
            $hayWarnings = true;
        }
        if ($sumIngreso === 0 && $sumDev === 0) {
            $this->warn('⚠ NINGÚN banco disponible para transferencia. El cajero no podrá cargar refs de pago central.');
            $hayWarnings = true;
        }
        if ($sumPosIngreso === 0 && $sumPosDev === 0) {
            $this->warn('⚠ NINGÚN banco disponible para POS. El cajero no podrá asignar bancos a lotes en el cierre (los pinpad sí, vienen autodetectados).');
            $hayWarnings = true;
        }

        // 9) Verificación SHA registrado en central vs local
        if ($sucId) {
            $this->line('');
            $this->info('─── Versión registrada en central ───');
            try {
                // Una nueva llamada para forzar que central actualice version_sistema con el SHA actual
                $sc2 = new \App\Http\Controllers\sendCentral();
                $r = $sc2->requestToCentral('get', '/getBancosBySucursal', [], ['timeout' => 10]);
                if ($r->ok()) {
                    $this->line('Central recibió el header X-Sucursal-Version=' . $versionLocal);
                    $this->line('Verifica en central panel BANCOS → "Por sucursal" → esta caja debería mostrar v' . substr($versionLocal, 0, 7));
                } else {
                    $this->warn('Respuesta no ok al verificar versión: HTTP ' . $r->status());
                }
            } catch (\Throwable $e) {
                $this->warn('No se pudo confirmar registro de versión: ' . $e->getMessage());
            }
        }

        $this->line('');
        if ($hayWarnings) {
            $this->warn('Hay warnings arriba — revisa antes de seguir.');
        } else {
            $this->info('✓ Todo consistente. ' . count($bancos) . ' bancos asignados a esta caja, sin warnings.');
        }

        return self::SUCCESS;
    }
}
