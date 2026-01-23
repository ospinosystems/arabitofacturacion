<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class CierresMetodosPago extends Model
{
    use HasFactory;
    
    protected $table = 'cierres_metodos_pago';
    
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        "id_cierre",
        "tipo_pago",
        "subtipo",
        "moneda",
        "monto_real",
        "monto_inicial",
        "monto_digital",
        "monto_diferencia",
        "estado_cuadre",
        "mensaje_cuadre",
        "metadatos",
    ];
    
    protected $casts = [
        'metadatos' => 'array',
        'monto_real' => 'decimal:2',
        'monto_inicial' => 'decimal:2',
        'monto_digital' => 'decimal:2',
        'monto_diferencia' => 'decimal:2',
    ];
    
    /**
     * Relación con el cierre
     */
    public function cierre()
    {
        return $this->belongsTo(cierres::class, 'id_cierre', 'id');
    }
}
