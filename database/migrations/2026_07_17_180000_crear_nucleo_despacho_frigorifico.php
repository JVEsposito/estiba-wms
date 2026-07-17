<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andenes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->string('codigo_externo', 100)->nullable()->index();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('cargas', function (Blueprint $table) {
            $table->foreignUuid('anden_previsto_id')
                ->nullable()
                ->after('camara_objetivo_id')
                ->constrained('andenes')
                ->restrictOnDelete();
            $table->uuid('operacion_cierre_id')->nullable()->unique()->after('cancelada_at');
            $table->char('cierre_payload_hash', 64)->nullable()->after('operacion_cierre_id');
            $table->string('patente', 20)->nullable()->after('cierre_payload_hash');
            $table->string('conductor', 150)->nullable()->after('patente');
            $table->text('observacion_cierre')->nullable()->after('conductor');
            $table->foreignId('cerrada_por_user_id')
                ->nullable()
                ->after('observacion_cierre')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('cerrada_at')->nullable()->index()->after('cerrada_por_user_id');
        });

        Schema::table('carga_folios', function (Blueprint $table) {
            $table->index('folio_id', 'carga_folios_folio_id_index');
        });

        Schema::table('carga_folios', function (Blueprint $table) {
            $table->dropUnique(['folio_id']);
            $table->string('estado', 30)->default('pendiente')->index()->after('folio_id');
            $table->foreignUuid('anden_id')
                ->nullable()
                ->after('estado')
                ->constrained('andenes')
                ->restrictOnDelete();
            $table->foreignUuid('reemplaza_a_carga_folio_id')
                ->nullable()
                ->unique()
                ->after('anden_id')
                ->constrained('carga_folios')
                ->restrictOnDelete();
            $table->foreignId('enviado_anden_por_user_id')
                ->nullable()
                ->after('asignado_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('enviado_anden_desde_dispositivo_id')
                ->nullable()
                ->after('enviado_anden_por_user_id')
                ->constrained('dispositivos')
                ->restrictOnDelete();
            $table->timestamp('enviado_anden_at')->nullable()->after('enviado_anden_desde_dispositivo_id');
            $table->foreignId('finalizado_por_user_id')
                ->nullable()
                ->after('enviado_anden_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('finalizado_at')->nullable()->after('finalizado_por_user_id');
            $table->text('motivo_finalizacion')->nullable()->after('finalizado_at');

            $table->index(['carga_id', 'estado']);
            $table->index(['folio_id', 'estado']);
        });

        Schema::create('reservas_carga_folio', function (Blueprint $table) {
            $table->foreignUuid('folio_id')->primary()->constrained('folios')->restrictOnDelete();
            $table->foreignUuid('carga_folio_id')->unique()->constrained('carga_folios')->restrictOnDelete();
            $table->timestamps();
        });

        $ahora = now();
        DB::table('carga_folios')
            ->orderBy('id')
            ->get(['id', 'folio_id'])
            ->each(function (object $asignacion) use ($ahora): void {
                DB::table('reservas_carga_folio')->insert([
                    'folio_id' => $asignacion->folio_id,
                    'carga_folio_id' => $asignacion->id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            });

        Schema::create('tareas_carga', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('carga_id')->constrained('cargas')->restrictOnDelete();
            $table->foreignUuid('camara_origen_id')->constrained('camaras')->restrictOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->timestamp('asumida_at')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->timestamps();

            $table->unique(['carga_id', 'camara_origen_id']);
        });

        Schema::create('incidencias_carga_folio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_reporte_id')->unique();
            $table->char('reporte_payload_hash', 64);
            $table->foreignUuid('carga_folio_id')->constrained('carga_folios')->restrictOnDelete();
            $table->string('tipo', 50)->index();
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('abierta')->index();
            $table->foreignUuid('camara_id')->constrained('camaras')->restrictOnDelete();
            $table->foreignUuid('posicion_id')->constrained('posiciones')->restrictOnDelete();
            $table->foreignId('reportado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->foreignUuid('sesion_estiba_id')->constrained('sesiones_estiba')->restrictOnDelete();
            $table->timestamp('reportada_at')->index();
            $table->uuid('operacion_resolucion_id')->nullable()->unique();
            $table->char('resolucion_payload_hash', 64)->nullable();
            $table->string('tipo_resolucion', 40)->nullable();
            $table->text('observacion_resolucion')->nullable();
            $table->foreignId('resuelta_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resuelta_at')->nullable()->index();
            $table->foreignUuid('carga_folio_reemplazo_id')
                ->nullable()
                ->constrained('carga_folios')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['carga_folio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_carga_folio');
        Schema::dropIfExists('tareas_carga');
        Schema::dropIfExists('reservas_carga_folio');

        Schema::table('carga_folios', function (Blueprint $table) {
            $table->dropForeign(['anden_id']);
            $table->dropForeign(['reemplaza_a_carga_folio_id']);
            $table->dropForeign(['enviado_anden_por_user_id']);
            $table->dropForeign(['enviado_anden_desde_dispositivo_id']);
            $table->dropForeign(['finalizado_por_user_id']);
            $table->dropIndex(['carga_id', 'estado']);
            $table->dropIndex(['folio_id', 'estado']);
            $table->dropColumn([
                'estado',
                'anden_id',
                'reemplaza_a_carga_folio_id',
                'enviado_anden_por_user_id',
                'enviado_anden_desde_dispositivo_id',
                'enviado_anden_at',
                'finalizado_por_user_id',
                'finalizado_at',
                'motivo_finalizacion',
            ]);
            $table->unique('folio_id');
        });

        Schema::table('carga_folios', function (Blueprint $table) {
            $table->dropIndex('carga_folios_folio_id_index');
        });

        Schema::table('cargas', function (Blueprint $table) {
            $table->dropForeign(['anden_previsto_id']);
            $table->dropForeign(['cerrada_por_user_id']);
            $table->dropUnique(['operacion_cierre_id']);
            $table->dropColumn([
                'anden_previsto_id',
                'operacion_cierre_id',
                'cierre_payload_hash',
                'patente',
                'conductor',
                'observacion_cierre',
                'cerrada_por_user_id',
                'cerrada_at',
            ]);
        });

        Schema::dropIfExists('andenes');
    }
};
