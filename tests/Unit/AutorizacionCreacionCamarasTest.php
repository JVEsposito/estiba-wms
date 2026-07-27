<?php

namespace Tests\Unit;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutorizacionCreacionCamarasTest extends TestCase
{
    #[DataProvider('contenidos')]
    public function test_solo_el_administrador_puede_crear_camaras(ContenidoCamara $contenido): void
    {
        $alcance = app(AlcanceOperacionalUsuario::class);
        $administrador = (new User)->forceFill([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $supervisorFrio = (new User)->forceFill([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $supervisorMateriales = (new User)->forceFill([
            'rol' => RolUsuario::SupervisorMateriales,
            'activo' => true,
        ]);

        $this->assertTrue($alcance->puedeCrearCamara($administrador, $contenido));
        $this->assertFalse($alcance->puedeCrearCamara($supervisorFrio, $contenido));
        $this->assertFalse($alcance->puedeCrearCamara($supervisorMateriales, $contenido));
    }

    public static function contenidos(): array
    {
        return collect(ContenidoCamara::cases())
            ->mapWithKeys(fn (ContenidoCamara $contenido): array => [
                $contenido->value => [$contenido],
            ])
            ->all();
    }
}
