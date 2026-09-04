<?php

namespace Tests\Unit;

use App\Enums\TipoLoteInspeccionSag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TipoLoteInspeccionSagTest extends TestCase
{
    #[DataProvider('tiposDeInspeccion')]
    public function test_solo_origen_y_usda_requieren_preparacion_fisica(
        TipoLoteInspeccionSag $tipo,
        bool $esperado,
    ): void {
        $this->assertSame($esperado, $tipo->requierePreparacionFisica());
    }

    /** @return array<string, array{TipoLoteInspeccionSag, bool}> */
    public static function tiposDeInspeccion(): array
    {
        return [
            'muestreo USDA' => [TipoLoteInspeccionSag::MuestreoUsda, true],
            'inspección de origen' => [TipoLoteInspeccionSag::InspeccionOrigen, true],
            'inspección en línea' => [TipoLoteInspeccionSag::InspeccionLinea, false],
            'fumigación' => [TipoLoteInspeccionSag::Fumigacion, false],
            'cambio de mercado' => [TipoLoteInspeccionSag::CambioMercado, false],
        ];
    }
}
