<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retorno_packing_entregas', function (Blueprint $table): void {
            $table->uuid('retorno_packing_id');
            $table->uuid('entrega_fruta_proceso_id');
            $table->boolean('cierra_entrega')->default(false);
            $table->timestamps(precision: 6);

            $table->primary(
                ['retorno_packing_id', 'entrega_fruta_proceso_id'],
                'retorno_pack_entregas_primary',
            );
            $table->foreign('retorno_packing_id', 'retorno_pack_entregas_retorno_fk')
                ->references('id')->on('retornos_packing')->cascadeOnDelete();
            $table->foreign('entrega_fruta_proceso_id', 'retorno_pack_entregas_entrega_fk')
                ->references('id')->on('entregas_fruta_proceso')->restrictOnDelete();
            $table->index(
                ['entrega_fruta_proceso_id', 'cierra_entrega'],
                'retorno_pack_entregas_cierre_index',
            );
        });

        $ahora = now();
        DB::table('retornos_packing')
            ->select(['id', 'entrega_fruta_proceso_id', 'cierra_entrega'])
            ->orderBy('id')
            ->chunk(500, function ($retornos) use ($ahora): void {
                DB::table('retorno_packing_entregas')->insertOrIgnore(
                    $retornos->map(fn ($retorno): array => [
                        'retorno_packing_id' => $retorno->id,
                        'entrega_fruta_proceso_id' => $retorno->entrega_fruta_proceso_id,
                        'cierra_entrega' => (bool) $retorno->cierra_entrega,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('retorno_packing_entregas');
    }
};
