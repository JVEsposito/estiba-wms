<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posiciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('banda_nueva')->default(1)->after('camara_id');
        });

        $filasPorCamara = DB::table('posiciones')
            ->select(['camara_id', 'fila'])
            ->distinct()
            ->get()
            ->groupBy('camara_id');

        foreach ($filasPorCamara as $camaraId => $filas) {
            $etiquetas = $filas
                ->pluck('fila')
                ->map(fn (mixed $fila): string => (string) $fila)
                ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
                ->values();

            foreach ($etiquetas as $indice => $fila) {
                DB::table('posiciones')
                    ->where('camara_id', $camaraId)
                    ->where('fila', $fila)
                    ->update(['banda_nueva' => $indice + 1]);
            }
        }

        Schema::table('posiciones', function (Blueprint $table) {
            $table->dropUnique('posiciones_coordenadas_unique');
            $table->dropColumn('fila');
        });

        Schema::table('posiciones', function (Blueprint $table) {
            $table->renameColumn('banda_nueva', 'banda');
            $table->renameColumn('profundidad', 'posicion');
        });

        Schema::table('posiciones', function (Blueprint $table) {
            $table->unique(
                ['camara_id', 'banda', 'posicion', 'nivel'],
                'posiciones_coordenadas_unique',
            );
        });

        DB::table('posiciones')
            ->select(['id', 'banda', 'posicion', 'nivel'])
            ->orderBy('id')
            ->get()
            ->each(function (object $posicion): void {
                DB::table('posiciones')
                    ->where('id', $posicion->id)
                    ->update([
                        'etiqueta' => sprintf(
                            'B%02d-P%02d-N%d',
                            $posicion->banda,
                            $posicion->posicion,
                            $posicion->nivel,
                        ),
                    ]);
            });

        Schema::table('camaras', function (Blueprint $table) {
            $table->foreignId('creado_por_user_id')
                ->nullable()
                ->after('version_plano')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')
                ->nullable()
                ->after('creado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->json('advertencias_confirmadas')->nullable()->after('motivo');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn('advertencias_confirmadas');
        });

        Schema::table('camaras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actualizado_por_user_id');
            $table->dropConstrainedForeignId('creado_por_user_id');
        });

        Schema::table('posiciones', function (Blueprint $table) {
            $table->string('fila_anterior', 50)->nullable()->after('camara_id');
        });

        DB::table('posiciones')
            ->orderBy('id')
            ->eachById(function (object $posicion): void {
                DB::table('posiciones')
                    ->where('id', $posicion->id)
                    ->update(['fila_anterior' => (string) $posicion->banda]);
            }, column: 'id');

        Schema::table('posiciones', function (Blueprint $table) {
            $table->dropUnique('posiciones_coordenadas_unique');
            $table->dropColumn('banda');
        });

        Schema::table('posiciones', function (Blueprint $table) {
            $table->renameColumn('fila_anterior', 'fila');
            $table->renameColumn('posicion', 'profundidad');
        });

        Schema::table('posiciones', function (Blueprint $table) {
            $table->unique(
                ['camara_id', 'fila', 'profundidad', 'nivel'],
                'posiciones_coordenadas_unique',
            );
        });
    }
};
