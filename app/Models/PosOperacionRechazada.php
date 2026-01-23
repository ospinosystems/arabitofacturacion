<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class PosOperacionRechazada extends Model
{
    use HasFactory;

    protected $table = 'pos_operaciones_rechazadas';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'message',
        'reference',
        'ordernumber',
        'sequence',
        'approval',
        'lote',
        'responsecode',
        'datetime',
        'amount',
        'commerce',
        'cardtype',
        'authid',
        'cardNumber',
        'cardholderid',
        'idmerchant',
        'terminal',
        'bank',
        'bankmessage',
        'tvr',
        'arqc',
        'tsi',
        'na',
        'aid',
        'json_response',
        'id_pedido',
        'id_usuario',
        'id_sucursal'
    ];

    /**
     * Relación con el pedido
     */
    public function pedido()
    {
        return $this->belongsTo(\App\Models\pedidos::class, 'id_pedido');
    }
}
