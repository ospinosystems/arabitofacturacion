<?php

namespace App\Support;

/**
 * Devuelve la versión "actual" del código de esta sucursal, derivada del SHA
 * del commit HEAD del repo. Se envía a central en cada request para que
 * central sepa qué tan actualizada está cada tienda.
 *
 * Implementación: lee `.git/HEAD` y resuelve la referencia hasta obtener el
 * SHA. NO requiere binario `git` instalado en el server — solo lectura de
 * archivos planos. Si por alguna razón no se puede leer (deploy sin .git,
 * permisos, etc.), devuelve "unknown" sin romper la petición.
 *
 * Cache en memoria por proceso PHP: el SHA del HEAD no cambia mientras corre
 * el mismo worker. Tras un `git pull` el nuevo worker leerá el nuevo SHA.
 */
class AppVersion
{
    private static ?string $cache = null;

    public static function current(): string
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $sha = self::leerGitSha();
        self::$cache = $sha ?: 'unknown';
        return self::$cache;
    }

    /**
     * Forma corta del SHA (7 chars), útil para logs y UIs.
     */
    public static function short(): string
    {
        $v = self::current();
        return $v === 'unknown' ? $v : substr($v, 0, 7);
    }

    /**
     * Limpia el cache en runtime (útil en tests).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    private static function leerGitSha(): ?string
    {
        $basePath = function_exists('base_path') ? base_path() : dirname(__DIR__, 2);
        $gitDir = $basePath . DIRECTORY_SEPARATOR . '.git';
        $headPath = $gitDir . DIRECTORY_SEPARATOR . 'HEAD';

        if (!is_readable($headPath)) {
            return null;
        }

        $head = trim((string) @file_get_contents($headPath));
        if ($head === '') return null;

        // Caso 1: HEAD es "ref: refs/heads/xxx" → leemos el SHA en ese ref.
        if (str_starts_with($head, 'ref: ')) {
            $ref = trim(substr($head, 5));
            $refPath = $gitDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref);
            if (is_readable($refPath)) {
                $sha = trim((string) @file_get_contents($refPath));
                if ($sha !== '') return $sha;
            }
            // Fallback: packed-refs (cuando el ref no tiene archivo loose).
            $packed = $gitDir . DIRECTORY_SEPARATOR . 'packed-refs';
            if (is_readable($packed)) {
                $contenido = (string) @file_get_contents($packed);
                foreach (preg_split('/\r?\n/', $contenido) as $linea) {
                    $linea = trim($linea);
                    if ($linea === '' || str_starts_with($linea, '#') || str_starts_with($linea, '^')) continue;
                    [$shaPacked, $refPacked] = preg_split('/\s+/', $linea, 2) + [null, null];
                    if ($refPacked === $ref && $shaPacked) {
                        return $shaPacked;
                    }
                }
            }
            return null;
        }

        // Caso 2: detached HEAD → el archivo HEAD ES el SHA directo.
        if (preg_match('/^[0-9a-f]{40}$/i', $head)) {
            return $head;
        }

        return null;
    }
}
