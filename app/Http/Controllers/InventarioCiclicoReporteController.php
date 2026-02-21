<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class InventarioCiclicoReporteController extends Controller
{
    /**
     * Mostrar reporte de planilla en Blade (cerrada o pendiente).
     * Con botones para imprimir y guardar como PDF.
     */
    public function reporte(Request $request, $id)
    {
        $sendCentral = new \App\Http\Controllers\sendCentral();
        try {
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/inventario-ciclico/planillas/{$id}");
            if (!$response->successful()) {
                abort(500, 'No se pudo cargar la planilla: ' . $response->body());
            }
            $json = $response->json();
            if (!isset($json['success']) || !$json['success'] || !isset($json['data'])) {
                abort(404, $json['message'] ?? 'Planilla no encontrada');
            }
            $planilla = $json['data'];
            $estatus = $planilla['estatus'] ?? 'Abierta';
            $tareas = $planilla['tareas'] ?? [];

            if (strtoupper($estatus) === 'CERRADA') {
                $estadisticas = $this->calcularEstadisticas($tareas);
                return view('reportes.inventario-ciclico-cerrada', [
                    'planilla' => $planilla,
                    'tareas' => $tareas,
                    'estadisticas' => $estadisticas,
                ]);
            }

            $pendientesLocales = $this->resolvePendientesLocales($request, $id);

            return view('reportes.inventario-ciclico-pendiente', [
                'planilla' => $planilla,
                'tareas' => $tareas,
                'pendientesLocales' => $pendientesLocales,
            ]);
        } catch (\Exception $e) {
            abort(500, 'Error de conexión: ' . $e->getMessage());
        }
    }

    /**
     * Guardar pendientes en caché en disco (file) y devolver URL corta para el reporte.
     * La nueva pestaña hace GET a la misma app; se lee la clave desde caché (persistente).
     * POST body: { "pendientes": [...] }
     */
    public function reportePendientesStore(Request $request, $id)
    {
        $request->validate(['pendientes' => 'required|array']);
        $key = 'ic_' . uniqid() . '_' . bin2hex(random_bytes(4));
        $cacheKey = "ic_report_pendientes.{$key}";
        $payload = [
            'planilla_id' => (int) $id,
            'pendientes' => $request->input('pendientes'),
        ];
        Cache::store('file')->put($cacheKey, $payload, now()->addMinutes(15));
        session()->put($cacheKey, $payload);
        $path = "/inventario-ciclico/planillas/{$id}/reporte?pendientes_key=" . urlencode($key);
        return response()->json([
            'redirect' => url($path),
            'redirect_path' => $path,
            'pendientes_key' => $key,
        ]);
    }

    /**
     * Obtener pendientes desde caché file (clave en URL), sesión o query (JSON en URL).
     */
    private function resolvePendientesLocales(Request $request, $id): array
    {
        $pendientesKey = $request->query('pendientes_key');
        if ($pendientesKey !== null && $pendientesKey !== '') {
            $cacheKey = "ic_report_pendientes.{$pendientesKey}";
            $store = Cache::store('file');
            $stored = $store->get($cacheKey);
            if (is_array($stored) && isset($stored['planilla_id']) && (int) $stored['planilla_id'] === (int) $id && isset($stored['pendientes'])) {
                $store->forget($cacheKey);
                return is_array($stored['pendientes']) ? $stored['pendientes'] : [];
            }
            $sessionKey = $cacheKey;
            $stored = session()->get($sessionKey);
            if (is_array($stored) && isset($stored['planilla_id']) && (int) $stored['planilla_id'] === (int) $id && isset($stored['pendientes'])) {
                session()->forget($sessionKey);
                return is_array($stored['pendientes']) ? $stored['pendientes'] : [];
            }
        }
        $pendientesParam = $request->query('pendientes');
        if ($pendientesParam !== null && $pendientesParam !== '') {
            $decoded = json_decode($pendientesParam, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function calcularEstadisticas(array $tareas): array
    {
        $totalItems = count($tareas);
        $itemsConDiferencia = 0;
        $totalCantidadSistema = 0;
        $totalCantidadFisica = 0;
        $totalDiferenciaCantidad = 0;
        $totalValorSistema = 0;
        $totalValorFisico = 0;
        $totalDiferenciaValor = 0;
        $totalDiferenciaValorBase = 0;
        $tareasAprobadas = 0;
        $tareasEjecutadas = 0;

        foreach ($tareas as $t) {
            $cantSis = (float) ($t['cantidad_sistema'] ?? 0);
            $cantFis = (float) ($t['cantidad_fisica'] ?? 0);
            $dif = $cantFis - $cantSis;
            $precio = (float) (isset($t['producto']['precio']) ? $t['producto']['precio'] : 0);
            $precioBase = (float) (isset($t['producto']['precio_base']) ? $t['producto']['precio_base'] : 0);

            if ($dif != 0) {
                $itemsConDiferencia++;
            }
            $totalCantidadSistema += $cantSis;
            $totalCantidadFisica += $cantFis;
            $totalDiferenciaCantidad += $dif;
            $totalValorSistema += $cantSis * $precio;
            $totalValorFisico += $cantFis * $precio;
            $totalDiferenciaValor += $dif * $precio;
            $totalDiferenciaValorBase += (float) ($t['diferencia_valor_base'] ?? ($dif * $precioBase));
            if (isset($t['permiso']) && (int) $t['permiso'] === 1) {
                $tareasAprobadas++;
            }
            if (isset($t['estado']) && (int) $t['estado'] === 1) {
                $tareasEjecutadas++;
            }
        }

        return [
            'total_items' => $totalItems,
            'items_con_diferencia' => $itemsConDiferencia,
            'items_sin_diferencia' => $totalItems - $itemsConDiferencia,
            'total_cantidad_sistema' => $totalCantidadSistema,
            'total_cantidad_fisica' => $totalCantidadFisica,
            'total_diferencia_cantidad' => $totalDiferenciaCantidad,
            'total_valor_sistema' => $totalValorSistema,
            'total_valor_fisico' => $totalValorFisico,
            'total_diferencia_valor' => $totalDiferenciaValor,
            'total_diferencia_valor_base' => $totalDiferenciaValorBase,
            'tareas_aprobadas' => $tareasAprobadas,
            'tareas_ejecutadas' => $tareasEjecutadas,
            'porcentaje_diferencia' => $totalItems > 0 ? round(($itemsConDiferencia / $totalItems) * 100, 2) : 0,
        ];
    }
}
