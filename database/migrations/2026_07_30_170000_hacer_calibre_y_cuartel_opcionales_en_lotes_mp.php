<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes_materia_prima', function (Blueprint $table): void {
            $table->dropForeign('lote_mp_calibre_fk');
        });
        Schema::table('lotes_materia_prima', function (Blueprint $table): void {
            $table->uuid('calibre_validacion_id')->nullable()->change();
            $table->string('calibre_snapshot', 50)->nullable()->change();
            $table->string('cuartel', 100)->nullable()->change();
            $table->foreign('calibre_validacion_id', 'lote_mp_calibre_fk')
                ->references('id')->on('calibres_validacion')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('lotes_materia_prima')
            ->whereNull('cuartel')
            ->update(['cuartel' => 'SIN INFORMAR']);

        DB::table('lotes_materia_prima')
            ->whereNull('calibre_validacion_id')
            ->select('especie_validacion_id')
            ->distinct()
            ->get()
            ->each(function (object $lote): void {
                $calibreId = DB::table('calibres_validacion')
                    ->where('especie_validacion_id', $lote->especie_validacion_id)
                    ->where('nombre', 'SIN INFORMAR')
                    ->value('id');
                if (! $calibreId) {
                    $calibreId = (string) Str::uuid();
                    DB::table('calibres_validacion')->insert([
                        'id' => $calibreId,
                        'especie_validacion_id' => $lote->especie_validacion_id,
                        'nombre' => 'SIN INFORMAR',
                        'activo' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('lotes_materia_prima')
                    ->where('especie_validacion_id', $lote->especie_validacion_id)
                    ->whereNull('calibre_validacion_id')
                    ->update([
                        'calibre_validacion_id' => $calibreId,
                        'calibre_snapshot' => 'SIN INFORMAR',
                    ]);
            });

        Schema::table('lotes_materia_prima', function (Blueprint $table): void {
            $table->dropForeign('lote_mp_calibre_fk');
        });
        Schema::table('lotes_materia_prima', function (Blueprint $table): void {
            $table->uuid('calibre_validacion_id')->nullable(false)->change();
            $table->string('calibre_snapshot', 50)->nullable(false)->change();
            $table->string('cuartel', 100)->nullable(false)->change();
            $table->foreign('calibre_validacion_id', 'lote_mp_calibre_fk')
                ->references('id')->on('calibres_validacion')->restrictOnDelete();
        });
    }
};
