<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class TmsRuta extends Model
{
    protected $table = 'tms_rutas';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'codigo',
        'fecha',
        'estado',
        'vehiculo_id',
        'conductor_id',
        'peso_total_kg',
        'volumen_total_m3',
        'bultos_total',
        'utilizacion_peso_pct',
        'utilizacion_volumen_pct',
        'distancia_estimada_km',
        'tiempo_estimado_min',
        'costo_estimado',
        'salida_real',
        'retorno_real',
        'usuario_planificador_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha'                   => 'date',
        'peso_total_kg'           => 'decimal:4',
        'volumen_total_m3'        => 'decimal:6',
        'bultos_total'            => 'integer',
        'utilizacion_peso_pct'    => 'decimal:2',
        'utilizacion_volumen_pct' => 'decimal:2',
        'distancia_estimada_km'   => 'decimal:2',
        'tiempo_estimado_min'     => 'integer',
        'costo_estimado'          => 'decimal:4',
        'salida_real'             => 'datetime',
        'retorno_real'            => 'datetime',
    ];

    public function vehiculo()
    {
        return $this->belongsTo(TmsVehiculo::class, 'vehiculo_id');
    }

    public function conductor()
    {
        return $this->belongsTo(TmsConductor::class, 'conductor_id');
    }

    public function paradas()
    {
        return $this->hasMany(TmsParada::class, 'ruta_id')->orderBy('orden');
    }

    public function scopeActivas($query)
    {
        return $query->whereIn('estado', ['planificada', 'cargando', 'en_ruta']);
    }

    /**
     * Recalcula totales y utilizacion a partir de las paradas.
     *
     * La utilizacion se mide contra ambas capacidades porque el limitante cambia
     * segun la carga: lo denso topa el peso, lo voluminoso topa el cubicaje.
     */
    public function recalcularTotales(): void
    {
        $paradas = $this->paradas()->get();

        $this->peso_total_kg    = $paradas->sum(fn ($p) => (float) $p->peso_kg);
        $this->volumen_total_m3 = $paradas->sum(fn ($p) => (float) $p->volumen_m3);
        $this->bultos_total     = $paradas->sum(fn ($p) => (int) $p->bultos);

        $vehiculo = $this->vehiculo;
        if ($vehiculo && (float) $vehiculo->capacidad_peso_kg > 0) {
            $this->utilizacion_peso_pct = round(((float) $this->peso_total_kg / (float) $vehiculo->capacidad_peso_kg) * 100, 2);
        }
        if ($vehiculo && (float) $vehiculo->capacidad_volumen_m3 > 0) {
            $this->utilizacion_volumen_pct = round(((float) $this->volumen_total_m3 / (float) $vehiculo->capacidad_volumen_m3) * 100, 2);
        }

        $this->save();
    }

    /**
     * Motivo por el que la ruta no admite mas carga, o null si aun cabe.
     */
    public function limitante(): ?string
    {
        if ((float) $this->utilizacion_peso_pct >= 100) {
            return 'peso';
        }
        if ((float) $this->utilizacion_volumen_pct >= 100) {
            return 'volumen';
        }

        return null;
    }
}
