<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazRecepcionMaterialMovilTest extends TestCase
{
    public function test_la_tablet_distribuye_bultos_en_bloque_y_selecciona_fechas_desde_calendario(): void
    {
        $mobile = file_get_contents(base_path('mobile/src/screens/MaterialReceptionScreen.tsx'));
        $domain = file_get_contents(base_path('mobile/src/domain/materialReception.ts'));

        $this->assertIsString($mobile);
        $this->assertIsString($domain);
        $this->assertStringContainsString('Cantidad total recibida', $mobile);
        $this->assertStringContainsString('Cantidad por bulto', $mobile);
        $this->assertStringContainsString('Calcular bultos', $mobile);
        $this->assertStringContainsString('distributePackages', $mobile);
        $this->assertStringContainsString('MAX_PACKAGES_PER_DETAIL = 500', $mobile);
        $this->assertStringContainsString('<DateField label="Fecha documento"', $mobile);
        $this->assertStringContainsString('cantidad_por_bulto', $domain);
        $this->assertStringNotContainsString('+ Agregar bulto', $mobile);
        $this->assertStringNotContainsString('placeholder="AAAA-MM-DD"', $mobile);
    }
}
