<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;


class pago_pedidos extends Model
{
    use HasFactory;
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        "push",
        "tipo",
        "monto",
        "monto_original",
        "cuenta",
        "id_pedido",
        "moneda",
        "referencia"
    ];

    /**
     * Relación con el pedido
     */
    public function pedido()
    {
        return $this->belongsTo(\App\Models\pedidos::class, 'id_pedido');
    }

}
