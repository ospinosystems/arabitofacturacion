<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FISCAL-LOGS 2026-05-27
 *
 * Tabla para persistir cada request enviado a la impresora fiscal y su respuesta.
 * Antes solo existía log en archivo (storage/logs/fiscal-YYYY-MM-DD.log) — bueno
 * para diagnóstico técnico pero difícil de consultar desde el admin operativo.
 *
 * Esta tabla expone los datos via endpoint /fiscal/logs para que el admin pueda
 * filtrar por id_pedido, caja, status, rango de fechas — sin necesidad de SSH.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_logs', function (Blueprint $table) {
            $table->id();

            // Contexto del pedido (si aplica — algunos requests como reportefiscal no tienen pedido)
            $table->unsignedBigInteger('id_pedido')->nullable()->index();

            // Tipo de operación: 'factura', 'notacredito', 'reportefiscal', etc.
            $table->string('type', 50)->index();

            // Caja / usuario que disparó la operación
            $table->string('caja', 100)->nullable()->index();
            $table->string('origen', 100)->nullable(); // codigo_origen sucursal
            $table->string('nombre_equipo', 100)->nullable(); // ej: "caja2"

            // Conexión
            $table->string('ip_resolved', 50)->nullable();
            $table->string('url', 255)->nullable();

            // Payload — guardamos el JSON tal cual se mandó/recibió
            $table->json('request_payload')->nullable();
            $table->integer('response_status')->nullable()->index();
            $table->longText('response_body')->nullable();
            $table->boolean('successful')->default(false)->index();

            // Si hubo excepción/timeout
            $table->text('error')->nullable();

            // Performance
            $table->integer('ms_duration')->nullable();

            $table->timestamps();

            // Índice compuesto para queries comunes (logs del día por caja)
            $table->index(['created_at', 'caja'], 'fiscal_logs_created_caja_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_logs');
    }
};
