<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

/**
 * Se escribe solo cuando la clase ABC de un producto cambia.
 * Un producto que pasa de C a A es senal de que hay que reubicarlo.
 */
class ProductoAbcHistorial extends Model
{
    protected $table = 'producto_abc_historial';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'inventario_id',
        'criterio',
        'clase_anterior',
        'clase_nueva',
        'metrica',
        'periodo_inicio',
        'periodo_fin',
        'calculado_en',
    ];

    protected $casts = [
        'metrica'        => 'decimal:4',
        'periodo_inicio' => 'date',
        'periodo_fin'    => 'date',
        'calculado_en'   => 'datetime',
    ];

    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }

    /**
     * Productos que subieron de clase (C->B, B->A, C->A): candidatos a reubicar
     * mas cerca del muelle.
     */
    public function scopeAscensos($query)
    {
        return $query->whereNotNull('clase_anterior')
                     ->whereRaw('clase_nueva < clase_anterior');
    }

    public function scopeDescensos($query)
    {
        return $query->whereNotNull('clase_anterior')
                     ->whereRaw('clase_nueva > clase_anterior');
    }
}
