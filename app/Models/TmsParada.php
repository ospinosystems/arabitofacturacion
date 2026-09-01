<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class TmsParada extends Model
{
    protected $table = 'tms_paradas';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'ruta_id',
        'orden',
        'tipo',
        'tcd_orden_id',
        'pedido_id',
        'cliente_id',
        'destino_nombre',
        'direccion',
        'latitud',
        'longitud',
        'peso_kg',
        'volumen_m3',
        'bultos',
        'ventana_inicio',
        'ventana_fin',
        'estado',
        'llegada_real',
        'salida_real',
        'pod_recibido_por',
        'pod_documento',
        'pod_firma_path',
        'pod_at',
        'motivo_fallo',
        'observaciones',
    ];

    protected $casts = [
        'orden'        => 'integer',
        'latitud'      => 'decimal:7',
        'longitud'     => 'decimal:7',
        'peso_kg'      => 'decimal:4',
        'volumen_m3'   => 'decimal:6',
        'bultos'       => 'integer',
        'llegada_real' => 'datetime',
        'salida_real'  => 'datetime',
        'pod_at'       => 'datetime',
    ];

    public function ruta()
    {
        return $this->belongsTo(TmsRuta::class, 'ruta_id');
    }

    public function items()
    {
        return $this->hasMany(TmsParadaItem::class, 'parada_id');
    }

    public function tcdOrden()
    {
        return $this->belongsTo(TCDOrden::class, 'tcd_orden_id');
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'en_sitio']);
    }

    /**
     * Recalcula peso/volumen de la parada desde sus items.
     */
    public function recalcularCarga(): void
    {
        $items = $this->items()->get();

        $this->peso_kg    = $items->sum(fn ($i) => (float) $i->peso_kg);
        $this->volumen_m3 = $items->sum(fn ($i) => (float) $i->volumen_m3);
        $this->save();
    }

    /**
     * Una entrega es parcial cuando algun item quedo corto.
     */
    public function esParcial(): bool
    {
        return $this->items()
            ->whereColumn('cantidad_entregada', '<', 'cantidad')
            ->exists();
    }
}
