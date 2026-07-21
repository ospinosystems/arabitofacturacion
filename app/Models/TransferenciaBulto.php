<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bulto físico de una transferencia. Varios bultos por transferencia.
 */
class TransferenciaBulto extends Model
{
    protected $table = 'transferencia_bultos';

    protected $fillable = [
        'id_transferencia', 'numero', 'codigo_barras', 'estado',
        'cerrado_por', 'cerrado_at', 'despachado_por', 'despachado_at',
    ];

    protected $casts = [
        'cerrado_at' => 'datetime',
        'despachado_at' => 'datetime',
    ];

    public function transferencia()
    {
        return $this->belongsTo(transferencias_inventario::class, 'id_transferencia');
    }

    public function items()
    {
        return $this->hasMany(TransferenciaBultoItem::class, 'id_bulto');
    }
}
