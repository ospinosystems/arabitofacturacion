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

        // Slotting — ver AddSlottingToWarehouses
        'coord_x',
        'coord_y',
        'distancia_muelle_m',
        'accesibilidad',
        'clase_abc',
        'alto_util_cm',
        'ancho_util_cm',
        'profundidad_util_cm',
        'permite_mezcla_productos',
        'permite_mezcla_lotes',
        'refrigerada',
        'admite_peligrosos',
        'bloqueada_para_putaway',
        'prioridad_picking',
    ];

    protected $casts = [
        'capacidad_peso' => 'decimal:2',
        'capacidad_volumen' => 'decimal:2',
        'capacidad_unidades' => 'integer',

        'coord_x'                  => 'decimal:2',
        'coord_y'                  => 'decimal:2',
        'distancia_muelle_m'       => 'decimal:2',
        'alto_util_cm'             => 'decimal:2',
        'ancho_util_cm'            => 'decimal:2',
        'profundidad_util_cm'      => 'decimal:2',
        'permite_mezcla_productos' => 'boolean',
        'permite_mezcla_lotes'     => 'boolean',
        'refrigerada'              => 'boolean',
        'admite_peligrosos'        => 'boolean',
        'bloqueada_para_putaway'   => 'boolean',
        'prioridad_picking'        => 'integer',
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

    /**
     * Scope: ubicaciones que pueden recibir mercancía.
     * Excluye las bloqueadas explícitamente para putaway aunque estén activas
     * (una ubicación puede estar operativa para picking pero cerrada a nuevo ingreso).
     */
    public function scopeDisponiblesParaPutaway($query)
    {
        return $query->where('estado', 'activa')
                     ->where('bloqueada_para_putaway', false);
    }

    public function scopeZona($query, $zona)
    {
        return $query->where('zona', $zona);
    }

    /**
     * Peso y volumen actualmente ocupados, sumando el cubicaje de cada producto
     * almacenado. Devuelve ['peso_kg' => float, 'volumen_m3' => float, 'unidades' => float].
     *
     * Los productos sin datos físicos aportan 0: la ocupación queda subestimada,
     * por eso `ocupacionFisica()['completa']` avisa si algún producto no tiene ficha.
     */
    public function ocupacionFisica(): array
    {
        $filas = $this->inventarios()->with('inventario')->get();

        $peso = 0.0;
        $volumen = 0.0;
        $unidades = 0.0;
        $completa = true;

        foreach ($filas as $fila) {
            $cantidad = (float) $fila->cantidad;
            $unidades += $cantidad;

            $producto = $fila->inventario;
            if (!$producto || !$producto->tieneDatosFisicos()) {
                if ($cantidad > 0) {
                    $completa = false;
                }
                continue;
            }

            $peso    += (float) $producto->peso_kg * $cantidad;
            $volumen += (float) $producto->volumen_m3 * $cantidad;
        }

        return [
            'peso_kg'    => round($peso, 4),
            'volumen_m3' => round($volumen, 8),
            'unidades'   => round($unidades, 4),
            'completa'   => $completa,
        ];
    }

    /**
     * Capacidad restante por cada dimensión. Un null significa "sin límite definido".
     */
    public function capacidadRestante(): array
    {
        $ocupado = $this->ocupacionFisica();

        return [
            'peso_kg'    => $this->capacidad_peso !== null
                ? round((float) $this->capacidad_peso - $ocupado['peso_kg'], 4) : null,
            'volumen_m3' => $this->capacidad_volumen !== null
                ? round((float) $this->capacidad_volumen - $ocupado['volumen_m3'], 8) : null,
            'unidades'   => $this->capacidad_unidades !== null
                ? round((float) $this->capacidad_unidades - $ocupado['unidades'], 4) : null,
        ];
    }

    /**
     * ¿Cabe físicamente esta cantidad de este producto?
     *
     * Devuelve ['cabe' => bool, 'motivo' => ?string]. Una dimensión sin capacidad
     * definida no bloquea: no se puede rechazar por un límite que nadie configuró.
     */
    public function cabeProducto(inventario $producto, float $cantidad): array
    {
        $restante = $this->capacidadRestante();

        if ($restante['unidades'] !== null && $cantidad > $restante['unidades']) {
            return ['cabe' => false, 'motivo' => 'Supera la capacidad en unidades'];
        }

        if ($producto->tieneDatosFisicos()) {
            $peso    = (float) $producto->peso_kg * $cantidad;
            $volumen = (float) $producto->volumen_m3 * $cantidad;

            if ($restante['peso_kg'] !== null && $peso > $restante['peso_kg']) {
                return ['cabe' => false, 'motivo' => 'Supera la capacidad de peso'];
            }
            if ($restante['volumen_m3'] !== null && $volumen > $restante['volumen_m3']) {
                return ['cabe' => false, 'motivo' => 'Supera la capacidad de volumen'];
            }
        }

        return ['cabe' => true, 'motivo' => null];
    }

    /**
     * Compatibilidad producto-ubicación por condiciones de almacenamiento.
     * Devuelve ['compatible' => bool, 'motivo' => ?string].
     */
    public function esCompatibleCon(inventario $producto): array
    {
        if ($producto->requiere_refrigeracion && !$this->refrigerada) {
            return ['compatible' => false, 'motivo' => 'El producto requiere refrigeración'];
        }

        if ($producto->peligroso && !$this->admite_peligrosos) {
            return ['compatible' => false, 'motivo' => 'La ubicación no admite mercancía peligrosa'];
        }

        if (!$this->permite_mezcla_productos) {
            $otro = $this->inventarios()
                ->where('inventario_id', '!=', $producto->id)
                ->where('cantidad', '>', 0)
                ->exists();
            if ($otro) {
                return ['compatible' => false, 'motivo' => 'La ubicación no permite mezclar productos'];
            }
        }

        // Un producto que no se puede apilar no debe ir a un nivel alto sin acceso directo.
        if (!$producto->apilable && $this->accesibilidad === 'altura') {
            return ['compatible' => false, 'motivo' => 'Producto no apilable en ubicación de altura'];
        }

        return ['compatible' => true, 'motivo' => null];
    }
}
