<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Proxy hacia arabitocentral para obtener la lista de bancos disponibles para la sucursal.
 * Consulta central en vivo en cada llamada (sin caché): la lista de bancos y su
 * asignación por caja deben reflejarse de inmediato en la facturación.
 */
class BancosListController extends Controller
{
    public function getBancos(Request $request)
    {
        $data = $this->fetchFromCentral();

        if ($data === null) {
            return response()->json([
                'estado' => false,
                'msj' => 'No se pudo obtener la lista de bancos desde central.',
                'bancos' => [],
                'sucursal_id' => null,
                'empresa_id' => null,
                'cached' => false,
            ], 200);
        }

        return response()->json(array_merge($data, ['cached' => false]));
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
