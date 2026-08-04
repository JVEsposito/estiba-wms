<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones_retiro_materiales', function (Blueprint $table): void {
            $table->uuid('dispositivo_id')->nullable()->change();
        });

        Schema::table('retiros_materiales', function (Blueprint $table): void {
            $table->uuid('dispositivo_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('retiros_materiales', function (Blueprint $table): void {
            $table->uuid('dispositivo_id')->nullable(false)->change();
        });

        Schema::table('operaciones_retiro_materiales', function (Blueprint $table): void {
            $table->uuid('dispositivo_id')->nullable(false)->change();
        });
    }
};
