<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TCDOrdenItem extends Model
{
    use HasFactory;
    
    protected $table = 'tcd_orden_items';
    
    protected $fillable = [
        'tcd_orden_id',
        'inventario_id',
        'codigo_barras',
        'codigo_proveedor',
        'descripcion',
        'precio',
        'precio_base',
        'ubicacion',
        'cantidad',
        'cantidad_descontada',
        'cantidad_bloqueada',
    ];
    
    protected $casts = [
        'precio' => 'decimal:2',
        'precio_base' => 'decimal:2',
        'cantidad' => 'decimal:4',
        'cantidad_descontada' => 'decimal:4',
        'cantidad_bloqueada' => 'decimal:4',
    ];
    
    // Relaciones
    public function orden()
    {
        return $this->belongsTo(TCDOrden::class, 'tcd_orden_id');
    }
    
    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }
    
    public function asignaciones()
    {
        return $this->hasMany(TCDAsignacion::class, 'tcd_orden_item_id');
    }
    
    // Métodos auxiliares
    public function cantidadPendiente()
    {
        return $this->cantidad - $this->cantidad_descontada;
    }
    
    public function cantidadPendienteDesbloquear()
    {
        return $this->cantidad_bloqueada;
    }
}

