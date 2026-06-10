<?php
/**
 * Reseteo de OPcache para el SAPI WEB de la SUCURSAL (utilidad de despliegue puntual).
 * Necesario para que rutas/controladores nuevos tomen efecto si el servidor tiene opcache
 * con validate_timestamps=0. >>> BORRAR DESPUÉS DE USAR. <<<
 */
header('Content-Type: text/plain; charset=utf-8');
$out = ['== Reseteo OPcache sucursal (SAPI: ' . php_sapi_name() . ') ==', 'Fecha: ' . date('Y-m-d H:i:s'), ''];
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    $out[] = 'Scripts cacheados ANTES: ' . (is_array($st) ? ($st['opcache_statistics']['num_cached_scripts'] ?? 'n/d') : 'n/d');
}
$out[] = 'opcache_reset(): ' . (function_exists('opcache_reset') ? (@opcache_reset() ? 'OK' : 'false') : 'no disponible');
clearstatcache(true);
$out[] = '';
$out[] = '1) Recarga el panel /sendAllTest y prueba de nuevo.';
$out[] = '2) BORRA public/opcache-reset.php.';
$out[] = 'Si persiste, reinicia PHP-FPM/Apache (Laragon: Menu > Reload).';
echo implode("\n", $out) . "\n";
