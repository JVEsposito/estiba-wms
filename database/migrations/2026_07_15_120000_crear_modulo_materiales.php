<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camaras', function (Blueprint $table) {
            $table->string('contenido', 30)->default('productos')->after('tipo')->index();
        });

        Schema::create('items_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 180);
            $table->string('categoria', 100)->nullable()->index();
            $table->string('unidad_medida', 40);
            $table->string('codigo_externo', 150)->nullable()->unique();
            $table->string('origen_sistema', 50)->default('manual')->index();
            $table->dateTime('sincronizado_at')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('destinos_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 180);
            $table->string('centro_costo', 100)->index();
            $table->text('descripcion')->nullable();
            $table->string('codigo_externo', 150)->nullable()->unique();
            $table->string('origen_sistema', 50)->default('manual')->index();
            $table->dateTime('sincronizado_at')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['nombre', 'centro_costo'], 'destinos_materiales_nombre_cc_unique');
        });

        Schema::create('folios_materiales', function (Blueprint $table) {
            $table->foreignUuid('folio_id')->primary()->constrained('folios')->restrictOnDelete();
            $table->foreignUuid('item_material_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad_inicial', 14, 3);
            $table->decimal('cantidad_actual', 14, 3);
            $table->decimal('cantidad_reservada', 14, 3)->default(0);
            $table->string('unidad_medida', 40);
            $table->string('lote', 100)->nullable()->index();
            $table->string('proveedor', 180)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        Schema::create('despachos_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 50)->unique();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->string('origen', 30)->index();
            $table->string('estado', 30)->index();
            $table->foreignUuid('destino_material_id')->constrained('destinos_materiales')->restrictOnDelete();
            $table->string('destino_nombre', 180);
            $table->string('destino_centro_costo', 100);
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('creado_desde_dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->dateTime('completado_at')->nullable();
            $table->dateTime('cancelado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('detalles_despacho_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('despacho_material_id')->constrained('despachos_materiales')->restrictOnDelete();
            $table->foreignUuid('item_material_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad_solicitada', 14, 3);
            $table->decimal('cantidad_despachada', 14, 3)->default(0);
            $table->string('unidad_medida', 40);
            $table->timestamps();

            $table->unique(
                ['despacho_material_id', 'item_material_id'],
                'detalles_despacho_material_item_unique',
            );
        });

        Schema::create('reservas_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('detalle_despacho_material_id')->constrained('detalles_despacho_materiales')->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios_materiales', 'folio_id')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->string('estado', 30)->index();
            $table->unsignedSmallInteger('orden_fifo')->default(1);
            $table->timestamps();

            $table->unique(
                ['detalle_despacho_material_id', 'folio_id'],
                'reservas_materiales_detalle_folio_unique',
            );
        });

        Schema::create('operaciones_retiro_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('despacho_material_id')->constrained('despachos_materiales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->char('payload_hash', 64);
            $table->dateTime('procesada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('retiros_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('operacion_retiro_material_id')->constrained('operaciones_retiro_materiales')->restrictOnDelete();
            $table->foreignUuid('detalle_despacho_material_id')->constrained('detalles_despacho_materiales')->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios_materiales', 'folio_id')->restrictOnDelete();
            $table->decimal('cantidad_anterior', 14, 3);
            $table->decimal('cantidad_retirada', 14, 3);
            $table->decimal('cantidad_resultante', 14, 3);
            $table->foreignUuid('camara_id')->constrained('camaras')->restrictOnDelete();
            $table->foreignUuid('posicion_id')->constrained('posiciones')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->boolean('siguio_fifo')->default(true);
            $table->dateTime('retirado_at');
            $table->timestamps();
        });

        Schema::create('movimientos_inventario_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('folio_id')->constrained('folios_materiales', 'folio_id')->restrictOnDelete();
            $table->foreignUuid('item_material_id')->constrained('items_materiales')->restrictOnDelete();
            $table->string('tipo', 30)->index();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('cantidad_anterior', 14, 3);
            $table->decimal('cantidad_resultante', 14, 3);
            $table->foreignUuid('despacho_material_id')->nullable()->constrained('despachos_materiales')->restrictOnDelete();
            $table->foreignUuid('retiro_material_id')->nullable()->constrained('retiros_materiales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->string('destino_nombre', 180)->nullable();
            $table->string('destino_centro_costo', 100)->nullable();
            $table->text('motivo')->nullable();
            $table->json('metadatos')->nullable();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario_materiales');
        Schema::dropIfExists('retiros_materiales');
        Schema::dropIfExists('operaciones_retiro_materiales');
        Schema::dropIfExists('reservas_materiales');
        Schema::dropIfExists('detalles_despacho_materiales');
        Schema::dropIfExists('despachos_materiales');
        Schema::dropIfExists('folios_materiales');
        Schema::dropIfExists('destinos_materiales');
        Schema::dropIfExists('items_materiales');

        Schema::table('camaras', function (Blueprint $table) {
            $table->dropColumn('contenido');
        });
    }
};
