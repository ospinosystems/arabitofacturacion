<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class TmsVehiculo extends Model
{
    protected $table = 'tms_vehiculos';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'placa',
        'nombre',
        'tipo',
        'capacidad_peso_kg',
        'capacidad_volumen_m3',
        'capacidad_bultos',
        'refrigerado',
        'costo_km',
        'costo_fijo_viaje',
        'estado',
        'conductor_habitual_id',
        'observaciones',
    ];

    protected $casts = [
        'capacidad_peso_kg'    => 'decimal:2',
        'capacidad_volumen_m3' => 'decimal:4',
        'capacidad_bultos'     => 'integer',
        'refrigerado'          => 'boolean',
        'costo_km'             => 'decimal:4',
        'costo_fijo_viaje'     => 'decimal:4',
    ];

    public function conductorHabitual()
    {
        return $this->belongsTo(TmsConductor::class, 'conductor_habitual_id');
    }

    public function rutas()
    {
        return $this->hasMany(TmsRuta::class, 'vehiculo_id');
    }

    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'disponible');
    }

    /**
     * Capacidad libre considerando lo ya planificado en una fecha.
     * Devuelve [peso_kg, volumen_m3].
     */
    public function capacidadLibre($fecha = null): array
    {
        $q = $this->rutas()->whereIn('estado', ['planificada', 'cargando', 'en_ruta']);
        if ($fecha) {
            $q->whereDate('fecha', $fecha);
        }
        $usado = $q->get();

        return [
            'peso_kg'    => max(0, (float) $this->capacidad_peso_kg - $usado->sum(fn ($r) => (float) $r->peso_total_kg)),
            'volumen_m3' => max(0, (float) $this->capacidad_volumen_m3 - $usado->sum(fn ($r) => (float) $r->volumen_total_m3)),
        ];
    }
}
