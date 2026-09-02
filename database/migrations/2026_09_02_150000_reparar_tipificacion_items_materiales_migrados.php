<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $clave = static fn (string $clienteId, string $codigo): string => $clienteId.'|'
            .mb_strtoupper(trim($codigo));

        $migraciones = DB::table('migraciones_temporadas')
            ->where('copio_catalogo_materiales', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['temporada_origen_id', 'temporada_destino_id']);

        foreach ($migraciones as $migracion) {
            $tipificacionesOrigen = DB::table('items_materiales as items')
                ->join('clientes_materiales as clientes', 'clientes.id', '=', 'items.cliente_material_id')
                ->join('temporadas_materiales as temporadas', 'temporadas.id', '=', 'clientes.temporada_material_id')
                ->where('temporadas.temporada_id', $migracion->temporada_origen_id)
                ->whereNotNull('items.categoria_operacional')
                ->get([
                    'clientes.cliente_id',
                    'items.codigo',
                    'items.categoria_operacional',
                ])
                ->mapWithKeys(fn (object $item): array => [
                    $clave($item->cliente_id, $item->codigo) => $item->categoria_operacional,
                ]);

            if ($tipificacionesOrigen->isEmpty()) {
                continue;
            }

            $reparaciones = DB::table('items_materiales as items')
                ->join('clientes_materiales as clientes', 'clientes.id', '=', 'items.cliente_material_id')
                ->join('temporadas_materiales as temporadas', 'temporadas.id', '=', 'clientes.temporada_material_id')
                ->where('temporadas.temporada_id', $migracion->temporada_destino_id)
                ->whereNull('items.categoria_operacional')
                ->get(['items.id', 'clientes.cliente_id', 'items.codigo'])
                ->map(fn (object $item): array => [
                    'id' => $item->id,
                    'categoria_operacional' => $tipificacionesOrigen->get(
                        $clave($item->cliente_id, $item->codigo),
                    ),
                ])
                ->filter(fn (array $item): bool => $item['categoria_operacional'] !== null)
                ->groupBy('categoria_operacional');

            foreach ($reparaciones as $categoria => $items) {
                DB::table('items_materiales')
                    ->whereIn('id', $items->pluck('id'))
                    ->whereNull('categoria_operacional')
                    ->update([
                        'categoria_operacional' => $categoria,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Conserva las tipificaciones recuperadas: no es seguro distinguirlas de cambios posteriores.
    }
};
