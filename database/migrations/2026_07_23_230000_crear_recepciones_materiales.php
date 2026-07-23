<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->char('codigo_folio_materiales', 2)->nullable()->unique()->after('codigo_externo');
        });

        Schema::table('items_materiales', function (Blueprint $table) {
            $table->string('categoria_operacional', 32)->nullable()->after('categoria');
        });

        Schema::create('recepciones_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('proveedor_material_id')->constrained('proveedores_materiales')->restrictOnDelete();
            $table->string('numero_guia_despacho', 50);
            $table->date('fecha_documento')->nullable();
            $table->string('orden_compra', 80)->nullable();
            $table->string('patente', 20)->nullable();
            $table->string('transportista', 150)->nullable();
            $table->string('estado', 24)->default('borrador')->index();
            $table->unsignedInteger('version')->default(1);
            $table->text('observacion')->nullable();
            $table->json('snapshot_confirmacion')->nullable();
            $table->uuid('confirmacion_operacion_id')->nullable()->unique();
            $table->char('confirmacion_payload_hash', 64)->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmado_at')->nullable();
            $table->uuid('anulacion_operacion_id')->nullable()->unique();
            $table->char('anulacion_payload_hash', 64)->nullable();
            $table->foreignId('anulado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->index(
                ['temporada_id', 'cliente_id', 'proveedor_material_id', 'numero_guia_despacho'],
                'recepciones_materiales_guia_busqueda',
            );
        });

        Schema::create('detalles_recepciones_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recepcion_material_id')->constrained('recepciones_materiales')->restrictOnDelete();
            $table->foreignUuid('item_material_id')->constrained('items_materiales')->restrictOnDelete();
            $table->string('categoria_operacional', 32);
            $table->string('unidad_medida', 30);
            $table->decimal('cantidad_documental', 14, 3);
            $table->decimal('cantidad_recibida', 14, 3);
            $table->decimal('cantidad_rechazada', 14, 3)->default(0);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->index(['recepcion_material_id', 'item_material_id']);
        });

        Schema::create('bultos_recepciones_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('detalle_recepcion_material_id')->constrained('detalles_recepciones_materiales')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->string('lote_proveedor', 100)->nullable();
            $table->date('fecha_fabricacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->boolean('bloqueado')->default(false);
            $table->text('motivo_bloqueo')->nullable();
            $table->timestamps();
        });

        Schema::create('correlativos_materiales_clientes', function (Blueprint $table) {
            $table->foreignUuid('cliente_id')->primary()->constrained('clientes')->restrictOnDelete();
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        Schema::create('eventos_recepciones_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recepcion_material_id')->constrained('recepciones_materiales')->restrictOnDelete();
            $table->uuid('operacion_id')->nullable()->unique();
            $table->string('tipo', 32)->index();
            $table->json('datos')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();
        });

        Schema::table('folios_materiales', function (Blueprint $table) {
            $table->foreignUuid('bulto_recepcion_material_id')
                ->nullable()
                ->unique()
                ->after('item_material_id')
                ->constrained('bultos_recepciones_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('proveedor_material_id')
                ->nullable()
                ->after('bulto_recepcion_material_id')
                ->constrained('proveedores_materiales')
                ->restrictOnDelete();
            $table->string('categoria_operacional', 32)->nullable()->after('proveedor_material_id');
            $table->date('fecha_fabricacion')->nullable()->after('lote');
            $table->date('fecha_vencimiento')->nullable()->after('fecha_fabricacion');
            $table->text('motivo_bloqueo')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('folios_materiales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bulto_recepcion_material_id');
            $table->dropConstrainedForeignId('proveedor_material_id');
            $table->dropColumn([
                'categoria_operacional',
                'fecha_fabricacion',
                'fecha_vencimiento',
                'motivo_bloqueo',
            ]);
        });

        Schema::dropIfExists('eventos_recepciones_materiales');
        Schema::dropIfExists('correlativos_materiales_clientes');
        Schema::dropIfExists('bultos_recepciones_materiales');
        Schema::dropIfExists('detalles_recepciones_materiales');
        Schema::dropIfExists('recepciones_materiales');

        Schema::table('items_materiales', function (Blueprint $table) {
            $table->dropColumn('categoria_operacional');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['codigo_folio_materiales']);
            $table->dropColumn('codigo_folio_materiales');
        });
    }
};
