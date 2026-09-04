<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maniobras_operacionales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_operacional_id')->constrained('planes_operacionales')->restrictOnDelete();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->string('estado', 30)->default('pendiente');
            $table->string('prioridad', 20)->default('normal');
            $table->string('candidate_key', 190);
            $table->string('titulo', 180);
            $table->text('motivo')->nullable();
            $table->unsignedInteger('secuencia_actual')->default(1);
            $table->unsignedInteger('costo_movimientos');
            $table->integer('beneficio_estimado')->default(0);
            $table->unsignedInteger('riesgo_operacional')->default(0);
            $table->json('contexto')->nullable();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->timestamp('asumida_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('pausada_at')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['plan_operacional_id', 'candidate_key'], 'maniobras_plan_candidate_idx');
            $table->index(['estado', 'prioridad', 'created_at'], 'maniobras_estado_prioridad_fecha_idx');
            $table->index(['responsable_user_id', 'estado'], 'maniobras_responsable_estado_idx');
            $table->index(['creado_por_user_id', 'created_at'], 'maniobras_creador_fecha_idx');
        });

        Schema::create('maniobra_objetivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('maniobra_operacional_id')->constrained('maniobras_operacionales')->restrictOnDelete();
            $table->foreignUuid('plan_operacional_id')->constrained('planes_operacionales')->restrictOnDelete();
            $table->boolean('es_principal')->default(false);
            $table->integer('beneficio_estimado')->default(0);
            $table->json('contexto')->nullable();
            $table->timestamps();

            $table->unique(['maniobra_operacional_id', 'plan_operacional_id'], 'maniobra_objetivo_unique');
        });

        Schema::table('tareas_movimiento', function (Blueprint $table): void {
            $table->foreignUuid('maniobra_operacional_id')
                ->nullable()
                ->after('plan_operacional_id')
                ->constrained('maniobras_operacionales')
                ->restrictOnDelete();
            $table->unsignedInteger('secuencia_maniobra')->nullable()->after('secuencia');
            $table->string('tipo_paso_maniobra', 40)->nullable()->after('tipo_movimiento');
            $table->index(
                ['maniobra_operacional_id', 'secuencia_maniobra'],
                'tareas_maniobra_secuencia_idx',
            );
        });

        Schema::create('custodias_temporales_maniobra', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('maniobra_operacional_id')->constrained('maniobras_operacionales')->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->foreignUuid('tarea_extraccion_id')->constrained('tareas_movimiento')->restrictOnDelete();
            $table->foreignUuid('tarea_resolucion_id')->nullable()->constrained('tareas_movimiento')->restrictOnDelete();
            $table->foreignUuid('camara_origen_id')->constrained('camaras')->restrictOnDelete();
            $table->foreignUuid('posicion_origen_id')->constrained('posiciones')->restrictOnDelete();
            $table->unsignedInteger('banda_origen');
            $table->unsignedInteger('posicion_origen');
            $table->unsignedInteger('nivel_origen');
            $table->string('estado', 30)->default('activa');
            $table->foreignUuid('bloqueo_folio_id')->nullable()->constrained('folios')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->timestamp('extraido_at');
            $table->timestamp('resuelto_at')->nullable();
            $table->json('contexto')->nullable();
            $table->timestamps();

            $table->unique('bloqueo_folio_id', 'custodias_bloqueo_folio_unique');
            $table->index(['maniobra_operacional_id', 'estado'], 'custodias_maniobra_estado_idx');
        });

        Schema::create('reservas_bandas_maniobra', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('maniobra_operacional_id')->constrained('maniobras_operacionales')->restrictOnDelete();
            $table->foreignUuid('camara_id')->constrained('camaras')->restrictOnDelete();
            $table->unsignedInteger('banda');
            $table->unsignedInteger('nivel');
            $table->string('clave_bloqueo', 190)->nullable();
            $table->timestamp('reservada_at');
            $table->timestamp('liberada_at')->nullable();
            $table->string('motivo_liberacion', 255)->nullable();
            $table->timestamps();

            $table->unique('clave_bloqueo', 'reservas_bandas_clave_unique');
            $table->index(['maniobra_operacional_id', 'liberada_at'], 'reservas_bandas_maniobra_idx');
        });

        Schema::create('discrepancias_maniobra', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('maniobra_operacional_id')->constrained('maniobras_operacionales')->restrictOnDelete();
            $table->foreignUuid('tarea_movimiento_id')->constrained('tareas_movimiento')->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->string('tipo', 50);
            $table->text('detalle')->nullable();
            $table->string('estado', 20)->default('abierta');
            $table->foreignId('reportada_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->timestamp('reportada_at');
            $table->foreignId('resuelta_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resuelta_at')->nullable();
            $table->text('resolucion')->nullable();
            $table->timestamps();

            $table->index(['maniobra_operacional_id', 'estado'], 'discrepancias_maniobra_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discrepancias_maniobra');
        Schema::dropIfExists('reservas_bandas_maniobra');
        Schema::dropIfExists('custodias_temporales_maniobra');

        Schema::table('tareas_movimiento', function (Blueprint $table): void {
            $table->dropIndex('tareas_maniobra_secuencia_idx');
            $table->dropColumn(['secuencia_maniobra', 'tipo_paso_maniobra']);
            $table->dropConstrainedForeignId('maniobra_operacional_id');
        });

        Schema::dropIfExists('maniobra_objetivos');
        Schema::dropIfExists('maniobras_operacionales');
    }
};
