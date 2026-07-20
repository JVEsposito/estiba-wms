<?php

namespace Tests\Feature\Domain;

use App\Models\ArticuloValidacion;
use App\Models\ClienteValidacion;
use App\Models\CombinacionValidacion;
use App\Models\CsgValidacion;
use App\Models\EspecieValidacion;
use App\Models\MarcaValidacion;
use App\Models\OrigenValidacion;
use App\Models\Temporada;
use App\Models\VariedadValidacion;
use App\Services\Validacion\ServicioCatalogoJerarquicoValidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperacionCatalogoJerarquicoValidacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_proyecta_la_jerarquia_al_contrato_actual_de_la_pda(): void
    {
        $temporada = Temporada::create([
            'codigo' => '2026',
            'nombre' => 'Temporada 2026',
            'activa' => true,
        ]);
        $servicio = app(ServicioCatalogoJerarquicoValidacion::class);

        $cliente = $servicio->guardarCliente([
            'temporada_id' => $temporada->id,
            'nombre' => 'Los Olmos',
            'activo' => true,
        ]);
        $servicio->guardarMarca([
            'cliente_validacion_id' => $cliente->id,
            'nombre' => 'Olmos Roja',
            'activo' => true,
        ]);
        $especie = $servicio->guardarEspecie([
            'temporada_id' => $temporada->id,
            'nombre' => 'Cereza',
            'activo' => true,
        ]);
        $variedad = $servicio->guardarVariedad([
            'especie_validacion_id' => $especie->id,
            'nombre' => 'Santina',
            'activo' => true,
        ]);
        $servicio->guardarCalibre([
            'especie_validacion_id' => $especie->id,
            'nombre' => 'xl',
            'activo' => true,
        ]);
        $servicio->guardarEnvase([
            'especie_validacion_id' => $especie->id,
            'nombre' => '5 KG',
            'activo' => true,
        ]);
        $csg = $servicio->guardarCsg([
            'temporada_id' => $temporada->id,
            'codigo' => 'csg-001',
            'predio' => 'Predio Norte',
            'variedad_ids' => [$variedad->id],
            'activo' => true,
        ]);

        $articulo = ArticuloValidacion::query()->sole();
        $origen = OrigenValidacion::query()->sole();
        $combinacion = CombinacionValidacion::query()->sole();

        $this->assertSame($especie->id, $articulo->especie_validacion_id);
        $this->assertSame('XL', $articulo->calibre);
        $this->assertSame($cliente->id, $origen->cliente_validacion_id);
        $this->assertSame($csg->id, $origen->csg_validacion_id);
        $this->assertTrue($articulo->activo);
        $this->assertTrue($origen->activo);
        $this->assertTrue($combinacion->activo);
    }

    public function test_un_csg_solo_habilita_las_variedades_declaradas(): void
    {
        $temporada = Temporada::create([
            'codigo' => '2027',
            'nombre' => 'Temporada 2027',
            'activa' => true,
        ]);
        $servicio = app(ServicioCatalogoJerarquicoValidacion::class);

        $cliente = ClienteValidacion::create([
            'temporada_id' => $temporada->id,
            'nombre' => 'Cliente',
            'activo' => true,
        ]);
        MarcaValidacion::create([
            'cliente_validacion_id' => $cliente->id,
            'nombre' => 'Marca',
            'activo' => true,
        ]);
        $especie = EspecieValidacion::create([
            'temporada_id' => $temporada->id,
            'nombre' => 'Cereza',
            'activo' => true,
        ]);
        $santina = VariedadValidacion::create([
            'especie_validacion_id' => $especie->id,
            'nombre' => 'Santina',
            'activo' => true,
        ]);
        VariedadValidacion::create([
            'especie_validacion_id' => $especie->id,
            'nombre' => 'Lapins',
            'activo' => true,
        ]);
        $servicio->guardarCalibre([
            'especie_validacion_id' => $especie->id,
            'nombre' => 'J',
            'activo' => true,
        ]);
        $servicio->guardarEnvase([
            'especie_validacion_id' => $especie->id,
            'nombre' => '5 KG',
            'activo' => true,
        ]);
        $servicio->guardarCsg([
            'temporada_id' => $temporada->id,
            'codigo' => 'CSG-002',
            'variedad_ids' => [$santina->id],
            'activo' => true,
        ]);

        $this->assertSame(2, ArticuloValidacion::query()->count());
        $this->assertSame(1, CombinacionValidacion::query()->where('activo', true)->count());
        $this->assertSame(
            'Santina',
            CombinacionValidacion::query()
                ->where('activo', true)
                ->firstOrFail()
                ->articulo
                ->variedad,
        );
        $this->assertSame(1, CsgValidacion::query()->sole()->variedades()->count());
    }
}
