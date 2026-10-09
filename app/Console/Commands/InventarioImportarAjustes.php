<?php

namespace App\Console\Commands;

use App\Services\Inventario\CentralReadOnly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa (solo lectura sobre central) los ajustes de inventario hechos por el equipo de inventario sobre esta
 * sucursal, desde el registro de movimientos de central (movsinventarios, alimentado por el DICI y por TitanioPOS):
 *   - "EDICION CENTRAL"            → EDICION DICI (revisión de inventario desde central), cantidad con signo
 *   - "AJUSTE POSITIVO/NEGATIVO"   → AJUSTE TPOS (ajustes hechos en TitanioPOS)
 *   - "Salida: ..." / "Reversión de garantía" → GARANTIA (producto enviado a garantía y su reversión)
 *   - "FUSIÓN: ... (id N)"         → FUSION (existencia que pasa a la ficha sobreviviente; si la ficha de origen N
 *                                    existe localmente se registra también la salida en ella)
 * No se importan ventas, cambios, despachos de venta ni lotes marcados "no es stock de venta": esos ya están en el libro
 * por las facturas de venta y de compra. Es repetible: cada movimiento se guarda por su clave TP<id>.
 */
class InventarioImportarAjustes extends Command
{
    protected $signature = 'inventario:importar-ajustes
                            {--sucursal-central= : id de la sucursal en central (default INVENTARIO_SUCURSAL_CENTRAL_ID)}
                            {--central-env= : ruta al .env de central para las credenciales (solo lectura)}
                            {--dry-run : no escribe, solo informa}';

    protected $description = 'Importa los ajustes de inventario del equipo de inventario (central/TitanioPOS) al libro de inventario.';

    public function handle(): int
    {
        $sucursal = (int) ($this->option('sucursal-central') ?: env('INVENTARIO_SUCURSAL_CENTRAL_ID', 0));
        if ($sucursal <= 0) {
            $this->error('Indique --sucursal-central=<id> (o INVENTARIO_SUCURSAL_CENTRAL_ID).');
            return self::FAILURE;
        }
        $central = CentralReadOnly::conectar($this->option('central-env') ?: null);
        $dry = (bool) $this->option('dry-run');
        $locales = DB::table('inventarios')->pluck('descripcion', 'id');
        $now = now();
        $filas = [];
        $conteo = [];
        $sinFicha = 0;
        $q = $central->table('movsinventarios')->where('id_sucursal', $sucursal)->where('cantidad', '<>', 0)
            ->where(function ($w) {
                foreach (['EDICION CENTRAL%', 'AJUSTE POSITIVO%', 'AJUSTE NEGATIVO%', 'Salida:%', 'Reversión de garantía%', 'FUSIÓN:%'] as $p) {
                    $w->orWhere('origen', 'like', $p);
                }
            })->orderBy('id');
        foreach ($q->get(['id', 'id_producto', 'id_pedido', 'id_usuario', 'cantidad', 'created_at', 'origen']) as $m) {
            $o = trim((string) $m->origen);
            $cant = (float) $m->cantidad;
            $idp = (int) $m->id_producto;
            if (str_starts_with($o, 'EDICION CENTRAL')) {
                [$sub, $ref] = ['EDICION DICI', 'Revisión de inventario desde central'];
            } elseif (str_starts_with($o, 'AJUSTE POSITIVO') || str_starts_with($o, 'AJUSTE NEGATIVO')) {
                [$sub, $ref] = ['AJUSTE TPOS', 'Ajuste en TitanioPOS #' . $m->id_pedido];
            } elseif (str_starts_with($o, 'Salida:') || str_starts_with($o, 'Reversión de garantía')) {
                [$sub, $ref] = ['GARANTIA', mb_substr($o, 0, 255)];
            } elseif (str_starts_with($o, 'FUSIÓN:')) {
                [$sub, $ref] = ['FUSION', mb_substr($o, 0, 255)];
            } else {
                continue;
            }
            $usuario = (int) $m->id_usuario > 0 ? 'central #' . $m->id_usuario : 'TitanioPOS';
            if (!isset($locales[$idp])) $sinFicha++;
            $filas[] = ['sucursal_central_id' => $sucursal, 'tipo' => 'AJUSTE', 'subtipo' => $sub, 'clave' => 'TP' . $m->id, 'fecha' => substr((string) $m->created_at, 0, 10),
                'id_producto' => $idp, 'cantidad' => $cant, 'usuario' => $usuario, 'referencia' => $ref];
            $conteo[$sub] = ($conteo[$sub] ?? 0) + 1;
            // Fusión: la ficha de origen "(id N)" pierde lo que gana la sobreviviente, si existe localmente y es otra ficha.
            if ($sub === 'FUSION' && preg_match('/\(id (\d+)\)/', $o, $mm) && (int) $mm[1] !== $idp && isset($locales[(int) $mm[1]])) {
                $filas[] = ['sucursal_central_id' => $sucursal, 'tipo' => 'AJUSTE', 'subtipo' => 'FUSION', 'clave' => 'TP' . $m->id . '-origen', 'fecha' => substr((string) $m->created_at, 0, 10),
                    'id_producto' => (int) $mm[1], 'cantidad' => -$cant, 'usuario' => $usuario, 'referencia' => 'Fusionada en la ficha ' . $idp . ': ' . mb_substr($locales[$idp] ?? '', 0, 120)];
                $conteo['FUSION (origen)'] = ($conteo['FUSION (origen)'] ?? 0) + 1;
            }
        }
        $mas = array_sum(array_map(fn ($f) => max($f['cantidad'], 0), $filas));
        $menos = array_sum(array_map(fn ($f) => min($f['cantidad'], 0), $filas));
        $this->info(sprintf('Sucursal central %d: %d ajustes (%s) | +%s / %s unidades | %d sobre productos sin ficha local%s',
            $sucursal, count($filas), json_encode($conteo), number_format($mas, 2, ',', '.'), number_format($menos, 2, ',', '.'), $sinFicha, $dry ? ' | DRY-RUN, no se escribe nada' : ''));
        if ($dry) {
            return self::SUCCESS;
        }
        $nuevos = 0;
        foreach ($filas as $f) {
            $existe = DB::table('inventario_ajustes')->where('clave', $f['clave'])->exists();
            DB::table('inventario_ajustes')->updateOrInsert(['clave' => $f['clave']], $f + ['importado_at' => $now, 'updated_at' => $now] + ($existe ? [] : ['created_at' => $now]));
            if (!$existe) $nuevos++;
        }
        $this->info(sprintf('Guardados: %d nuevos, %d actualizados. Luego ejecute inventario:saldos-iniciales para recalcular los saldos de apertura.', $nuevos, count($filas) - $nuevos));
        return self::SUCCESS;
    }
}
