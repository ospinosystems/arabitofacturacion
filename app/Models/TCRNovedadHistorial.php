<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TCRNovedadHistorial extends Model
{
    use HasFactory;
    
    protected $table = 'tcr_novedades_historial';
    
    protected $fillable = [
        'novedad_id',
        'cantidad_agregada',
        'cantidad_total',
        'pasillero_id',
        'observaciones',
    ];
    
    protected $casts = [
        'cantidad_agregada' => 'decimal:4',
        'cantidad_total' => 'decimal:4',
    ];
    
    // Relaciones
    public function novedad()
    {
        return $this->belongsTo(TCRNovedad::class, 'novedad_id');
    }
    
    public function pasillero()
    {
        return $this->belongsTo(usuarios::class, 'pasillero_id');
    }
}

