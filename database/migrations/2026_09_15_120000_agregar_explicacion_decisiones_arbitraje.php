<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisiones_arbitraje_maniobras', function (Blueprint $table): void {
            $table->json('explicacion')->nullable()->after('conflictos');
        });
    }

    public function down(): void
    {
        Schema::table('decisiones_arbitraje_maniobras', function (Blueprint $table): void {
            $table->dropColumn('explicacion');
        });
    }
};
