<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

/**
 * Cabecera de un conteo ciclico por ubicacion.
 *
 * No confundir con el inventario ciclico de arabitocentral, que cuenta productos
 * contra el stock general. Este cuenta ubicaciones fisicas contra warehouse_inventory.
 */
class ConteoCiclico extends Model
{
    protected $table = 'conteos_ciclicos';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'codigo',
        'tipo',
        'estado',
        'ciego',
        'exige_recuento',
        'criterio_abc',
        'zona',
        'usuario_creador_id',
        'usuario_conteo_id',
        'fecha_programada',
        'iniciado_en',
        'finalizado_en',
        'ajustado_en',
        'total_lineas',
        'lineas_contadas',
        'lineas_con_diferencia',
        'valor_diferencia',
        'exactitud_pct',
        'observaciones',
    ];

    protected $casts = [
        'ciego'                 => 'boolean',
        'exige_recuento'        => 'boolean',
        'fecha_programada'      => 'date',
        'iniciado_en'           => 'datetime',
        'finalizado_en'         => 'datetime',
        'ajustado_en'           => 'datetime',
        'total_lineas'          => 'integer',
        'lineas_contadas'       => 'integer',
        'lineas_con_diferencia' => 'integer',
        'valor_diferencia'      => 'decimal:4',
        'exactitud_pct'         => 'decimal:4',
    ];

    public function detalles()
    {
        return $this->hasMany(ConteoCiclicoDetalle::class, 'conteo_id');
    }

    public function usuarioCreador()
    {
        return $this->belongsTo(usuarios::class, 'usuario_creador_id');
    }

    public function usuarioConteo()
    {
        return $this->belongsTo(usuarios::class, 'usuario_conteo_id');
    }

    public function scopeAbiertos($query)
    {
        return $query->whereIn('estado', ['planificado', 'en_proceso', 'contado']);
    }

    /**
     * Recalcula el resumen a partir de los detalles.
     * exactitud = lineas sin diferencia / lineas contadas.
     */
    public function recalcularResumen(): void
    {
        $detalles = $this->detalles()->get();
        $contadas = $detalles->whereIn('estado', ['contado', 'en_recuento', 'ajustado']);

        $conDiferencia = $contadas->filter(fn ($d) => (float) $d->diferencia != 0.0);

        $this->total_lineas          = $detalles->count();
        $this->lineas_contadas       = $contadas->count();
        $this->lineas_con_diferencia = $conDiferencia->count();
        $this->valor_diferencia      = $conDiferencia->sum(fn ($d) => (float) $d->valor_diferencia);
        $this->exactitud_pct         = $contadas->count() > 0
            ? round((($contadas->count() - $conDiferencia->count()) / $contadas->count()) * 100, 4)
            : null;

        $this->save();
    }
}
