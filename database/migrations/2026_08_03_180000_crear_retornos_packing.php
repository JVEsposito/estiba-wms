<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas_fruta_proceso', function (Blueprint $table): void {
            $table->decimal('kilos_enviados', 12, 3)->nullable()
                ->after('cantidad_envases');
        });

        Schema::create('tipos_resultado_packing', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique('tipo_resultado_pack_codigo_unique');
            $table->string('nombre', 100);
            $table->string('prefijo_sublote', 5);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $ahora = now();
        DB::table('tipos_resultado_packing')->insert([
            ['id' => (string) Str::uuid(), 'codigo' => 'precalibre', 'nombre' => 'Precalibre', 'prefijo_sublote' => 'PC', 'activo' => true, 'orden' => 10, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => (string) Str::uuid(), 'codigo' => 'comercial', 'nombre' => 'Comercial', 'prefijo_sublote' => 'CO', 'activo' => true, 'orden' => 20, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => (string) Str::uuid(), 'codigo' => 'descarte', 'nombre' => 'Descarte', 'prefijo_sublote' => 'DE', 'activo' => true, 'orden' => 30, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => (string) Str::uuid(), 'codigo' => 'otro', 'nombre' => 'Otro resultado', 'prefijo_sublote' => 'OT', 'activo' => true, 'orden' => 40, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        DB::table('secuencias_documentos')->updateOrInsert(
            ['clave' => 'retornos_packing'],
            ['ultimo_numero' => 0],
        );
        DB::table('secuencias_documentos')->updateOrInsert(
            ['clave' => 'sublotes_packing'],
            ['ultimo_numero' => 0],
        );

        Schema::create('retornos_packing', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique('retorno_pack_operacion_unique');
            $table->char('payload_hash', 64);
            $table->string('numero', 20)->unique('retorno_pack_numero_unique');
            $table->uuid('entrega_fruta_proceso_id');
            $table->boolean('cierra_entrega')->default(false);
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por_user_id');
            $table->uuid('dispositivo_id')->nullable();
            $table->timestamp('registrado_at', precision: 6);
            $table->uuid('operacion_anulacion_id')->nullable()
                ->unique('retorno_pack_anulacion_unique');
            $table->foreignId('anulado_por_user_id')->nullable();
            $table->timestamp('anulado_at', precision: 6)->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps(precision: 6);

            $table->foreign('entrega_fruta_proceso_id', 'retorno_pack_entrega_fk')
                ->references('id')->on('entregas_fruta_proceso')->restrictOnDelete();
            $table->foreign('registrado_por_user_id', 'retorno_pack_usuario_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('dispositivo_id', 'retorno_pack_dispositivo_fk')
                ->references('id')->on('dispositivos')->restrictOnDelete();
            $table->foreign('anulado_por_user_id', 'retorno_pack_anulado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->index(
                ['entrega_fruta_proceso_id', 'anulado_at', 'registrado_at'],
                'retorno_pack_entrega_estado_fecha_index',
            );
        });

        Schema::create('sublotes_retorno_packing', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('retorno_packing_id');
            $table->uuid('tipo_resultado_packing_id');
            $table->string('numero_sublote', 20)
                ->unique('sublote_pack_numero_unique');
            $table->string('nombre_resultado', 100);
            $table->unsignedInteger('cantidad_bins');
            $table->decimal('kilos_netos', 12, 3)->nullable();
            $table->string('estado', 30)->default('pendiente_ubicacion');
            $table->uuid('camara_id')->nullable();
            $table->uuid('operacion_ubicacion_id')->nullable()
                ->unique('sublote_pack_ubicacion_unique');
            $table->foreignId('ubicado_por_user_id')->nullable();
            $table->uuid('dispositivo_ubicacion_id')->nullable();
            $table->timestamp('ubicado_at', precision: 6)->nullable();
            $table->text('observacion_ubicacion')->nullable();
            $table->timestamps(precision: 6);

            $table->foreign('retorno_packing_id', 'sublote_pack_retorno_fk')
                ->references('id')->on('retornos_packing')->restrictOnDelete();
            $table->foreign('tipo_resultado_packing_id', 'sublote_pack_tipo_fk')
                ->references('id')->on('tipos_resultado_packing')->restrictOnDelete();
            $table->foreign('camara_id', 'sublote_pack_camara_fk')
                ->references('id')->on('camaras')->restrictOnDelete();
            $table->foreign('ubicado_por_user_id', 'sublote_pack_ubicado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('dispositivo_ubicacion_id', 'sublote_pack_dispositivo_fk')
                ->references('id')->on('dispositivos')->restrictOnDelete();
            $table->index(
                ['estado', 'tipo_resultado_packing_id'],
                'sublote_pack_estado_tipo_index',
            );
            $table->index(
                ['camara_id', 'ubicado_at'],
                'sublote_pack_camara_fecha_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sublotes_retorno_packing');
        Schema::dropIfExists('retornos_packing');
        Schema::dropIfExists('tipos_resultado_packing');

        DB::table('secuencias_documentos')
            ->whereIn('clave', ['retornos_packing', 'sublotes_packing'])
            ->delete();

        Schema::table('entregas_fruta_proceso', function (Blueprint $table): void {
            $table->dropColumn('kilos_enviados');
        });
    }
};
