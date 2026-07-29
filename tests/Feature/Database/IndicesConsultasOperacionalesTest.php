<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndicesConsultasOperacionalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_listados_operacionales_tienen_indices_para_sus_filtros_y_ordenamiento(): void
    {
        $this->assertIndexColumns(
            'despachos_materiales',
            'despachos_materiales_temporada_fecha_idx',
            ['temporada_id', 'created_at'],
        );
        $this->assertIndexColumns(
            'despachos_materiales',
            'despachos_materiales_temporada_estado_fecha_idx',
            ['temporada_id', 'estado', 'created_at'],
        );
        $this->assertIndexColumns(
            'movimientos_inventario_materiales',
            'movimientos_inventario_folio_fecha_idx',
            ['folio_id', 'ocurrido_at'],
        );
        $this->assertIndexColumns(
            'movimientos_inventario_materiales',
            'movimientos_inventario_item_fecha_idx',
            ['item_material_id', 'ocurrido_at'],
        );
        $this->assertIndexColumns(
            'movimientos',
            'movimientos_camara_origen_fecha_idx',
            ['camara_origen_id', 'created_at'],
        );
        $this->assertIndexColumns(
            'movimientos',
            'movimientos_camara_destino_fecha_idx',
            ['camara_destino_id', 'created_at'],
        );
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertIndexColumns(string $table, string $indexName, array $columns): void
    {
        $index = collect(Schema::getIndexes($table))->firstWhere('name', $indexName);

        $this->assertNotNull($index, "No existe el índice {$indexName} en {$table}.");
        $this->assertSame($columns, $index['columns']);
    }
}
