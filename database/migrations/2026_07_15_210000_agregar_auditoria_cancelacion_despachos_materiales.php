<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despachos_materiales', function (Blueprint $table) {
            $table->uuid('cancelacion_operacion_id')
                ->nullable()
                ->unique()
                ->after('cancelado_at');
            $table->char('cancelacion_payload_hash', 64)
                ->nullable()
                ->after('cancelacion_operacion_id');
            $table->foreignId('cancelado_por_user_id')
                ->nullable()
                ->after('cancelacion_payload_hash')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('cancelado_desde_dispositivo_id')
                ->nullable()
                ->after('cancelado_por_user_id')
                ->constrained('dispositivos')
                ->restrictOnDelete();
            $table->text('cancelacion_motivo')
                ->nullable()
                ->after('cancelado_desde_dispositivo_id');
        });
    }

    public function down(): void
    {
        Schema::table('despachos_materiales', function (Blueprint $table) {
            $table->dropForeign(['cancelado_por_user_id']);
            $table->dropForeign(['cancelado_desde_dispositivo_id']);
            $table->dropUnique(['cancelacion_operacion_id']);
            $table->dropColumn([
                'cancelacion_operacion_id',
                'cancelacion_payload_hash',
                'cancelado_por_user_id',
                'cancelado_desde_dispositivo_id',
                'cancelacion_motivo',
            ]);
        });
    }
};
