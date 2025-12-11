<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sucursal extends Model
{
    use HasFactory;
    protected $fillable = [
        "sucursal",
        "codigo",
        "direccion_registro",
        "direccion_sucursal",
        "telefono1",
        "telefono2",
        "tickera",
        "fiscal",
        "correo",
        "nombre_registro",
        "rif",
        "modo_transferencia",
        "pinpad",
    ];
    
    protected $casts = [
        'pinpad' => 'boolean',
    ];
}
