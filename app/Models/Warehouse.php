<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class Warehouse extends Model
{
    use HasFactory;
    
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    
    protected $fillable = [
        'pasillo',
        'cara',
        'rack',
        'nivel',
        'codigo',
        'nombre',
        'descripcion',
        'tipo',
        'estado',
        'zona',
        'capacidad_peso',
        'capacidad_volumen',
        'capacidad_unidades',
    ];
    
    protected $casts = [
        'capacidad_peso' => 'decimal:2',
        'capacidad_volumen' => 'decimal:2',
        'capacidad_unidades' => 'integer',
    ];
    
    // Boot method para generar código automáticamente
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($warehouse) {
            if (!$warehouse->codigo) {
                $warehouse->codigo = "{$warehouse->pasillo}{$warehouse->cara}-{$warehouse->rack}-{$warehouse->nivel}";
            }
        });
        
        static::updating(function ($warehouse) {
            $warehouse->codigo = "{$warehouse->pasillo}{$warehouse->cara}-{$warehouse->rack}-{$warehouse->nivel}";
        });
    }
    
    /**
     * Relación con warehouse_inventory
     */
    public function inventarios()
    {
        return $this->hasMany(WarehouseInventory::class);
    }
    
    /**
     * Productos únicos en esta ubicación
     */
    public function productos()
    {
        return $this->belongsToMany(inventario::class, 'warehouse_inventory', 'warehouse_id', 'inventario_id')
                    ->withPivot('cantidad', 'lote', 'fecha_vencimiento', 'estado', 'observaciones')
                    ->withTimestamps();
    }
    
    /**
     * Movimientos desde esta ubicación
     */
    public function movimientosOrigen()
    {
        return $this->hasMany(WarehouseMovement::class, 'warehouse_origen_id');
    }
    
    /**
     * Movimientos hacia esta ubicación
     */
    public function movimientosDestino()
    {
        return $this->hasMany(WarehouseMovement::class, 'warehouse_destino_id');
    }
    
    /**
     * Scope para ubicaciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }
    
    /**
     * Scope para ubicaciones por tipo
     */
    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
    
    /**
     * Scope para búsqueda por código
     */
    public function scopeBuscarCodigo($query, $codigo)
    {
        return $query->where('codigo', 'like', "%{$codigo}%");
    }
    
    /**
     * Calcular ocupación actual
     */
    public function getOcupacionAttribute()
    {
        $totalProductos = $this->inventarios()->sum('cantidad');
        
        if ($this->capacidad_unidades) {
            return round(($totalProductos / $this->capacidad_unidades) * 100, 2);
        }
        
        return null;
    }
    
    /**
     * Verificar si tiene capacidad disponible
     */
    public function tieneCapacidad($cantidad = 1)
    {
        if (!$this->capacidad_unidades) {
            return true; // Sin límite definido
        }
        
        $totalActual = $this->inventarios()->sum('cantidad');
        return ($totalActual + $cantidad) <= $this->capacidad_unidades;
    }
    
    /**
     * Obtener capacidad disponible
     */
    public function capacidadDisponible()
    {
        if (!$this->capacidad_unidades) {
            return null; // Sin límite definido
        }
        
        $totalActual = $this->inventarios()->sum('cantidad');
        return max(0, $this->capacidad_unidades - $totalActual);
    }
    
    /**
     * Obtener alias para capacidad_maxima
     */
    public function getCapacidadMaximaAttribute()
    {
        return $this->capacidad_unidades;
    }
}
