<?php

use App\Enums\SentidoNumeracionBandas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camaras', function (Blueprint $table) {
            $table->string('sentido_numeracion_bandas', 24)
                ->default(SentidoNumeracionBandas::IzquierdaADerecha->value)
                ->after('cantidad_niveles');
        });
    }

    public function down(): void
    {
        Schema::table('camaras', function (Blueprint $table) {
            $table->dropColumn('sentido_numeracion_bandas');
        });
    }
};
