<?php

namespace App\Console\Commands;

use App\Http\Controllers\PedidosController;
use App\Models\cierres;
use App\Models\CierresMetodosPago;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * COMANDO TEMPORAL — rehacer cierres viejos corruptos/faltantes (junio 2026).
 *
 * Contexto del incidente:
 *   - 06-06: el cierre de caja1 quedó guardado por error como ADMINISTRADOR
 *            (tipo_cierre=1 bajo id_usuario=4, creado el 08-06). Bloquea reguardar
 *            caja1 ("ya existe cierre de administrador") y no aparece en histórico cajero.
 *   - 07-06: falta el cierre de caja1 y el administrador del día.
 *   - 08-06: faltan TODAS las cajas y el administrador.
 *   - 09-06: los registros existentes (caja3 13703 y admin 13708) contienen en
 *            realidad data del día 08 y están vacíos (numventas=0). Faltan todas
 *            las cajas reales + administrador.
 *
 * Qué hace, por cada día:
 *   1) Borra cualquier cierre tipo_cierre=1 (administrador) del día — desbloquea
 *      el reguardado de cajeros y elimina el admin corrupto.
 *   2) Crea/regenera los cierres de cajero FALTANTES con el efectivo real del Excel
 *      (el débito/pinpad se auto-reconstruye desde los pagos POS de los pedidos:
 *       real = digital, no genera faltante).
 *   3) Crea el cierre de ADMINISTRADOR (totalizado) del día.
 *
 * NO transmite. Los registros quedan con push=0 listos para retransmitir a mano
 * (sendalltest). Central es idempotente por (id_sucursal, fecha): al recibir el
 * admin sobrescribe el cierre, regenera los lotes pyb no liquidados y el movimiento
 * "INGRESO DESDE CIERRE", así que no duplica aunque el corrupto ya se haya enviado.
 *
 * Reparto del fondo dejado (dejar = real - entregado):
 *   - 06 y 07: caja1 tiene dejar derivado EXACTO (admin/ walida menos las otras cajas).
 *   - 08 y 09: solo se conoce el entregado TOTAL (fila "walida"); el fondo total
 *              (Σreal - walida) se reparte PROPORCIONAL al efectivo real de cada caja.
 *              El administrador queda exacto en todo caso (es la suma).
 *   - 09: débito = digital (no se hizo cierre bancaribe) — automático porque el
 *         pinpad se reconcilia real=digital desde los pedidos.
 *
 * Uso:
 *   php artisan cierres:rehacer-junio                 # DRY-RUN: calcula y muestra cuadre, no escribe
 *   php artisan cierres:rehacer-junio --commit        # Persiste los cierres
 *   php artisan cierres:rehacer-junio --commit --dias=8,9
 */
class RehacerCierresJunioCommand extends Command
{
    protected $signature = 'cierres:rehacer-junio
        {--commit : Persiste los cambios. Sin esto es DRY-RUN (no escribe nada).}
        {--dias= : Lista de días a procesar separados por coma (ej. 6,7). Default: 6,7,8,9}
        {--otros-digital : Para cajeros faltantes, inyecta el débito "otros puntos" (POS Provincial no pinpad) = digital, banco bid:3. Necesario en 08/09 donde no se hizo el cierre bancaribe.}
        {--inicial-cero : Cierra sin arrastrar saldo inicial (caja_inicial=0). Necesario en 09 donde el dejar proporcional del 08 no es un saldo inicial real.}';

    /** Banco de los "otros puntos" (POS no-pinpad). bid:3 = Banco Provincial. */
    private const BANCO_OTROS = 'bid:3';

    protected $description = 'TEMPORAL: rehace cierres cajero+administrador faltantes/corruptos de junio 2026 (no transmite).';

    /** id_usuario por caja en esta sucursal. */
    private const CAJA1 = 4;
    private const CAJA2 = 19;
    private const CAJA3 = 20;
    private const CAJA4 = 24;
    private const ADMIN = 37; // Walid Aldebs (tipo_usuario=1)

