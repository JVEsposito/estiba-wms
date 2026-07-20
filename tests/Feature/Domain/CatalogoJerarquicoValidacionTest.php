<?php

namespace Tests\Feature\Domain;

use App\Models\CalibreValidacion;
use App\Models\ClienteValidacion;
use App\Models\CsgValidacion;
use App\Models\EnvaseValidacion;
use App\Models\EspecieValidacion;
use App\Models\MarcaValidacion;
use App\Models\Temporada;
use App\Models\VariedadValidacion;
use App\Services\Validacion\ServicioCopiaCatalogoValidacion;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoJerarquicoValidacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_copia_una_temporada_con_jerarquias_y_autorizaciones_csg(): void
    {
        $origen = $this->temporada('2026', true);
        $destino = $this->temporada('2027');

        $cliente = ClienteValidacion::create([
            'temporada_id' => $origen->id,
            'nombre' => 'Exportadora Los Olmos',
            'codigo_externo' => 'CLI-01',
            'activo' => true,
        ]);
        MarcaValidacion::create([
            'cliente_validacion_id' => $cliente->id,
            'nombre' => 'Olmos Roja',
            'activo' => true,
        ]);

        $cereza = EspecieValidacion::create([
            'temporada_id' => $origen->id,
            'nombre' => 'Cereza',
            'activo' => true,
        ]);
        $santina = VariedadValidacion::create([
            'especie_validacion_id' => $cereza->id,
            'nombre' => 'Santina',
            'activo' => true,
        ]);
        CalibreValidacion::create([
            'especie_validacion_id' => $cereza->id,
            'nombre' => 'XL',
            'activo' => true,
        ]);
        EnvaseValidacion::create([
            'especie_validacion_id' => $cereza->id,
            'nombre' => '5 KG',
            'activo' => true,
        ]);

        $kiwi = EspecieValidacion::create([
            'temporada_id' => $origen->id,
            'nombre' => 'Kiwi',
            'activo' => true,
        ]);
        CalibreValidacion::create([
            'especie_validacion_id' => $kiwi->id,
            'nombre' => 'XL',
            'activo' => true,
        ]);
        EnvaseValidacion::create([
            'especie_validacion_id' => $kiwi->id,
            'nombre' => '5 KG',
            'activo' => true,
        ]);

        $csg = CsgValidacion::create([
            'temporada_id' => $origen->id,
            'codigo' => 'CSG-001',
            'predio' => 'Predio Norte',
            'activo' => true,
        ]);
        $csg->variedades()->attach($santina->id);

        $resultado = app(ServicioCopiaCatalogoValidacion::class)->copiar($origen, $destino);

        $this->assertSame(1, $resultado->clientes_count);
        $this->assertSame(2, $resultado->especies_count);
        $this->assertSame(1, $resultado->csg_count);
        $this->assertSame(2, $resultado->version_catalogo);
        $this->assertDatabaseHas('marcas_validacion', ['nombre' => 'Olmos Roja']);
        $this->assertSame(
            2,
            CalibreValidacion::query()->where('nombre', 'XL')->count(),
            'El mismo calibre debe poder existir como hijo de especies diferentes.',
        );
        $this->assertSame(
            2,
            EnvaseValidacion::query()->where('nombre', '5 KG')->count(),
            'El mismo envase debe poder existir como hijo de especies diferentes.',
        );

        $csgCopiado = CsgValidacion::query()
            ->where('temporada_id', $destino->id)
            ->where('codigo', 'CSG-001')
            ->firstOrFail();
        $this->assertSame(['Santina'], $csgCopiado->variedades()->pluck('nombre')->all());
    }

    public function test_no_sobrescribe_una_temporada_que_ya_posee_catalogo(): void
    {
        $origen = $this->temporada('2026', true);
        $destino = $this->temporada('2027');
        ClienteValidacion::create([
            'temporada_id' => $destino->id,
            'nombre' => 'Cliente existente',
            'activo' => true,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ya posee un catálogo');

        app(ServicioCopiaCatalogoValidacion::class)->copiar($origen, $destino);
    }

    private function temporada(string $codigo, bool $activa = false): Temporada
    {
        return Temporada::create([
            'codigo' => $codigo,
            'nombre' => "Temporada {$codigo}",
            'activa' => $activa,
        ]);
    }
}
