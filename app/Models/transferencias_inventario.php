<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class transferencias_inventario extends Model
{
    use HasFactory;
    protected $fillable = ['id_transferencia_central', 'id_destino', 'id_usuario', 'estado', 'observacion'];

    public function items()
    {
        return $this->hasMany(transferencias_inventario_items::class, 'id_transferencia');
    }

    public function asignaciones()
    {
        return $this->hasMany(TransferenciaAsignacion::class, 'id_transferencia');
    }

    public function bultos()
    {
        return $this->hasMany(TransferenciaBulto::class, 'id_transferencia');
    }
}
