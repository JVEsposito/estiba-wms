<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_recepciones_materiales', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('bultos_recepciones_materiales', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('bultos_recepciones_materiales', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('detalles_recepciones_materiales', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