    /**
     * Datos por día. Para cada caja: efectivo real del Excel (bs/usd) y si falta.
     * 'dejar' explícito solo cuando es derivable exacto (06/07 caja1); si no, null
     * y se reparte proporcional. 'walida' = entregado TOTAL del día (fila Excel).
     */
    private function datos(): array
    {
        return [
            '2026-06-06' => [
                'walida' => ['bs' => 608180, 'usd' => 1600],
                'cajas' => [
                    self::CAJA1 => ['bs' => 86470,  'usd' => 660, 'falta' => true,  'dejar' => ['bs' => 13470, 'usd' => 160]],
                    self::CAJA2 => ['bs' => 107330, 'usd' => 594, 'falta' => false],
                    self::CAJA3 => ['bs' => 177660, 'usd' => 552, 'falta' => false],
                    self::CAJA4 => ['bs' => 282280, 'usd' => 430, 'falta' => false],
                ],
            ],
            '2026-06-07' => [
                'walida' => ['bs' => 166000, 'usd' => 600],
                'cajas' => [
                    self::CAJA1 => ['bs' => 62740,  'usd' => 209, 'falta' => true,  'dejar' => ['bs' => 4740, 'usd' => 109]],
                    self::CAJA2 => ['bs' => 21340,  'usd' => 285, 'falta' => false],
                    self::CAJA3 => ['bs' => 26000,  'usd' => 220, 'falta' => false],
                    self::CAJA4 => ['bs' => 100570, 'usd' => 196, 'falta' => false],
                ],
            ],
            '2026-06-08' => [
                'walida' => ['bs' => 555732, 'usd' => 1600],
                'cajas' => [
                    self::CAJA1 => ['bs' => 236806, 'usd' => 234, 'falta' => true],
                    self::CAJA2 => ['bs' => 86030,  'usd' => 351, 'falta' => true],
                    self::CAJA3 => ['bs' => 115626, 'usd' => 750, 'falta' => true],
                    self::CAJA4 => ['bs' => 179800, 'usd' => 780, 'falta' => true],
                ],
            ],
            '2026-06-09' => [
                'walida' => ['bs' => 433100, 'usd' => 1400],
                'cajas' => [
                    self::CAJA1 => ['bs' => 227780, 'usd' => 407,  'falta' => true],
                    self::CAJA2 => ['bs' => 76560,  'usd' => 322,  'falta' => true],
                    self::CAJA3 => ['bs' => 20980,  'usd' => 150,  'falta' => true],
                    self::CAJA4 => ['bs' => 155260, 'usd' => 1063, 'falta' => true],
                ],
            ],
        ];
    }

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $otrosDigital = (bool) $this->option('otros-digital');
        $inicialCero = (bool) $this->option('inicial-cero');
        $ctrl = new PedidosController();
        $moneda = $ctrl->get_moneda();
        $tasa = $moneda['bs'] > 0 ? (float) $moneda['bs'] : 1.0;
        $tasacop = $moneda['cop'] > 0 ? (float) $moneda['cop'] : 1.0;

        $diasOpt = $this->option('dias');
        $diasFiltro = null;
        if ($diasOpt) {
            $diasFiltro = array_map('intval', array_filter(explode(',', $diasOpt), 'strlen'));
        }

        $this->newLine();
        $this->info('==================================================================');
        $this->info('  REHACER CIERRES JUNIO  ·  modo: ' . ($commit ? 'COMMIT (escribe)' : 'DRY-RUN (no escribe)'));
        $this->info('  tasa bs=' . $tasa . '  ·  tasa cop=' . $tasacop);
        $this->info('==================================================================');

        $nombres = [self::CAJA1 => 'caja1', self::CAJA2 => 'caja2', self::CAJA3 => 'caja3', self::CAJA4 => 'caja4', self::ADMIN => 'ADMIN'];

