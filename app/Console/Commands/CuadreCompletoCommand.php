<?php

namespace App\Console\Commands;

use App\Services\Cuadre\CuadreCsvReader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orquestador de punta a punta del cuadre por monto objetivo de una sucursal.
 *
 * Lee una carpeta con: el ZIP del respaldo de la BD (mysqldump .sql) y los ZIP mensuales de montos objetivo
 * (CSV/XLSX con FECHA, CONCEPTO, FACTURA, VENTA, TIPO). Pasos, en orden:
 *
 *   1. preparar   Descomprime, clasifica archivos y fusiona los objetivos en un CSV canónico. Estima tiempos.
 *   2. restaurar  BORRA todas las tablas de la BD local (.env) e importa el respaldo (mysql CLI o PHP).
 *   3. migrar     php artisan migrate --force (columnas del cuadre, tabla cuadre_ajustes, uuid, etc.).
 *   4. titanio    Importa desde Titanio POS los pedidos entre --titanio-desde y --titanio-hasta.
 *   5. respaldo   mysqldump de la BD ya completa (punto de retorno antes de cuadrar).
 *   6. simular    (opcional) corre la selección sin escribir y reporta ajustes por grupo.
 *   7. cuadre     cuadre:pedidos-diario con el CSV fusionado (auditado y reanudable).
 *   8. medir      Resultado por mes: objetivo vs logrado, facturas, ajustes, numeración.
 *
 * El estado se guarda en <trabajo>/estado.json: si el proceso se interrumpe (apagón, cierre de sesión), volver a
 * ejecutar el MISMO comando continúa donde quedó. Cada paso es idempotente.
 */
class CuadreCompletoCommand extends Command
{
    protected $signature = 'cuadre:completo
                            {carpeta : Carpeta con los ZIP (respaldo de BD y montos objetivo)}
                            {--sucursal=anaco : Código esperado de la sucursal en la BD restaurada}
                            {--store-id= : storeId de la sucursal en Titanio POS (o env TITANIO_STORE_ID)}
                            {--titanio-desde=2026-08-30 : Primer día a importar desde Titanio POS}
                            {--titanio-hasta= : Último día a importar desde Titanio POS (default hoy)}
                            {--sin-titanio : Omitir la importación desde Titanio POS}
                            {--sin-respaldo : No hacer mysqldump antes del cuadre}
                            {--simular-antes : Correr una simulación (sin escribir) antes del cuadre real}
                            {--solo-simular : Correr solo la simulación del cuadre y detenerse}
                            {--desde-cero : Pasar --desde-cero al cuadre (resetea cuadres previos en el rango del CSV)}
                            {--max-segundos=15 : Tiempo máximo de búsqueda por grupo día+máquina}
                            {--tolerancia-bs=1 : Detener la búsqueda al llegar a ±N Bs del objetivo (el resto lo cubre el ajuste)}
                            {--umbral-ajuste=5 : % de ajuste a partir del cual se avisa}
                            {--trabajo= : Carpeta de trabajo (default storage/app/cuadre-completo/<sucursal>)}
                            {--mysql-bin= : Carpeta con mysql/mysqldump (default: detectar; en Windows C:\\xampp\\mysql\\bin)}
                            {--paso= : Ejecutar solo este paso (preparar|restaurar|migrar|titanio|respaldo|simular|cuadre|medir)}
                            {--desde-paso= : Reanudar desde este paso aunque el estado lo marque como hecho}
                            {--reiniciar : Ignorar el estado guardado y empezar desde el primer paso}
                            {--permitir-remoto : Permitir restaurar sobre una BD cuyo host no es local}
                            {--si : No pedir confirmaciones (corridas desatendidas)}
                            {--dry-run : Solo preparar archivos y (si hay BD) simular el cuadre; no escribe en la BD}';

    protected $description = 'Proceso completo: restaurar respaldo de la sucursal, importar Titanio POS, cuadrar contra montos objetivo y medir.';

    protected const PASOS = ['preparar', 'restaurar', 'migrar', 'titanio', 'respaldo', 'simular', 'cuadre', 'medir'];

    protected string $carpeta = '';
    protected string $trabajo = '';
    protected string $sucursal = '';
    protected array $estado = [];
    protected bool $dryRun = false;
    protected float $inicioGlobal = 0.0;

    /** @var resource|null */
    protected $logFh = null;

