@echo off
REM ============================================================================
REM  Registra en el Programador de tareas de Windows una tarea que corre el
REM  scheduler de Laravel CADA MINUTO (scheduler-run.bat). Con eso, la
REM  sincronizacion de inventario agendada en Kernel.php (cada 2 h) se dispara
REM  sola, en segundo plano, sin ocupar el trabajo del usuario.
REM
REM  >>> CORRER ESTE .BAT COMO ADMINISTRADOR <<<
REM  (click derecho -> "Ejecutar como administrador")
REM ============================================================================
setlocal
set "TAREA=Sinapsis - Laravel Scheduler"
set "RUNNER=%~dp0scheduler-run.bat"

echo Registrando la tarea "%TAREA%" ...
echo   Ejecuta: %RUNNER%
echo   Frecuencia: cada 1 minuto (Laravel decide que corre; inventario cada 2 h)
echo.

REM /RU SYSTEM: corre sin usuario logueado (segundo plano). /RL HIGHEST: privilegios altos.
REM /SC MINUTE /MO 1: cada minuto. /F: reemplaza si ya existe.
schtasks /Create /TN "%TAREA%" /TR "cmd /c \"%RUNNER%\"" /SC MINUTE /MO 1 /RL HIGHEST /RU SYSTEM /F

if %ERRORLEVEL%==0 (
    echo.
    echo [OK] Tarea creada. Verificar con:
    echo      schtasks /Query /TN "%TAREA%" /V /FO LIST
    echo.
    echo Para sincronizar YA una vez a mano:  php artisan inventario:sincronizar
) else (
    echo.
    echo [ERROR] No se pudo crear la tarea. Asegurate de correr este .bat
    echo         como Administrador. Si "php" no esta en el PATH del sistema,
    echo         edita scheduler-run.bat y pone la ruta completa en PHP_BIN.
)
echo.
endlocal
pause
