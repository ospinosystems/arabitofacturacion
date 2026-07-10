<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use App\Models\inventario;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Siembra la tabla local `inventarios` con el inventario de ESTA sucursal traído de
 * Arábito Central, SOLO cuando la tabla está en blanco (instalación nueva o recuperación
 * tras pérdida de datos).
 *
 * Central identifica la sucursal por su `codigo` (codigo_origen, que sendCentral inyecta
 * en cada request) y devuelve las filas de inventario_sucursals de ese id_sucursal. Cada
 * producto trae `id_local` = idinsucursal en central = el id que el producto tenía en la
 * tabla `inventarios` local; se reusa como PK para conservar la integridad con
 * items_pedidos.id_producto, vinculaciones, etc.
 */
class SeedInventarioDesdeCentral extends Command
{
    protected $signature = 'central:seed-inventario
        {--force : Sembrar aunque la tabla inventarios ya tenga productos (NO recomendado: pueden chocar ids/códigos)}
        {--probar-conexion : Solo comprobar URL, codigo, API key y estado local/remoto (no siembra)}
        {--dry-run : Traer el inventario y mostrar cuántos productos llegarían, sin insertar}';

    protected $description = 'Trae de Central el inventario de esta sucursal y siembra la tabla local `inventarios` cuando está en blanco';

