<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class WarehouseInventory extends Model
{
    use HasFactory;
    
    protected $table = 'warehouse_inventory';
    
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    
    protected $fillable = [
        'warehouse_id',
        'inventario_id',
        'cantidad',
        'cantidad_bloqueada',
        'lote',
        'fecha_vencimiento',
        'fecha_entrada',
        'estado',
        'observaciones',
    ];
    
    protected $casts = [
        'cantidad' => 'decimal:2',
        'cantidad_bloqueada' => 'decimal:4',
        'fecha_vencimiento' => 'date',
        'fecha_entrada' => 'date',
    ];
    
    /**
     * Relación con warehouse
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    
    /**
     * Relación con inventario (producto)
     */
    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }
    
    /**
     * Mapa producto_id => "COD1, COD2" con los códigos de ubicación (warehouses.codigo)
     * donde cada producto tiene stock (cantidad > 0). Una sola consulta para muchos productos.
     */
    public static function ubicacionesPorProductos(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }
        return static::query()
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_inventory.warehouse_id')
            ->whereIn('warehouse_inventory.inventario_id', $ids)
            ->where('warehouse_inventory.cantidad', '>', 0)
            ->orderBy('warehouses.codigo')
            ->get(['warehouse_inventory.inventario_id as pid', 'warehouses.codigo as codigo'])
            ->groupBy('pid')
            ->map(fn ($g) => $g->pluck('codigo')->filter()->unique()->implode(', '))
            ->toArray();
    }

    /**
     * Scope para inventario disponible
     */
    public function scopeDisponible($query)
    {
        return $query->where('estado', 'disponible')->where('cantidad', '>', 0);
    }
    
    /**
     * Scope para inventario con stock
     */
    public function scopeConStock($query)
    {
        return $query->where('cantidad', '>', 0);
    }
    
    /**
     * Scope para productos próximos a vencer
     */
    public function scopeProximosVencer($query, $dias = 30)
    {
        return $query->whereNotNull('fecha_vencimiento')
                     ->whereDate('fecha_vencimiento', '<=', now()->addDays($dias))
                     ->whereDate('fecha_vencimiento', '>=', now());
    }
    
    /**
     * Scope para buscar por lote
     */
    public function scopePorLote($query, $lote)
    {
        return $query->where('lote', $lote);
    }
    
    /**
     * Verificar si está próximo a vencer
     */
    public function estaProximoVencer($dias = 30)
    {
        if (!$this->fecha_vencimiento) {
            return false;
        }
        
        return $this->fecha_vencimiento->diffInDays(now()) <= $dias 
               && $this->fecha_vencimiento >= now();
    }
    
    /**
     * Verificar si está vencido
     */
    public function estaVencido()
    {
        if (!$this->fecha_vencimiento) {
            return false;
        }
        
        return $this->fecha_vencimiento < now();
    }
}
