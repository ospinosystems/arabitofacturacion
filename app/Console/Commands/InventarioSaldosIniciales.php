<?php

namespace App\Console\Commands;

use App\Services\Inventario\LibroInventarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Saldos iniciales de apertura: recorre todo el historial del libro (entradas, ventas y ajustes) y, para cada producto
 * cuya existencia llega a ser negativa en algún momento, registra en inventario_ajustes (tipo SALDO_INICIAL) una
 * existencia de apertura fechada antes del inicio del libro (marzo) igual al mínimo negativo alcanzado, de modo que
 * ningún mes muestre existencia negativa. Decisión del usuario (09-oct-2026): "el saldo inicial negativo pásalo a marzo".
 * Es repetible: borra los saldos anteriores y los recalcula con los datos actuales.
 */
class InventarioSaldosIniciales extends Command
{
    protected $signature = 'inventario:saldos-iniciales
                            {--fecha=2026-03-01 : fecha del saldo de apertura (antes del primer documento del libro)}
                            {--dry-run : no escribe, solo informa}';

    protected $description = 'Calcula y registra los saldos iniciales (marzo) que evitan existencias negativas en el libro de inventario.';

    public function handle(LibroInventarioService $svc): int
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $fecha = $this->option('fecha');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha)) {
            $this->error('--fecha debe ser AAAA-MM-DD');
            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        // Historial completo sin los saldos iniciales previos: mínimo de existencia alcanzado por producto.
        $libro = $svc->construir(null, null, LibroInventarioService::TIPOS_DEFECTO, null, true, false);
        $minimo = [];
        foreach ($libro['movimientos'] as $m) {
            $k = $m['producto'];
            if (!is_int($k)) continue; // sin ficha local: no hay dónde registrar el saldo
            $minimo[$k] = min($minimo[$k] ?? 0.0, $m['saldo_qty']);
        }
        $saldos = [];
        foreach ($minimo as $k => $min) {
            if ($min < -0.00005) $saldos[$k] = round(-$min, 4);
        }
        $this->info(sprintf('Productos con existencia negativa en algún momento: %d | unidades de apertura: %s | fecha %s%s',
            count($saldos), number_format(array_sum($saldos), 2, ',', '.'), $fecha, $dry ? ' | DRY-RUN' : ''));
        foreach (array_slice($saldos, 0, 15, true) as $k => $q) {
            $p = $libro['productos'][$k];
            $this->line(sprintf('  %-16s %-45s %10.2f', $p['codigo'], mb_substr($p['descripcion'], 0, 45), $q));
        }
        if (count($saldos) > 15) $this->line('  ...');
        if ($dry) {
            return self::SUCCESS;
        }
        $now = now();
        DB::transaction(function () use ($saldos, $fecha, $now) {
            DB::table('inventario_ajustes')->where('tipo', 'SALDO_INICIAL')->delete();
            $filas = [];
            foreach ($saldos as $k => $q) {
                $filas[] = ['sucursal_central_id' => (int) env('INVENTARIO_SUCURSAL_CENTRAL_ID', 0) ?: null, 'tipo' => 'SALDO_INICIAL', 'subtipo' => 'SALDO INICIAL', 'clave' => 'SI' . $k,
                    'fecha' => $fecha, 'id_producto' => $k, 'cantidad' => $q, 'usuario' => null,
                    'referencia' => 'Existencia de apertura: compensa el mínimo negativo alcanzado en el historial', 'importado_at' => $now, 'created_at' => $now, 'updated_at' => $now];
                if (count($filas) >= 500) { DB::table('inventario_ajustes')->insert($filas); $filas = []; }
            }
            if ($filas) DB::table('inventario_ajustes')->insert($filas);
        });
        // Verificación: con los saldos puestos no debe quedar ningún producto con ficha en negativo.
        $verif = $svc->construir(null, null, LibroInventarioService::TIPOS_DEFECTO, null, false);
        $neg = count(array_filter($verif['productos'], fn ($p) => $p['negativo'] && is_int($p['id'])));
        $this->info(sprintf('Registrados %d saldos iniciales. Verificación: productos con ficha que siguen en negativo = %d', count($saldos), $neg));
        return self::SUCCESS;
    }
}
