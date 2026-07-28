<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productores_csg', function (Blueprint $table): void {
            $table->json('especies_variedades')->nullable()->after('especies');
        });
    }

    public function down(): void
    {
        Schema::table('productores_csg', function (Blueprint $table): void {
            $table->dropColumn('especies_variedades');
        });
    }
};
