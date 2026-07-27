<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\ConexionExistencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExistenciasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_ve_las_tres_existencias_y_descarga_un_corte_xlsx(): void
    {
        [, $token] = $this->acceso(RolUsuario::Administrador);

        $this->withToken($token)
            ->getJson('/api/existencias')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['tipo' => 'producto-terminado'])
            ->assertJsonFragment(['tipo' => 'materiales'])
            ->assertJsonFragment(['tipo' => 'materia-prima']);

        $respuesta = $this->withToken($token)
            ->get('/api/existencias/producto-terminado/corte');

        $respuesta
            ->assertOk()
            ->assertDownload()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $this->assertStringContainsString(
            'Existencia_Producto_Terminado_',
            (string) $respuesta->headers->get('content-disposition'),
        );
    }

    public function test_conexion_excel_es_revocable_y_deja_de_actualizarse(): void
    {
        [, $tokenOficina] = $this->acceso(RolUsuario::Administrador);

        $respuesta = $this->withToken($tokenOficina)
            ->post('/api/existencias/materiales/conexion-excel');

        $respuesta
            ->assertCreated()
            ->assertHeader('content-type', 'application/x-msquery; charset=UTF-8');
        $this->assertStringContainsString("WEB\r\n1\r\n", $respuesta->getContent());
        $this->assertMatchesRegularExpression('/token=([A-Za-z0-9]+)/', $respuesta->getContent());
        preg_match('/token=([A-Za-z0-9]+)/', $respuesta->getContent(), $coincidencias);
        $tokenConsulta = $coincidencias[1];
        $conexion = ConexionExistencia::query()->firstOrFail();

        $this->get('/api/existencias/materiales/consulta?token='.$tokenConsulta)
            ->assertOk()
            ->assertSee('Existencia de materiales')
            ->assertSee('Cantidad disponible');

        $this->withToken($tokenOficina)
            ->postJson("/api/existencias/conexiones/{$conexion->id}/revocar")
            ->assertOk()
            ->assertJsonPath('data.vigente', false);

        $this->get('/api/existencias/materiales/consulta?token='.$tokenConsulta)
            ->assertGone();
    }

    public function test_supervisor_materiales_solo_recibe_existencia_de_materiales(): void
    {
        [, $token] = $this->acceso(RolUsuario::SupervisorMateriales);

        $this->withToken($token)
            ->get('/api/existencias/materia-prima/corte')
            ->assertForbidden();
        $this->withToken($token)
            ->get('/api/existencias/producto-terminado/corte')
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/existencias')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'materiales');
    }

    public function test_oficina_de_existencias_esta_disponible(): void
    {
        $this->get('/oficina/existencias')
            ->assertOk()
            ->assertSee('Tres inventarios. Una fuente oficial.')
            ->assertSee('Excel conectado');
    }

    /** @return array{User, string} */
    private function acceso(RolUsuario $rol): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $token = $usuario->createToken('prueba-existencias', ['oficina'])->plainTextToken;

        return [$usuario, $token];
    }
}
