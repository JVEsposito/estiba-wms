<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ubicaciones_actuales', function (Blueprint $table): void {
            $table->foreignUuid('camara_id')
                ->nullable()
                ->after('folio_id')
                ->constrained('camaras')
                ->restrictOnDelete();
        });

        DB::statement(
            'UPDATE ubicaciones_actuales ua '
            .'INNER JOIN posiciones p ON p.id = ua.posicion_id '
            .'SET ua.camara_id = p.camara_id '
            .'WHERE ua.camara_id IS NULL'
        );

        Schema::table('ubicaciones_actuales', function (Blueprint $table): void {
            $table->uuid('camara_id')->nullable(false)->change();
            $table->uuid('posicion_id')->nullable()->change();
        });

        Schema::table('retiros_materiales', function (Blueprint $table): void {
            $table->uuid('posicion_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement('DELETE FROM ubicaciones_actuales WHERE posicion_id IS NULL');
        DB::statement(
            'UPDATE retiros_materiales r '
            .'SET r.posicion_id = ('
            .'SELECT MIN(p.id) FROM posiciones p WHERE p.camara_id = r.camara_id'
            .') WHERE r.posicion_id IS NULL'
        );

        Schema::table('retiros_materiales', function (Blueprint $table): void {
            $table->uuid('posicion_id')->nullable(false)->change();
        });

        Schema::table('ubicaciones_actuales', function (Blueprint $table): void {
            $table->uuid('posicion_id')->nullable(false)->change();
            $table->dropForeign(['camara_id']);
            $table->dropColumn('camara_id');
        });
    }
};
