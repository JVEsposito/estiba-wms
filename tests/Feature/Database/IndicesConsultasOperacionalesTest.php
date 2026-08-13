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
        $this->assertIndexColumns(
            'folios',
            'folios_activo_tipo_estado_idx',
            ['activo', 'tipo_bulto', 'estado_operacional'],
        );
        $this->assertIndexColumns(
            'lotes_materia_prima',
            'lote_mp_temporada_envase_estado_idx',
            ['temporada_id', 'envase_primario', 'estado', 'created_at'],
        );
        $this->assertIndexColumns(
            'recepciones_romana',
            'romana_temporada_estado_ingreso_idx',
            ['temporada_id', 'estado', 'ingreso_at'],
        );
        $this->assertIndexColumns(
            'procesos_prefrio',
            'prefrio_temporada_estado_fecha_idx',
            ['temporada_id', 'estado', 'created_at'],
        );
        $this->assertIndexColumns(
            'cargas',
            'cargas_temporada_estado_publicada_idx',
            ['temporada_id', 'estado', 'publicada_at'],
        );
        $this->assertIndexColumns(
            'validaciones_pallet',
            'validacion_temporada_estado_fecha_idx',
            ['temporada_id', 'estado', 'recibido_servidor_at'],
        );
        $this->assertIndexColumns(
            'validaciones_pallet',
            'validacion_usuario_dispositivo_sesion_idx',
            ['user_id', 'dispositivo_id', 'generado_dispositivo_at'],
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