        foreach ($this->datos() as $fecha => $cfg) {
            $diaNum = (int) substr($fecha, 8, 2);
            if ($diasFiltro !== null && !in_array($diaNum, $diasFiltro, true)) {
                continue;
            }

            $this->newLine();
            $this->line("<fg=cyan>──────── DÍA {$fecha} ────────</>");

            // ---- Reparto del fondo dejado por caja ----
            $dejarPorCaja = $this->calcularDejar($cfg);

            // Σ real y Σ dejar del día (para el administrador)
            $sumRealBs = 0; $sumRealUsd = 0; $sumDejarBs = 0; $sumDejarUsd = 0;
            foreach ($cfg['cajas'] as $uid => $caja) {
                $sumRealBs  += $caja['bs'];
                $sumRealUsd += $caja['usd'];
                $sumDejarBs  += $dejarPorCaja[$uid]['bs'];
                $sumDejarUsd += $dejarPorCaja[$uid]['usd'];
            }

            if ($commit) {
                DB::beginTransaction();
            }

            try {
                // 1) Borrar administrador(es) corruptos del día (desbloquea cajeros)
                $adminsPrevios = cierres::where('fecha', $fecha)->where('tipo_cierre', 1)->get();
                foreach ($adminsPrevios as $adm) {
                    $this->line("  · borrar admin previo id={$adm->id} (uid={$adm->id_usuario})" . ($commit ? '' : '  [dry]'));
                    if ($commit) {
                        CierresMetodosPago::where('id_cierre', $adm->id)->delete();
                        Cache::forget("cierre_guardado_usuario_{$adm->id_usuario}_fecha_{$fecha}");
                        $adm->delete();
                    }
                }

                // 2) Cajeros faltantes
                foreach ($cfg['cajas'] as $uid => $caja) {
                    if (empty($caja['falta'])) {
                        $this->line("  · {$nombres[$uid]} (uid={$uid}) ya existe — se respeta");
                        continue;
                    }
                    $dejar = $dejarPorCaja[$uid];
                    $this->procesarUno(
                        $ctrl, $fecha, $uid, false,
                        $caja['bs'], $caja['usd'], $dejar['bs'], $dejar['usd'],
                        $commit, $tasa, $tasacop, $nombres[$uid], $otrosDigital, $inicialCero
                    );
                }

                // 3) Administrador (totalizado)
                $admDejarBs = $sumRealBs - $cfg['walida']['bs'];
                $admDejarUsd = $sumRealUsd - $cfg['walida']['usd'];
                $this->procesarUno(
                    $ctrl, $fecha, self::ADMIN, true,
                    $sumRealBs, $sumRealUsd, $admDejarBs, $admDejarUsd,
                    $commit, $tasa, $tasacop, 'ADMIN', false, $inicialCero
                );

                if ($commit) {
                    Cache::forget('lastcierres');
                    DB::commit();
                    $this->info("  ✔ DÍA {$fecha} guardado (cajeros + administrador).");
                }
            } catch (\Throwable $e) {
                if ($commit) {
                    DB::rollBack();
                }
                $this->error("  ✘ DÍA {$fecha} abortado: " . $e->getMessage());
            }
        }

        $this->newLine();
        if ($commit) {
            $this->info('LISTO. Los cierres de administrador quedaron con push=0.');
            $this->info('Retransmite a mano cuando quieras (sendalltest).');
        } else {
            $this->warn('DRY-RUN: no se escribió nada. Repite con --commit para persistir.');
        }
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Calcula el dejar por caja: explícito si viene en los datos, si no proporcional
     * al efectivo real (fondo total = Σreal - walida), ajustando el residuo en la
     * última caja para cuadrar exacto contra walida.
     */
    private function calcularDejar(array $cfg): array
    {
        $out = [];
        $faltanProporcional = [];
        $sumRealBs = 0; $sumRealUsd = 0;
        foreach ($cfg['cajas'] as $uid => $caja) {
            $sumRealBs  += $caja['bs'];
            $sumRealUsd += $caja['usd'];
            if (isset($caja['dejar'])) {
                $out[$uid] = ['bs' => (float) $caja['dejar']['bs'], 'usd' => (float) $caja['dejar']['usd']];
            } else {
                $faltanProporcional[$uid] = $caja;
            }
        }

        if (!empty($faltanProporcional)) {
            $totalDejarBs = $sumRealBs - $cfg['walida']['bs'];
            $totalDejarUsd = $sumRealUsd - $cfg['walida']['usd'];

            // base real solo de las cajas a repartir proporcionalmente
            $baseBs = 0; $baseUsd = 0;
            foreach ($faltanProporcional as $caja) { $baseBs += $caja['bs']; $baseUsd += $caja['usd']; }

            $accBs = 0; $accUsd = 0;
            $keys = array_keys($faltanProporcional);
            $last = end($keys);
            foreach ($faltanProporcional as $uid => $caja) {
                if ($uid === $last) {
                    $out[$uid] = ['bs' => round($totalDejarBs - $accBs, 2), 'usd' => round($totalDejarUsd - $accUsd, 2)];
                } else {
                    $dBs = $baseBs > 0 ? round($totalDejarBs * $caja['bs'] / $baseBs, 2) : 0;
                    $dUsd = $baseUsd > 0 ? round($totalDejarUsd * $caja['usd'] / $baseUsd, 2) : 0;
                    $out[$uid] = ['bs' => $dBs, 'usd' => $dUsd];
                    $accBs += $dBs; $accUsd += $dUsd;
                }
            }
        }

        return $out;
    }

