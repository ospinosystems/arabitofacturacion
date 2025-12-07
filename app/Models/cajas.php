<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;


class cajas extends Model
{
    use HasFactory;
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        "push",
        "concepto",
        "categoria",
        
        "dolarbalance",
        "montodolar",
        "montopeso",
        "pesobalance",
        "montobs",
        "bsbalance",

        "montoeuro",
        "eurobalance",

        "tipo",
        "fecha",
        "estatus",
        "id_sucursal_destino",
        "id_sucursal_emisora",
        "idincentralrecepcion",
        "sucursal_destino_aprobacion",
        "id_departamento",
        
    ];

    public function cat() { 
        return $this->hasOne(\App\Models\catcajas::class,"id","categoria"); 
    }
}
