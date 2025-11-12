<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class WarehouseMovement extends Model
{
    use HasFactory;
    
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    
    protected $fillable = [
        'tipo',
        'inventario_id',
        'warehouse_origen_id',
        'warehouse_destino_id',
        'cantidad',
        'lote',
        'fecha_vencimiento',
        'usuario_id',
        'documento_referencia',
        'observaciones',
        'fecha_movimiento',
    ];
    
    protected $casts = [
        'cantidad' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_movimiento' => 'datetime',
    ];
    
    /**
     * Relación con inventario (producto)
     */
    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }
    
    /**
     * Relación con warehouse origen
     */
    public function warehouseOrigen()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_origen_id');
    }
    
    /**
     * Relación con warehouse destino
     */
    public function warehouseDestino()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_destino_id');
    }
    
    /**
     * Relación con usuario que realizó el movimiento
     */
    public function usuario()
    {
        return $this->belongsTo(usuarios::class, 'usuario_id');
    }
    
    /**
     * Scope para filtrar por tipo de movimiento
     */
    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
    
    /**
     * Scope para movimientos de entrada
     */
    public function scopeEntradas($query)
    {
        return $query->where('tipo', 'entrada');
    }
    
    /**
     * Scope para movimientos de salida
     */
    public function scopeSalidas($query)
    {
        return $query->where('tipo', 'salida');
    }
    
    /**
     * Scope para transferencias
     */
    public function scopeTransferencias($query)
    {
        return $query->where('tipo', 'transferencia');
    }
    
    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_movimiento', [$fechaInicio, $fechaFin]);
    }
    
    /**
     * Scope para filtrar por producto
     */
    public function scopePorProducto($query, $inventarioId)
    {
        return $query->where('inventario_id', $inventarioId);
    }
    
    /**
     * Scope para filtrar por usuario
     */
    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }
}
