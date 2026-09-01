<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;


class inventario extends Model
{
    use HasFactory;
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public function items_pedidos() { 
        return $this->hasMany(\App\Models\items_pedidos::class,"id_producto","id"); 
    }

   
   

    /**
     * Ubicaciones de almacén donde está el producto
     */
    public function ubicaciones()
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_inventory', 'inventario_id', 'warehouse_id')
                    ->withPivot('cantidad', 'lote', 'fecha_vencimiento', 'estado', 'observaciones')
                    ->withTimestamps();
    }

    /**
     * Inventario en ubicaciones
     */
    public function warehouseInventory()
    {
        return $this->hasMany(WarehouseInventory::class, 'inventario_id');
    }

    /**
     * Movimientos de almacén
     */
    public function warehouseMovements()
    {
        return $this->hasMany(WarehouseMovement::class, 'inventario_id');
    }

    protected $fillable = [
        "id",
        "super",
        "codigo_proveedor",
        "codigo_barras",
        "id_proveedor",
        "id_categoria",
        "id_marca",
        "unidad",
        "id_deposito",
        "descripcion",
        "iva",
        "porcentaje_ganancia",
        "precio_base",
        "precio",
        "cantidad",

        "bulto",
        "precio1",
        "precio2",
        "precio3",

        "stockmin",
        "stockmax",
        "id_vinculacion",
        "push",
        "activo",

        // Datos físicos (cubicaje) — ver AddDatosFisicosToInventarios
        "peso_kg",
        "largo_cm",
        "ancho_cm",
        "alto_cm",
        "volumen_m3",
        "unidades_por_bulto",
        "peso_bulto_kg",
        "volumen_bulto_m3",
        "bultos_por_capa",
        "capas_por_paleta",
        "apilable",
        "max_apilamiento",
        "fragil",
        "requiere_refrigeracion",
        "peligroso",
        "datos_fisicos_fuente",
        "datos_fisicos_medido_en",
    ];

    protected $casts = [
        "peso_kg"                 => "decimal:4",
        "largo_cm"                => "decimal:2",
        "ancho_cm"                => "decimal:2",
        "alto_cm"                 => "decimal:2",
        "volumen_m3"              => "decimal:8",
        "unidades_por_bulto"      => "integer",
        "peso_bulto_kg"           => "decimal:4",
        "volumen_bulto_m3"        => "decimal:8",
        "bultos_por_capa"         => "integer",
        "capas_por_paleta"        => "integer",
        "apilable"                => "boolean",
        "max_apilamiento"         => "integer",
        "fragil"                  => "boolean",
        "requiere_refrigeracion"  => "boolean",
        "peligroso"               => "boolean",
        "datos_fisicos_medido_en" => "datetime",
    ];

    /**
     * Recalcula el volumen a partir de las dimensiones cada vez que cambian.
     * Se persiste para poder sumar y ordenar por volumen en SQL.
     */
    protected static function booted()
    {
        static::saving(function ($producto) {
            if ($producto->largo_cm && $producto->ancho_cm && $producto->alto_cm) {
                // cm³ -> m³
                $producto->volumen_m3 = round(
                    ($producto->largo_cm * $producto->ancho_cm * $producto->alto_cm) / 1000000,
                    8
                );

                $upb = (int) ($producto->unidades_por_bulto ?: $producto->bulto ?: 0);
                if ($upb > 0) {
                    $producto->volumen_bulto_m3 = round($producto->volumen_m3 * $upb, 8);
                    if ($producto->peso_kg !== null) {
                        $producto->peso_bulto_kg = round($producto->peso_kg * $upb, 4);
                    }
                }
            }
        });
    }

    /**
     * Clasificaciones ABC del producto (una por criterio).
     */
    public function clasificacionesAbc()
    {
        return $this->hasMany(ProductoAbc::class, 'inventario_id');
    }

    /**
     * Clase ABC vigente para un criterio. 'popularidad' es la que manda para slotting:
     * lo que importa para ubicar no es cuánto vale sino cuántas veces hay que ir a buscarlo.
     */
    public function claseAbc(string $criterio = 'popularidad'): ?string
    {
        return optional($this->clasificacionesAbc->firstWhere('criterio', $criterio))->clase;
    }

    /** ¿Tiene datos físicos utilizables? */
    public function tieneDatosFisicos(): bool
    {
        return $this->peso_kg !== null && $this->volumen_m3 !== null && (float) $this->volumen_m3 > 0;
    }

    /** ¿Los datos físicos son estimados (no medidos)? */
    public function datosFisicosEstimados(): bool
    {
        return $this->datos_fisicos_fuente === 'estimado';
    }

    /** Peso total de N unidades, en kg. Null si no hay dato. */
    public function pesoDe($cantidad): ?float
    {
        return $this->peso_kg === null ? null : round((float) $this->peso_kg * (float) $cantidad, 4);
    }

    /** Volumen total de N unidades, en m³. Null si no hay dato. */
    public function volumenDe($cantidad): ?float
    {
        return $this->volumen_m3 === null ? null : round((float) $this->volumen_m3 * (float) $cantidad, 8);
    }


}
