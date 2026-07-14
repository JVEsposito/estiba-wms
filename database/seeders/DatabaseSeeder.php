<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\CondicionSag;
use App\Models\Dispositivo;
use App\Models\Posicion;
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

        User::query()->firstOrCreate(
            ['email' => 'operador@estiba.local'],
            [
                'name' => 'Operador de prueba',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::Operador,
                'activo' => true,
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'supervisor@estiba.local'],
            [
                'name' => 'Supervisor de prueba',
                'password' => Hash::make('password'),
                'rol' => RolUsuario::Supervisor,
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

        $this->crearCamaraConPosiciones('CAM-01', 'Cámara de tránsito 01', 'transito');
        $this->crearCamaraConPosiciones('CAM-02', 'Cámara de tránsito 02', 'transito');
        $this->crearCamaraConPosiciones('DES-01', 'Zona de despacho', 'despacho');
    }

    private function crearCamaraConPosiciones(
        string $codigo,
        string $nombre,
        string $tipo,
    ): void {
        $camara = Camara::query()->firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $nombre, 'tipo' => $tipo],
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
