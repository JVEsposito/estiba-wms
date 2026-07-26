<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes_transformacion_materiales', function (Blueprint $table) {
            $table->foreignId('reversado_por_user_id')
                ->nullable()
                ->after('cerrado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->dateTime('reversado_at')->nullable()->after('cerrado_at');
            $table->text('motivo_reversa')->nullable()->after('reversado_at');
        });
    }

    public function down(): void
    {
        Schema::table('lotes_transformacion_materiales', function (Blueprint $table) {
            $table->dropForeign(['reversado_por_user_id']);
            $table->dropColumn([
                'reversado_por_user_id',
                'reversado_at',
                'motivo_reversa',
            ]);
        });
    }
};
