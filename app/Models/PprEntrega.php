<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class PprEntrega extends Model
{
    protected $table = 'ppr_entregas';

    protected $fillable = [
        'id_pedido',
        'id_item_pedido',
        'id_producto',
        'unidades_entregadas',
        'id_usuario_portero',
    ];

    protected $casts = [
        'unidades_entregadas' => 'decimal:4',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function pedido()
    {
        return $this->belongsTo(pedidos::class, 'id_pedido');
    }

    public function itemPedido()
    {
        return $this->belongsTo(items_pedidos::class, 'id_item_pedido');
    }

    public function producto()
    {
        return $this->belongsTo(\App\Models\inventario::class, 'id_producto');
    }

    public function usuarioPortero()
    {
        return $this->belongsTo(usuarios::class, 'id_usuario_portero');
    }
}
