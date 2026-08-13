<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folios', function (Blueprint $table): void {
            $table->index(
                ['activo', 'tipo_bulto', 'estado_operacional'],
                'folios_activo_tipo_estado_idx',
            );
        });

        Schema::table('lotes_materia_prima', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'envase_primario', 'estado', 'created_at'],
                'lote_mp_temporada_envase_estado_idx',
            );
        });

        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'estado', 'ingreso_at'],
                'romana_temporada_estado_ingreso_idx',
            );
        });

        Schema::table('procesos_prefrio', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'estado', 'created_at'],
                'prefrio_temporada_estado_fecha_idx',
            );
        });

        Schema::table('cargas', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'estado', 'publicada_at'],
                'cargas_temporada_estado_publicada_idx',
            );
        });

        Schema::table('validaciones_pallet', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'estado', 'recibido_servidor_at'],
                'validacion_temporada_estado_fecha_idx',
            );
            $table->index(
                ['user_id', 'dispositivo_id', 'generado_dispositivo_at'],
                'validacion_usuario_dispositivo_sesion_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('validaciones_pallet', function (Blueprint $table): void {
            $table->dropIndex('validacion_temporada_estado_fecha_idx');
            $table->dropIndex('validacion_usuario_dispositivo_sesion_idx');
        });

        Schema::table('cargas', function (Blueprint $table): void {
            $table->dropIndex('cargas_temporada_estado_publicada_idx');
        });

        Schema::table('procesos_prefrio', function (Blueprint $table): void {
            $table->dropIndex('prefrio_temporada_estado_fecha_idx');
        });

        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->dropIndex('romana_temporada_estado_ingreso_idx');
        });

        Schema::table('lotes_materia_prima', function (Blueprint $table): void {
            $table->dropIndex('lote_mp_temporada_envase_estado_idx');
        });

        Schema::table('folios', function (Blueprint $table): void {
            $table->dropIndex('folios_activo_tipo_estado_idx');
        });
    }
};
