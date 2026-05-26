<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Proxy hacia arabitocentral para obtener la lista de bancos disponibles para la sucursal.
 * Cachea ~2 horas para evitar martillar central en cada montaje del componente.
 */
class BancosListController extends Controller
{
    private const CACHE_KEY = 'bancos_list_by_sucursal';
    private const CACHE_TTL_SECONDS = 7200; // 2 horas

    public function getBancos(Request $request)
    {
        $force = $request->boolean('refresh', false);
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->fetchFromCentral();
        });

        // Si la última obtención falló (null), no cachear el error: forzar re-fetch en la siguiente llamada.
        if ($data === null) {
            Cache::forget(self::CACHE_KEY);
            return response()->json([
                'estado' => false,
                'msj' => 'No se pudo obtener la lista de bancos desde central.',
                'bancos' => [],
                'sucursal_id' => null,
                'empresa_id' => null,
                'cached' => false,
            ], 200);
        }

        return response()->json(array_merge($data, ['cached' => true]));
    }

    private function fetchFromCentral(): ?array
    {
        try {
            $sendCentral = new \App\Http\Controllers\sendCentral();
            $response = $sendCentral->requestToCentral('get', '/getBancosBySucursal', [], ['timeout' => 30]);

            if (!$response->successful()) {
                Log::warning('getBancosBySucursal: respuesta no exitosa de central', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $payload = $response->json();

            return [
                'estado' => true,
                'sucursal_id' => $payload['sucursal_id'] ?? null,
                'empresa_id' => $payload['empresa_id'] ?? null,
                'bancos' => $payload['bancos'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('getBancosBySucursal: excepción al consultar central', [
                'msg' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
