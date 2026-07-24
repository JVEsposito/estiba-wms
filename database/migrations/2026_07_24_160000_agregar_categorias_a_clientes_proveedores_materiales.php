<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes_proveedores_materiales', function (Blueprint $table): void {
            $table->json('categorias')->nullable()->after('activo');
        });

        $vinculos = DB::table('clientes_proveedores_materiales')
            ->where('activo', true)
            ->get(['id', 'cliente_id']);

        foreach ($vinculos as $vinculo) {
            $categorias = DB::table('items_materiales as items')
                ->join('clientes_materiales as catalogos', 'catalogos.id', '=', 'items.cliente_material_id')
                ->where('catalogos.cliente_id', $vinculo->cliente_id)
                ->where('catalogos.activo', true)
                ->where('items.activo', true)
                ->whereNotNull('items.categoria')
                ->pluck('items.categoria')
                ->map(fn ($categoria): string => trim((string) $categoria))
                ->filter()
                ->unique(fn (string $categoria): string => mb_strtolower($categoria))
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            DB::table('clientes_proveedores_materiales')
                ->where('id', $vinculo->id)
                ->update(['categorias' => json_encode($categorias, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        Schema::table('clientes_proveedores_materiales', function (Blueprint $table): void {
            $table->dropColumn('categorias');
        });
    }
};
