<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class pedidos extends Model
{
    protected $fillable= [
        "id",
        "push",
        "estado",
        "export",
        "fecha_inicio",
        "fecha_vence",
        "formato_pago",
        "ticked",
        "id_cliente",
        "id_vendedor",
        "fiscal",
        "isdevolucionOriginalid",
        "fecha_factura",
        "retencion",
    ];
    // use HasFactory;

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

     public function vendedor() { 
        return $this->hasOne('App\Models\usuarios',"id","id_vendedor"); 
    }
    public function cliente() { 
        return $this->hasOne('App\Models\clientes',"id","id_cliente"); 
    }
    public function items() { 
        return $this->hasMany('App\Models\items_pedidos',"id_pedido","id"); 
    }
    public function referencias() { 
        return $this->hasMany('App\Models\pagos_referencias',"id_pedido","id"); 
    }
    public function pagos() { 
        return $this->hasMany('App\Models\pago_pedidos',"id_pedido","id"); 
    }
    public function retenciones() { 
        return $this->hasMany('App\Models\retenciones',"id_pedido","id"); 
    }
    
    // Relación con el pedido original (cuando este es una devolución)
    public function pedidoOriginal() { 
        return $this->belongsTo('App\Models\pedidos', 'isdevolucionOriginalid', 'id'); 
    }
    
    // Relación con las devoluciones que se han hecho de este pedido
    public function devoluciones() { 
        return $this->hasMany('App\Models\pedidos', 'isdevolucionOriginalid', 'id'); 
    }
}
