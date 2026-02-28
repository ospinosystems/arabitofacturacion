<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;

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
            $response = $sendCentral->requestToCentral('GET', "/api/inventario-ciclico/planillas/{$id}", [], ['timeout' => 30]);
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
     * Devolver el HTML del reporte para mostrar en el mismo componente (POST con pendientes en body).
     * POST body: { "pendientes": [...] } (opcional)
     */
    public function reporteHtml(Request $request, $id)
    {
        $sendCentral = new \App\Http\Controllers\sendCentral();
        try {
            $response = $sendCentral->requestToCentral('GET', "/api/inventario-ciclico/planillas/{$id}", [], ['timeout' => 30]);
            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'No se pudo cargar la planilla'], 500);
            }
            $json = $response->json();
            if (!isset($json['success']) || !$json['success'] || !isset($json['data'])) {
                return response()->json(['success' => false, 'message' => $json['message'] ?? 'Planilla no encontrada'], 404);
            }
            $planilla = $json['data'];
            $estatus = $planilla['estatus'] ?? 'Abierta';
            $tareas = $planilla['tareas'] ?? [];

            if (strtoupper($estatus) === 'CERRADA') {
                $estadisticas = $this->calcularEstadisticas($tareas);
                $html = view('reportes.inventario-ciclico-cerrada', [
                    'planilla' => $planilla,
                    'tareas' => $tareas,
                    'estadisticas' => $estadisticas,
                ])->render();
            } else {
                $pendientesLocales = $request->input('pendientes', []);
                if (!is_array($pendientesLocales)) {
                    $pendientesLocales = [];
                }
                $html = view('reportes.inventario-ciclico-pendiente', [
                    'planilla' => $planilla,
                    'tareas' => $tareas,
                    'pendientesLocales' => $pendientesLocales,
                ])->render();
            }

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        return [];
        }
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
