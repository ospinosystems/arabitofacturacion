<?php

namespace App\Console;

use App\Http\Controllers\sendCentral;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        $schedule->call(function () {
            (new sendCentral)->sendComovamos();

        })->everyTwoHours();

        // $schedule->command('database:backup')->daily();


        $schedule->command('database:backup')->twiceDaily(8, 18);

        // Sincronización automática de inventario contra central, cada 2 horas.
        // withoutOverlapping(110): si una corrida sigue viva (o crasheó), no arranca otra encima; el
        // lock expira a los 110 min para no quedar trabado antes de la siguiente ventana de 2 h.
        // runInBackground: no bloquea otras tareas agendadas del mismo minuto.
        $schedule->command('inventario:sincronizar')
            ->everyTwoHours()
            ->withoutOverlapping(110)
            ->runInBackground();

        // Reclasificación ABC semanal.
        //
        // Domingo de madrugada porque recorre toda la demanda del periodo y reescribe
        // las cuatro clasificaciones; no conviene que compita con la operación.
        // Semanal es la cadencia adecuada: la rotación de un producto no cambia de un
        // día para otro, y recalcular a diario haría que las ubicaciones sugeridas
        // bailaran sin motivo real.
        $schedule->command('wms:abc-recalcular')
            ->weeklyOn(0, '03:30')
            ->withoutOverlapping(60)
            ->runInBackground();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
