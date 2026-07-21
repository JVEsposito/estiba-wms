<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'cargas' => 'cargas_temporada_idx',
            'procesos_prefrio' => 'procesos_prefrio_temporada_idx',
            'despachos_materiales' => 'despachos_materiales_temporada_idx',
        ] as $tabla => $indice) {
            Schema::table($tabla, function (Blueprint $table) use ($indice): void {
                $table->uuid('temporada_id')->nullable()->after('id')->index($indice);
            });
        }

        $temporadaFallback = DB::table('temporadas')
            ->where('activa', true)
            ->orderByDesc('updated_at')
            ->value('id')
            ?? DB::table('temporadas')->orderBy('created_at')->value('id');

        if ($temporadaFallback !== null) {
            DB::table('folios')
                ->whereNull('temporada_id')
                ->update(['temporada_id' => $temporadaFallback]);
        }

        DB::table('cargas')->orderBy('created_at')->each(function (object $carga) use ($temporadaFallback): void {
            $temporadaId = DB::table('carga_folios as cf')
                ->join('folios as f', 'f.id', '=', 'cf.folio_id')
                ->where('cf.carga_id', $carga->id)
                ->whereNotNull('f.temporada_id')
                ->value('f.temporada_id') ?? $temporadaFallback;

            DB::table('cargas')->where('id', $carga->id)->update(['temporada_id' => $temporadaId]);
        });

        DB::table('procesos_prefrio')->orderBy('created_at')->each(function (object $proceso) use ($temporadaFallback): void {
            $temporadaId = DB::table('procesos_prefrio_folios as ppf')
                ->join('folios as f', 'f.id', '=', 'ppf.folio_id')
                ->where('ppf.proceso_prefrio_id', $proceso->id)
                ->whereNotNull('f.temporada_id')
                ->value('f.temporada_id') ?? $temporadaFallback;

            DB::table('procesos_prefrio')->where('id', $proceso->id)->update(['temporada_id' => $temporadaId]);
        });

        DB::table('despachos_materiales')->orderBy('created_at')->each(function (object $despacho) use ($temporadaFallback): void {
            $temporadaId = DB::table('detalles_despacho_materiales as ddm')
                ->join('items_materiales as im', 'im.id', '=', 'ddm.item_material_id')
                ->join('clientes_materiales as cm', 'cm.id', '=', 'im.cliente_material_id')
                ->join('temporadas_materiales as tm', 'tm.id', '=', 'cm.temporada_material_id')
                ->where('ddm.despacho_material_id', $despacho->id)
                ->value('tm.temporada_id') ?? $temporadaFallback;

            DB::table('despachos_materiales')->where('id', $despacho->id)->update(['temporada_id' => $temporadaId]);
        });

        Schema::table('cargas', function (Blueprint $table): void {
            $table->foreign('temporada_id', 'cargas_temporada_fk')
                ->references('id')->on('temporadas')->restrictOnDelete();
        });
        Schema::table('procesos_prefrio', function (Blueprint $table): void {
            $table->foreign('temporada_id', 'procesos_prefrio_temporada_fk')
                ->references('id')->on('temporadas')->restrictOnDelete();
        });
        Schema::table('despachos_materiales', function (Blueprint $table): void {
            $table->foreign('temporada_id', 'despachos_materiales_temporada_fk')
                ->references('id')->on('temporadas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach ([
            'cargas' => ['cargas_temporada_fk', 'cargas_temporada_idx'],
            'procesos_prefrio' => ['procesos_prefrio_temporada_fk', 'procesos_prefrio_temporada_idx'],
            'despachos_materiales' => ['despachos_materiales_temporada_fk', 'despachos_materiales_temporada_idx'],
        ] as $tabla => [$llave, $indice]) {
            Schema::table($tabla, function (Blueprint $table) use ($llave, $indice): void {
                $table->dropForeign($llave);
                $table->dropIndex($indice);
                $table->dropColumn('temporada_id');
            });
        }
    }
};
