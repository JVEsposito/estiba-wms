<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->string('tipo_camion', 20)->nullable()->after('patente_camion');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->dropColumn('tipo_camion');
        });
    }
};
