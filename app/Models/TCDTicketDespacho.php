<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TCDTicketDespacho extends Model
{
    use HasFactory;
    
    protected $table = 'tcd_tickets_despacho';
    
    protected $fillable = [
        'tcd_orden_id',
        'codigo_barras',
        'estado',
        'usuario_despacho_id',
        'fecha_escaneo',
    ];
    
    protected $casts = [
        'fecha_escaneo' => 'datetime',
    ];
    
    // Relaciones
    public function orden()
    {
        return $this->belongsTo(TCDOrden::class, 'tcd_orden_id');
    }
    
    public function usuarioDespacho()
    {
        return $this->belongsTo(usuarios::class, 'usuario_despacho_id');
    }
    
    // Scopes
    public function scopeGenerado($query)
    {
        return $query->where('estado', 'generado');
    }
    
    public function scopeEscaneado($query)
    {
        return $query->where('estado', 'escaneado');
    }
}