    /**
     * Procesa un cierre (cajero o admin). En dry-run llama cerrarFun (solo lee) y
     * reporta el cuadre; en commit llama guardarCierre (persiste) y verifica estado.
     */
    private function procesarUno(
        PedidosController $ctrl, string $fecha, int $uid, bool $esAdmin,
        float $realBs, float $realUsd, float $dejarBs, float $dejarUsd,
        bool $commit, float $tasa, float $tasacop, string $etiqueta, bool $otrosDigital,
        bool $inicialCero = false
    ): void {
        // Inyección de débito "otros puntos" = digital (POS Provincial no conciliado).
        // Solo para cajeros. El admin lo consolida solo desde los cajeros.
        $puntosOtros = [];
        if ($otrosDigital && !$esAdmin) {
            $otrosDig = $this->otrosDigitalBs($ctrl, $fecha, $uid);
            if ($otrosDig > 0.005) {
                $puntosOtros[] = [
                    'banco' => self::BANCO_OTROS,
                    'monto' => round($otrosDig, 2),
                    'descripcion' => 'PROV',
                    'categoria' => 'DEBITO',
                    'id_usuario' => $uid,
                ];
            }
        }

        if (!$commit) {
            // DRY: calcular sin escribir. usuario explícito evita depender de sesión.
            $opciones = [
                'grafica' => false,
                'totalizarcierre' => $esAdmin,
                'check_pendiente' => true,
                'pendiente_filtrar_fecha' => true,
                'caja_inicial_cero' => $inicialCero,
                'usuario' => $esAdmin ? null : [$uid],
            ];
            $res = $ctrl->cerrarFun($fecha, [
                'caja_usd' => $realUsd, 'caja_bs' => $realBs, 'caja_cop' => 0,
                'total_biopago' => 0,
                'dejar_usd' => $dejarUsd, 'dejar_bs' => $dejarBs, 'dejar_cop' => 0,
                'puntos_adicionales' => ['puntos' => $puntosOtros, 'lotes_pinpad' => []],
            ], $opciones);

            $data = $res instanceof JsonResponse ? $res->getData(true) : (is_array($res) ? $res : []);

            if (isset($data['estado']) && $data['estado'] === false) {
                $this->error("    {$etiqueta}: cerrarFun rechazó → " . ($data['msj'] ?? 'sin detalle'));
                return;
            }

            $cd = $data['cuadre_detallado'] ?? [];
            $dif = $this->totalDiferenciaUsd($cd, $tasa, $tasacop);
            $numv = $data['numventas'] ?? '?';
            $flag = $dif < -5 ? '<fg=red>FALTANTE</>' : ($dif > 5 ? '<fg=yellow>sobra</>' : '<fg=green>cuadra</>');
            $inj = !empty($puntosOtros) ? ' | +otrosBs=' . number_format($puntosOtros[0]['monto'], 2) . ' (' . self::BANCO_OTROS . ')' : '';
            $this->line(sprintf(
                "    %-6s realBs=%s realUsd=%s dejarBs=%s dejarUsd=%s | ventas=%s | dif=%s USD %s%s",
                $etiqueta,
                number_format($realBs, 2), number_format($realUsd, 2),
                number_format($dejarBs, 2), number_format($dejarUsd, 2),
                $numv, number_format($dif, 2), $flag, $inj
            ));
            if ($dif < -5) {
                $this->warn("      ⚠ Este cierre se BLOQUEARÍA al guardar (faltante > 5 USD). Revisar efectivo real.");
            }
            return;
        }

        // COMMIT: usar guardarCierre (persistencia real). Requiere sesión.
        session()->put('id_usuario', $uid);
        session()->put('tipo_usuario', $esAdmin ? 1 : 4);

        $req = new Request();
        $req->merge([
            'fecha' => $fecha,
            'caja_usd' => $realUsd, 'caja_bs' => $realBs, 'caja_cop' => 0,
            'dejar_usd' => $dejarUsd, 'dejar_bs' => $dejarBs, 'dejar_cop' => 0,
            'total_biopago' => 0,
            'totalizarcierre' => $esAdmin ? 'true' : 'false',
            'cierre_por_fecha' => '1', // guard línea 3073: exige === true || === '1'
            'caja_inicial_cero' => $inicialCero ? '1' : '0',
            // Array PLANO: guardarCierre lo envuelve como ["puntos"=>$dataPuntosAdicionales]
            // (línea 2874). Vacío en el admin => rama de consolidación desde BD (línea 2147).
            'dataPuntosAdicionales' => $puntosOtros,
            'lotesPinpad' => [],
        ]);

        $resp = $ctrl->guardarCierre($req);
        $data = $resp instanceof JsonResponse ? $resp->getData(true) : (is_array($resp) ? $resp : ['raw' => $resp]);

        if (isset($data['estado']) && $data['estado'] === false) {
            // Lanzar para que el día completo haga rollback.
            throw new \RuntimeException("{$etiqueta} rechazado: " . ($data['msj'] ?? json_encode($data)));
        }

        $nuevo = cierres::where('fecha', $fecha)
            ->where('id_usuario', $uid)
            ->where('tipo_cierre', $esAdmin ? 1 : 0)
            ->latest('id')->first();
        $this->line("    <fg=green>✔</> {$etiqueta} guardado id=" . ($nuevo->id ?? '?')
            . " | ventas=" . ($nuevo->numventas ?? '?')
            . " | efBs=" . number_format($nuevo->efectivo_actual_bs ?? 0, 2)
            . " | descuadre=" . number_format($nuevo->descuadre ?? 0, 2));
    }

