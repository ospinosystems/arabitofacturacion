<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class SucursalCentralCache extends Model
{
    use HasFactory;
    
    protected $table = 'sucursales_central_cache';
    
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    
    protected $fillable = [
        'codigo',
        'nombre',
        'direccion',
        'rif',
        'razon_social',
        'direccion_fiscal',
        'zona',
    ];
}
