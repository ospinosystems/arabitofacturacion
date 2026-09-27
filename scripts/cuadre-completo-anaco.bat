@echo off
REM ============================================================================
REM  Cuadre completo de ANACO en esta PC (XAMPP). Reanudable: si se apaga la PC,
REM  vuelva a ejecutar este .bat y continua donde quedo.
REM
REM  Uso:  cuadre-completo-anaco.bat "C:\Users\alvar\Downloads\ANACO FACTURAS" <storeId Titanio>
REM        cuadre-completo-anaco.bat "C:\Users\alvar\Downloads\ANACO FACTURAS" --sin-titanio
REM
REM  OJO: el paso "restaurar" BORRA la base de datos configurada en .env (DB_DATABASE)
REM  e importa el respaldo de Anaco. Use una BD local dedicada para esto.
REM ============================================================================
setlocal
set "PHP_BIN=php"
cd /d "%~dp0.."

if "%~1"=="" (
  echo Falta la carpeta con los ZIP. Ejemplo:
  echo   %~nx0 "C:\Users\alvar\Downloads\ANACO FACTURAS" 25
  exit /b 1
)
set "CARPETA=%~1"
set "STORE=%~2"
set "EXTRA="
if "%STORE%"=="" (
  echo Falta el storeId de Anaco en Titanio POS. Si quiere omitir la importacion use --sin-titanio.
  exit /b 1
)
if /I "%STORE%"=="--sin-titanio" (
  set "EXTRA=--sin-titanio"
) else (
  set "EXTRA=--store-id=%STORE%"
)

if not exist "storage\logs" mkdir "storage\logs"
set "LOG=storage\logs\cuadre_completo_anaco.log"
echo Log: %LOG%
echo Corriendo... (esta ventana puede cerrarse solo cuando termine; si se apaga la PC, vuelva a ejecutar este .bat)
"%PHP_BIN%" -d memory_limit=-1 -d max_execution_time=0 artisan cuadre:completo "%CARPETA%" --sucursal=anaco %EXTRA% --si --no-ansi %3 %4 %5 %6 >> "%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
if "%RC%"=="0" (
  echo Terminado OK. Resultado en storage\app\cuadre-completo\anaco\resultado_anaco.csv
) else (
  echo Termino con error %RC%. Revise %LOG% y vuelva a ejecutar para reanudar.
)
endlocal & exit /b %RC%
