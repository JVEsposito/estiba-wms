<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias_despacho_envases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->string('numero', 24)->unique();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('estado', 20)->index();
            $table->timestamp('salida_at');
            $table->string('patente_camion', 12)->nullable();
            $table->string('rut_conductor', 12)->nullable();
            $table->string('nombre_conductor', 150)->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('anulado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->index(['cliente_id', 'salida_at'], 'guia_envases_cliente_salida_idx');
        });

        Schema::create('detalles_guias_despacho_envases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('guia_despacho_envase_id')->constrained('guias_despacho_envases')->restrictOnDelete();
            $table->string('tipo_envase', 20);
            $table->unsignedInteger('cantidad');
            $table->string('propiedad', 20);
            $table->foreignUuid('movimiento_origen_id')->nullable()->constrained('movimientos_envases')->restrictOnDelete();
            $table->string('origen_snapshot', 180)->nullable();
            $table->timestamps();
            $table->unique(
                ['guia_despacho_envase_id', 'tipo_envase', 'propiedad', 'movimiento_origen_id'],
                'detalle_guia_envase_linea_unique',
            );
        });

        Schema::create('correlativos_guias_despacho_envases', function (Blueprint $table): void {
            $table->char('periodo', 4)->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_guias_despacho_envases');
        Schema::dropIfExists('guias_despacho_envases');
        Schema::dropIfExists('correlativos_guias_despacho_envases');
    }
};
