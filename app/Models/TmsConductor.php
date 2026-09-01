<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class TmsConductor extends Model
{
    protected $table = 'tms_conductores';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'usuario_id',
        'nombre',
        'documento',
        'telefono',
        'licencia',
        'licencia_vence',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'licencia_vence' => 'date',
    ];

    public function usuario()
    {
        return $this->belongsTo(usuarios::class, 'usuario_id');
    }

    public function rutas()
    {
        return $this->hasMany(TmsRuta::class, 'conductor_id');
    }

    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'disponible');
    }

    /** No se debe despachar a un conductor con la licencia vencida. */
    public function licenciaVigente(): bool
    {
        if (!$this->licencia_vence) {
            return true;
        }

        return $this->licencia_vence->gte(now()->startOfDay());
    }
}
