<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones_romana', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->string('numero_recepcion', 24)->nullable()->unique();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('temporada_codigo_snapshot', 30);
            $table->string('temporada_nombre_snapshot', 100);
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('cliente_codigo_snapshot', 100)->nullable();
            $table->string('cliente_nombre_snapshot', 150);
            $table->string('tipo_servicio', 30);
            $table->unsignedInteger('cantidad_envases_declarados');
            $table->string('tipo_envase_declarado', 20);
            $table->string('numero_guia_despacho', 80);
            $table->string('patente_camion', 12);
            $table->string('patente_carro', 12)->nullable();
            $table->string('rut_conductor', 12);
            $table->string('nombre_conductor', 150);
            $table->decimal('peso_bruto', 10, 2);
            $table->decimal('peso_tara', 10, 2)->nullable();
            $table->decimal('peso_neto', 10, 2)->nullable();
            $table->string('estado', 30)->index();
            $table->timestamp('ingreso_at');
            $table->timestamp('ingreso_confirmado_at')->nullable();
            $table->timestamp('salida_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ingreso_confirmado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cerrado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->timestamps();
            $table->unique(
                ['temporada_id', 'cliente_id', 'numero_guia_despacho'],
                'recepciones_romana_temporada_cliente_guia_unique',
            );
            $table->index(['ingreso_at', 'estado'], 'recepciones_romana_ingreso_estado_index');
            $table->index('patente_camion');
        });

        Schema::create('eventos_recepcion_romana', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('recepcion_romana_id')->constrained('recepciones_romana')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('ocurrido_at');
            $table->json('datos')->nullable();
            $table->timestamps();
            $table->index(['recepcion_romana_id', 'ocurrido_at'], 'eventos_romana_recepcion_fecha_index');
        });

        Schema::create('correlativos_recepcion_romana', function (Blueprint $table): void {
            $table->char('periodo', 4)->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_recepcion_romana');
        Schema::dropIfExists('recepciones_romana');
        Schema::dropIfExists('correlativos_recepcion_romana');
    }
};