    /**
     * Débito "otros puntos" digital (Bs) de un cajero: suma de pagos tipo 2 sin
     * pos_terminal (POS no-pinpad, p.ej. Provincial). Se obtiene corriendo cerrarFun
     * en seco para esa caja y leyendo cuadre_detallado.debito_otros.digital.
     */
    private function otrosDigitalBs(PedidosController $ctrl, string $fecha, int $uid): float
    {
        $res = $ctrl->cerrarFun($fecha, [
            'caja_usd' => 0, 'caja_bs' => 0, 'caja_cop' => 0, 'total_biopago' => 0,
            'dejar_usd' => 0, 'dejar_bs' => 0, 'dejar_cop' => 0,
            'puntos_adicionales' => ['puntos' => [], 'lotes_pinpad' => []],
        ], [
            'grafica' => false, 'totalizarcierre' => false,
            'check_pendiente' => false, 'usuario' => [$uid],
        ]);
        $data = $res instanceof JsonResponse ? $res->getData(true) : (is_array($res) ? $res : []);
        return (float) ($data['cuadre_detallado']['debito_otros']['digital'] ?? 0);
    }

    /**
     * Replica el cálculo de faltante de guardarCierre (total_real - total_digital
     * - total_inicial, todo en USD) a partir del cuadre_detallado, para previsualizar
     * en dry-run si el guardado se bloquearía.
     */
    private function totalDiferenciaUsd(array $cd, float $tasa, float $tasacop): float
    {
        $g = function ($arr, $k) { return isset($arr[$k]) ? (float) $arr[$k] : 0.0; };

        $eUsd = $cd['efectivo_usd'] ?? []; $eBs = $cd['efectivo_bs'] ?? []; $eCop = $cd['efectivo_cop'] ?? [];

        $efReal = $g($eUsd, 'real') + $g($eBs, 'real') / $tasa + $g($eCop, 'real') / $tasacop;
        $efIni  = $g($eUsd, 'inicial') + $g($eBs, 'inicial') / $tasa + $g($eCop, 'inicial') / $tasacop;
        $efDig  = $g($eUsd, 'digital') + $g($eBs, 'digital') / $tasa + $g($eCop, 'digital') / $tasacop;

        $dRealBs = $g($cd['debito_pinpad'] ?? [], 'real') + $g($cd['debito_otros'] ?? [], 'real');
        $dDigBs  = $g($cd['debito_pinpad'] ?? [], 'digital') + $g($cd['debito_otros'] ?? [], 'digital');
        $dReal = $dRealBs / $tasa; $dDig = $dDigBs / $tasa;

        $tReal = $g($cd['transferencia'] ?? [], 'real'); $tDig = $g($cd['transferencia'] ?? [], 'digital');
        $bReal = $g($cd['biopago'] ?? [], 'real');       $bDig = $g($cd['biopago'] ?? [], 'digital');

        $totalReal = $efReal + $dReal + $tReal + $bReal;
        $totalDig  = $efDig + $dDig + $tDig + $bDig;
        $totalIni  = $efIni;

        return $totalReal - $totalDig - $totalIni;
    }
}
