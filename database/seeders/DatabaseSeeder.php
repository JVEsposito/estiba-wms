<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\ArticuloValidacion;
use App\Models\Camara;
use App\Models\Cliente;
use App\Models\ClienteValidacion;
use App\Models\CombinacionValidacion;
use App\Models\CondicionSag;
use App\Models\Dispositivo;
use App\Models\OrigenValidacion;
use App\Models\Posicion;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'operador@estiba.local'],
            [
                'name' => 'Operador de prueba',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::CamareroFrio,
                'activo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'supervisor@estiba.local'],
            [
                'name' => 'Supervisor de prueba',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::SupervisorFrio,
                'activo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'administrador@estiba.local'],
            [
                'name' => 'Administrador de prueba',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::Administrador,
                'activo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'despachador@estiba.local'],
            [
                'name' => 'Despachador de prueba',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::Despachador,
                'activo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'validador@estiba.local'],
            [
                'name' => 'Validador de pallets',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::Validador,
                'activo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'camarero.materiales@estiba.local'],
            [
                'name' => 'Camarero de materiales',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::CamareroMateriales,
                'activo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'supervisor.materiales@estiba.local'],
            [
                'name' => 'Supervisor de materiales',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::SupervisorMateriales,
                'activo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'romana@estiba.local'],
            [
                'name' => 'Operador de romana',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::OperadorRomana,
                'activo' => true,
            ],
        );

        Dispositivo::query()->firstOrCreate(
            ['codigo' => 'TABLET-01'],
            [
                'nombre' => 'Tablet cámara 01',
                'plataforma' => 'android',
                'activo' => true,
            ],
        );

        foreach ([
            ['codigo' => 'APTA', 'nombre' => 'Apta para exportación'],
            ['codigo' => 'PENDIENTE', 'nombre' => 'Pendiente de inspección'],
            ['codigo' => 'OBSERVADA', 'nombre' => 'Con observación SAG'],
        ] as $condicion) {
            CondicionSag::query()->firstOrCreate(
                ['codigo' => $condicion['codigo']],
                [...$condicion, 'activo' => true],
            );
        }

        $this->crearCatalogoValidacion();
        $this->crearCamaraConPosiciones('CAM-01', 'Cámara de tránsito 01', 'transito');
        $this->crearCamaraConPosiciones('CAM-02', 'Cámara de tránsito 02', 'transito');
        $this->crearCamaraConPosiciones('DES-01', 'Zona de despacho', 'despacho');
    }

    private function crearCatalogoValidacion(): void
    {
        Temporada::query()->where('codigo', '!=', '2026-2027')->update(['activa' => false]);
        $temporada = Temporada::query()->updateOrCreate(
            ['codigo' => '2026-2027'],
            [
                'nombre' => 'Temporada cerezas 2026–2027',
                'fecha_inicio' => '2026-10-01',
                'fecha_fin' => '2027-02-28',
                'activa' => true,
            ],
        );

        $cliente = Cliente::query()->updateOrCreate(
            ['codigo' => 'EXPORTADORA-DEMO'],
            [
                'nombre' => 'Exportadora demo',
                'codigo_externo' => 'EXP-DEMO',
                'activo' => true,
            ],
        );
        ClienteValidacion::query()->updateOrCreate(
            [
                'temporada_id' => $temporada->id,
                'nombre' => 'Exportadora demo',
            ],
            [
                'cliente_id' => $cliente->id,
                'codigo_externo' => 'EXP-DEMO',
                'activo' => true,
            ],
        );

        $articulo = ArticuloValidacion::query()->updateOrCreate(
            [
                'temporada_id' => $temporada->id,
                'especie' => 'Cereza',
                'variedad' => 'Santina',
                'calibre' => '2J',
                'envase' => 'Caja 5 kg',
            ],
            [
                'codigo_externo' => 'CER-SAN-2J-5KG',
                'activo' => true,
            ],
        );
        $origen = OrigenValidacion::query()->updateOrCreate(
            [
                'temporada_id' => $temporada->id,
                'cliente' => 'Exportadora demo',
                'marca' => 'Estiba Select',
                'csg' => '105410',
            ],
            [
                'predio' => 'Predio demostrativo',
                'codigo_externo' => 'ORI-DEMO-01',
                'activo' => true,
            ],
        );

        CombinacionValidacion::query()->updateOrCreate(
            [
                'temporada_id' => $temporada->id,
                'articulo_validacion_id' => $articulo->id,
                'origen_validacion_id' => $origen->id,
            ],
            [
                'codigo_externo' => 'VAL-DEMO-001',
                'activo' => true,
            ],
        );
    }

    private function crearCamaraConPosiciones(
        string $codigo,
        string $nombre,
        string $tipo,
    ): void {
        $camara = Camara::query()->firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $nombre,
                'tipo' => $tipo,
                'cantidad_bandas' => 3,
                'posiciones_por_banda' => 4,
                'cantidad_niveles' => 2,
            ],
        );

        foreach (range(1, 3) as $banda) {
            foreach (range(1, 4) as $posicion) {
                foreach (range(1, 2) as $nivel) {
                    Posicion::query()->firstOrCreate(
                        [
                            'camara_id' => $camara->id,
                            'banda' => $banda,
                            'posicion' => $posicion,
                            'nivel' => $nivel,
                        ],
                        ['etiqueta' => sprintf('B%02d-P%02d-N%d', $banda, $posicion, $nivel)],
                    );
                }
            }
        }
    }
}