    public function handle(): int
    {
        $send = new sendCentral();

        if ($this->option('probar-conexion')) {
            return $this->runProbarConexion($send);
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        // Guarda principal: solo sembrar si la tabla está en blanco.
        if (inventario::exists() && !$force) {
            $this->info('El inventario local ya tiene productos (' . inventario::count() . '). Nada que hacer.');
            $this->line('Usa --force solo si sabes lo que haces (podrían chocar ids/códigos existentes).');

            return self::SUCCESS;
        }

        $apiKey = $send->getCentralApiKey();
        if ($apiKey === null || $apiKey === '') {
            $this->error('No hay API key de Central (storage/app/central_api_key.txt o CENTRAL_API_KEY en .env). Central responde 401.');

            return self::FAILURE;
        }

        $urlBase = rtrim($send->path(), '/');
        $this->info('Sucursal (codigo_origen): ' . $send->getOrigen());
        $this->info("Origen: {$urlBase}/getInventarioInicialSucursal");

        try {
            $response = $send->requestToCentral('post', '/getInventarioInicialSucursal', [], ['timeout' => 180]);
        } catch (ConnectionException $e) {
            $this->error('Error de red: ' . $e->getMessage());
            Log::error('central:seed-inventario ConnectionException', ['e' => $e->getMessage()]);

            return self::FAILURE;
        }

        if (!$response->ok()) {
            $this->error('HTTP ' . $response->status() . ' — ' . substr($response->body(), 0, 500));

            return self::FAILURE;
        }

        $json = $response->json();
        if (!is_array($json) || !($json['estado'] ?? false)) {
            $this->error('Central respondió estado=false o formato inesperado: ' . substr($response->body(), 0, 500));

            return self::FAILURE;
        }

        $productos = $json['productos'] ?? [];
        $total = count($productos);
        $this->info("Central devolvió {$total} productos para la sucursal " . ($json['codigo'] ?? '?') . ' (id ' . ($json['sucursal_id'] ?? '?') . ').');

        if ($total === 0) {
            $this->warn('No hay inventario en central para esta sucursal; nada que sembrar.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $muestra = array_slice($productos, 0, 5);
            $this->table(
                ['id_local', 'codigo_barras', 'descripcion', 'precio', 'cantidad'],
                array_map(fn ($p) => [
                    $p['id_local'] ?? '?',
                    $p['codigo_barras'] ?? '',
                    mb_strimwidth((string) ($p['descripcion'] ?? ''), 0, 40, '…'),
                    $p['precio'] ?? '',
                    $p['cantidad'] ?? '',
                ], $muestra)
            );
            $this->warn('Dry-run: NO se insertó nada. Quita --dry-run para sembrar.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $now = now();
        $intentados = 0;
        $omitidos = 0;
        $lote = 0;

        try {
            foreach (array_chunk($productos, 500) as $chunk) {
                $lote++;
                $rows = [];
                foreach ($chunk as $p) {
                    // codigo_barras es NOT NULL y único; sin él la fila no se puede insertar.
                    $codigo = $p['codigo_barras'] ?? null;
                    if (!isset($p['id_local']) || $codigo === null || $codigo === '') {
                        $omitidos++;
                        continue;
                    }
                    $rows[] = [
                        'id'                  => (int) $p['id_local'],
                        'codigo_barras'       => $codigo,
                        'codigo_proveedor'    => $p['codigo_proveedor'] ?? null,
                        'id_proveedor'        => $p['id_proveedor'] ?? null,
                        'id_categoria'        => $p['id_categoria'] ?? null,
                        'id_marca'            => $p['id_marca'] ?? null,
                        'unidad'              => $p['unidad'] ?? 'UND',
                        'id_deposito'         => $p['id_deposito'] ?? null,
                        'descripcion'         => $p['descripcion'] ?? '',
                        'iva'                 => $p['iva'] ?? 0,
                        'porcentaje_ganancia' => $p['porcentaje_ganancia'] ?? 0,
                        'precio_base'         => $p['precio_base'] ?? 0,
                        'precio'              => $p['precio'] ?? 0,
                        'precio1'             => $p['precio1'] ?? null,
                        'precio2'             => $p['precio2'] ?? null,
                        'precio3'             => $p['precio3'] ?? null,
                        'bulto'               => $p['bulto'] ?? null,
                        'stockmin'            => $p['stockmin'] ?? null,
                        'stockmax'            => $p['stockmax'] ?? null,
                        'cantidad'            => $p['cantidad'] ?? 0,
                        'id_vinculacion'      => $p['id_vinculacion'] ?? null,
                        'push'                => $p['push'] ?? 0,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ];
                }
                if ($rows === []) {
                    continue;
                }
                // insertOrIgnore: si un codigo_barras/id_vinculacion viniera duplicado desde central,
                // se salta esa fila en lugar de abortar todo el sembrado.
                DB::table('inventarios')->insertOrIgnore($rows);
                $intentados += count($rows);
                $this->line("  Lote {$lote}: " . count($rows) . ' productos.');
            }
        } catch (Throwable $e) {
            $this->error('Error insertando: ' . $e->getMessage());
            Log::error('central:seed-inventario insert', ['e' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return self::FAILURE;
        }

        $final = inventario::count();
        $this->info("Listo. Filas en `inventarios` ahora: {$final} (intentados {$intentados}, omitidos por datos incompletos {$omitidos}, recibidos {$total}).");
        if ($final < $intentados) {
            $this->warn('Se insertaron menos filas de las intentadas: hubo colisiones de codigo_barras/id_vinculacion que se ignoraron.');
        }

        return self::SUCCESS;
    }

    private function runProbarConexion(sendCentral $send): int
    {
        $urlBase = rtrim($send->path(), '/');
        $codigo = $send->getOrigen();
        $key = $send->getCentralApiKey();

        $this->info("URL Central: {$urlBase}");
        $this->info("codigo_origen: {$codigo}");
        $this->info('API key: ' . ($key !== null && $key !== '' ? '(configurada, longitud ' . strlen($key) . ')' : 'NO CONFIGURADA — obtendrás 401'));
        $this->info('inventario local: ' . inventario::count() . ' filas '
            . (inventario::exists() ? '(NO está en blanco — el comando no sembrará sin --force)' : '(en blanco — listo para sembrar)'));

        try {
            $response = $send->requestToCentral('post', '/getInventarioInicialSucursal', [], ['timeout' => 60]);
        } catch (ConnectionException $e) {
            $this->error('No hubo conexión: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('HTTP ' . $response->status());
        if ($response->ok()) {
            $json = $response->json();
            if (is_array($json) && ($json['estado'] ?? false)) {
                $this->info('Conexión OK. Central reporta ' . ($json['total'] ?? '?') . ' productos para esta sucursal.');

                return self::SUCCESS;
            }
            $this->warn('Respuesta: ' . substr($response->body(), 0, 500));
        }

        $this->warn('Si ves 401, configura la misma API key que en Central. Si ves 404, aún no está desplegada la ruta en central.');

        return self::FAILURE;
    }
}
