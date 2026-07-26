<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->uuid('resultado_operacion_id')->nullable()->unique()->after('dispositivo_id');
            $table->char('resultado_payload_hash', 64)->nullable()->after('resultado_operacion_id');
            $table->json('destino_impresion_snapshot')->nullable()->after('resultado_payload_hash');
            $table->unsignedInteger('bytes_enviados')->nullable()->after('destino_impresion_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->dropUnique(['resultado_operacion_id']);
            $table->dropColumn([
                'resultado_operacion_id',
                'resultado_payload_hash',
                'destino_impresion_snapshot',
                'bytes_enviados',
            ]);
        });
    }
};
