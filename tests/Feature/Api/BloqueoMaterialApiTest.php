<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\ClienteMaterial;
use App\Models\Dispositivo;
use App\Models\EventoBloqueoMaterial;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\Posicion;
use App\Models\User;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BloqueoMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_acceso_de_oficina_expone_el_permiso_de_bloqueo_supervisado(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorMateriales,
            'activo' => true,
            'email' => 'supervision.bloqueos@estiba.local',
            'password' => 'password123',
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => $supervisor->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_gestionar_bloqueos_materiales', true)
            ->assertJsonPath(
                'usuario.capacidades.puede_gestionar_bloqueos_materiales',
                true,
            );
    }

    public function test_supervisor_bloquea_y_libera_un_folio_con_auditoria_idempotente(): void
    {
        [$folio, $material] = $this->crearMaterialUbicado();
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorMateriales,
            'activo' => true,
        ]);
        $token = $supervisor->createToken('oficina-supervisor', ['oficina'])->plainTextToken;
        $operacionBloqueo = (string) Str::uuid();
        $payloadBloqueo = [
            'operacion_id' => $operacionBloqueo,
            'motivo' => 'Envase pendiente de inspección visual.',
        ];

        $this->conToken($token)
            ->postJson("/api/materiales/inventario/{$folio->id}/bloquear", $payloadBloqueo)
            ->assertOk()
            ->assertJsonPath('data.tipo', 'bloqueado')
            ->assertJsonPath('data.folio.estado_operacional', 'bloqueado')
            ->assertJsonPath('data.folio.motivo_bloqueo', $payloadBloqueo['motivo'])
            ->assertJsonPath('data.usuario.id', $supervisor->id);
        $this->conToken($token)
            ->postJson("/api/materiales/inventario/{$folio->id}/bloquear", $payloadBloqueo)
            ->assertOk()
            ->assertJsonPath('data.operacion_id', $operacionBloqueo);
        $this->conToken($token)
            ->postJson("/api/materiales/inventario/{$folio->id}/bloquear", [
                ...$payloadBloqueo,
                'motivo' => 'Reintento alterado que debe rechazarse.',
            ])
            ->assertConflict();
        $this->assertSame(1, EventoBloqueoMaterial::query()->count());
        $this->assertSame(
            EstadoOperacionalFolio::Bloqueado,
            $folio->refresh()->estado_operacional,
        );
        $this->assertSame($payloadBloqueo['motivo'], $material->refresh()->motivo_bloqueo);

        $operacionLiberacion = (string) Str::uuid();
        $this->conToken($token)
            ->postJson("/api/materiales/inventario/{$folio->id}/liberar-bloqueo", [
                'operacion_id' => $operacionLiberacion,
                'motivo' => 'Inspección conforme, material autorizado.',
            ])
            ->assertOk()
            ->assertJsonPath('data.tipo', 'liberado')
            ->assertJsonPath('data.estado_anterior', 'bloqueado')
            ->assertJsonPath('data.estado_resultante', 'disponible')
            ->assertJsonPath('data.folio.estado_operacional', 'disponible')
            ->assertJsonPath('data.folio.motivo_bloqueo', null);
        $this->assertSame(
            EstadoOperacionalFolio::Disponible,
            $folio->refresh()->estado_operacional,
        );
        $this->assertNull($material->refresh()->motivo_bloqueo);
        $this->assertSame(2, EventoBloqueoMaterial::query()->count());
    }

    public function test_restringe_bloqueo_a_supervision_y_rechaza_folios_reservados(): void
    {
        [$folio, $material] = $this->crearMaterialUbicado();
        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $tokenCamarero = $camarero
            ->createToken('oficina-camarero', ['oficina'])
            ->plainTextToken;
        $payload = [
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Intento de bloqueo no autorizado.',
        ];
        $this->conToken($tokenCamarero)
            ->postJson("/api/materiales/inventario/{$folio->id}/bloquear", $payload)
            ->assertForbidden();

        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorMateriales,
            'activo' => true,
        ]);
        $tokenSupervisor = $supervisor
            ->createToken('oficina-supervisor', ['oficina'])
            ->plainTextToken;
        $material->update(['cantidad_reservada' => 1]);
        $payload['operacion_id'] = (string) Str::uuid();
        $payload['motivo'] = 'Bloqueo con una reserva activa.';
        $this->conToken($tokenSupervisor)
            ->postJson("/api/materiales/inventario/{$folio->id}/bloquear", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->assertSame(
            EstadoOperacionalFolio::Disponible,
            $folio->refresh()->estado_operacional,
        );
        $this->assertDatabaseCount('eventos_bloqueos_materiales', 0);
    }

    public function test_liberar_un_folio_sin_ubicacion_lo_devuelve_a_pendiente(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $item = $this->crearItem($administrador);
        $folio = Folio::create([
            'numero_folio' => 'FGE7654321',
            'tipo_bulto' => TipoBulto::Material,
            'estado_operacional' => EstadoOperacionalFolio::Bloqueado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'cantidad_inicial' => 5,
            'cantidad_actual' => 5,
            'cantidad_reservada' => 0,
            'unidad_medida' => $item->unidad_medida,
            'motivo_bloqueo' => 'Pendiente de certificado.',
        ]);
        $token = $administrador->createToken('oficina-admin', ['oficina'])->plainTextToken;

        $this->conToken($token)
            ->postJson("/api/materiales/inventario/{$folio->id}/liberar-bloqueo", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Certificado recibido y conforme.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado_resultante', 'pendiente_ubicacion');
        $this->assertSame(
            EstadoOperacionalFolio::PendienteUbicacion,
            $folio->refresh()->estado_operacional,
        );
    }

    /**
     * @return array{Folio, FolioMaterial}
     */
    private function crearMaterialUbicado(): array
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $operador = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-BLOQ-'.Str::upper(Str::random(5)),
            'nombre' => 'Tablet bloqueo',
            'activo' => true,
        ]);
        $item = $this->crearItem($administrador);
        $camara = Camara::create([
            'codigo' => 'MAT-BLOQ-'.Str::upper(Str::random(4)),
            'nombre' => 'Cámara de bloqueo',
            'contenido' => ContenidoCamara::Materiales,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        $sesion = app(ServicioSesionEstiba::class)->abrir(
            $camara,
            $operador,
            $dispositivo,
        );
        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(
            operacionId: (string) Str::uuid(),
            numeroFolio: 'FGE1234567',
            tipoBulto: TipoBulto::Material,
            posicionDestino: $posicion,
            sesionDestino: $sesion,
            usuario: $operador,
            dispositivo: $dispositivo,
            versionDestinoConocida: 0,
            generadoDispositivoAt: now(),
            datosMaterial: [
                'item_material_id' => $item->id,
                'cantidad' => 10,
            ],
        );

        return [$movimiento->folio, $movimiento->folio->material];
    }

    private function crearItem(User $administrador): ItemMaterial
    {
        return ItemMaterial::create([
            'cliente_material_id' => ClienteMaterial::query()
                ->where('codigo', 'GENERAL')
                ->firstOrFail()
                ->id,
            'codigo' => 'ITEM-BLOQ-'.Str::upper(Str::random(5)),
            'nombre' => 'Material sujeto a bloqueo',
            'categoria' => 'Embalaje',
            'unidad_medida' => 'unidad',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
    }

    private function conToken(string $token): static
    {
        auth('sanctum')->forgetUser();

        return $this->withToken($token);
    }
}
