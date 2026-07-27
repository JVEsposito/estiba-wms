<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->string('tipo_envase_calculo_neto', 20)->nullable()->after('peso_neto');
            $table->unsignedInteger('cantidad_envase_calculo_neto')->nullable()->after('tipo_envase_calculo_neto');
            $table->decimal('peso_neto_por_envase', 12, 3)->nullable()->after('cantidad_envase_calculo_neto');
        });
        $this->compatibilizarRecepcionesCerradas();

        Schema::create('lotes_materia_prima', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique('lote_mp_operacion_unique');
            $table->char('payload_hash', 64);
            $table->uuid('segmento_validacion_mp_id');
            $table->uuid('recepcion_romana_id');
            $table->uuid('temporada_id');
            $table->uuid('cliente_id');
            $table->string('numero_lote', 80);
            $table->char('clave_numero_vigente', 64)->nullable()
                ->unique('lote_mp_numero_vigente_unique');
            $table->string('estado', 30)->index('lote_mp_estado_index');
            $table->uuid('csg_validacion_id');
            $table->string('csg_snapshot', 50);
            $table->string('sdp', 30);
            $table->char('ggn', 13);
            $table->date('fecha_cosecha');
            $table->string('predio', 150);
            $table->uuid('especie_validacion_id');
            $table->string('especie_snapshot', 100);
            $table->uuid('variedad_validacion_id');
            $table->string('variedad_snapshot', 100);
            $table->uuid('calibre_validacion_id');
            $table->string('calibre_snapshot', 50);
            $table->string('cuartel', 100);
            $table->string('tipo_producto', 30);
            $table->string('envase_primario', 20);
            $table->string('envase_secundario', 20)->nullable();
            $table->unsignedInteger('cantidad_envases_primarios');
            $table->unsignedInteger('cantidad_envases_secundarios')->default(0);
            $table->decimal('kilos_brutos', 12, 3);
            $table->decimal('kilos_netos_calculados', 12, 3);
            $table->decimal('kilos_netos_confirmados', 12, 3);
            $table->boolean('requiere_hidrocooler')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id');
            $table->foreignId('actualizado_por_user_id');
            $table->foreignId('confirmado_por_user_id')->nullable();
            $table->timestamp('confirmado_at')->nullable();
            $table->foreignId('anulado_por_user_id')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->foreign('segmento_validacion_mp_id', 'lote_mp_segmento_fk')
                ->references('id')->on('segmentos_validacion_mp')->restrictOnDelete();
            $table->foreign('recepcion_romana_id', 'lote_mp_recepcion_fk')
                ->references('id')->on('recepciones_romana')->restrictOnDelete();
            $table->foreign('temporada_id', 'lote_mp_temporada_fk')
                ->references('id')->on('temporadas')->restrictOnDelete();
            $table->foreign('cliente_id', 'lote_mp_cliente_fk')
                ->references('id')->on('clientes')->restrictOnDelete();
            $table->foreign('csg_validacion_id', 'lote_mp_csg_fk')
                ->references('id')->on('csg_validacion')->restrictOnDelete();
            $table->foreign('especie_validacion_id', 'lote_mp_especie_fk')
                ->references('id')->on('especies_validacion')->restrictOnDelete();
            $table->foreign('variedad_validacion_id', 'lote_mp_variedad_fk')
                ->references('id')->on('variedades_validacion')->restrictOnDelete();
            $table->foreign('calibre_validacion_id', 'lote_mp_calibre_fk')
                ->references('id')->on('calibres_validacion')->restrictOnDelete();
            $table->foreign('creado_por_user_id', 'lote_mp_creado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('actualizado_por_user_id', 'lote_mp_actualizado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('confirmado_por_user_id', 'lote_mp_confirmado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('anulado_por_user_id', 'lote_mp_anulado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();

            $table->index(
                ['segmento_validacion_mp_id', 'estado'],
                'lote_mp_segmento_estado_index',
            );
        });

        Schema::create('procesos_hidrocooler_materia_prima', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('lote_materia_prima_id')->unique('hidro_mp_lote_unique');
            $table->uuid('operacion_inicio_id')->unique('hidro_mp_inicio_unique');
            $table->uuid('operacion_termino_id')->nullable()->unique('hidro_mp_termino_unique');
            $table->string('estado', 20)->index('hidro_mp_estado_index');
            $table->string('equipo', 100);
            $table->timestamp('inicio_at');
            $table->timestamp('termino_at')->nullable();
            $table->unsignedInteger('duracion_minutos')->nullable();
            $table->decimal('temperatura_c', 6, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('iniciado_por_user_id');
            $table->foreignId('completado_por_user_id')->nullable();
            $table->timestamps();

            $table->foreign('lote_materia_prima_id', 'hidro_mp_lote_fk')
                ->references('id')->on('lotes_materia_prima')->restrictOnDelete();
            $table->foreign('iniciado_por_user_id', 'hidro_mp_iniciado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('completado_por_user_id', 'hidro_mp_completado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('asignaciones_camara_lote_materia_prima', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique('asig_cam_lote_mp_operacion_unique');
            $table->uuid('lote_materia_prima_id')->unique('asig_cam_lote_mp_lote_unique');
            $table->uuid('camara_id');
            $table->foreignId('asignado_por_user_id');
            $table->timestamp('asignado_at');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->foreign('lote_materia_prima_id', 'asig_cam_lote_mp_lote_fk')
                ->references('id')->on('lotes_materia_prima')->restrictOnDelete();
            $table->foreign('camara_id', 'asig_cam_lote_mp_camara_fk')
                ->references('id')->on('camaras')->restrictOnDelete();
            $table->foreign('asignado_por_user_id', 'asig_cam_lote_mp_usuario_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('eventos_lote_materia_prima', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('lote_materia_prima_id');
            $table->uuid('operacion_id')->nullable()->unique('evento_lote_mp_operacion_unique');
            $table->string('tipo', 40);
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30)->nullable();
            $table->foreignId('user_id');
            $table->timestamp('ocurrido_at');
            $table->json('datos')->nullable();
            $table->timestamps();

            $table->foreign('lote_materia_prima_id', 'evento_lote_mp_lote_fk')
                ->references('id')->on('lotes_materia_prima')->restrictOnDelete();
            $table->foreign('user_id', 'evento_lote_mp_usuario_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->index(
                ['lote_materia_prima_id', 'ocurrido_at'],
                'evento_lote_mp_lote_fecha_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_lote_materia_prima');
        Schema::dropIfExists('asignaciones_camara_lote_materia_prima');
        Schema::dropIfExists('procesos_hidrocooler_materia_prima');
        Schema::dropIfExists('lotes_materia_prima');

        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->dropColumn([
                'tipo_envase_calculo_neto',
                'cantidad_envase_calculo_neto',
                'peso_neto_por_envase',
            ]);
        });
    }

    private function compatibilizarRecepcionesCerradas(): void
    {
        DB::table('recepciones_romana')
            ->whereNotNull('peso_neto')
            ->whereNull('peso_neto_por_envase')
            ->orderBy('created_at')
            ->get(['id', 'tipo_envase_declarado', 'peso_neto'])
            ->each(function (object $recepcion): void {
                $detalle = DB::table('detalles_envases_recepcion_romana')
                    ->where('recepcion_romana_id', $recepcion->id)
                    ->where('tipo_envase', $recepcion->tipo_envase_declarado)
                    ->first(['tipo_envase', 'cantidad_declarada']);
                if (! $detalle) {
                    $detalle = DB::table('detalles_envases_recepcion_romana')
                        ->where('recepcion_romana_id', $recepcion->id)
                        ->orderBy('created_at')
                        ->first(['tipo_envase', 'cantidad_declarada']);
                }
                if (! $detalle || (int) $detalle->cantidad_declarada < 1) {
                    return;
                }

                DB::table('recepciones_romana')
                    ->where('id', $recepcion->id)
                    ->update([
                        'tipo_envase_calculo_neto' => $detalle->tipo_envase,
                        'cantidad_envase_calculo_neto' => $detalle->cantidad_declarada,
                        'peso_neto_por_envase' => round(
                            (float) $recepcion->peso_neto
                            / (int) $detalle->cantidad_declarada,
                            3,
                        ),
                    ]);
            });
    }
};
