<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TCDAsignacion extends Model
{
    use HasFactory;
    
    protected $table = 'tcd_asignaciones';
    
    protected $fillable = [
        'tcd_orden_id',
        'tcd_orden_item_id',
        'pasillero_id',
        'cantidad',
        'cantidad_procesada',
        'estado',
        'warehouse_codigo',
        'warehouse_id',
        'observaciones',
    ];
    
    protected $casts = [
        'cantidad' => 'decimal:4',
        'cantidad_procesada' => 'decimal:4',
    ];
    
    // Relaciones
    public function orden()
    {
        return $this->belongsTo(TCDOrden::class, 'tcd_orden_id');
    }
    
    public function ordenItem()
    {
        return $this->belongsTo(TCDOrdenItem::class, 'tcd_orden_item_id');
    }
    
    public function pasillero()
    {
        return $this->belongsTo(usuarios::class, 'pasillero_id');
    }
    
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    
    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }
    
    public function scopeEnProceso($query)
    {
        return $query->where('estado', 'en_proceso');
    }
    
    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'completada');
    }
    
    public function scopePorPasillero($query, $pasilleroId)
    {
        return $query->where('pasillero_id', $pasilleroId);
    }
    
    // Métodos auxiliares
    public function cantidadPendiente()
    {
        return $this->cantidad - $this->cantidad_procesada;
    }
    
    public function estaCompleta()
    {
        return $this->cantidad_procesada >= $this->cantidad;
    }
}

