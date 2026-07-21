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
        Schema::table('temporadas_materiales', function (Blueprint $table): void {
            $table->uuid('temporada_id')->nullable()->after('id')->index();
        });
        Schema::table('folios', function (Blueprint $table): void {
            $table->uuid('temporada_id')->nullable()->after('id')->index();
        });

        foreach (DB::table('temporadas_materiales')->orderBy('created_at')->get() as $configuracion) {
            $temporada = DB::table('temporadas')
                ->whereRaw('UPPER(codigo) = ?', [mb_strtoupper($configuracion->codigo)])
                ->first();

            if (! $temporada) {
                $temporadaId = (string) Str::uuid();
                DB::table('temporadas')->insert([
                    'id' => $temporadaId,
                    'codigo' => $configuracion->codigo,
                    'nombre' => $configuracion->nombre,
                    'fecha_inicio' => $configuracion->fecha_inicio,
                    'fecha_fin' => $configuracion->fecha_fin,
                    'activa' => false,
                    'version_catalogo' => 1,
                    'created_at' => $configuracion->created_at,
                    'updated_at' => $configuracion->updated_at,
                ]);
            } else {
                $temporadaId = $temporada->id;
            }

            DB::table('temporadas_materiales')
                ->where('id', $configuracion->id)
                ->update(['temporada_id' => $temporadaId]);
        }

        foreach (DB::table('temporadas')->orderBy('created_at')->get() as $temporada) {
            if (DB::table('temporadas_materiales')->where('temporada_id', $temporada->id)->exists()) {
                continue;
            }

            DB::table('temporadas_materiales')->insert([
                'id' => (string) Str::uuid(),
                'temporada_id' => $temporada->id,
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
                'fecha_inicio' => $temporada->fecha_inicio,
                'fecha_fin' => $temporada->fecha_fin,
                'activa' => false,
                'created_at' => $temporada->created_at,
                'updated_at' => $temporada->updated_at,
            ]);
        }

        $temporadaActiva = DB::table('temporadas')->where('activa', true)->orderByDesc('updated_at')->first();
        if (! $temporadaActiva) {
            $temporadaActiva = DB::table('temporadas_materiales as tm')
                ->join('temporadas as t', 't.id', '=', 'tm.temporada_id')
                ->where('tm.activa', true)
                ->select('t.*')
                ->orderByDesc('tm.updated_at')
                ->first();
        }

        if ($temporadaActiva) {
            DB::table('temporadas')->update(['activa' => false]);
            DB::table('temporadas')->where('id', $temporadaActiva->id)->update(['activa' => true]);
            DB::table('temporadas_materiales')->update(['activa' => false]);
            DB::table('temporadas_materiales')
                ->where('temporada_id', $temporadaActiva->id)
                ->update(['activa' => true]);
        }

        DB::table('validaciones_pallet')
            ->whereNotNull('folio_id')
            ->orderBy('created_at')
            ->each(function (object $validacion): void {
                DB::table('folios')
                    ->where('id', $validacion->folio_id)
                    ->whereNull('temporada_id')
                    ->update(['temporada_id' => $validacion->temporada_id]);
            });

        DB::table('folios_materiales as fm')
            ->join('items_materiales as im', 'im.id', '=', 'fm.item_material_id')
            ->join('clientes_materiales as cm', 'cm.id', '=', 'im.cliente_material_id')
            ->join('temporadas_materiales as tm', 'tm.id', '=', 'cm.temporada_material_id')
            ->select('fm.folio_id', 'tm.temporada_id')
            ->orderBy('fm.folio_id')
            ->each(function (object $material): void {
                DB::table('folios')
                    ->where('id', $material->folio_id)
                    ->whereNull('temporada_id')
                    ->update(['temporada_id' => $material->temporada_id]);
            });

        Schema::table('temporadas_materiales', function (Blueprint $table): void {
            $table->uuid('temporada_id')->nullable(false)->change();
            $table->foreign('temporada_id', 'temporadas_materiales_temporada_global_fk')
                ->references('id')->on('temporadas')->restrictOnDelete();
            $table->unique('temporada_id', 'temporadas_materiales_temporada_global_unique');
        });
        Schema::table('folios', function (Blueprint $table): void {
            $table->foreign('temporada_id', 'folios_temporada_global_fk')
                ->references('id')->on('temporadas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('folios', function (Blueprint $table): void {
            $table->dropForeign('folios_temporada_global_fk');
            $table->dropIndex('folios_temporada_id_index');
            $table->dropColumn('temporada_id');
        });
        Schema::table('temporadas_materiales', function (Blueprint $table): void {
            $table->dropUnique('temporadas_materiales_temporada_global_unique');
            $table->dropForeign('temporadas_materiales_temporada_global_fk');
            $table->dropIndex('temporadas_materiales_temporada_id_index');
            $table->dropColumn('temporada_id');
        });
    }
};
