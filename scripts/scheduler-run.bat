@echo off
REM ============================================================================
REM  Corre el scheduler de Laravel UNA vez. El Programador de tareas de Windows
REM  llama a este .bat CADA MINUTO; Laravel decide que ejecutar segun
REM  app\Console\Kernel.php. La sincronizacion de inventario esta agendada cada
REM  2 horas (comando inventario:sincronizar, withoutOverlapping).
REM
REM  Corre en segundo plano, en un proceso PHP aparte: NO ocupa ni bloquea el
REM  trabajo del usuario en el sistema.
REM ============================================================================

REM Ruta al ejecutable de PHP. Si "php" NO esta en el PATH del sistema, pone la
REM ruta completa, por ejemplo:  set "PHP_BIN=C:\php\php.exe"
set "PHP_BIN=php"

REM Ir a la raiz del proyecto (este .bat vive en la carpeta \scripts).
cd /d "%~dp0.."

"%PHP_BIN%" artisan schedule:run >> "storage\logs\schedule.log" 2>&1
