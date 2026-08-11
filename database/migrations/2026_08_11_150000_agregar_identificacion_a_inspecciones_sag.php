<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correlativos_inspecciones_sag_clientes', function (Blueprint $table): void {
            $table->foreignUuid('cliente_id')->primary()->constrained('clientes')->restrictOnDelete();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });

        Schema::table('lotes_inspeccion_sag', function (Blueprint $table): void {
            $table->foreignUuid('cliente_id')->nullable()->after('temporada_id')
                ->constrained('clientes')->restrictOnDelete();
            $table->unsignedInteger('numero_correlativo')->nullable()->after('codigo');
            $table->string('numero_inspeccion_sag', 100)->nullable()->after('numero_correlativo')->index();
            $table->unique(
                ['cliente_id', 'numero_correlativo'],
                'lote_sag_cliente_correlativo_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('lotes_inspeccion_sag', function (Blueprint $table): void {
            $table->dropUnique('lote_sag_cliente_correlativo_unique');
            $table->dropForeign(['cliente_id']);
            $table->dropColumn(['cliente_id', 'numero_correlativo', 'numero_inspeccion_sag']);
        });

        Schema::dropIfExists('correlativos_inspecciones_sag_clientes');
    }
};
