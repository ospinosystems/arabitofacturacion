<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TCRNovedad extends Model
{
    use HasFactory;
    
    protected $table = 'tcr_novedades';
    
    protected $fillable = [
        'pedido_central_id',
        'item_pedido_id',
        'codigo_barras',
        'codigo_proveedor',
        'descripcion',
        'cantidad_llego',
        'cantidad_enviada',
        'diferencia',
        'inventario_id',
        'pasillero_id',
        'chequeador_id',
        'estado',
        'observaciones',
    ];
    
    protected $casts = [
        'cantidad_llego' => 'decimal:4',
        'cantidad_enviada' => 'decimal:4',
        'diferencia' => 'decimal:4',
    ];
    
    // Relaciones
    public function pasillero()
    {
        return $this->belongsTo(usuarios::class, 'pasillero_id');
    }
    
    public function chequeador()
    {
        return $this->belongsTo(usuarios::class, 'chequeador_id');
    }
    
    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }
    
    // Scopes
    public function scopePorPasillero($query, $pasilleroId)
    {
        return $query->where('pasillero_id', $pasilleroId);
    }
    
    public function scopePorPedido($query, $pedidoId)
    {
        return $query->where('pedido_central_id', $pedidoId);
    }
    
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }
    
    // Métodos auxiliares
    public function calcularDiferencia()
    {
        $this->diferencia = $this->cantidad_llego - $this->cantidad_enviada;
        return $this->diferencia;
    }
}

