<?php

namespace App\Support;

/**
 * Helpers para resolver la MONEDA de un banco a partir del valor persistido
 * en el campo `banco` (puede ser "bid:<id>", el codigo string nuevo, o legacy).
 *
 * Source of truth: el catálogo de bancos que vive en central y se sirve vía
 * el proxy local `/bancos-central` (con cache de 2h). Cada banco trae `moneda`
 * con "bs" o "dolar".
 *
 * Antes esto se hardcodeaba como
 *   `in_array($banco, ['ZELLE', 'BINANCE', 'AirTM'])`
 * que se rompió cuando los selects empezaron a guardar `bid:<id>`. Este helper
 * lo arregla y además permite que central agregue más bancos en dólares sin
 * tocar código.
 */
class BancoMoneda
{
    /** Cache en memoria por proceso (el proxy ya cachea 2h en disco). */
    private static ?array $catalogoCache = null;

    /**
     * Devuelve true si el banco es de moneda 'dolar'.
     * Fallback: si no se puede resolver, mira los nombres legacy
     * (ZELLE/BINANCE/AIRTM) para no romper datos viejos.
     */
    public static function esDivisa(?string $valor): bool
    {
        if ($valor === null || $valor === '') return false;

        $b = self::resolverBanco($valor);
        if ($b !== null) {
            return strtolower((string) ($b['moneda'] ?? '')) === 'dolar';
        }

        // Fallback legacy: nombres antiguos que SIEMPRE fueron divisa.
        $u = strtoupper(trim($valor));
        return in_array($u, ['ZELLE', 'BINANCE', 'AIRTM'], true);
    }

    /**
     * Devuelve la moneda ('bs', 'dolar', etc.) o null si no resuelve.
     */
    public static function moneda(?string $valor): ?string
    {
        if ($valor === null || $valor === '') return null;
        $b = self::resolverBanco($valor);
        if ($b !== null) {
            return strtolower((string) ($b['moneda'] ?? '')) ?: null;
        }
        return null;
    }

    /**
     * Resuelve el array del banco en el catálogo central. Acepta:
     *   - "bid:<id>"          → busca por id
     *   - cualquier string    → busca por codigo
     * Devuelve null si no encuentra (catálogo inalcanzable, banco eliminado, etc).
     */
    public static function resolverBanco(?string $valor): ?array
    {
        if ($valor === null || $valor === '') return null;

        $cat = self::catalogo();
        if (str_starts_with($valor, 'bid:')) {
            $idStr = substr($valor, 4);
            if (!ctype_digit($idStr)) return null;
            return $cat['byId'][(int) $idStr] ?? null;
        }
        return $cat['byCodigo'][$valor] ?? null;
    }

    /**
     * Carga el catálogo de bancos vía el proxy local `/bancos-central` (mismo
     * que usa el frontend). El proxy cachea 2h en disco; acá cacheamos también
     * en memoria por proceso para no llamarlo varias veces en el mismo request.
     */
    private static function catalogo(): array
    {
        if (self::$catalogoCache !== null) return self::$catalogoCache;

        $byId = [];
        $byCodigo = [];
        try {
            $controller = new \App\Http\Controllers\BancosListController();
            $resp = $controller->getBancos(new \Illuminate\Http\Request());
            $payload = json_decode($resp->getContent(), true);
            $bancos = $payload['bancos'] ?? [];
            foreach ($bancos as $b) {
                if (!is_array($b) || !isset($b['id'])) continue;
                $byId[(int) $b['id']] = $b;
                if (!empty($b['codigo'])) {
                    $byCodigo[(string) $b['codigo']] = $b;
                }
            }
        } catch (\Throwable $e) {
            // Catálogo inalcanzable: el caller cae al fallback legacy.
            \Log::warning('BancoMoneda::catalogo() falló: ' . $e->getMessage());
        }

        self::$catalogoCache = ['byId' => $byId, 'byCodigo' => $byCodigo];
        return self::$catalogoCache;
    }

    /** Limpia el cache en memoria (útil para tests). */
    public static function clearCache(): void
    {
        self::$catalogoCache = null;
    }
}
