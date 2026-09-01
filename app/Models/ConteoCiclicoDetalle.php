<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

/**
 * Una linea de conteo = una combinacion ubicacion + producto + lote.
 *
 * cantidad_sistema se congela al generar el conteo: si alguien mueve stock mientras
 * se cuenta, la diferencia debe reflejar lo que habia cuando se emitio la tarea.
 */
class ConteoCiclicoDetalle extends Model
{
    protected $table = 'conteo_ciclico_detalles';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'conteo_id',
        'warehouse_id',
        'inventario_id',
        'lote',
        'cantidad_sistema',
        'cantidad_contada',
        'cantidad_recuento',
        'diferencia',
        'valor_diferencia',
        'estado',
        'es_hallazgo',
        'usuario_id',
        'contado_en',
        'warehouse_movement_id',
        'observaciones',
    ];

    protected $casts = [
        'cantidad_sistema'  => 'decimal:4',
        'cantidad_contada'  => 'decimal:4',
        'cantidad_recuento' => 'decimal:4',
        'diferencia'        => 'decimal:4',
        'valor_diferencia'  => 'decimal:4',
        'es_hallazgo'       => 'boolean',
        'contado_en'        => 'datetime',
    ];

    public function conteo()
    {
        return $this->belongsTo(ConteoCiclico::class, 'conteo_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeConDiferencia($query)
    {
        return $query->whereNotNull('diferencia')->where('diferencia', '!=', 0);
    }

    /**
     * Cantidad que se toma como verdadera: el recuento manda sobre el primer conteo.
     */
    public function cantidadFinal(): ?float
    {
        if ($this->cantidad_recuento !== null) {
            return (float) $this->cantidad_recuento;
        }

        return $this->cantidad_contada !== null ? (float) $this->cantidad_contada : null;
    }
}
