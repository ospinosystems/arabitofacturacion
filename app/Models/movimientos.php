<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;


class movimientos extends Model
{
    use HasFactory;
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
     public function items() { 
        return $this->hasMany('App\Models\items_movimiento',"id_movimiento","id"); 
    }
    public function usuario() { 
        return $this->hasOne(\App\Models\usuarios::class,"id","id_usuario"); 
    }
    
    protected $fillable = [
        "push",
        "tipo",
        "descripcion",
        "fecha",
        "id_usuario",
    ];
}
