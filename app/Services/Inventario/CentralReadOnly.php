<?php

namespace App\Services\Inventario;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Conexión de SOLO LECTURA a la base de datos de central (SET SESSION TRANSACTION READ ONLY).
 * Credenciales: el .env de central indicado, o DB_CENTRAL_* en el .env local.
 */
class CentralReadOnly
{
    public static function conectar(?string $rutaEnv): Connection
    {
        $env = [];
        if ($rutaEnv) {
            if (!is_readable($rutaEnv)) {
                throw new RuntimeException("No se puede leer {$rutaEnv}");
            }
            foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $l, $m)) {
                    $env[$m[1]] = trim($m[2], " \t\"'");
                }
            }
        } else {
            $env = ['DB_HOST' => env('DB_CENTRAL_HOST', '127.0.0.1'), 'DB_PORT' => env('DB_CENTRAL_PORT', 3306), 'DB_DATABASE' => env('DB_CENTRAL_DATABASE'),
                'DB_USERNAME' => env('DB_CENTRAL_USERNAME'), 'DB_PASSWORD' => env('DB_CENTRAL_PASSWORD')];
        }
        if (empty($env['DB_DATABASE']) || empty($env['DB_USERNAME'])) {
            throw new RuntimeException('faltan credenciales (DB_DATABASE/DB_USERNAME); use --central-env=/ruta/al/.env de central o DB_CENTRAL_* en el .env local.');
        }
        config(['database.connections.central' => [
            'driver' => 'mysql', 'host' => $env['DB_HOST'] ?: '127.0.0.1', 'port' => $env['DB_PORT'] ?: 3306,
            'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => false, 'engine' => null,
            'options' => [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY'],
        ]]);
        DB::purge('central');
        $c = DB::connection('central');
        $c->select('select 1');
        return $c;
    }
}
