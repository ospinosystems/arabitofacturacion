<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class TmsParadaItem extends Model
{
    protected $table = 'tms_parada_items';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'parada_id',
        'inventario_id',
        'descripcion',
        'cantidad',
        'cantidad_entregada',
        'peso_kg',
        'volumen_m3',
        'observaciones',
    ];

    protected $casts = [
        'cantidad'           => 'decimal:4',
        'cantidad_entregada' => 'decimal:4',
        'peso_kg'            => 'decimal:4',
        'volumen_m3'         => 'decimal:6',
    ];

    public function parada()
    {
        return $this->belongsTo(TmsParada::class, 'parada_id');
    }

    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }
}
