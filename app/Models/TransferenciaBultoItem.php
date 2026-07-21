<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mercancía contenida dentro de un bulto.
 */
class TransferenciaBultoItem extends Model
{
    protected $table = 'transferencia_bulto_items';

    protected $fillable = [
        'id_bulto', 'id_transferencia_item', 'id_producto', 'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
    ];

    public function bulto()
    {
        return $this->belongsTo(TransferenciaBulto::class, 'id_bulto');
    }

    public function item()
    {
        return $this->belongsTo(transferencias_inventario_items::class, 'id_transferencia_item');
    }

    public function producto()
    {
        return $this->belongsTo(inventario::class, 'id_producto');
    }
}
