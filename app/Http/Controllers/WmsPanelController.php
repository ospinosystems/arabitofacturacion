<?php

namespace App\Http\Controllers;

use App\Models\ConteoCiclico;
use App\Models\ProductoAbc;
use App\Models\TmsRuta;
use App\Models\TmsVehiculo;
use App\Models\Warehouse;
use App\Models\inventario;
use App\Services\Wms\AbcClassificationService;
use App\Services\Wms\ConteoCiclicoService;
use App\Services\Wms\SlottingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vistas de los módulos WMS/TMS. La lógica vive en los servicios; aquí sólo se
 * arma lo que necesita cada pantalla.
 */
class WmsPanelController extends Controller
{
    /**
     * Panel de clasificación ABC y salud de los datos físicos.
     */
    public function abc(Request $request)
    {
        $criterio = $request->criterio ?? config('wms.abc.criterio_slotting');
        $servicio = new AbcClassificationService();

        $distribucion = $servicio->distribucion($criterio);

        $top = ProductoAbc::with('inventario')
            ->where('criterio', $criterio)
            ->orderBy('ranking')
            ->limit(50)
            ->get();

        $reubicar = $servicio->candidatosReubicacion($criterio, 25);

        // Salud del dato físico: sin esto el slotting y el TMS trabajan a ciegas.
        $totalActivos = inventario::where('activo', 1)->count();
        $medidos = inventario::where('activo', 1)
            ->whereIn('datos_fisicos_fuente', ['medido', 'proveedor'])->count();
        $sinDatos = inventario::where('activo', 1)
            ->where(function ($q) {
                $q->whereNull('peso_kg')->orWhereNull('volumen_m3');
            })->count();

        $periodo = ProductoAbc::where('criterio', $criterio)->first();

        return view('wms.abc', compact(
            'criterio', 'distribucion', 'top', 'reubicar',
            'totalActivos', 'medidos', 'sinDatos', 'periodo'
        ));
    }

    /**
     * Panel de conteo cíclico.
     */
    public function conteo(Request $request)
    {
        $conteos = ConteoCiclico::withCount('detalles')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $exactitud = (new ConteoCiclicoService())->exactitudPeriodo(90);

        // Cuántas ubicaciones tienen el plazo de recuento vencido, por clase.
        $frecuencias = config('wms.conteo.frecuencia_dias');
        $pendientes = [];

        foreach ($frecuencias as $clase => $dias) {
            $pendientes[$clase] = DB::table('warehouse_inventory as wi')
                ->join('warehouses as w', 'w.id', '=', 'wi.warehouse_id')
                ->join('producto_abc as abc', function ($j) {
                    $j->on('abc.inventario_id', '=', 'wi.inventario_id')
                      ->where('abc.criterio', '=', config('wms.abc.criterio_slotting'));
                })
                ->leftJoin(DB::raw('(SELECT warehouse_id, MAX(contado_en) ultimo
                                      FROM conteo_ciclico_detalles
                                      WHERE contado_en IS NOT NULL
                                      GROUP BY warehouse_id) uc'),
                           'uc.warehouse_id', '=', 'wi.warehouse_id')
                ->where('w.estado', 'activa')
                ->where('abc.clase', $clase)
                ->whereRaw('(uc.ultimo IS NULL OR DATEDIFF(NOW(), uc.ultimo) >= ?)', [$dias])
                ->distinct()
                ->count('wi.warehouse_id');
        }

        return view('wms.conteo', compact('conteos', 'exactitud', 'pendientes', 'frecuencias'));
    }

    /**
     * Panel del TMS.
     */
    public function tms(Request $request)
    {
        $vehiculos = TmsVehiculo::with('conductorHabitual')->orderBy('placa')->get();

        $rutas = TmsRuta::with(['vehiculo', 'conductor'])
            ->withCount('paradas')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit(30)
            ->get();

        $indicadores = app(TmsController::class)->indicadores($request)->getData(true);

        return view('wms.tms', compact('vehiculos', 'rutas', 'indicadores'));
    }
}
