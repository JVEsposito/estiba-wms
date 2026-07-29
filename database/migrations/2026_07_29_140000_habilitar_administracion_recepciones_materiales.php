<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folios_materiales_liberados', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cliente_id');
            $table->foreign('cliente_id', 'folios_mat_liberados_cliente_fk')
                ->references('id')
                ->on('clientes')
                ->restrictOnDelete();
            $table->string('numero_folio', 64);
            $table->unique('numero_folio', 'folios_mat_liberados_numero_unq');
            $table->unsignedBigInteger('numero_correlativo');
            $table->uuid('recepcion_material_id_original');
            $table->text('motivo');
            $table->unsignedBigInteger('liberado_por_user_id');
            $table->foreign('liberado_por_user_id', 'folios_mat_liberados_user_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['cliente_id', 'numero_correlativo'],
                'folios_mat_liberados_cliente_num_unq',
            );
            $table->index(
                ['cliente_id', 'created_at'],
                'folios_mat_liberados_cliente_fecha_idx',
            );
        });

        Schema::create('eliminaciones_recepciones_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id');
            $table->unique('operacion_id', 'elim_recepciones_mat_operacion_unq');
            $table->uuid('recepcion_material_id_original');
            $table->unique(
                'recepcion_material_id_original',
                'elim_recepciones_mat_recepcion_unq',
            );
            $table->uuid('temporada_id');
            $table->foreign('temporada_id', 'elim_recepciones_mat_temporada_fk')
                ->references('id')
                ->on('temporadas')
                ->restrictOnDelete();
            $table->uuid('cliente_id');
            $table->foreign('cliente_id', 'elim_recepciones_mat_cliente_fk')
                ->references('id')
                ->on('clientes')
                ->restrictOnDelete();
            $table->uuid('proveedor_material_id');
            $table->foreign('proveedor_material_id', 'elim_recepciones_mat_proveedor_fk')
                ->references('id')
                ->on('proveedores_materiales')
                ->restrictOnDelete();
            $table->string('numero_guia_despacho', 50);
            $table->text('motivo');
            $table->json('folios');
            $table->json('snapshot');
            $table->unsignedBigInteger('eliminado_por_user_id');
            $table->foreign('eliminado_por_user_id', 'elim_recepciones_mat_user_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->dateTime('eliminado_at');
            $table->timestamps();

            $table->index(
                ['cliente_id', 'eliminado_at'],
                'elim_recepciones_mat_cliente_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eliminaciones_recepciones_materiales');
        Schema::dropIfExists('folios_materiales_liberados');
    }
};
