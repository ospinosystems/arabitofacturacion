<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Línea de transferencia asignada a un pasillero (orden de recolección).
 */
class TransferenciaAsignacion extends Model
{
    protected $table = 'transferencia_asignaciones';

    protected $fillable = [
        'id_transferencia', 'id_transferencia_item', 'id_producto', 'pasillero_id',
        'cantidad', 'cantidad_recolectada', 'estado', 'warehouse_codigo', 'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'cantidad_recolectada' => 'decimal:3',
    ];

    public function transferencia()
    {
        return $this->belongsTo(transferencias_inventario::class, 'id_transferencia');
    }

    public function item()
    {
        return $this->belongsTo(transferencias_inventario_items::class, 'id_transferencia_item');
    }

    public function pasillero()
    {
        return $this->belongsTo(usuarios::class, 'pasillero_id');
    }

    public function producto()
    {
        return $this->belongsTo(inventario::class, 'id_producto');
    }
}
