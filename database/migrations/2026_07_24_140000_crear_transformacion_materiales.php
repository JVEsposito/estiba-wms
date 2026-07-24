<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('item_salida_id')->constrained('items_materiales')->restrictOnDelete();
            $table->string('nombre', 180);
            $table->boolean('activa')->default(true)->index();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['temporada_id', 'cliente_id', 'nombre'],
                'recetas_mat_temporada_cliente_nombre_uq',
            );
        });

        Schema::create('versiones_recetas_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receta_material_id')->constrained('recetas_materiales')->restrictOnDelete();
            $table->unsignedInteger('numero_version');
            $table->string('estado', 24)->default('borrador')->index();
            $table->decimal('cantidad_base_salida', 14, 3);
            $table->string('unidad_medida_salida', 40);
            $table->json('snapshot')->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('activado_at')->nullable();
            $table->dateTime('retirado_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['receta_material_id', 'numero_version'],
                'versiones_receta_numero_uq',
            );
        });

        Schema::create('detalles_versiones_recetas_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_receta_material_id')
                ->constrained('versiones_recetas_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('item_entrada_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad_estandar', 14, 3);
            $table->string('unidad_medida', 40);
            $table->boolean('es_componente_principal')->default(false);
            $table->decimal('factor_conversion', 14, 6)->default(1);
            $table->decimal('merma_estandar_porcentaje', 8, 4)->default(0);
            $table->decimal('tolerancia_porcentaje', 8, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['version_receta_material_id', 'item_entrada_id'],
                'detalle_version_receta_item_uq',
            );
        });

        Schema::create('ordenes_transformacion_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('version_receta_material_id')
                ->constrained('versiones_recetas_materiales')
                ->restrictOnDelete();
            $table->string('estado', 30)->default('borrador')->index();
            $table->decimal('cantidad_planificada_salida', 14, 3);
            $table->decimal('cantidad_real_salida', 14, 3)->nullable();
            $table->string('linea', 100)->nullable();
            $table->string('turno', 80)->nullable();
            $table->date('fecha_operacional');
            $table->unsignedInteger('version')->default(1);
            $table->json('snapshot_receta');
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('iniciado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cerrado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('iniciado_at')->nullable();
            $table->dateTime('cerrado_at')->nullable();
            $table->dateTime('cancelado_at')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps();
        });

        Schema::create('eventos_transformacion_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_transformacion_material_id')
                ->constrained('ordenes_transformacion_materiales')
                ->restrictOnDelete();
            $table->uuid('operacion_id')->nullable()->unique();
            $table->string('tipo', 32)->index();
            $table->json('datos')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();
        });

        Schema::create('lotes_transformacion_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_transformacion_material_id')
                ->constrained('ordenes_transformacion_materiales')
                ->restrictOnDelete();
            $table->unsignedInteger('numero_lote');
            $table->string('estado', 24)->default('abierto')->index();
            $table->decimal('cantidad_planificada_salida', 14, 3);
            $table->decimal('cantidad_real_salida', 14, 3)->nullable();
            $table->decimal('salida_teorica', 14, 3)->nullable();
            $table->decimal('merma_estandar', 14, 3)->nullable();
            $table->decimal('merma_real', 14, 3)->nullable();
            $table->decimal('desviacion_merma', 14, 3)->nullable();
            $table->foreignId('iniciado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cerrado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('iniciado_at');
            $table->dateTime('cerrado_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['orden_transformacion_material_id', 'numero_lote'],
                'lote_transformacion_orden_numero_uq',
            );
        });

        Schema::create('reservas_transformacion_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_transformacion_material_id')
                ->constrained('ordenes_transformacion_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios_materiales', 'folio_id')->restrictOnDelete();
            $table->foreignUuid('item_material_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->string('estado', 30)->index();
            $table->unsignedSmallInteger('orden_fifo')->default(1);
            $table->timestamps();

            $table->unique(
                ['orden_transformacion_material_id', 'folio_id'],
                'reserva_transformacion_orden_folio_uq',
            );
        });

        Schema::create('consumos_transformacion_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lote_transformacion_material_id')
                ->constrained('lotes_transformacion_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios_materiales', 'folio_id')->restrictOnDelete();
            $table->foreignUuid('item_material_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad_consumida', 14, 3);
            $table->decimal('cantidad_anterior', 14, 3);
            $table->decimal('cantidad_resultante', 14, 3);
            $table->boolean('siguio_fifo')->default(true);
            $table->text('motivo_desviacion_fifo')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();
        });

        Schema::create('salidas_transformacion_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lote_transformacion_material_id')
                ->constrained('lotes_transformacion_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')->unique()->constrained('folios_materiales', 'folio_id')->restrictOnDelete();
            $table->foreignUuid('item_material_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad_producida', 14, 3);
            $table->boolean('es_salida_principal')->default(true);
            $table->timestamps();
        });

        Schema::table('folios_materiales', function (Blueprint $table) {
            $table->foreignUuid('lote_transformacion_origen_id')
                ->nullable()
                ->after('bulto_recepcion_material_id')
                ->constrained('lotes_transformacion_materiales')
                ->restrictOnDelete();
        });

        Schema::table('movimientos_inventario_materiales', function (Blueprint $table) {
            $table->foreignUuid('orden_transformacion_material_id')
                ->nullable()
                ->after('retiro_material_id')
                ->constrained('ordenes_transformacion_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('lote_transformacion_material_id')
                ->nullable()
                ->after('orden_transformacion_material_id')
                ->constrained('lotes_transformacion_materiales')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario_materiales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lote_transformacion_material_id');
            $table->dropConstrainedForeignId('orden_transformacion_material_id');
        });

        Schema::table('folios_materiales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lote_transformacion_origen_id');
        });

        Schema::dropIfExists('salidas_transformacion_materiales');
        Schema::dropIfExists('consumos_transformacion_materiales');
        Schema::dropIfExists('reservas_transformacion_materiales');
        Schema::dropIfExists('lotes_transformacion_materiales');
        Schema::dropIfExists('eventos_transformacion_materiales');
        Schema::dropIfExists('ordenes_transformacion_materiales');
        Schema::dropIfExists('detalles_versiones_recetas_materiales');
        Schema::dropIfExists('versiones_recetas_materiales');
        Schema::dropIfExists('recetas_materiales');
    }
};
