<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;


class items_pedidos extends Model
{
    use HasFactory;
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function producto() { 
        return $this->hasOne('App\Models\inventario',"id","id_producto"); 
    }
    public function pedido() { 
        return $this->hasOne('App\Models\pedidos',"id","id_pedido"); 
    }
    
    protected $fillable = [
        "push",
        "id_producto",
        "id_pedido",
        "abono",
        "cantidad",
        "descuento",
        "monto",
        "lote",
        "created_at",
        "updated_at",
        "condicion",

        "ct_real",
        "barras_real",
        "alterno_real",
        "tasa",
        "tasa_cop",
        "precio_unitario",
    ];
}
