<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporadas_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('activa')->default(false)->index();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('clientes_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_material_id')->constrained('temporadas_materiales')->restrictOnDelete();
            $table->string('codigo', 80);
            $table->string('nombre', 180);
            $table->string('codigo_externo', 150)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['temporada_material_id', 'codigo'],
                'clientes_materiales_temporada_codigo_unique',
            );
            $table->unique(
                ['temporada_material_id', 'codigo_externo'],
                'clientes_materiales_temporada_externo_unique',
            );
        });

        $temporadaGeneralId = (string) Str::uuid();
        DB::table('temporadas_materiales')->insert([
            'id' => $temporadaGeneralId,
            'codigo' => 'GENERAL',
            'nombre' => 'Temporada inicial',
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clienteGeneralId = (string) Str::uuid();
        DB::table('clientes_materiales')->insert([
            'id' => $clienteGeneralId,
            'temporada_material_id' => $temporadaGeneralId,
            'codigo' => 'GENERAL',
            'nombre' => 'Sin clasificar',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('items_materiales', function (Blueprint $table): void {
            $table->uuid('cliente_material_id')->nullable()->after('id');
        });

        DB::table('items_materiales')->update([
            'cliente_material_id' => $clienteGeneralId,
        ]);

        Schema::table('items_materiales', function (Blueprint $table): void {
            $table->dropUnique('items_materiales_codigo_unique');
            $table->dropUnique('items_materiales_codigo_externo_unique');
        });

        Schema::table('items_materiales', function (Blueprint $table): void {
            $table->uuid('cliente_material_id')->nullable(false)->change();
        });

        Schema::table('items_materiales', function (Blueprint $table): void {
            $table->foreign('cliente_material_id', 'items_materiales_cliente_fk')
                ->references('id')
                ->on('clientes_materiales')
                ->restrictOnDelete();
            $table->unique(
                ['cliente_material_id', 'codigo'],
                'items_materiales_cliente_codigo_unique',
            );
            $table->unique(
                ['cliente_material_id', 'codigo_externo'],
                'items_materiales_cliente_externo_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('items_materiales', function (Blueprint $table): void {
            $table->dropUnique('items_materiales_cliente_codigo_unique');
            $table->dropUnique('items_materiales_cliente_externo_unique');
            $table->dropForeign('items_materiales_cliente_fk');
        });

        Schema::table('items_materiales', function (Blueprint $table): void {
            $table->dropColumn('cliente_material_id');
        });

        Schema::table('items_materiales', function (Blueprint $table): void {
            $table->unique('codigo');
            $table->unique('codigo_externo');
        });

        Schema::dropIfExists('clientes_materiales');
        Schema::dropIfExists('temporadas_materiales');
    }
};
