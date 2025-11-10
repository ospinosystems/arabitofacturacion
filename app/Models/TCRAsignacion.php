<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TCRAsignacion extends Model
{
    use HasFactory;
    
    protected $table = 'tcr_asignaciones';
    
    protected $fillable = [
        'pedido_central_id',
        'item_pedido_id',
        'codigo_barras',
        'codigo_proveedor',
        'descripcion',
        'cantidad',
        'cantidad_asignada',
        'chequeador_id',
        'pasillero_id',
        'estado',
        'warehouse_codigo',
        'warehouse_id',
        'precio_base',
        'precio_venta',
        'sucursal_origen',
        'observaciones',
    ];
    
    protected $casts = [
        'cantidad' => 'decimal:4',
        'cantidad_asignada' => 'decimal:4',
        'precio_base' => 'decimal:2',
        'precio_venta' => 'decimal:2',
    ];
    
    // Relaciones
    public function chequeador()
    {
        return $this->belongsTo(usuarios::class, 'chequeador_id');
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
    
    public function scopeCompletados($query)
    {
        return $query->where('estado', 'completado');
    }
    
    public function scopeEnEspera($query)
    {
        return $query->where('estado', 'en_espera');
    }
    
    public function scopePorPasillero($query, $pasilleroId)
    {
        return $query->where('pasillero_id', $pasilleroId);
    }
    
    public function scopePorPedido($query, $pedidoId)
    {
        return $query->where('pedido_central_id', $pedidoId);
    }
    
    // Métodos auxiliares
    public function cantidadPendiente()
    {
        return $this->cantidad - $this->cantidad_asignada;
    }
    
    public function estaCompleto()
    {
        return $this->cantidad_asignada >= $this->cantidad;
    }
}
