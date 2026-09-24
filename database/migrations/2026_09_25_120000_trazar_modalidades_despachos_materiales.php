<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despachos_materiales', function (Blueprint $table): void {
            $table->string('modalidad', 20)->default('delegado');
            $table->foreignId('asignado_a_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });

        // Los despachos directos ya confirmados se identifican por un retiro
        // registrado en Oficina sin tablet asociada.
        DB::table('despachos_materiales')
            ->whereExists(fn ($consulta) => $consulta
                ->selectRaw('1')
                ->from('operaciones_retiro_materiales')
                ->whereColumn('operaciones_retiro_materiales.despacho_material_id', 'despachos_materiales.id')
                ->whereNull('operaciones_retiro_materiales.dispositivo_id'))
            ->update(['modalidad' => 'directo']);

        Schema::table('retiros_materiales', function (Blueprint $table): void {
            $table->text('motivo_excepcion_fifo')->nullable();
        });

        Schema::create('asignaciones_despachos_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->foreignUuid('despacho_material_id')->constrained('despachos_materiales')->restrictOnDelete();
            $table->foreignId('asignado_a_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('asignado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->string('motivo', 1000);
            $table->timestamps();
            $table->index(['despacho_material_id', 'created_at'], 'asignaciones_despachos_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_despachos_materiales');
        Schema::table('retiros_materiales', fn (Blueprint $table) => $table->dropColumn('motivo_excepcion_fifo'));
        Schema::table('despachos_materiales', function (Blueprint $table): void {
            $table->dropForeign(['asignado_a_user_id']);
            $table->dropColumn(['asignado_a_user_id', 'modalidad']);
        });
    }
};
