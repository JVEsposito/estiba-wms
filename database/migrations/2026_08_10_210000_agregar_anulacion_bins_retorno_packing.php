<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bins_retorno_packing', function (Blueprint $table): void {
            $table->uuid('operacion_anulacion_id')->nullable()->unique()->after('regularizado_at');
            $table->string('payload_anulacion_hash', 64)->nullable()->after('operacion_anulacion_id');
            $table->foreignId('anulado_por_user_id')->nullable()->after('payload_anulacion_hash')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable()->after('anulado_por_user_id');
            $table->text('motivo_anulacion')->nullable()->after('anulado_at');
        });
    }

    public function down(): void
    {
        Schema::table('bins_retorno_packing', function (Blueprint $table): void {
            $table->dropForeign(['anulado_por_user_id']);
            $table->dropUnique(['operacion_anulacion_id']);
            $table->dropColumn([
                'operacion_anulacion_id',
                'payload_anulacion_hash',
                'anulado_por_user_id',
                'anulado_at',
                'motivo_anulacion',
            ]);
        });
    }
};
