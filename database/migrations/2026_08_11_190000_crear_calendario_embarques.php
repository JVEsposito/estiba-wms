<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporadas', function (Blueprint $table): void {
            $table->unsignedSmallInteger('intervalo_embarques_minutos')
                ->default(60)
                ->after('version_catalogo');
        });

        Schema::create('correlativos_embarques_clientes', function (Blueprint $table): void {
            $table->foreignUuid('cliente_id')->primary()->constrained('clientes')->restrictOnDelete();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        Schema::create('embarques', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('carga_id')->nullable()->unique()->constrained('cargas')->restrictOnDelete();
            $table->string('codigo', 10)->unique();
            $table->unsignedInteger('numero_correlativo');
            $table->date('fecha_programada');
            $table->time('hora_programada');
            $table->unsignedSmallInteger('intervalo_minutos');
            $table->string('modalidad', 30);
            $table->string('estado', 30)->default('tentativo');
            $table->string('referencia_correo', 200)->nullable();
            $table->string('nave_vuelo', 150)->nullable();
            $table->string('transportista', 180)->nullable();
            $table->string('puerto_embarque', 180)->nullable();
            $table->string('contenedor', 100)->nullable();
            $table->string('sello', 100)->nullable();
            $table->string('patente_camion', 30)->nullable();
            $table->string('patente_trasera', 30)->nullable();
            $table->text('documentos')->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('actualizado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('sobrecupo_autorizado_por_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->text('sobrecupo_motivo')->nullable();
            $table->timestamp('sobrecupo_autorizado_at')->nullable();
            $table->foreignUuid('confirmado_por_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmado_at')->nullable();
            $table->foreignUuid('cancelado_por_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->text('cancelacion_motivo')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['cliente_id', 'numero_correlativo'],
                'embarque_cliente_correlativo_unique',
            );
            $table->index(
                ['temporada_id', 'fecha_programada', 'hora_programada'],
                'embarque_calendario_index',
            );
            $table->index(['temporada_id', 'estado']);
        });

        Schema::create('instructivos_embarque', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('embarque_id')->constrained('embarques')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('numero_externo', 150)->nullable();
            $table->string('recibidor', 180)->nullable();
            $table->string('destino_pais', 120)->nullable();
            $table->string('destino_ciudad', 120)->nullable();
            $table->unsignedSmallInteger('cantidad_pallets')->nullable();
            $table->unsignedInteger('cantidad_cajas')->nullable();
            $table->string('booking', 150)->nullable();
            $table->string('sps', 150)->nullable();
            $table->string('dus', 150)->nullable();
            $table->string('planilla_sag', 150)->nullable();
            $table->string('sello_sag', 150)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['embarque_id', 'orden']);
            $table->index('numero_externo');
        });

        Schema::create('eventos_embarque', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('embarque_id')->constrained('embarques')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 60);
            $table->json('datos')->nullable();
            $table->timestamps();
            $table->index(['embarque_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_embarque');
        Schema::dropIfExists('instructivos_embarque');
        Schema::dropIfExists('embarques');
        Schema::dropIfExists('correlativos_embarques_clientes');

        Schema::table('temporadas', function (Blueprint $table): void {
            $table->dropColumn('intervalo_embarques_minutos');
        });
    }
};
