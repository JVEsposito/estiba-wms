<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->decimal('peso_bruto', 12, 3)->change();
            $table->decimal('peso_tara', 12, 3)->nullable()->change();
            $table->decimal('peso_neto', 12, 3)->nullable()->change();
            $table->string('tipo_envase_pesaje', 20)
                ->nullable()
                ->after('tipo_envase_calculo_neto');
            $table->decimal('tara_unitaria_envase', 10, 3)
                ->nullable()
                ->after('tipo_envase_pesaje');
            $table->unsignedInteger('cantidad_envases_pesados')
                ->default(0)
                ->after('tara_unitaria_envase');
        });

        Schema::create('pesajes_envases_recepcion_romana', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('recepcion_romana_id')
                ->constrained('recepciones_romana')
                ->restrictOnDelete();
            $table->unsignedInteger('secuencia');
            $table->string('tipo_envase', 20);
            $table->unsignedInteger('cantidad_envases');
            $table->decimal('peso_bruto', 12, 3);
            $table->decimal('tara_unitaria_envase', 10, 3);
            $table->decimal('peso_tara', 12, 3);
            $table->decimal('peso_neto', 12, 3);
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('pesado_at');
            $table->uuid('operacion_anulacion_id')->nullable()->unique();
            $table->char('payload_anulacion_hash', 64)->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->unique(
                ['recepcion_romana_id', 'secuencia'],
                'pesaje_envases_recepcion_secuencia_unique',
            );
            $table->index(
                ['recepcion_romana_id', 'anulado_at', 'pesado_at'],
                'pesaje_envases_recepcion_estado_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesajes_envases_recepcion_romana');

        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->decimal('peso_bruto', 10, 2)->change();
            $table->decimal('peso_tara', 10, 2)->nullable()->change();
            $table->decimal('peso_neto', 10, 2)->nullable()->change();
            $table->dropColumn([
                'tipo_envase_pesaje',
                'tara_unitaria_envase',
                'cantidad_envases_pesados',
            ]);
        });
    }
};
