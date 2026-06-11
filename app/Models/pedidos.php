<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class pedidos extends Model
{
    // PERF 2026-05-27 — bump del version-stamp en cada cambio de pedidos.
    // getPedidosFast usa este número en su cache key, así un INSERT/UPDATE/DELETE
    // invalida instantáneamente el cache de la lista sin que tengamos que conocer
    // todos los keys derivados (por vendedor, por fecha).
    //
    // CUADRE-INTERCEPTOR 2026-06-11 — captura CUALQUIER intento de cerrar un pedido
    // (estado 0→1) e independientemente del path que vino. Loguea con stacktrace y
    // bloquea si descuadra. Caso reportado por usuario: pedido #343756 cerrado con
    // $26.84 cobrado vs $49.30 que valía — la validación de setPagoPedido no atajó.
    // Esto cubre cualquier path edge que se nos haya escapado.
    //
    // Flag bypass estático: `pedidos::$skipCuadreCheck = true;` para flujos que el
    // admin sabe que cierran sin pago (cortesía explícita, restauración, etc.).
    public static bool $skipCuadreCheck = false;

    protected static function booted()
    {
        $bump = function () {
            try {
                $current = (int) (\Cache::get('pedidos_fast_version', 0));
                \Cache::forever('pedidos_fast_version', $current + 1);
            } catch (\Throwable $e) {
                // si falla cache, seguimos — solo perdemos micro-cache
            }
        };
        static::saved($bump);
        static::deleted($bump);

        // CUADRE-INTERCEPTOR — antes de guardar, si estado pasa de 0→1, validar cuadre.
        static::saving(function (pedidos $pedido) {
            // Solo intervenir si estado va a quedar en 1 y antes no lo era
            $estadoNuevo = (int) ($pedido->estado ?? 0);
            $estadoAnterior = (int) ($pedido->getOriginal('estado') ?? 0);

            if ($estadoNuevo !== 1 || $estadoAnterior === 1) {
                return; // no es transición 0→1, no aplica
            }

            if (self::$skipCuadreCheck) {
                \Log::warning('[CUADRE-INTERCEPTOR] cierre con skipCuadreCheck=true', [
                    'id_pedido' => $pedido->id,
                ]);
                return;
            }

            try {
                $cuadre = \App\Http\Controllers\PagoPedidosController::validarCuadrePedido((int) $pedido->id);
                if (!$cuadre['cuadra']) {
                    // Capturar trace simplificado (3 frames con archivo:linea)
                    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
                    $frames = array_map(function ($f) {
                        return ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' → ' .
                               ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '');
                    }, array_slice($bt, 1, 7));

                    \Log::error('[CUADRE-INTERCEPTOR] BLOQUEADO cierre descuadrado', [
                        'id_pedido' => $pedido->id,
                        'cuadre' => $cuadre,
                        'usuario' => session('usuario') ?? 'cli',
                        'stack' => $frames,
                    ]);

                    throw new \RuntimeException(
                        "BLOQUEADO: Pedido #{$pedido->id} no cuadra. " .
                        "Total USD " . $cuadre['total_pedido_usd'] .
                        " vs Pagos USD " . $cuadre['total_pagos_usd'] .
                        " (diff " . $cuadre['diferencia_usd'] . "). " .
                        "Verificá pagos antes de cerrar."
                    );
                }

                // Cuadre OK — log info con trace abreviado para auditoría
                \Log::info('[CUADRE-INTERCEPTOR] cierre OK', [
                    'id_pedido' => $pedido->id,
                    'cuadre' => $cuadre,
                    'usuario' => session('usuario') ?? 'cli',
                ]);
            } catch (\RuntimeException $e) {
                // Re-throw para que Laravel lo propague y reverse la transacción
                throw $e;
            } catch (\Throwable $e) {
                // Cualquier otra excepción al validar — loguear pero NO bloquear
                // (no romper el flujo de cobro por un error del interceptor)
                \Log::warning('[CUADRE-INTERCEPTOR] error al validar (NO bloquea)', [
                    'id_pedido' => $pedido->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    protected $fillable= [
        "id",
        "uuid",
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
        "numero_factura",
        "maquina_fiscal",
        "valido",
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
