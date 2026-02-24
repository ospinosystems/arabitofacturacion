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
    ];
    



}
