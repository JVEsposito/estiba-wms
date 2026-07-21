<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias_despacho_envases', function (Blueprint $table): void {
            $table->char('payload_hash', 64)->nullable()->after('operacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('guias_despacho_envases', function (Blueprint $table): void {
            $table->dropColumn('payload_hash');
        });
    }
};
