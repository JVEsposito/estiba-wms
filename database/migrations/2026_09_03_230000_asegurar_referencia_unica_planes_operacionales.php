<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->dropIndex('planes_referencia_idx');
            $table->unique(
                ['referencia_tipo', 'referencia_id'],
                'planes_referencia_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->dropUnique('planes_referencia_unique');
            $table->index(
                ['referencia_tipo', 'referencia_id'],
                'planes_referencia_idx',
            );
        });
    }
};
