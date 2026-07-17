<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folios', function (Blueprint $table) {
            $table->string('condicion_termica', 50)->nullable()->index()->after('estado_operacional');
            $table->string('habilitacion_almacenamiento', 50)->nullable()->index()->after('condicion_termica');
            $table->string('fuente_habilitacion_almacenamiento', 80)->nullable()->index()->after('habilitacion_almacenamiento');
            $table->dateTime('habilitado_almacenamiento_at')->nullable()->after('fuente_habilitacion_almacenamiento');
            $table->foreignId('habilitado_almacenamiento_por_user_id')
                ->nullable()
                ->after('habilitado_almacenamiento_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('retencion_termica_motivo')->nullable()->after('habilitado_almacenamiento_por_user_id');
        });

        DB::table('folios')
            ->whereIn('tipo_bulto', ['pallet', 'saldo'])
            ->where('estado_operacional', 'pendiente_prefrio')
            ->update([
                'condicion_termica' => 'pendiente_prefrio',
                'habilitacion_almacenamiento' => 'no_habilitado',
            ]);

        DB::table('folios')
            ->whereIn('tipo_bulto', ['pallet', 'saldo'])
            ->where('estado_operacional', '!=', 'pendiente_prefrio')
            ->update([
                'condicion_termica' => 'condicion_heredada',
                'habilitacion_almacenamiento' => 'habilitado',
                'fuente_habilitacion_almacenamiento' => 'regularizacion_existente',
                'habilitado_almacenamiento_at' => now(),
            ]);

        Schema::create('tuneles_prefrio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            $table->unsignedInteger('capacidad_posiciones');
            $table->decimal('setpoint_habitual', 5, 2)->nullable();
            $table->string('estado_administrativo', 30)->default('activo')->index();
            $table->string('estado_tecnico', 30)->default('operativo')->index();
            $table->string('codigo_externo', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('version_configuracion')->default(0);
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('posiciones_tunel_prefrio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tunel_prefrio_id')->constrained('tuneles_prefrio')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->string('etiqueta', 50);
            $table->boolean('activa')->default(true)->index();
            $table->timestamps();

            $table->unique(['tunel_prefrio_id', 'numero'], 'posicion_tunel_numero_unique');
            $table->unique(['tunel_prefrio_id', 'etiqueta'], 'posicion_tunel_etiqueta_unique');
        });

        Schema::create('secuencias_procesos_prefrio', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        Schema::create('procesos_prefrio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 40)->unique();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('tunel_prefrio_id')->constrained('tuneles_prefrio')->restrictOnDelete();
            $table->string('estado', 40)->default('borrador')->index();
            $table->decimal('setpoint', 5, 2);
            $table->unsignedInteger('duracion_objetivo_minutos')->nullable();
            $table->string('formato_referencia', 100)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->foreignId('iniciado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('finalizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('iniciado_at')->nullable();
            $table->dateTime('pendiente_verificacion_at')->nullable();
            $table->dateTime('finalizado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        Schema::create('procesos_prefrio_folios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proceso_prefrio_id')->constrained('procesos_prefrio')->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->foreignUuid('posicion_tunel_prefrio_id')->constrained('posiciones_tunel_prefrio')->restrictOnDelete();
            $table->string('estado', 40)->default('cargado')->index();
            $table->decimal('temperatura_inicial', 5, 2)->nullable();
            $table->decimal('temperatura_final', 5, 2)->nullable();
            $table->dateTime('cargado_at');
            $table->dateTime('retirado_at')->nullable();
            $table->string('motivo_resultado', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('cargado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('retirado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['proceso_prefrio_id', 'folio_id'], 'proceso_prefrio_folio_unique');
            $table->unique(['proceso_prefrio_id', 'posicion_tunel_prefrio_id'], 'proceso_prefrio_posicion_unique');
        });

        Schema::create('eventos_prefrio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('proceso_prefrio_id')->constrained('procesos_prefrio')->restrictOnDelete();
            $table->foreignUuid('proceso_prefrio_folio_id')->nullable()->constrained('procesos_prefrio_folios')->restrictOnDelete();
            $table->string('tipo', 50)->index();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->dateTime('ocurrido_at');
            $table->json('datos')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_prefrio');
        Schema::dropIfExists('procesos_prefrio_folios');
        Schema::dropIfExists('procesos_prefrio');
        Schema::dropIfExists('secuencias_procesos_prefrio');
        Schema::dropIfExists('posiciones_tunel_prefrio');
        Schema::dropIfExists('tuneles_prefrio');

        Schema::table('folios', function (Blueprint $table) {
            $table->dropForeign(['habilitado_almacenamiento_por_user_id']);
            $table->dropColumn([
                'condicion_termica',
                'habilitacion_almacenamiento',
                'fuente_habilitacion_almacenamiento',
                'habilitado_almacenamiento_at',
                'habilitado_almacenamiento_por_user_id',
                'retencion_termica_motivo',
            ]);
        });
    }
};
