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
        Schema::table('repaletizajes', function (Blueprint $table): void {
            $table->string('modalidad', 24)->default('consolidacion')->after('codigo')->index();
        });

        Schema::create('repaletizaje_resultados', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repaletizaje_id')->constrained('repaletizajes')->cascadeOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios');
            $table->unsignedSmallInteger('orden');
            $table->string('tipo_resultado', 20);
            $table->unsignedInteger('cantidad_objetivo')->nullable();
            $table->unsignedInteger('cantidad_resultante');
            $table->boolean('hereda_ubicacion')->default(false);
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['repaletizaje_id', 'orden']);
            $table->unique(['repaletizaje_id', 'folio_id']);
            $table->index('folio_id');
        });

        DB::table('repaletizajes')->orderBy('created_at')->get()->each(function (object $repa): void {
            DB::table('repaletizaje_resultados')->insert([
                'id' => (string) Str::uuid(),
                'repaletizaje_id' => $repa->id,
                'folio_id' => $repa->folio_resultante_id,
                'orden' => 1,
                'tipo_resultado' => $repa->tipo_resultado,
                'cantidad_objetivo' => $repa->cantidad_objetivo,
                'cantidad_resultante' => $repa->cantidad_resultante,
                'hereda_ubicacion' => $repa->estrategia_folio === 'nuevo',
                'snapshot' => $repa->snapshot,
                'created_at' => $repa->created_at,
                'updated_at' => $repa->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repaletizaje_resultados');
        Schema::table('repaletizajes', function (Blueprint $table): void {
            $table->dropIndex(['modalidad']);
            $table->dropColumn('modalidad');
        });
    }
};