    public function handle(CuadreCsvReader $reader): int
    {
        $this->inicioGlobal = microtime(true);
        $this->dryRun = (bool) $this->option('dry-run');
        $this->sucursal = strtolower(trim((string) $this->option('sucursal')));
        $this->carpeta = rtrim((string) $this->argument('carpeta'), "\\/");

        if (!is_dir($this->carpeta)) {
            $this->error("La carpeta no existe: {$this->carpeta}");
            return Command::FAILURE;
        }

        $this->trabajo = $this->option('trabajo')
            ? rtrim((string) $this->option('trabajo'), "\\/")
            : storage_path('app/cuadre-completo/' . ($this->sucursal ?: 'sucursal'));
        if (!is_dir($this->trabajo) && !@mkdir($this->trabajo, 0777, true)) {
            $this->error("No se pudo crear la carpeta de trabajo: {$this->trabajo}");
            return Command::FAILURE;
        }
        $this->logFh = @fopen($this->trabajo . DIRECTORY_SEPARATOR . 'cuadre_completo.log', 'a');

        $this->cargarEstado();
        if ($this->option('reiniciar')) {
            $this->estado = ['pasos' => [], 'datos' => []];
            $this->guardarEstado();
            $this->log('Estado reiniciado por --reiniciar.');
        }

        $soloPaso = $this->option('paso') ? strtolower(trim((string) $this->option('paso'))) : null;
        $desdePaso = $this->option('desde-paso') ? strtolower(trim((string) $this->option('desde-paso'))) : null;
        foreach ([$soloPaso, $desdePaso] as $p) {
            if ($p !== null && !in_array($p, self::PASOS, true)) {
                $this->error("Paso desconocido: {$p}. Válidos: " . implode(', ', self::PASOS));
                return Command::FAILURE;
            }
        }

        $this->cabecera();

        $forzarDesde = $desdePaso !== null;
        $forzando = false;
        foreach (self::PASOS as $paso) {
            if ($soloPaso !== null && $paso !== $soloPaso) {
                continue;
            }
            if ($forzarDesde && $paso === $desdePaso) {
                $forzando = true;
            }

            $omitir = $this->motivoOmision($paso);
            if ($omitir !== null) {
                $this->log("── Paso {$paso}: omitido ({$omitir})");
                continue;
            }

            if (!$forzando && $soloPaso === null && $this->pasoHecho($paso)) {
                $this->log("── Paso {$paso}: ya hecho el " . ($this->estado['pasos'][$paso]['fin'] ?? '?') . ' (use --desde-paso=' . $paso . ' para repetirlo)');
                continue;
            }

            $this->log("══ Paso {$paso} ═══════════════════════════════════════════");
            $inicio = microtime(true);
            try {
                $ok = $this->{'paso' . ucfirst($paso)}($reader);
            } catch (\Throwable $e) {
                $this->log('ERROR en paso ' . $paso . ': ' . $e->getMessage(), 'error');
                \Log::error('cuadre:completo fallo', ['paso' => $paso, 'mensaje' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $ok = false;
            }
            $dur = microtime(true) - $inicio;
            if (!$ok) {
                $this->log("Paso {$paso} FALLÓ tras " . $this->formatearDuracion($dur) . '. Corrija y vuelva a ejecutar el mismo comando: continuará desde este paso.', 'error');
                $this->cerrar();
                return Command::FAILURE;
            }
            if (!$this->dryRun) {
                $this->marcarHecho($paso, $dur);
            }
            $this->log("Paso {$paso} completado en " . $this->formatearDuracion($dur) . '.');
        }

        $this->log('Proceso terminado. Duración total de esta corrida: ' . $this->formatearDuracion(microtime(true) - $this->inicioGlobal));
        $this->log('Carpeta de trabajo: ' . $this->trabajo);
        $this->cerrar();
        return Command::SUCCESS;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Paso 1: preparar
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function pasoPreparar(CuadreCsvReader $reader): bool
    {
        $extraidos = $this->trabajo . DIRECTORY_SEPARATOR . 'extraidos';
        if (!is_dir($extraidos)) {
            mkdir($extraidos, 0777, true);
        }

        $entradas = $this->listarArchivos($this->carpeta, 1);
        $zips = array_values(array_filter($entradas, fn ($f) => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'zip'));
        $sueltos = array_values(array_filter($entradas, fn ($f) => strtolower(pathinfo($f, PATHINFO_EXTENSION)) !== 'zip'));

        $this->log('Carpeta de entrada: ' . $this->carpeta);
        $this->log('ZIP encontrados: ' . count($zips) . ' | archivos sueltos: ' . count($sueltos));
        if (empty($zips) && empty($sueltos)) {
            $this->log('La carpeta está vacía.', 'error');
            return false;
        }

        $candidatos = $sueltos;
        foreach ($zips as $zip) {
            $destino = $extraidos . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_.-]+/', '_', pathinfo($zip, PATHINFO_FILENAME));
            $yaExtraido = is_dir($destino) && count($this->listarArchivos($destino, 3)) > 0;
            if ($yaExtraido) {
                $this->log('  ' . basename($zip) . ' → ya extraído en ' . $destino);
            } else {
                $this->log('  Extrayendo ' . basename($zip) . ' (' . $this->formatearBytes(filesize($zip)) . ')…');
                if (!$this->extraerZip($zip, $destino)) {
                    return false;
                }
            }
            foreach ($this->listarArchivos($destino, 3) as $f) {
                $candidatos[] = $f;
            }
        }

        // ZIP anidados (un nivel).
        foreach ($candidatos as $f) {
            if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'zip') {
                $destino = dirname($f) . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_.-]+/', '_', pathinfo($f, PATHINFO_FILENAME)) . '_zip';
                if (!is_dir($destino) || count($this->listarArchivos($destino, 3)) === 0) {
                    $this->log('  Extrayendo ZIP anidado ' . basename($f) . '…');
                    if (!$this->extraerZip($f, $destino)) {
                        return false;
                    }
                }
                foreach ($this->listarArchivos($destino, 3) as $g) {
                    $candidatos[] = $g;
                }
            }
        }

        $dumps = [];
        $objetivos = [];
        $otros = [];
        foreach (array_unique($candidatos) as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $base = basename($f);
            if (str_starts_with($base, '.') || str_contains($f, '__MACOSX')) {
                continue;
            }
            if ($ext === 'sql') {
                $dumps[] = $f;
            } elseif (in_array($ext, ['csv', 'tsv', 'xlsx', 'xls', 'xlsm'], true)) {
                $objetivos[] = $f;
            } elseif ($ext !== 'zip') {
                $otros[] = $f;
            }
        }
        if (!empty($otros)) {
            $this->log('Archivos ignorados (' . count($otros) . '): ' . implode(', ', array_map('basename', array_slice($otros, 0, 10))) . (count($otros) > 10 ? ', …' : ''));
        }

        // ── Respaldo de BD ──
        if (empty($dumps)) {
            $this->log('No se encontró ningún .sql (respaldo de la BD) en la carpeta ni dentro de los ZIP.', 'error');
            return false;
        }
        $completos = [];
        foreach ($dumps as $d) {
            $info = $this->inspeccionarDump($d);
            $this->log(sprintf('  SQL: %s (%s) tablas=%d pedidos=%s USE/CREATE DATABASE=%s', basename($d), $this->formatearBytes(filesize($d)), $info['tablas'], $info['tiene_pedidos'] ? 'sí' : 'no', $info['cambia_bd'] ? 'sí (se ignorará)' : 'no'));
            if ($info['tiene_pedidos']) {
                $completos[] = $d;
            }
        }
        if (!empty($completos)) {
            usort($completos, fn ($a, $b) => filesize($b) <=> filesize($a));
            $sqlAImportar = [$completos[0]];
            if (count($completos) > 1) {
                $this->log('Hay ' . count($completos) . ' respaldos completos; se usará el más grande: ' . basename($completos[0]), 'warn');
            }
        } else {
            sort($dumps);
            $sqlAImportar = $dumps;
            $this->log('Ningún .sql contiene CREATE TABLE pedidos; se importarán todos en orden de nombre (' . count($dumps) . ').', 'warn');
        }

        // ── Objetivos ──
        if (empty($objetivos)) {
            $this->log('No se encontró ningún CSV/XLSX de montos objetivo.', 'error');
            return false;
        }
        sort($objetivos);
        $csvObjetivo = $this->trabajo . DIRECTORY_SEPARATOR . 'objetivo_' . ($this->sucursal ?: 'sucursal') . '.csv';
        $stats = $reader->fusionarEnCsv($objetivos, $csvObjetivo);
        foreach ($stats['archivos'] as $nombre => $n) {
            $this->log(sprintf('  Objetivo: %-45s filas válidas: %d', $nombre, $n), $n === 0 ? 'warn' : 'info');
        }
        if (!empty($stats['rechazadas'])) {
            $this->log('Filas rechazadas: ' . count($stats['rechazadas']) . ' (primeras 8):', 'warn');
            foreach (array_slice($stats['rechazadas'], 0, 8) as $r) {
                $this->log('    - ' . $r, 'warn');
            }
        }
        if (($stats['duplicadas_omitidas'] ?? 0) > 0) {
            $this->log('Filas duplicadas entre archivos omitidas: ' . $stats['duplicadas_omitidas'], 'warn');
        }
        if ($stats['filas'] === 0) {
            $this->log('El CSV fusionado quedó vacío: revise la cabecera de los archivos (FECHA, CONCEPTO, FACTURA, VENTA, TIPO).', 'error');
            return false;
        }
        $this->log("CSV fusionado: {$csvObjetivo} ({$stats['filas']} filas, {$stats['grupos']} grupos día+máquina, {$stats['fecha_min']} → {$stats['fecha_max']})");
        $filas = [];
        foreach ($stats['por_mes'] as $mes => $d) {
            $filas[] = [$mes, $d['dias'], $d['facturas'], number_format((float) $d['venta'], 2, ',', '.'), implode(' ', $d['maquinas'])];
        }
        $this->table(['Mes', 'Días', 'Facturas objetivo', 'Venta objetivo (Bs)', 'Máquinas'], $filas);

        // ── Estimación ──
        $maxSeg = max(1.0, (float) $this->option('max-segundos'));
        $bytesDump = array_sum(array_map('filesize', $sqlAImportar));
        $diasTitanio = $this->option('sin-titanio') ? 0 : count($this->fechasTitanio());
        $estRestaurar = max(60, $bytesDump / (25 * 1024 * 1024)); // ~25 MB/s con mysql CLI en disco local
        $estTitanio = $diasTitanio * 20;                            // ~20 s por día (API + inserción)
        $estCuadreMax = $stats['grupos'] * $maxSeg;
        $estCuadreTip = $stats['grupos'] * min($maxSeg, 3.0); // con tolerancia en Bs la mayoría de los grupos cierra en 1–3 s
        $this->log('Estimación de tiempo:');
        $this->log(sprintf('  restaurar BD (%s): ~%s', $this->formatearBytes($bytesDump), $this->formatearDuracion($estRestaurar)));
        $this->log(sprintf('  importar Titanio (%d días): ~%s', $diasTitanio, $this->formatearDuracion($estTitanio)));
        $this->log(sprintf('  cuadre (%d grupos × %.0f s): típico ~%s, máximo %s', $stats['grupos'], $maxSeg, $this->formatearDuracion($estCuadreTip), $this->formatearDuracion($estCuadreMax)));
        $this->log(sprintf('  TOTAL estimado: entre %s y %s', $this->formatearDuracion($estRestaurar + $estTitanio + $estCuadreTip), $this->formatearDuracion($estRestaurar * 2 + $estTitanio * 2 + $estCuadreMax)));

        $this->estado['datos']['sql'] = $sqlAImportar;
        $this->estado['datos']['objetivos'] = $objetivos;
        $this->estado['datos']['csv_objetivo'] = $csvObjetivo;
        $this->estado['datos']['csv_stats'] = ['filas' => $stats['filas'], 'grupos' => $stats['grupos'], 'fecha_min' => $stats['fecha_min'], 'fecha_max' => $stats['fecha_max'], 'por_mes' => $stats['por_mes']];
        $this->guardarEstado();
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Paso 2: restaurar (DESTRUCTIVO sobre la BD local)
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function pasoRestaurar(CuadreCsvReader $reader): bool
    {
        $sqls = $this->estado['datos']['sql'] ?? [];
        if (empty($sqls)) {
            $this->log('No hay respaldo .sql registrado; ejecute primero el paso preparar.', 'error');
            return false;
        }
        foreach ($sqls as $s) {
            if (!is_file($s)) {
                $this->log("El respaldo ya no existe: {$s}. Ejecute --desde-paso=preparar.", 'error');
                return false;
            }
        }

        $cfg = DB::connection()->getConfig();
        $host = (string) ($cfg['host'] ?? '');
        $bd = (string) ($cfg['database'] ?? '');
        $esLocal = in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1', ''], true);
        if (!$esLocal && !$this->option('permitir-remoto')) {
            $this->log("La BD configurada está en '{$host}', que no es local. Use --permitir-remoto si realmente quiere BORRARLA y restaurar ahí.", 'error');
            return false;
        }

        $tablasActuales = $this->listarTablas();
        $this->log(sprintf('BD destino: %s@%s:%s/%s | tablas actuales: %d', $cfg['username'] ?? '', $host, $cfg['port'] ?? '', $bd, count($tablasActuales)), 'warn');
        if (!$this->option('si')) {
            if (!$this->confirm("Se BORRARÁN TODAS las tablas de '{$bd}' y se importará " . implode(', ', array_map('basename', $sqls)) . '. ¿Continuar?', false)) {
                $this->log('Cancelado por el usuario.');
                return false;
            }
        }

        // 1. Limpiar
        $this->log('Eliminando ' . count($tablasActuales) . ' tablas/vistas…');
        $this->eliminarTodasLasTablas();

        // 2. Importar
        $bin = $this->localizarMysqlBin('mysql');
        foreach ($sqls as $sql) {
            $this->log('Importando ' . basename($sql) . ' (' . $this->formatearBytes(filesize($sql)) . ') con ' . ($bin ? 'mysql CLI (' . $bin . ')' : 'importador PHP (mysql CLI no encontrado; más lento)') . '…');
            $ok = $bin ? $this->importarConCli($bin, $sql, $cfg) : $this->importarConPhp($sql);
            if (!$ok) {
                return false;
            }
        }

        // 3. Verificar
        DB::reconnect();
        foreach (['pedidos', 'items_pedidos', 'pago_pedidos', 'sucursals', 'usuarios', 'inventarios'] as $t) {
            if (!Schema::hasTable($t)) {
                $this->log("Tras la importación falta la tabla '{$t}'. El respaldo no parece ser de Arabito Facturación.", 'error');
                return false;
            }
        }
        $suc = DB::table('sucursals')->first();
        $codigo = strtolower((string) ($suc->codigo ?? ''));
        if ($this->sucursal !== '' && $codigo !== $this->sucursal) {
            $this->log("La BD restaurada es de la sucursal '{$codigo}', no de '{$this->sucursal}'. Si es correcto, repita con --sucursal={$codigo}.", 'error');
            return false;
        }
        $totalPedidos = (int) DB::table('pedidos')->count();
        $rango = DB::table('pedidos')->selectRaw('MIN(COALESCE(fecha_factura, created_at)) as min_f, MAX(COALESCE(fecha_factura, created_at)) as max_f')->first();
        $yaValidos = Schema::hasColumn('pedidos', 'valido') ? (int) DB::table('pedidos')->where('valido', true)->count() : 0;
        $this->log(sprintf('Restaurado: sucursal=%s | pedidos=%d | rango=%s → %s | ya marcados válidos (cuadre previo)=%d', $codigo, $totalPedidos, $rango->min_f ?? '?', $rango->max_f ?? '?', $yaValidos));
        if ($yaValidos > 0) {
            $this->log('La BD trae un cuadre previo. Por defecto esos días se respetan (el cuadre los omite). Para rehacerlos use --desde-cero (restaura ajustes auditados).', 'warn');
        }
        $this->estado['datos']['restaurado'] = ['sucursal' => $codigo, 'pedidos' => $totalPedidos, 'min' => $rango->min_f ?? null, 'max' => $rango->max_f ?? null, 'validos_previos' => $yaValidos];
        // Cualquier paso posterior debe rehacerse sobre la BD nueva.
        foreach (['migrar', 'titanio', 'respaldo', 'simular', 'cuadre', 'medir'] as $p) {
            unset($this->estado['pasos'][$p]);
        }
        $this->guardarEstado();
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Paso 3: migrar
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function pasoMigrar(CuadreCsvReader $reader): bool
    {
        DB::reconnect();
        $rc = 1;
        try {
            $rc = $this->call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            $this->log('migrate lanzó excepción: ' . $e->getMessage(), 'warn');
        }
        if ($rc !== 0) {
            $this->log('php artisan migrate no terminó limpio; se asegura el esquema mínimo del cuadre a mano.', 'warn');
        }
        $faltantes = $this->asegurarEsquemaMinimo();
        if (!empty($faltantes)) {
            $this->log('No se pudo completar el esquema mínimo: ' . implode(', ', $faltantes), 'error');
            return false;
        }
        $this->log('Esquema listo (numero_factura, maquina_fiscal, valido, uuid, monto_bs, cuadre_ajustes).');
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Paso 4: titanio
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function pasoTitanio(CuadreCsvReader $reader): bool
    {
        $storeId = $this->option('store-id') ?: env('TITANIO_STORE_ID', '');
        if ($storeId === '' || $storeId === null) {
            $this->log('Falta el storeId de la sucursal en Titanio POS: use --store-id=NN (o TITANIO_STORE_ID en .env), o --sin-titanio para omitir este paso.', 'error');
            return false;
        }
        $fechas = $this->fechasTitanio();
        if (empty($fechas)) {
            $this->log('Rango de Titanio vacío.', 'error');
            return false;
        }
        $desde = $fechas[0];
        $hasta = $fechas[count($fechas) - 1];

        DB::reconnect();
        $dateExpr = 'DATE(COALESCE(fecha_factura, created_at))';
        $antes = (int) DB::table('pedidos')->whereRaw("{$dateExpr} >= ?", [$desde])->whereRaw("{$dateExpr} <= ?", [$hasta])->count();
        $antesDia1 = (int) DB::table('pedidos')->whereRaw("{$dateExpr} = ?", [$desde])->count();
        $this->log("Pedidos ya en BD entre {$desde} y {$hasta}: {$antes} (el día {$desde} tiene {$antesDia1} del sistema anterior; los de Titanio se agregan por uuid, no se duplican entre corridas).");

        $rc = $this->call('titanio:importar', array_filter([
            '--desde'    => $desde,
            '--hasta'    => $hasta,
            '--store-id' => (string) $storeId,
            '--sucursal' => $this->sucursal !== '' ? $this->sucursal : null,
            '--dry-run'  => $this->dryRun ? true : null,
        ], fn ($v) => $v !== null));

        DB::reconnect();
        $despues = (int) DB::table('pedidos')->whereRaw("{$dateExpr} >= ?", [$desde])->whereRaw("{$dateExpr} <= ?", [$hasta])->count();
        $porDia = DB::table('pedidos')
            ->join('items_pedidos', 'items_pedidos.id_pedido', '=', 'pedidos.id')
            ->whereNotNull('pedidos.uuid')
            ->whereRaw("DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at)) >= ?", [$desde])
            ->whereRaw("DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at)) <= ?", [$hasta])
            ->selectRaw('DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at)) as fecha, COUNT(DISTINCT pedidos.id) as pedidos, SUM(COALESCE(items_pedidos.monto,0)) as usd, SUM(COALESCE(items_pedidos.monto_bs,0)) as bs')
            ->groupBy('fecha')->orderBy('fecha')->get();
        $filas = [];
        $diasSinPedidos = [];
        $porFecha = [];
        foreach ($porDia as $d) {
            $porFecha[$d->fecha] = $d;
        }
        foreach ($fechas as $f) {
            if (isset($porFecha[$f])) {
                $filas[] = [$f, $porFecha[$f]->pedidos, number_format((float) $porFecha[$f]->usd, 2), number_format((float) $porFecha[$f]->bs, 2, ',', '.')];
            } else {
                $filas[] = [$f, 0, '0.00', '0,00'];
                $diasSinPedidos[] = $f;
            }
        }
        $this->table(['Fecha', 'Pedidos Titanio', 'USD', 'Bs'], $filas);
        $this->log("Pedidos en el rango: antes {$antes} → después {$despues}.");
        if (!empty($diasSinPedidos)) {
            $this->log('Días del rango sin pedidos de Titanio: ' . implode(', ', $diasSinPedidos) . ' (¿cerrado, o falló la API?).', 'warn');
        }
        $this->estado['datos']['titanio'] = ['desde' => $desde, 'hasta' => $hasta, 'store_id' => (string) $storeId, 'pedidos_rango' => $despues, 'dias_sin_pedidos' => $diasSinPedidos];
        $this->guardarEstado();

        if ($rc !== 0) {
            $this->log('titanio:importar terminó con errores (ver detalle arriba). Corrija (cajas, productos, tipos de pago o red) y vuelva a ejecutar: es idempotente por uuid.', 'error');
            return false;
        }
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Paso 5: respaldo previo al cuadre
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function pasoRespaldo(CuadreCsvReader $reader): bool
    {
        $bin = $this->localizarMysqlBin('mysqldump');
        if (!$bin) {
            $this->log('No se encontró mysqldump (indique --mysql-bin=<carpeta>). Se continúa SIN respaldo previo; el reverso queda cubierto por la auditoría cuadre_ajustes.', 'warn');
            $this->estado['datos']['respaldo_pre_cuadre'] = null;
            $this->guardarEstado();
            return true;
        }
        $cfg = DB::connection()->getConfig();
        $destino = $this->trabajo . DIRECTORY_SEPARATOR . sprintf('respaldo_pre_cuadre_%s_%s.sql', $this->sucursal ?: 'sucursal', date('Ymd_His'));
        $args = [
            $bin,
            '--host=' . ($cfg['host'] ?? '127.0.0.1'),
            '--port=' . ($cfg['port'] ?? 3306),
            '--user=' . ($cfg['username'] ?? 'root'),
            '--single-transaction', '--quick', '--routines', '--triggers',
            '--default-character-set=utf8mb4',
            '--result-file=' . $destino,
            (string) ($cfg['database'] ?? ''),
        ];
        $this->log('mysqldump → ' . $destino . ' …');
        $res = $this->ejecutar($args, ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
        if ($res['codigo'] !== 0 || !is_file($destino) || filesize($destino) < 1024) {
            $this->log('mysqldump falló (código ' . $res['codigo'] . '): ' . trim($res['stderr']), 'error');
            return false;
        }
        $this->log('Respaldo previo al cuadre: ' . $destino . ' (' . $this->formatearBytes(filesize($destino)) . ')');
        $this->estado['datos']['respaldo_pre_cuadre'] = $destino;
        $this->guardarEstado();
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Pasos 6 y 7: simular / cuadre
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function pasoSimular(CuadreCsvReader $reader): bool
    {
        return $this->correrCuadre(true);
    }

    protected function pasoCuadre(CuadreCsvReader $reader): bool
    {
        return $this->correrCuadre($this->dryRun);
    }

    protected function correrCuadre(bool $simular): bool
    {
        $csv = $this->estado['datos']['csv_objetivo'] ?? null;
        if (!$csv || !is_file($csv)) {
            $this->log('No existe el CSV fusionado; ejecute el paso preparar.', 'error');
            return false;
        }
        DB::reconnect();
        if (!Schema::hasTable('pedidos')) {
            $this->log('La BD no tiene la tabla pedidos (¿falta restaurar?).', 'error');
            return false;
        }
        $reporte = $this->trabajo . DIRECTORY_SEPARATOR . ($simular ? 'simulacion_' : 'cuadre_') . date('Ymd_His') . '.csv';
        $args = [
            'archivo'          => $csv,
            '--si'             => true,
            '--max-segundos'   => (string) $this->option('max-segundos'),
            '--tolerancia-bs'  => (string) $this->option('tolerancia-bs'),
            '--umbral-ajuste'  => (string) $this->option('umbral-ajuste'),
            '--reporte'        => $reporte,
        ];
        if ($simular) {
            $args['--simular'] = true;
        } elseif ($this->option('desde-cero')) {
            $args['--desde-cero'] = true;
        }
        $rc = $this->call('cuadre:pedidos-diario', $args);
        $this->estado['datos'][$simular ? 'reporte_simulacion' : 'reporte_cuadre'] = $reporte;
        $this->guardarEstado();
        if ($rc !== 0) {
            $this->log(($simular ? 'La simulación' : 'El cuadre') . ' terminó con error. Al volver a ejecutar continúa por los grupos pendientes (los ya cuadrados se omiten).', 'error');
            return false;
        }
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Paso 8: medir
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function pasoMedir(CuadreCsvReader $reader): bool
    {
        $csv = $this->estado['datos']['csv_objetivo'] ?? null;
        if (!$csv || !is_file($csv)) {
            $this->log('No existe el CSV fusionado; ejecute el paso preparar.', 'error');
            return false;
        }
        DB::reconnect();
        $grupos = $reader->agregarPorDiaMaquina($reader->leerNormalizado($csv));
        $objetivo = [];
        foreach ($grupos as $g) {
            $mes = substr($g['fecha'], 0, 7);
            $objetivo[$mes] = $objetivo[$mes] ?? ['bs' => '0', 'facturas' => 0, 'grupos' => 0, 'dias' => []];
            $objetivo[$mes]['bs'] = bcadd($objetivo[$mes]['bs'], $g['total_venta'], 4);
            $objetivo[$mes]['facturas'] += (int) $g['cantidad'];
            $objetivo[$mes]['grupos']++;
            $objetivo[$mes]['dias'][$g['fecha']] = true;
        }
        if (empty($objetivo)) {
            $this->log('El CSV de objetivos no tiene grupos.', 'error');
            return false;
        }
        $meses = array_keys($objetivo);
        sort($meses);
        $mesMin = $meses[0] . '-01';
        $mesMax = date('Y-m-t', strtotime(end($meses) . '-01'));

        $mesExpr = "DATE_FORMAT(COALESCE(pedidos.fecha_factura, pedidos.created_at), '%Y-%m')";
        $dateExpr = 'DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at))';

        $logrado = DB::table('pedidos')
            ->join('items_pedidos', 'items_pedidos.id_pedido', '=', 'pedidos.id')
            ->where('pedidos.valido', true)
            ->whereRaw("{$dateExpr} >= ?", [$mesMin])->whereRaw("{$dateExpr} <= ?", [$mesMax])
            ->selectRaw("{$mesExpr} as mes, COUNT(DISTINCT pedidos.id) as facturas, SUM(COALESCE(items_pedidos.monto_bs, items_pedidos.monto * COALESCE(NULLIF(items_pedidos.tasa,0),1), 0)) as bs, COUNT(DISTINCT {$dateExpr}) as dias")
            ->groupByRaw($mesExpr)->get()->keyBy('mes');

        $universo = DB::table('pedidos')
            ->join('items_pedidos', 'items_pedidos.id_pedido', '=', 'pedidos.id')
            ->whereRaw("{$dateExpr} >= ?", [$mesMin])->whereRaw("{$dateExpr} <= ?", [$mesMax])
            ->when(Schema::hasColumn('pedidos', 'estado'), fn ($q) => $q->where('pedidos.estado', 1))
            ->selectRaw("{$mesExpr} as mes, COUNT(DISTINCT pedidos.id) as pedidos, SUM(COALESCE(items_pedidos.monto_bs, items_pedidos.monto * COALESCE(NULLIF(items_pedidos.tasa,0),1), 0)) as bs")
            ->groupByRaw($mesExpr)->get()->keyBy('mes');

        $ajustes = collect();
        if (Schema::hasTable('cuadre_ajustes')) {
            $ajustes = DB::table('cuadre_ajustes')
                ->whereNull('revertido_at')
                ->whereBetween('fecha', [$mesMin, $mesMax])
                ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, COUNT(*) as n, SUM(ABS(ajuste_aplicado_bs)) as abs_bs, MAX(ABS(ajuste_aplicado_bs)) as max_bs")
                ->groupByRaw("DATE_FORMAT(fecha, '%Y-%m')")->get()->keyBy('mes');
        }

        $filas = [];
        $salida = [];
        foreach ($meses as $mes) {
            $o = $objetivo[$mes];
            $l = $logrado[$mes] ?? null;
            $u = $universo[$mes] ?? null;
            $a = $ajustes[$mes] ?? null;
            $objBs = (float) $o['bs'];
            $realBs = (float) ($l->bs ?? 0);
            $diff = $realBs - $objBs;
            $pct = $objBs > 0 ? $diff / $objBs * 100 : 0;
            $fila = [
                'mes'               => $mes,
                'dias_objetivo'     => count($o['dias']),
                'dias_logrados'     => (int) ($l->dias ?? 0),
                'facturas_objetivo' => $o['facturas'],
                'facturas_logradas' => (int) ($l->facturas ?? 0),
                'objetivo_bs'       => round($objBs, 2),
                'logrado_bs'        => round($realBs, 2),
                'diferencia_bs'     => round($diff, 2),
                'diferencia_pct'    => round($pct, 4),
                'pedidos_totales_mes' => (int) ($u->pedidos ?? 0),
                'venta_total_mes_bs'  => round((float) ($u->bs ?? 0), 2),
                'ajustes'           => (int) ($a->n ?? 0),
                'ajuste_abs_bs'     => round((float) ($a->abs_bs ?? 0), 2),
                'ajuste_max_bs'     => round((float) ($a->max_bs ?? 0), 2),
            ];
            $salida[] = $fila;
            $filas[] = [
                $mes,
                $fila['dias_logrados'] . '/' . $fila['dias_objetivo'],
                $fila['facturas_logradas'] . '/' . $fila['facturas_objetivo'],
                number_format($objBs, 2, ',', '.'),
                number_format($realBs, 2, ',', '.'),
                number_format($diff, 2, ',', '.') . ' (' . number_format($pct, 3) . '%)',
                number_format((float) ($u->bs ?? 0), 2, ',', '.'),
                $fila['ajustes'] . ' / máx ' . number_format($fila['ajuste_max_bs'], 2, ',', '.'),
            ];
        }
        $this->table(['Mes', 'Días', 'Facturas', 'Objetivo Bs', 'Logrado Bs', 'Diferencia', 'Venta total mes Bs', 'Ajustes / máx Bs'], $filas);

        // Numeración: duplicados por máquina.
        $duplicados = DB::table('pedidos')
            ->where('valido', true)->whereNotNull('numero_factura')
            ->selectRaw("COALESCE(maquina_fiscal,'') as maquina, numero_factura, COUNT(*) as n")
            ->groupByRaw("COALESCE(maquina_fiscal,''), numero_factura")->havingRaw('COUNT(*) > 1')->count();
        if ($duplicados > 0) {
            $this->log("ATENCIÓN: {$duplicados} números de factura repetidos dentro de la misma máquina fiscal.", 'warn');
        } else {
            $this->log('Numeración: sin números de factura repetidos por máquina.');
        }

        // Grupos sin pedidos / ajuste alto según el último reporte del cuadre.
        $rep = $this->estado['datos']['reporte_cuadre'] ?? null;
        if ($rep && is_file($rep)) {
            $sinPedidos = 0; $altos = 0; $omitidos = 0; $procesados = 0;
            $umbral = (float) $this->option('umbral-ajuste');
            if (($fh = fopen($rep, 'r')) !== false) {
                $head = fgetcsv($fh);
                while (($r = fgetcsv($fh)) !== false) {
                    $row = @array_combine($head, $r);
                    if (!$row) continue;
                    if (($row['estado'] ?? '') === 'sin_pedidos') $sinPedidos++;
                    if (($row['estado'] ?? '') === 'omitido') $omitidos++;
                    if (($row['estado'] ?? '') === 'procesado') {
                        $procesados++;
                        if ((float) ($row['pct_ajuste'] ?? 0) > $umbral) $altos++;
                    }
                }
                fclose($fh);
            }
            $this->log(sprintf('Último cuadre (%s): grupos procesados %d | omitidos (ya hechos) %d | sin pedidos %d | con ajuste > %s%%: %d', basename($rep), $procesados, $omitidos, $sinPedidos, $umbral, $altos), $sinPedidos + $altos > 0 ? 'warn' : 'info');
        }

        $destino = $this->trabajo . DIRECTORY_SEPARATOR . 'resultado_' . ($this->sucursal ?: 'sucursal') . '.csv';
        $fh = fopen($destino, 'w');
        if ($fh) {
            fputcsv($fh, array_keys($salida[0]));
            foreach ($salida as $s) {
                fputcsv($fh, $s);
            }
            fclose($fh);
            $this->log('Resultado por mes escrito en: ' . $destino);
        }
        $this->log('Reporte web: /reportes/cuadre-diario/validacion?csv=' . $csv . '&fecha_desde=' . $mesMin . '&fecha_hasta=' . $mesMax);
        $this->estado['datos']['resultado'] = $destino;
        $this->guardarEstado();
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Utilidades: estado, omisiones, log
    // ═══════════════════════════════════════════════════════════════════════════════════════

    protected function motivoOmision(string $paso): ?string
    {
        if ($this->dryRun && in_array($paso, ['restaurar', 'migrar', 'titanio', 'respaldo', 'medir'], true)) {
            return 'dry-run';
        }
        if ($paso === 'titanio' && $this->option('sin-titanio')) {
            return '--sin-titanio';
        }
        if ($paso === 'respaldo' && $this->option('sin-respaldo')) {
            return '--sin-respaldo';
        }
        if ($paso === 'simular' && !$this->option('simular-antes') && !$this->option('solo-simular') && !$this->dryRun) {
            return 'sin --simular-antes';
        }
        if ($paso === 'cuadre' && ($this->option('solo-simular') || $this->dryRun)) {
            return $this->dryRun ? 'dry-run' : '--solo-simular';
        }
        if ($paso === 'medir' && $this->option('solo-simular')) {
            return '--solo-simular';
        }
        return null;
    }

    protected function rutaEstado(): string
    {
        return $this->trabajo . DIRECTORY_SEPARATOR . 'estado.json';
    }

    protected function cargarEstado(): void
    {
        $this->estado = ['pasos' => [], 'datos' => []];
        $ruta = $this->rutaEstado();
        if (is_file($ruta)) {
            $json = json_decode((string) file_get_contents($ruta), true);
            if (is_array($json)) {
                $this->estado = array_merge($this->estado, $json);
            }
        }
    }

    protected function guardarEstado(): void
    {
        $this->estado['actualizado'] = date('Y-m-d H:i:s');
        $this->estado['parametros'] = [
            'carpeta' => $this->carpeta, 'sucursal' => $this->sucursal,
            'store_id' => (string) ($this->option('store-id') ?: env('TITANIO_STORE_ID', '')),
            'titanio_desde' => $this->option('titanio-desde'), 'titanio_hasta' => $this->option('titanio-hasta'),
            'max_segundos' => $this->option('max-segundos'),
        ];
        file_put_contents($this->rutaEstado(), json_encode($this->estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function pasoHecho(string $paso): bool
    {
        return !empty($this->estado['pasos'][$paso]['fin']);
    }

    protected function marcarHecho(string $paso, float $segundos): void
    {
        $this->estado['pasos'][$paso] = ['fin' => date('Y-m-d H:i:s'), 'segundos' => round($segundos, 1)];
        $this->guardarEstado();
    }

    protected function cabecera(): void
    {
        $this->log('cuadre:completo | sucursal=' . $this->sucursal . ' | trabajo=' . $this->trabajo . ($this->dryRun ? ' | DRY-RUN' : ''));
        $hechos = array_keys(array_filter($this->estado['pasos'] ?? [], fn ($p) => !empty($p['fin'])));
        if (!empty($hechos)) {
            $this->log('Pasos ya completados en corridas anteriores: ' . implode(', ', $hechos) . ' (se omiten; --reiniciar para empezar de cero).');
        }
    }

    protected function log(string $mensaje, string $nivel = 'info'): void
    {
        switch ($nivel) {
            case 'error': $this->error($mensaje); break;
            case 'warn':  $this->warn($mensaje); break;
            default:      $this->line($mensaje);
        }
        if ($this->logFh) {
            fwrite($this->logFh, '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($nivel) . ' ' . $mensaje . PHP_EOL);
        }
    }

    protected function cerrar(): void
    {
        if ($this->logFh) {
            fclose($this->logFh);
            $this->logFh = null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Utilidades: archivos
    // ═══════════════════════════════════════════════════════════════════════════════════════

    /** @return string[] rutas de archivos (no directorios) hasta la profundidad dada */
    protected function listarArchivos(string $dir, int $profundidad): array
    {
        $out = [];
        $items = @scandir($dir);
        if ($items === false) {
            return $out;
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $ruta = $dir . DIRECTORY_SEPARATOR . $it;
            if (is_dir($ruta)) {
                if ($profundidad > 1) {
                    $out = array_merge($out, $this->listarArchivos($ruta, $profundidad - 1));
                }
            } elseif (is_file($ruta)) {
                $out[] = $ruta;
            }
        }
        return $out;
    }

    protected function extraerZip(string $zip, string $destino): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->log('La extensión zip de PHP no está habilitada; descomprima los ZIP a mano en la carpeta.', 'error');
            return false;
        }
        $za = new \ZipArchive();
        $rc = $za->open($zip);
        if ($rc !== true) {
            $this->log("No se pudo abrir {$zip} (código {$rc}).", 'error');
            return false;
        }
        if (!is_dir($destino)) {
            mkdir($destino, 0777, true);
        }
        $ok = $za->extractTo($destino);
        $za->close();
        if (!$ok) {
            $this->log("Falló la extracción de {$zip}.", 'error');
            return false;
        }
        return true;
    }

    /**
     * Inspecciona un .sql: cuenta CREATE TABLE, detecta la tabla pedidos y sentencias USE/CREATE DATABASE.
     */
    protected function inspeccionarDump(string $path): array
    {
        $info = ['tablas' => 0, 'tiene_pedidos' => false, 'cambia_bd' => false];
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return $info;
        }
        while (($line = fgets($fh)) !== false) {
            if (isset($line[0]) && ($line[0] === 'C' || $line[0] === 'U' || $line[0] === 'c' || $line[0] === 'u')) {
                if (preg_match('/^CREATE TABLE\s+(?:IF NOT EXISTS\s+)?[`"]?([A-Za-z0-9_]+)[`"]?/i', $line, $m)) {
                    $info['tablas']++;
                    if (strtolower($m[1]) === 'pedidos') {
                        $info['tiene_pedidos'] = true;
                    }
                } elseif (preg_match('/^(USE\s|CREATE DATABASE)/i', $line)) {
                    $info['cambia_bd'] = true;
                }
            }
        }
        fclose($fh);
        return $info;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Utilidades: MySQL
    // ═══════════════════════════════════════════════════════════════════════════════════════

    /** @return string[] */
    protected function listarTablas(): array
    {
        $bd = DB::connection()->getDatabaseName();
        $rows = DB::select('SHOW FULL TABLES');
        $out = [];
        foreach ($rows as $r) {
            $arr = (array) $r;
            $vals = array_values($arr);
            $out[] = ['nombre' => (string) $vals[0], 'tipo' => strtoupper((string) ($vals[1] ?? 'BASE TABLE'))];
        }
        unset($bd);
        return $out;
    }

    protected function eliminarTodasLasTablas(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->listarTablas() as $t) {
            if ($t['tipo'] === 'VIEW') {
                DB::statement('DROP VIEW IF EXISTS `' . str_replace('`', '``', $t['nombre']) . '`');
            } else {
                DB::statement('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t['nombre']) . '`');
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Localiza el binario mysql/mysqldump: --mysql-bin, env MYSQL_BIN_DIR, config dump_binary_path,
     * rutas típicas de XAMPP/Laragon/MySQL en Windows, y finalmente el PATH.
     */
    protected function localizarMysqlBin(string $nombre): ?string
    {
        $esWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $exe = $esWin ? $nombre . '.exe' : $nombre;
        $alias = $nombre === 'mysql' ? 'mariadb' : ($nombre === 'mysqldump' ? 'mariadb-dump' : $nombre);
        $aliasExe = $esWin ? $alias . '.exe' : $alias;

        $dirs = [];
        if ($this->option('mysql-bin')) {
            $dirs[] = rtrim((string) $this->option('mysql-bin'), "\\/");
        }
        if (($e = env('MYSQL_BIN_DIR')) && is_string($e)) {
            $dirs[] = rtrim($e, "\\/");
        }
        $cfgDir = config('database.connections.mysql.dump.dump_binary_path');
        if (is_string($cfgDir) && $cfgDir !== '') {
            $dirs[] = rtrim($cfgDir, "\\/");
        }
        if ($esWin) {
            $dirs = array_merge($dirs, ['C:\\xampp\\mysql\\bin', 'C:\\wamp64\\bin\\mysql', 'C:\\Program Files\\MariaDB\\bin']);
            foreach (array_merge(glob('C:\\laragon\\bin\\mysql\\*\\bin') ?: [], glob('C:\\Program Files\\MySQL\\*\\bin') ?: [], glob('C:\\Program Files\\MariaDB*\\bin') ?: [], glob('C:\\wamp64\\bin\\mysql\\*\\bin') ?: []) as $g) {
                $dirs[] = $g;
            }
        } else {
            $dirs = array_merge($dirs, ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/usr/local/mysql/bin', '/opt/lampp/bin']);
        }
        foreach ($dirs as $d) {
            foreach ([$exe, $aliasExe] as $n) {
                $p = $d . DIRECTORY_SEPARATOR . $n;
                if (is_file($p)) {
                    return $p;
                }
            }
        }
        // PATH
        foreach ([$nombre, $alias] as $n) {
            $res = $this->ejecutar($esWin ? ['where', $n] : ['which', $n]);
            $linea = trim(strtok($res['stdout'] ?: '', "\r\n") ?: '');
            if ($res['codigo'] === 0 && $linea !== '' && is_file($linea)) {
                return $linea;
            }
        }
        return null;
    }

    /**
     * Importa un .sql con el cliente mysql, alimentándolo línea a línea por stdin (muestra progreso y permite
     * saltar USE/CREATE DATABASE y la línea de "sandbox" de MariaDB 11 que clientes viejos no entienden).
     */
    protected function importarConCli(string $bin, string $sql, array $cfg): bool
    {
        $args = [
            $bin,
            '--host=' . ($cfg['host'] ?? '127.0.0.1'),
            '--port=' . ($cfg['port'] ?? 3306),
            '--user=' . ($cfg['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
            '--max-allowed-packet=1G',
            (string) ($cfg['database'] ?? ''),
        ];
        $env = array_merge($this->entornoBase(), ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($args, $desc, $pipes, null, $env);
        if (!is_resource($proc)) {
            $this->log('No se pudo lanzar el cliente mysql.', 'error');
            return false;
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $fh = fopen($sql, 'r');
        $total = filesize($sql);
        $leidos = 0;
        $ultimoAviso = microtime(true);
        $inicio = $ultimoAviso;
        $stderr = '';
        $stdout = '';
        $okEscritura = true;
        while (($line = fgets($fh)) !== false) {
            $leidos += strlen($line);
            if (isset($line[0]) && ($line[0] === 'U' || $line[0] === 'u' || $line[0] === 'C' || $line[0] === 'c' || $line[0] === '/')) {
                if (preg_match('/^(USE\s|CREATE DATABASE|\/\*!999999)/i', $line)) {
                    continue;
                }
            }
            $escrito = @fwrite($pipes[0], $line);
            if ($escrito === false) {
                $okEscritura = false;
                break;
            }
            // Drenar salida para que el proceso no se bloquee.
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            if (strlen($stderr) > 0 && stripos($stderr, 'ERROR') !== false) {
                break;
            }
            $ahora = microtime(true);
            if ($ahora - $ultimoAviso >= 15) {
                $pct = $total > 0 ? $leidos / $total * 100 : 0;
                $eta = $pct > 0 ? ($ahora - $inicio) * (100 - $pct) / $pct : 0;
                $this->line(sprintf('    … %.1f%% (%s de %s), ETA %s', $pct, $this->formatearBytes($leidos), $this->formatearBytes($total), $this->formatearDuracion($eta)));
                $ultimoAviso = $ahora;
            }
        }
        fclose($fh);
        fclose($pipes[0]);
        // Esperar a que termine leyendo lo que quede.
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($proc);

        $stderrLimpio = trim(preg_replace('/^.*Using a password on the command line.*$/mi', '', $stderr));
        if ($codigo !== 0 || !$okEscritura || stripos($stderrLimpio, 'ERROR') !== false) {
            $this->log('El cliente mysql falló (código ' . $codigo . '). ' . substr($stderrLimpio, 0, 1500), 'error');
            if (stripos($stderrLimpio, 'max_allowed_packet') !== false) {
                $this->log('Sugerencia: aumente max_allowed_packet en my.ini/my.cnf del servidor (p. ej. 256M) y repita.', 'warn');
            }
            if (stripos($stderrLimpio, 'Unknown collation') !== false) {
                $this->log('Sugerencia: el respaldo viene de MySQL 8 (collation utf8mb4_0900_*); impórtelo en MySQL 8 o cambie la collation en el .sql.', 'warn');
            }
            return false;
        }
        $this->log('Importación terminada en ' . $this->formatearDuracion(microtime(true) - $inicio) . '.');
        return true;
    }

    /**
     * Importador PHP de respaldo (sin cliente mysql): acumula sentencias por línea (mysqldump escapa saltos de
     * línea dentro de cadenas, así que una sentencia termina en la línea que acaba con el delimitador).
     */
    protected function importarConPhp(string $sql): bool
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 600);
        try {
            $pdo->exec('SET GLOBAL max_allowed_packet=268435456');
        } catch (\Throwable $e) {
            // sin privilegios; seguimos
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET UNIQUE_CHECKS=0');
        $pdo->exec('SET autocommit=0');
        $pdo->exec("SET NAMES utf8mb4");

        $fh = fopen($sql, 'r');
        $total = filesize($sql);
        $leidos = 0;
        $delim = ';';
        $buffer = '';
        $sentencias = 0;
        $inicio = microtime(true);
        $ultimoAviso = $inicio;
        $ultimoCommit = 0;

        while (($line = fgets($fh)) !== false) {
            $leidos += strlen($line);
            $trim = rtrim($line, "\r\n");
            if ($buffer === '') {
                $t = ltrim($trim);
                if ($t === '' || str_starts_with($t, '--') || (str_starts_with($t, '/*') && !str_starts_with($t, '/*!')) || str_starts_with($t, '/*!999999')) {
                    continue;
                }
                if (preg_match('/^DELIMITER\s+(\S+)/i', $t, $m)) {
                    $delim = $m[1];
                    continue;
                }
                if (preg_match('/^(USE\s|CREATE DATABASE)/i', $t)) {
                    continue;
                }
            }
            $buffer .= $line;
            $fin = rtrim($trim);
            if ($fin !== '' && substr($fin, -strlen($delim)) === $delim) {
                $stmt = substr(rtrim($buffer), 0, -strlen($delim));
                $buffer = '';
                if (trim($stmt) === '') {
                    continue;
                }
                try {
                    $pdo->exec($stmt);
                } catch (\Throwable $e) {
                    $this->log('Error SQL en sentencia #' . ($sentencias + 1) . ': ' . $e->getMessage() . ' | ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 200), 'error');
                    fclose($fh);
                    return false;
                }
                $sentencias++;
                if ($sentencias - $ultimoCommit >= 200) {
                    $pdo->exec('COMMIT');
                    $ultimoCommit = $sentencias;
                }
                $ahora = microtime(true);
                if ($ahora - $ultimoAviso >= 15) {
                    $pct = $total > 0 ? $leidos / $total * 100 : 0;
                    $eta = $pct > 0 ? ($ahora - $inicio) * (100 - $pct) / $pct : 0;
                    $this->line(sprintf('    … %.1f%% (%d sentencias), ETA %s', $pct, $sentencias, $this->formatearDuracion($eta)));
                    $ultimoAviso = $ahora;
                }
            }
        }
        fclose($fh);
        $pdo->exec('COMMIT');
        $pdo->exec('SET autocommit=1');
        $pdo->exec('SET UNIQUE_CHECKS=1');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $this->log("Importación PHP terminada: {$sentencias} sentencias en " . $this->formatearDuracion(microtime(true) - $inicio) . '.');
        return true;
    }

    /**
     * Garantiza columnas/tablas que necesitan el cuadre y el importador Titanio aunque migrate falle.
     * @return string[] elementos que no se pudieron crear
     */
    protected function asegurarEsquemaMinimo(): array
    {
        $faltan = [];
        $intentar = function (string $nombre, callable $fn) use (&$faltan) {
            try {
                $fn();
            } catch (\Throwable $e) {
                $this->log("  No se pudo asegurar {$nombre}: " . $e->getMessage(), 'warn');
                $faltan[] = $nombre;
            }
        };

        $intentar('pedidos.numero_factura', function () {
            if (!Schema::hasColumn('pedidos', 'numero_factura')) {
                Schema::table('pedidos', fn ($t) => $t->string('numero_factura')->nullable()->after('id'));
            }
        });
        $intentar('pedidos.maquina_fiscal', function () {
            if (!Schema::hasColumn('pedidos', 'maquina_fiscal')) {
                Schema::table('pedidos', fn ($t) => $t->string('maquina_fiscal', 50)->nullable());
            }
        });
        $intentar('pedidos.valido', function () {
            if (!Schema::hasColumn('pedidos', 'valido')) {
                Schema::table('pedidos', fn ($t) => $t->boolean('valido')->nullable()->default(null));
            }
        });
        $intentar('pedidos.uuid', function () {
            if (!Schema::hasColumn('pedidos', 'uuid')) {
                Schema::table('pedidos', fn ($t) => $t->string('uuid', 36)->nullable()->after('id'));
            }
        });
        $intentar('items_pedidos.monto_bs', function () {
            if (!Schema::hasColumn('items_pedidos', 'monto_bs')) {
                Schema::table('items_pedidos', fn ($t) => $t->decimal('monto_bs', 18, 4)->nullable());
            }
        });
        $intentar('pago_pedidos.monto_bs', function () {
            if (!Schema::hasColumn('pago_pedidos', 'monto_bs')) {
                Schema::table('pago_pedidos', fn ($t) => $t->decimal('monto_bs', 18, 4)->nullable());
            }
        });
        $intentar('pagos_referencias.uuid_pedido', function () {
            if (Schema::hasTable('pagos_referencias') && !Schema::hasColumn('pagos_referencias', 'uuid_pedido')) {
                Schema::table('pagos_referencias', fn ($t) => $t->string('uuid_pedido', 36)->nullable());
            }
        });
        $intentar('cuadre_ajustes', function () {
            if (!Schema::hasTable('cuadre_ajustes')) {
                $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_09_27_000000_create_cuadre_ajustes_table.php']);
                if (!Schema::hasTable('cuadre_ajustes')) {
                    throw new \RuntimeException('la migración no creó la tabla');
                }
            }
        });
        return $faltan;
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Utilidades varias
    // ═══════════════════════════════════════════════════════════════════════════════════════

    /** @return string[] */
    protected function fechasTitanio(): array
    {
        try {
            $d = Carbon::parse((string) $this->option('titanio-desde'))->startOfDay();
            $h = Carbon::parse((string) ($this->option('titanio-hasta') ?: Carbon::today()->toDateString()))->startOfDay();
        } catch (\Throwable $e) {
            return [];
        }
        if ($h->lt($d)) {
            return [];
        }
        $out = [];
        for ($f = $d->copy(); $f->lte($h) && count($out) < 400; $f->addDay()) {
            $out[] = $f->toDateString();
        }
        return $out;
    }

    /**
     * Ejecuta un proceso (array de argumentos, sin shell) y devuelve código, stdout y stderr.
     */
    protected function ejecutar(array $args, array $envExtra = []): array
    {
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = array_merge($this->entornoBase(), $envExtra);
        $proc = @proc_open($args, $desc, $pipes, null, $env);
        if (!is_resource($proc)) {
            return ['codigo' => 127, 'stdout' => '', 'stderr' => 'no se pudo lanzar ' . ($args[0] ?? '?')];
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($proc);
        return ['codigo' => $codigo, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    protected function entornoBase(): array
    {
        $env = [];
        foreach (['PATH', 'SystemRoot', 'SYSTEMROOT', 'TEMP', 'TMP', 'HOME', 'USERPROFILE', 'LANG', 'LC_ALL'] as $k) {
            $v = getenv($k);
            if ($v !== false) {
                $env[$k] = $v;
            }
        }
        return $env;
    }

    protected function formatearBytes(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($u) - 1) {
            $v /= 1024;
            $i++;
        }
        return sprintf($i === 0 ? '%d %s' : '%.1f %s', $v, $u[$i]);
    }

    protected function formatearDuracion(float $segundos): string
    {
        $segundos = max(0, (int) round($segundos));
        $h = intdiv($segundos, 3600);
        $m = intdiv($segundos % 3600, 60);
        $s = $segundos % 60;
        return $h > 0 ? sprintf('%dh %02dm %02ds', $h, $m, $s) : sprintf('%dm %02ds', $m, $s);
    }
}
