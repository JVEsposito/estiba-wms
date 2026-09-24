<?php

use App\Models\Folio;
use App\Services\Validacion\ProyeccionTrazabilidadFolio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proyección consultable de la composición del folio: una fila por CSG, lote MP y
        // proceso de packing. La fuente de verdad sigue siendo folios.datos_externos.
        Schema::create('trazabilidad_folio_origenes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Es una proyección: desaparece con su folio y nunca impide eliminar un lote MP.
            $table->foreignUuid('folio_id')->constrained('folios')->cascadeOnDelete();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('csg', 50)->nullable();
            $table->string('predio', 150)->nullable();
            $table->date('fecha_embalaje')->nullable();
            $table->string('numero_lote_materia_prima', 80)->nullable();
            // Cliente del origen validado; el vínculo con el lote MP solo se afirma si coincide.
            $table->foreignUuid('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignUuid('lote_materia_prima_id')->nullable()->constrained('lotes_materia_prima')->nullOnDelete();
            $table->string('numero_proceso_packing', 80)->nullable();
            $table->unsignedInteger('cantidad_cajas');
            $table->timestamps();

            $table->index('folio_id', 'trazabilidad_folio_idx');
            $table->index(['temporada_id', 'numero_lote_materia_prima', 'cliente_id'], 'trazabilidad_lote_numero_idx');
            $table->index('lote_materia_prima_id', 'trazabilidad_lote_idx');
            $table->index(['temporada_id', 'numero_proceso_packing'], 'trazabilidad_proceso_idx');
            $table->index(['temporada_id', 'csg'], 'trazabilidad_csg_idx');
        });

        $proyeccion = app(ProyeccionTrazabilidadFolio::class);
        Folio::query()
            ->whereNotNull('datos_externos')
            ->orderBy('id')
            ->chunkById(500, function ($folios) use ($proyeccion): void {
                foreach ($folios as $folio) {
                    $proyeccion->sincronizar($folio);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('trazabilidad_folio_origenes');
    }
};
