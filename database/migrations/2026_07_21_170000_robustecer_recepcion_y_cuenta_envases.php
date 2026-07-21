<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->string('tipo_recepcion', 30)
                ->default('fruta_con_envases')
                ->after('cliente_nombre_snapshot');
            $table->string('concepto_envases', 20)
                ->nullable()
                ->after('tipo_recepcion');
            $table->string('estado_validacion_mp', 20)
                ->default('pendiente')
                ->index()
                ->after('estado');
            $table->foreignId('validacion_tomada_por_user_id')
                ->nullable()
                ->after('cerrado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('validacion_tomada_at')->nullable()->after('salida_at');
            $table->timestamp('validado_at')->nullable()->after('validacion_tomada_at');
        });

        Schema::create('detalles_envases_recepcion_romana', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('recepcion_romana_id')
                ->constrained('recepciones_romana')
                ->restrictOnDelete();
            $table->string('tipo_envase', 20);
            $table->unsignedInteger('cantidad_declarada');
            $table->unsignedInteger('cantidad_validada')->nullable();
            $table->timestamps();
            $table->unique(
                ['recepcion_romana_id', 'tipo_envase'],
                'detalle_envase_recepcion_tipo_unique',
            );
        });

        Schema::create('movimientos_envases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('recepcion_romana_id')
                ->nullable()
                ->constrained('recepciones_romana')
                ->restrictOnDelete();
            $table->string('documento_tipo', 40);
            $table->uuid('documento_id')->nullable();
            $table->string('numero_documento', 80);
            $table->string('tipo_movimiento', 30);
            $table->string('tipo_envase', 20);
            $table->unsignedInteger('cantidad');
            $table->smallInteger('signo_cuenta');
            $table->smallInteger('signo_existencia');
            $table->string('propiedad', 20);
            $table->foreignUuid('movimiento_origen_id')
                ->nullable()
                ->constrained('movimientos_envases')
                ->restrictOnDelete();
            $table->timestamp('ocurrido_at');
            $table->timestamp('ingreso_at')->nullable();
            $table->timestamp('salida_at')->nullable();
            $table->string('estado_revision', 20)->default('pendiente');
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->json('datos')->nullable();
            $table->timestamps();
            $table->index(['cliente_id', 'tipo_envase', 'ocurrido_at'], 'mov_envases_cliente_tipo_fecha_idx');
            $table->index(['temporada_id', 'ocurrido_at'], 'mov_envases_temporada_fecha_idx');
            $table->index(['documento_tipo', 'documento_id'], 'mov_envases_documento_idx');
        });

        Schema::create('revisiones_movimientos_envases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('movimiento_envase_id')
                ->constrained('movimientos_envases')
                ->restrictOnDelete();
            $table->string('estado', 20);
            $table->text('nota')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('revisado_at');
            $table->timestamps();
            $table->index(['movimiento_envase_id', 'revisado_at'], 'revision_mov_envase_fecha_idx');
        });

        Schema::table('notificaciones_operacionales', function (Blueprint $table): void {
            $table->foreignUuid('recepcion_romana_id')
                ->nullable()
                ->after('despacho_material_id')
                ->constrained('recepciones_romana')
                ->restrictOnDelete();
            $table->index(
                ['recepcion_romana_id', 'created_at'],
                'notificaciones_recepcion_romana_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones_operacionales', function (Blueprint $table): void {
            $table->dropIndex('notificaciones_recepcion_romana_fecha_idx');
            $table->dropConstrainedForeignId('recepcion_romana_id');
        });
        Schema::dropIfExists('revisiones_movimientos_envases');
        Schema::dropIfExists('movimientos_envases');
        Schema::dropIfExists('detalles_envases_recepcion_romana');
        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('validacion_tomada_por_user_id');
            $table->dropColumn([
                'tipo_recepcion',
                'concepto_envases',
                'estado_validacion_mp',
                'validacion_tomada_at',
                'validado_at',
            ]);
        });
    }
};
