<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\PerfilAcceso;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorizacionPerfilAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_personalizado_manda_sobre_restricciones_legacy_del_rol_base(): void
    {
        $perfil = PerfilAcceso::create([
            'codigo' => 'DIGITADOR_MP_AMPLIADO',
            'nombre' => 'Digitador MP ampliado',
            'rol_base' => RolUsuario::DigitadorMateriaPrima,
            'modulos' => [
                'materia-prima.romana',
                'materia-prima.digitacion',
                'materia-prima.hidrocooler',
                'materia-prima.fruta-proceso',
                'materia-prima.validacion-mp',
                'materia-prima.despacho-envases',
            ],
            'modulos_tablet' => [
                'validacion_mp',
                'fruta_proceso',
            ],
            'activo' => true,
            'predeterminado' => false,
            'protegido' => false,
        ]);
        $usuario = User::factory()->create([
            'rol' => RolUsuario::DigitadorMateriaPrima,
            'perfil_acceso_id' => $perfil->id,
            'activo' => true,
        ]);

        $alcance = app(AlcanceOperacionalUsuario::class);

        $this->assertTrue($alcance->puedeOperarRomana($usuario));
        $this->assertTrue($alcance->puedeGestionarLotesMateriaPrima($usuario));
        $this->assertTrue($alcance->puedeOperarHidrocoolerMateriaPrima($usuario));
        $this->assertTrue($alcance->puedeEntregarFrutaProceso($usuario));
        $this->assertTrue($alcance->puedeValidarMp($usuario));
        $this->assertTrue($alcance->puedeGestionarDespachoEnvases($usuario));
        $this->assertFalse($alcance->puedeOperarPrefrio($usuario));
    }

    public function test_perfil_predeterminado_conserva_la_matriz_legacy_del_rol(): void
    {
        $perfil = PerfilAcceso::query()
            ->where('rol_base', RolUsuario::DigitadorMateriaPrima->value)
            ->where('predeterminado', true)
            ->firstOrFail();
        $usuario = User::factory()->create([
            'rol' => RolUsuario::DigitadorMateriaPrima,
            'perfil_acceso_id' => $perfil->id,
            'activo' => true,
        ]);

        $alcance = app(AlcanceOperacionalUsuario::class);

        $this->assertTrue($alcance->puedeGestionarLotesMateriaPrima($usuario));
        $this->assertTrue($alcance->puedeOperarHidrocoolerMateriaPrima($usuario));
        $this->assertFalse($alcance->puedeOperarRomana($usuario));
        $this->assertFalse($alcance->puedeValidarMp($usuario));
        $this->assertFalse($alcance->puedeEntregarFrutaProceso($usuario));
        $this->assertFalse($alcance->puedeGestionarDespachoEnvases($usuario));
    }

    public function test_perfil_personalizado_de_solo_consulta_no_adquiere_escritura_por_marcar_modulos(): void
    {
        $perfil = PerfilAcceso::create([
            'codigo' => 'CONSULTA_MP_AMPLIADA',
            'nombre' => 'Consulta MP ampliada',
            'rol_base' => RolUsuario::Consulta,
            'modulos' => [
                'materia-prima.romana',
                'materia-prima.validacion-mp',
                'frigorifico.validacion',
            ],
            'modulos_tablet' => [],
            'activo' => true,
            'predeterminado' => false,
            'protegido' => false,
        ]);
        $usuario = User::factory()->create([
            'rol' => RolUsuario::Consulta,
            'perfil_acceso_id' => $perfil->id,
            'activo' => true,
        ]);

        $alcance = app(AlcanceOperacionalUsuario::class);

        $this->assertTrue($alcance->puedeConsultarRomana($usuario));
        $this->assertTrue($alcance->puedeConsultarValidacionesPallet($usuario));
        $this->assertFalse($alcance->puedeOperarRomana($usuario));
        $this->assertFalse($alcance->puedeValidarMp($usuario));
        $this->assertFalse($alcance->puedeValidarPallets($usuario));
    }
}
