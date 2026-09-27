# Despliega y lanza el cuadre completo en el servidor Cloudways DESDE LA PC (Windows 10/11, PowerShell).
#
#   1. Abrir PowerShell.
#   2. Ejecutar:  powershell -ExecutionPolicy Bypass -File desplegar-desde-pc.ps1
#      (o desde el repo: powershell -ExecutionPolicy Bypass -File scripts\cuadre-servidor\desplegar-desde-pc.ps1)
#   3. Responder las preguntas (IP, usuario SFTP, carpeta con los ZIP, datos MySQL de la app, storeId).
#      La contraseña SFTP la pide el propio ssh/scp cada vez (2 o 3 veces).
#
# Qué hace: sube los ZIP por SFTP a la carpeta datos/ del servidor, clona (o actualiza) el repositorio,
# ejecuta scripts/cuadre-servidor/cloudways.sh instalar (composer + .env) y cloudways.sh correr (arranca el
# proceso en segundo plano). Luego se puede seguir con:  ssh usuario@IP "bash ~/private_html/cuadre/scripts/cuadre-servidor/cloudways.sh estado"
param(
    [string]$Servidor = "",
    [string]$Usuario = "",
    [string]$Carpeta = "",
    [string]$Rama = "master"
)
$ErrorActionPreference = "Stop"

foreach ($cmd in @("ssh", "scp")) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
        Write-Host "No se encontró '$cmd'. Active 'Cliente OpenSSH' en Configuración > Aplicaciones > Características opcionales." -ForegroundColor Red
        exit 1
    }
}

if (-not $Servidor) { $Servidor = Read-Host "IP del servidor (Public IP en Cloudways)" }
if (-not $Usuario)  { $Usuario  = Read-Host "Usuario SFTP/SSH de la aplicación" }
if (-not $Carpeta)  { $Carpeta  = Read-Host "Carpeta local con los ZIP (ej. C:\Users\alvar\Downloads\ANACO FACTURAS)" }
$Carpeta = $Carpeta.Trim('"').TrimEnd('\')
if (-not (Test-Path $Carpeta)) { Write-Host "La carpeta no existe: $Carpeta" -ForegroundColor Red; exit 1 }
$zips = Get-ChildItem -Path $Carpeta -Filter *.zip -File
if ($zips.Count -eq 0) { Write-Host "No hay archivos .zip en $Carpeta" -ForegroundColor Red; exit 1 }

Write-Host ""
Write-Host "Datos MySQL de la aplicación (Cloudways > Application > Access Details > MySQL Access)." -ForegroundColor Cyan
Write-Host "Esa base de datos se BORRA y se restaura con el respaldo de la sucursal." -ForegroundColor Yellow
$DbName = Read-Host "  DB name"
$DbUser = Read-Host "  DB user"
$DbPassSecure = Read-Host "  DB password" -AsSecureString
$DbPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($DbPassSecure))
$Store  = Read-Host "  storeId de la sucursal en Titanio POS (vacío = omitir Titanio por ahora)"
$Sucursal = Read-Host "  Código de la sucursal [anaco]"
if (-not $Sucursal) { $Sucursal = "anaco" }

$destino = "$Usuario@$Servidor"
$sshOpts = @("-o", "StrictHostKeyChecking=accept-new", "-o", "ServerAliveInterval=30")
# El paso 1 devuelve dos líneas: la ruta absoluta (para ssh) y la relativa al home (para scp/SFTP, que en
# Cloudways está enjaulado en la carpeta de la aplicación y no ve las rutas absolutas).
$remoto = 'D=$HOME/private_html/cuadre; [ -d $HOME/private_html ] || D=$HOME/cuadre; mkdir -p $D/datos; echo $D; echo ${D#$HOME/}'

Write-Host ""
Write-Host "1/3 Preparando carpeta en el servidor (se pedirá la contraseña SFTP)..." -ForegroundColor Cyan
$salida = @(& ssh @sshOpts $destino $remoto | Where-Object { $_ -and $_.Trim() } | ForEach-Object { $_.Trim() })
if ($salida.Count -lt 2) { Write-Host "No se pudo crear la carpeta remota." -ForegroundColor Red; exit 1 }
$dirRemoto = $salida[-2]
$dirRel = $salida[-1]
Write-Host "    Carpeta remota: $dirRemoto  (SFTP: $dirRel)"

Write-Host "2/3 Subiendo $($zips.Count) ZIP a $dirRel/datos (se pedirá la contraseña)..." -ForegroundColor Cyan
$archivos = $zips | ForEach-Object { $_.FullName }
& scp @sshOpts $archivos "${destino}:$dirRel/datos/"
if ($LASTEXITCODE -ne 0) {
    Write-Host "    Reintentando con el protocolo scp clásico (-O)..." -ForegroundColor Yellow
    & scp -O @sshOpts $archivos "${destino}:$dirRemoto/datos/"
    if ($LASTEXITCODE -ne 0) { Write-Host "Falló la subida por SFTP." -ForegroundColor Red; exit 1 }
}

Write-Host "3/3 Instalando y lanzando el proceso en el servidor (se pedirá la contraseña)..." -ForegroundColor Cyan
$escDbPass = $DbPass -replace "'", "'\''"
$instalar = @(
    "set -e",
    "D='$dirRemoto'",
    "if [ -d `$D/.git ]; then cd `$D && git fetch -q origin $Rama && git checkout -q $Rama && git pull -q origin $Rama; else rm -rf `$D.tmp && git clone -q -b $Rama https://github.com/ospinosystems/arabitofacturacion.git `$D.tmp && cp -a `$D.tmp/. `$D/ && rm -rf `$D.tmp; fi",
    "cd `$D",
    "export CW_DB_NAME='$DbName' CW_DB_USER='$DbUser' CW_DB_PASS='$escDbPass' CW_STORE_ID='$Store' CUADRE_SUCURSAL='$Sucursal'",
    "bash scripts/cuadre-servidor/cloudways.sh instalar",
    "bash scripts/cuadre-servidor/cloudways.sh correr"
) -join "; "
# El script viaja en base64 para evitar problemas de comillas entre PowerShell, ssh.exe y bash.
$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($instalar))
& ssh -t @sshOpts $destino "echo $b64 | base64 -d | bash -l"
if ($LASTEXITCODE -ne 0) { Write-Host "Algo falló en el servidor; revise el mensaje anterior." -ForegroundColor Red; exit 1 }

Write-Host ""
Write-Host "Listo. El proceso corre en el servidor en segundo plano. Para ver el avance:" -ForegroundColor Green
Write-Host "  ssh $destino `"bash $dirRemoto/scripts/cuadre-servidor/cloudways.sh estado`""
Write-Host "  ssh $destino `"bash $dirRemoto/scripts/cuadre-servidor/cloudways.sh log`""
