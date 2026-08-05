<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza el inventario local contra Arábito Central (MISMA lógica que el botón "Sincronizar"
 * del menú → sendCentral::getAllInventarioFromCentral). Pensado para correr por TAREA PROGRAMADA
 * (cada 2 h) sin sesión de usuario ni request HTTP: getOrigen() lee el código de la sucursal de la
 * tabla `sucursals` y la API key de storage/app/central_api_key.txt (o env CENTRAL_API_KEY).
 *
 * Corre en un proceso PHP aparte del servidor web, así que NO bloquea el trabajo del usuario. El
 * agendado usa withoutOverlapping para que dos corridas no se pisen.
 *
 * Uso manual: php artisan inventario:sincronizar
 */
class SincronizarInventarioCommand extends Command
{
    protected $signature = 'inventario:sincronizar';

    protected $description = 'Sincroniza el inventario local contra central (para tarea programada, sin sesión).';

    public function handle(): int
    {
        @set_time_limit(0); // la sync puede tardar varios minutos
        $inicio = microtime(true);
        $this->info('[inventario:sincronizar] Iniciando sincronización…');
        Log::info('[inventario:sincronizar] Iniciando sincronización de inventario (tarea programada).');

        try {
            // Devuelve una View (pensada para HTTP); desde CLI se ignora el valor de retorno.
            (new sendCentral())->getAllInventarioFromCentral();
            $seg = round(microtime(true) - $inicio, 1);
            $this->info("[inventario:sincronizar] Sincronización OK en {$seg}s.");
            Log::info("[inventario:sincronizar] Sincronización OK en {$seg}s.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $msg = $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine();
            $this->error('[inventario:sincronizar] Falló: ' . $msg);
            Log::error('[inventario:sincronizar] Falló: ' . $msg);
            return self::FAILURE;
        }
    }
}
