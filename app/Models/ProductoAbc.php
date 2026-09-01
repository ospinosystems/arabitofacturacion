<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

/**
 * Clasificacion ABC vigente de un producto para un criterio dado.
 *
 * @see \App\Services\Wms\AbcClassificationService
 */
class ProductoAbc extends Model
{
    use HasFactory;

    protected $table = 'producto_abc';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'inventario_id',
        'criterio',
        'periodo_inicio',
        'periodo_fin',
        'unidades',
        'valor',
        'lineas',
        'metrica',
        'participacion_pct',
        'acumulado_pct',
        'clase',
        'ranking',
        'calculado_en',
    ];

    protected $casts = [
        'periodo_inicio'    => 'date',
        'periodo_fin'       => 'date',
        'unidades'          => 'decimal:4',
        'valor'             => 'decimal:4',
        'lineas'            => 'integer',
        'metrica'           => 'decimal:4',
        'participacion_pct' => 'decimal:6',
        'acumulado_pct'     => 'decimal:6',
        'ranking'           => 'integer',
        'calculado_en'      => 'datetime',
    ];

    public function inventario()
    {
        return $this->belongsTo(inventario::class, 'inventario_id');
    }

    public function scopeCriterio($query, $criterio)
    {
        return $query->where('criterio', $criterio);
    }

    public function scopeClase($query, $clase)
    {
        return $query->where('clase', strtoupper($clase));
    }

    /**
     * Mapa inventario_id => clase, para un criterio. Una sola consulta.
     */
    public static function mapaClases(array $ids, string $criterio = 'popularidad'): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        return static::where('criterio', $criterio)
            ->whereIn('inventario_id', $ids)
            ->pluck('clase', 'inventario_id')
            ->toArray();
    }
}
