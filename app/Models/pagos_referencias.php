<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;
class pagos_referencias extends Model
{
    use HasFactory;
    
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'push',
        'categoria',
        'tipo',
        'descripcion',
        'monto',
        'id_pedido',
        'banco',
        'response',
        'estatus',
        'fecha_pago',
        'banco_origen',
        'monto_real',
        'cuenta_origen',
        'telefono',
        'cedula',
    ];
}
