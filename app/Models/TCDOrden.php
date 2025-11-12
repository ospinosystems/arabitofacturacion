<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TCDOrden extends Model
{
    use HasFactory;
    
    protected $table = 'tcd_ordenes';
    
    protected $fillable = [
        'numero_orden',
        'chequeador_id',
        'estado',
        'observaciones',
        'fecha_despacho',
    ];
    
    protected $casts = [
        'fecha_despacho' => 'datetime',
    ];
    
    // Relaciones
    public function chequeador()
    {
        return $this->belongsTo(usuarios::class, 'chequeador_id');
    }
    
    public function items()
    {
        return $this->hasMany(TCDOrdenItem::class, 'tcd_orden_id');
    }
    
    public function asignaciones()
    {
        return $this->hasMany(TCDAsignacion::class, 'tcd_orden_id');
    }
    
    public function ticketDespacho()
    {
        return $this->hasOne(TCDTicketDespacho::class, 'tcd_orden_id');
    }
    
    // Scopes
    public function scopeBorrador($query)
    {
        return $query->where('estado', 'borrador');
    }
    
    public function scopeAsignada($query)
    {
        return $query->where('estado', 'asignada');
    }
    
    public function scopeEnProceso($query)
    {
        return $query->where('estado', 'en_proceso');
    }
    
    public function scopeCompletada($query)
    {
        return $query->where('estado', 'completada');
    }
    
    // Métodos auxiliares
    public function estaCompleta()
    {
        return $this->asignaciones()->where('estado', '!=', 'completada')->count() === 0;
    }
    
    public function cantidadTotalItems()
    {
        return $this->items()->sum('cantidad');
    }
    
    public function cantidadProcesada()
    {
        return $this->asignaciones()->sum('cantidad_procesada');
    }
}

