<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use App\Models\inventario;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DESPACHO de prueba: crea VARIAS transferencias con VARIOS productos y cantidades
 * variables, validando en tiempo real que el inventario se DESCUENTE exacto.
 * Con --concurrente dispara las órdenes en paralelo (procesos OS) para probar que
 * el descuento es robusto bajo concurrencia (no hay lost updates).
 *
 * NO recibe ni limpia: deja las transferencias creadas para que vos marques el
 * estado "revisado" (4) en central a mano, y luego corras `prueba:recepcion`.
 *
 *   php artisan prueba:despacho --ordenes=3 --productos=4 --force
 *   php artisan prueba:despacho --ordenes=6 --productos=5 --concurrente --force
 *
 * (Tip: corré `php artisan transferencias:watch` en otra terminal para verlo en vivo.)
 */
class PruebaDespacho extends Command
{
    protected $signature = 'prueba:despacho
                            {--ordenes=3 : cantidad de transferencias a crear}
                            {--productos=4 : productos distintos por transferencia}
                            {--max-cantidad=3 : cantidad máxima por ítem (aleatorio 1..max)}
                            {--concurrente : disparar las órdenes en paralelo (robustez)}
                            {--force : sin confirmación}
                            {--fire= : (interno) worker: crea UNA orden}
                            {--items= : (interno) prod:cant,prod:cant para el worker}';

    protected $description = 'Crea varias transferencias multi-producto y valida los descuentos de inventario (con opción concurrente).';

    private int $idDestino = 8;

