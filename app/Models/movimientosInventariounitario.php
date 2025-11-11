<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class movimientosInventariounitario extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function usuario() { 
        return $this->hasOne(\App\Models\usuarios::class,"id","id_usuario"); 
    }

    public function producto() { 
        return $this->hasOne(\App\Models\inventario::class,"id","id_producto"); 
    }

    protected $fillable = [
        "id_producto",
        "id_pedido",
        "cantidad",
        "cantidadafter",
        "origen",
        "id_usuario",
    ];
}
