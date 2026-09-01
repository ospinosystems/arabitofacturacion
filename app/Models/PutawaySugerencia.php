<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

/**
 * Sugerencia de ubicacion emitida por el motor de slotting y decision real del operario.
 *
 * Cada fila con fue_aceptada = false es una correccion humana: el material de
 * entrenamiento para reajustar los pesos del scoring.
 */
class PutawaySugerencia extends Model
{
    protected $table = 'putaway_sugerencias';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'inventario_id',
        'cantidad',
        'contexto',
        'referencia',
        'candidatas',
        'warehouse_sugerido_id',
        'score_sugerido',
        'warehouse_elegido_id',
        'score_elegido',
        'posicion_elegida',
        'fue_aceptada',
        'motivo_override',
        'clase_abc',
        'datos_fisicos_estimados',
        'usuario_id',
        'decidido_en',
    ];

    protected $casts = [
        'cantidad'                => 'decimal:4',
        'candidatas'              => 'array',
        'score_sugerido'          => 'decimal:4',
        'score_elegido'           => 'decimal:4',
        'posicion_elegida'        => 'integer',
        'fue_aceptada'            => 'boolean',
        'datos_fisicos_estimados' => 'boolean',
        'decidido_en'             => 'datetime',
    ];

    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }

    public function warehouseSugerido()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_sugerido_id');
    }

    public function warehouseElegido()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_elegido_id');
    }

    public function usuario()
    {
        return $this->belongsTo(usuarios::class, 'usuario_id');
    }

    /** Sugerencias ya resueltas (el operario decidio). */
    public function scopeDecididas($query)
    {
        return $query->whereNotNull('fue_aceptada');
    }

    public function scopeRechazadas($query)
    {
        return $query->where('fue_aceptada', false);
    }
}