    public function handle(): int
    {
        if ($this->option('fire')) {
            return $this->runFire();
        }

        $nOrdenes = max(1, (int) $this->option('ordenes'));
        $nProd = max(1, (int) $this->option('productos'));
        $maxCant = max(1, (int) $this->option('max-cantidad'));
        $concurrente = (bool) $this->option('concurrente');

        $this->warn('⚠️  Crea transferencias REALES (descuenta inventario y crea espejos en central).');
        if (!$this->option('force') && !$this->confirm('¿Continuar?', false)) {
            return self::SUCCESS;
        }
        session(['id_usuario' => 1, 'tipo_usuario' => 7]);

        // ── pool de productos con stock suficiente ──
        $necesarioPorProd = $nOrdenes * $maxCant; // peor caso: el mismo producto en todas las órdenes
        $pool = inventario::query()
            ->whereNotNull('codigo_barras')->where('codigo_barras', '<>', '')
            ->where('cantidad', '>=', $necesarioPorProd)
            ->whereColumn('precio_base', '<', 'precio')->where('precio', '>', 0)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('arabitocentral.inventario_sucursals as s')
                    ->whereColumn('s.id', 'inventarios.id')->where('s.id_sucursal', 13);
            })
            ->inRandomOrder()->limit(max($nProd + 2, 6))->get();

        if ($pool->count() < $nProd) {
            $this->error("No hay suficientes productos sembrados con stock >= {$necesarioPorProd}. Encontrados: {$pool->count()}.");
            return self::FAILURE;
        }
        $poolIds = $pool->pluck('id')->all();

        // ── definir N órdenes: cada una M productos al azar + cantidad variable ──
        $ordenes = [];
        $esperado = []; // producto => cantidad total esperada a descontar
        for ($o = 0; $o < $nOrdenes; $o++) {
            shuffle($poolIds);
            $items = [];
            foreach (array_slice($poolIds, 0, $nProd) as $pid) {
                $cant = rand(1, $maxCant);
                $items[$pid] = $cant;
                $esperado[$pid] = ($esperado[$pid] ?? 0) + $cant;
            }
            $ordenes[] = $items;
        }

        // ── snapshot ANTES ──
        $antes = [];
        foreach (array_keys($esperado) as $pid) {
            $antes[$pid] = $this->stock($pid);
        }

        $this->info(($concurrente ? 'CONCURRENTE' : 'SECUENCIAL') . ": {$nOrdenes} órdenes × {$nProd} productos (cant 1..{$maxCant}).");
        $this->newLine();

        $creadas = []; // [ ['local'=>, 'central'=>, 'items'=>[pid=>cant]], ... ]
        $exitosos = []; // índices de órdenes que se crearon OK

        if ($concurrente) {
            $procs = [];
            foreach ($ordenes as $i => $items) {
                $spec = collect($items)->map(fn ($c, $p) => "$p:$c")->implode(',');
                $p = new \Symfony\Component\Process\Process(
                    ['php', 'artisan', 'prueba:despacho', '--fire=1', '--items=' . $spec],
                    base_path()
                );
                $p->setTimeout(180);
                $p->start();
                $procs[$i] = $p;
            }
            foreach ($procs as $i => $p) {
                $p->wait();
                if (preg_match('/OK:central:(\d+):local:(\d+)/', $p->getOutput(), $m)) {
                    $creadas[] = ['local' => (int) $m[2], 'central' => (int) $m[1], 'items' => $ordenes[$i]];
                    $exitosos[] = $i;
                    $this->line("  ✓ orden " . ($i + 1) . " creada (central {$m[1]})");
                } else {
                    $this->line("  ✗ orden " . ($i + 1) . " falló: " . trim(preg_replace('/\s+/', ' ', $p->getOutput())));
                }
            }
        } else {
            $sc = new sendCentral();
            foreach ($ordenes as $i => $items) {
                $r = $this->asArray($sc->settransferenciaDici($this->reqOrden($items)));
                if (!empty($r['estado'])) {
                    $creadas[] = ['local' => $r['transferencia']['id'] ?? null, 'central' => $r['transferencia']['id_transferencia_central'] ?? null, 'items' => $items];
                    $exitosos[] = $i;
                    $detalle = collect($items)->map(fn ($c, $p) => "#{$p}×{$c}")->implode(' ');
                    $this->line("  ✓ orden " . ($i + 1) . ": {$detalle}  → central " . ($r['transferencia']['id_transferencia_central'] ?? '?'));
                } else {
                    $this->line("  ✗ orden " . ($i + 1) . " falló: " . ($r['msj'] ?? 'sin respuesta'));
                }
            }
        }

        // ── recalcular esperado SOLO con las órdenes exitosas ──
        $esperadoOk = [];
        foreach ($exitosos as $i) {
            foreach ($ordenes[$i] as $pid => $c) {
                $esperadoOk[$pid] = ($esperadoOk[$pid] ?? 0) + $c;
            }
        }

        // ── validar descuentos ──
        $this->newLine();
        $filas = [];
        $okTotal = true;
        foreach ($antes as $pid => $stockAntes) {
            $stockDespues = $this->stock($pid);
            $real = $stockAntes - $stockDespues;
            $exp = $esperadoOk[$pid] ?? 0;
            $ok = abs($real - $exp) < 0.0001;
            if (!$ok) $okTotal = false;
            $filas[] = ["#{$pid}", $stockAntes, $stockDespues, $real, $exp, $ok ? '✓' : '✗ LOST UPDATE'];
        }
        $this->table(['Producto', 'Antes', 'Después', 'Descontado real', 'Esperado', 'OK'], $filas);

        if ($okTotal) {
            $this->info('✅ DESCUENTOS OK: el inventario descontó EXACTO lo de las órdenes creadas. Sin lost updates.');
        } else {
            $this->error('❌ DESCUADRE: el descuento real no coincide con lo creado → hay lost updates.');
        }

        // ── listar transferencias para que el usuario marque estado=4 a mano ──
        $this->newLine();
        $this->info('Transferencias creadas (marcá su estado a "revisado"/4 en central para luego recibir):');
        $idsCentral = collect($creadas)->pluck('central')->filter()->values();
        $this->line('  central ids: ' . $idsCentral->implode(', '));
        $this->line('  (atajo: UPDATE arabitocentral.pedidos SET estado=4 WHERE id IN (' . $idsCentral->implode(',') . ');)');
        $this->newLine();
        $this->line('Luego: php artisan prueba:recepcion --force');

        return self::SUCCESS;
    }

    /** Worker: crea UNA orden a partir de --items y reporta OK:central:X:local:Y */
    private function runFire(): int
    {
        session(['id_usuario' => 1, 'tipo_usuario' => 7]);
        $items = $this->parseItems((string) $this->option('items'));
        $r = $this->asArray((new sendCentral)->settransferenciaDici($this->reqOrden($items)));
        if (!empty($r['estado'])) {
            $this->output->writeln('OK:central:' . ($r['transferencia']['id_transferencia_central'] ?? 0) . ':local:' . ($r['transferencia']['id'] ?? 0));
        } else {
            $this->output->writeln('ERR:' . ($r['msj'] ?? 'sin respuesta'));
        }
        return self::SUCCESS;
    }

    /** @param array<int,int> $items prod=>cant */
    private function reqOrden(array $items): Request
    {
        $itemsReq = [];
        foreach ($items as $pid => $cant) {
            $itemsReq[] = ['id_producto_insucursal' => (int) $pid, 'cantidad' => (int) $cant];
        }
        return Request::create('/x', 'POST', [
            'id_destino' => $this->idDestino,
            'observaciones' => 'prueba despacho',
            'actualizando' => false,
            'items' => $itemsReq,
        ]);
    }

    private function parseItems(string $spec): array
    {
        $out = [];
        foreach (array_filter(explode(',', $spec)) as $par) {
            [$p, $c] = array_pad(explode(':', $par), 2, 1);
            $out[(int) $p] = (int) $c;
        }
        return $out;
    }

    private function stock($pid): float
    {
        return (float) inventario::whereKey($pid)->value('cantidad');
    }

    private function asArray($res): array
    {
        if (is_array($res)) return $res;
        if ($res instanceof \Illuminate\Http\JsonResponse) {
            $d = $res->getData(true);
            return is_array($d) ? $d : ['estado' => false, 'msj' => (string) $res->getContent()];
        }
        if (is_string($res)) {
            $d = json_decode($res, true);
            return is_array($d) ? $d : ['estado' => false, 'msj' => $res];
        }
        return ['estado' => false, 'msj' => 'respuesta no interpretable'];
    }
}
