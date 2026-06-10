<?php
/**
 * Reseteo de OPcache para el SAPI WEB (utilidad de despliegue, NO parte del módulo de sync).
 *
 * Por qué: si el servidor tiene opcache con validate_timestamps=0, el proceso web sigue
 * ejecutando el bytecode viejo de routes/web.php y los controladores; los cambios recién
 * subidos no se ven (rutas 404, métodos viejos). `php artisan` no ayuda (SAPI CLI distinto).
 * Este archivo es nuevo: al abrirlo en el navegador corre en el SAPI web y opcache_reset()
 * limpia TODO el bytecode cacheado de ese proceso.
 *
 * >>> BORRA ESTE ARCHIVO después de usarlo. <<<
 */

header('Content-Type: text/plain; charset=utf-8');

$out = [];
$out[] = '== Reseteo de OPcache (SAPI web: ' . php_sapi_name() . ') ==';
$out[] = 'Fecha: ' . date('Y-m-d H:i:s');
$out[] = '';

if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    if (is_array($st)) {
        $out[] = 'OPcache habilitado: ' . (($st['opcache_enabled'] ?? false) ? 'sí' : 'no');
        $out[] = 'Scripts cacheados ANTES: ' . ($st['opcache_statistics']['num_cached_scripts'] ?? 'n/d');
    } else {
        $out[] = 'OPcache: get_status no devolvió datos (¿deshabilitado para web?).';
    }
} else {
    $out[] = 'OPcache: extensión no disponible en este SAPI.';
}

$reseteado = false;
if (function_exists('opcache_reset')) {
    $reseteado = @opcache_reset();
    $out[] = 'opcache_reset(): ' . ($reseteado ? 'OK' : 'devolvió false (puede estar deshabilitado o restringido)');
} else {
    $out[] = 'opcache_reset(): función no disponible.';
}

clearstatcache(true);
$out[] = 'clearstatcache(): OK';

if (function_exists('opcache_get_status')) {
    $st2 = @opcache_get_status(false);
    if (is_array($st2)) {
        $out[] = 'Scripts cacheados DESPUES: ' . ($st2['opcache_statistics']['num_cached_scripts'] ?? 'n/d');
    }
}

$out[] = '';
$out[] = '------------------------------------------------------------';
$out[] = 'Siguiente paso:';
$out[] = '  1) Recarga el panel /sendAllTest (o /sync) y pulsa Sincronizar.';
$out[] = '  2) BORRA este archivo public/opcache-reset.php.';
$out[] = '';
$out[] = 'Si opcache_reset devolvio false o el problema persiste, reinicia php-fpm/Apache';
$out[] = '(Cloudways: reiniciar PHP-FPM; Laragon: Menu > Reload).';

echo implode("\n", $out) . "\n";
