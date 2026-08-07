<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bins_retorno_packing', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->string('folio_provisional', 80)->unique();
            $table->string('folio_definitivo', 80)->nullable()->unique();
            $table->decimal('kilos_totales', 12, 3);
            $table->foreignUuid('tipo_resultado_packing_id')
                ->nullable()
                ->constrained('tipos_resultado_packing')
                ->nullOnDelete();
            $table->string('nombre_resultado', 100)->nullable();
            $table->string('estado', 40)->default('pendiente_regularizacion')->index();
            $table->foreignUuid('retorno_packing_legacy_id')
                ->nullable()
                ->unique()
                ->constrained('retornos_packing')
                ->restrictOnDelete();
            $table->foreignId('registrado_por_user_id')->constrained('users');
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->nullOnDelete();
            $table->timestamp('registrado_at');
            $table->uuid('operacion_regularizacion_id')->nullable()->unique();
            $table->foreignId('regularizado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('regularizado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps(precision: 6);

            $table->index(['estado', 'registrado_at'], 'bins_retorno_estado_fecha_index');
        });

        Schema::create('bin_retorno_packing_origenes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('bin_retorno_packing_id')
                ->constrained('bins_retorno_packing')
                ->cascadeOnDelete();
            $table->foreignUuid('lote_materia_prima_id')
                ->constrained('lotes_materia_prima')
                ->restrictOnDelete();
            $table->string('numero_lote', 100)->nullable();
            $table->string('numero_orden', 80);
            $table->string('linea_proceso', 50);
            $table->string('turno', 20);
            $table->char('clave_proceso', 64);
            $table->decimal('kilos_aportados', 12, 3);
            $table->timestamps(precision: 6);

            $table->unique(
                ['bin_retorno_packing_id', 'clave_proceso'],
                'bin_retorno_origen_proceso_unique',
            );
            $table->index('lote_materia_prima_id');
        });

        Schema::create('regularizaciones_retorno_packing_legacy', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->uuid('retorno_packing_id');
            $table->unique('retorno_packing_id', 'reg_retorno_legacy_retorno_unique');
            $table->foreign(
                'retorno_packing_id',
                'reg_retorno_legacy_retorno_fk',
            )->references('id')->on('retornos_packing')->restrictOnDelete();
            $table->uuid('bin_retorno_packing_id')->nullable();
            $table->foreign(
                'bin_retorno_packing_id',
                'reg_retorno_legacy_bin_fk',
            )->references('id')->on('bins_retorno_packing')->nullOnDelete();
            $table->string('accion', 30)->index();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->foreign(
                'registrado_por_user_id',
                'reg_retorno_legacy_user_fk',
            )->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('registrado_at');
            $table->timestamps(precision: 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularizaciones_retorno_packing_legacy');
        Schema::dropIfExists('bin_retorno_packing_origenes');
        Schema::dropIfExists('bins_retorno_packing');
    }
};
