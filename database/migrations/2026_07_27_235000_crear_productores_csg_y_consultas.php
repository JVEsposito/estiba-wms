<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productores_csg', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 50)->unique('prod_csg_codigo_unique');
            $table->string('rut', 20)->nullable()->index('prod_csg_rut_index');
            $table->string('razon_social', 200);
            $table->string('predio', 200);
            $table->string('direccion', 500)->nullable();
            $table->string('estado_sag', 30)->index('prod_csg_estado_index');
            $table->string('tipo_codigo', 10)->default('CSG');
            $table->json('especies')->nullable();
            $table->string('fuente_url', 500);
            $table->timestamp('primera_verificacion_at');
            $table->timestamp('ultima_verificacion_at');
            $table->foreignId('ultima_consulta_user_id');
            $table->char('respuesta_hash', 64);
            $table->json('datos_fuente')->nullable();
            $table->timestamps();

            $table->foreign('ultima_consulta_user_id', 'prod_csg_ultima_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('clientes_productores_csg', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cliente_id');
            $table->uuid('productor_csg_id');
            $table->boolean('activo')->default(true)->index('cli_prod_csg_activo_index');
            $table->foreignId('asociado_por_user_id');
            $table->foreignId('actualizado_por_user_id');
            $table->timestamps();

            $table->unique(['cliente_id', 'productor_csg_id'], 'cli_prod_csg_unique');
            $table->foreign('cliente_id', 'cli_prod_csg_cliente_fk')
                ->references('id')->on('clientes')->restrictOnDelete();
            $table->foreign('productor_csg_id', 'cli_prod_csg_productor_fk')
                ->references('id')->on('productores_csg')->restrictOnDelete();
            $table->foreign('asociado_por_user_id', 'cli_prod_csg_asociado_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('actualizado_por_user_id', 'cli_prod_csg_actualizado_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('consultas_sag', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tipo_busqueda', 20);
            $table->string('valor_normalizado', 100);
            $table->string('estado', 20);
            $table->unsignedInteger('cantidad_resultados')->default(0);
            $table->unsignedInteger('duracion_ms');
            $table->string('error', 500)->nullable();
            $table->foreignId('user_id');
            $table->timestamp('ocurrido_at');
            $table->timestamps();

            $table->foreign('user_id', 'consulta_sag_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->index(
                ['tipo_busqueda', 'valor_normalizado', 'ocurrido_at'],
                'consulta_sag_busqueda_index',
            );
            $table->index(['estado', 'ocurrido_at'], 'consulta_sag_estado_fecha_index');
        });

        Schema::table('csg_validacion', function (Blueprint $table): void {
            $table->uuid('productor_csg_id')->nullable()->after('id')
                ->index('csg_val_productor_index');
            $table->foreign('productor_csg_id', 'csg_val_productor_fk')
                ->references('id')->on('productores_csg')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('csg_validacion', function (Blueprint $table): void {
            $table->dropForeign('csg_val_productor_fk');
            $table->dropIndex('csg_val_productor_index');
            $table->dropColumn('productor_csg_id');
        });
        Schema::dropIfExists('consultas_sag');
        Schema::dropIfExists('clientes_productores_csg');
        Schema::dropIfExists('productores_csg');
    }
};
