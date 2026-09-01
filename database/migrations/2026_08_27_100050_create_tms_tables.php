<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TMS: flota, rutas y prueba de entrega.
 *
 * El WMS termina en el muelle; el TMS empieza ahi. La planificacion de carga usa
 * los mismos datos fisicos del producto (peso/volumen) que el slotting, por eso
 * depende de la migracion de datos fisicos.
 */
class CreateTmsTables extends Migration
{
    public function up()
    {
        Schema::create('tms_conductores', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('nombre');
            $table->string('documento', 40)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('licencia', 40)->nullable();
            $table->date('licencia_vence')->nullable();
            $table->enum('estado', ['disponible', 'en_ruta', 'inactivo'])->default('disponible');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('usuario_id');
        });

        Schema::create('tms_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 20)->unique();
            $table->string('nombre')->nullable();
            $table->enum('tipo', ['moto', 'camioneta', 'camion', 'furgon', 'trailer'])->default('camion');

            // Capacidades: el par que limita la carga. Casi siempre se llena uno antes
            // que el otro: mercancia densa satura el peso, voluminosa satura el cubicaje.
            $table->decimal('capacidad_peso_kg', 12, 2)->default(0);
            $table->decimal('capacidad_volumen_m3', 12, 4)->default(0);
            $table->unsignedInteger('capacidad_bultos')->nullable();

            $table->boolean('refrigerado')->default(false);
            $table->decimal('costo_km', 12, 4)->nullable();
            $table->decimal('costo_fijo_viaje', 12, 4)->nullable();

            $table->enum('estado', ['disponible', 'en_ruta', 'mantenimiento', 'inactivo'])->default('disponible');
            $table->unsignedBigInteger('conductor_habitual_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('tipo');
        });

        Schema::create('tms_rutas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->date('fecha');
            $table->enum('estado', ['planificada', 'cargando', 'en_ruta', 'completada', 'cancelada'])
                  ->default('planificada');

            $table->unsignedBigInteger('vehiculo_id')->nullable();
            $table->unsignedBigInteger('conductor_id')->nullable();

            // Totales cargados (se recalculan al agregar/quitar paradas)
            $table->decimal('peso_total_kg', 14, 4)->default(0);
            $table->decimal('volumen_total_m3', 14, 6)->default(0);
            $table->unsignedInteger('bultos_total')->default(0);
            $table->decimal('utilizacion_peso_pct', 7, 2)->nullable();
            $table->decimal('utilizacion_volumen_pct', 7, 2)->nullable();

            $table->decimal('distancia_estimada_km', 10, 2)->nullable();
            $table->unsignedInteger('tiempo_estimado_min')->nullable();
            $table->decimal('costo_estimado', 14, 4)->nullable();

            $table->timestamp('salida_real')->nullable();
            $table->timestamp('retorno_real')->nullable();

            $table->unsignedInteger('usuario_planificador_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('fecha');
            $table->index('vehiculo_id');
        });

        Schema::create('tms_paradas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ruta_id');
            $table->unsignedInteger('orden')->default(1);
            $table->enum('tipo', ['entrega', 'recogida', 'deposito'])->default('entrega');

            // Origen de la carga: orden de despacho TCD o pedido directo.
            $table->unsignedBigInteger('tcd_orden_id')->nullable();
            $table->unsignedInteger('pedido_id')->nullable();
            $table->unsignedInteger('cliente_id')->nullable();

            $table->string('destino_nombre')->nullable();
            $table->string('direccion', 500)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            $table->decimal('peso_kg', 14, 4)->default(0);
            $table->decimal('volumen_m3', 14, 6)->default(0);
            $table->unsignedInteger('bultos')->default(0);

            $table->time('ventana_inicio')->nullable();
            $table->time('ventana_fin')->nullable();

            $table->enum('estado', ['pendiente', 'en_sitio', 'entregada', 'parcial', 'fallida', 'reprogramada'])
                  ->default('pendiente');
            $table->timestamp('llegada_real')->nullable();
            $table->timestamp('salida_real')->nullable();

            // Prueba de entrega
            $table->string('pod_recibido_por')->nullable();
            $table->string('pod_documento', 40)->nullable();
            $table->string('pod_firma_path', 255)->nullable();
            $table->timestamp('pod_at')->nullable();
            $table->string('motivo_fallo', 255)->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('ruta_id')->references('id')->on('tms_rutas')->onDelete('cascade');
            $table->index(['ruta_id', 'orden']);
            $table->index('estado');
            $table->index('tcd_orden_id');
        });

        Schema::create('tms_parada_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parada_id');
            $table->unsignedInteger('inventario_id');
            $table->string('descripcion')->nullable();

            $table->decimal('cantidad', 14, 4)->default(0);
            $table->decimal('cantidad_entregada', 14, 4)->default(0);

            // Congelados al planificar: si manana cambia la ficha del producto,
            // el manifiesto firmado no debe cambiar retroactivamente.
            $table->decimal('peso_kg', 14, 4)->default(0);
            $table->decimal('volumen_m3', 14, 6)->default(0);

            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('parada_id')->references('id')->on('tms_paradas')->onDelete('cascade');
            $table->index('inventario_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tms_parada_items');
        Schema::dropIfExists('tms_paradas');
        Schema::dropIfExists('tms_rutas');
        Schema::dropIfExists('tms_vehiculos');
        Schema::dropIfExists('tms_conductores');
    }
}
