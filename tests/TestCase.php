<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function withToken($token, $type = 'Bearer')
    {
        if (isset($this->app)) {
            $this->app->make('auth')->forgetGuards();
        }

        return parent::withToken($token, $type);
    }

    /**
     * Fechas y prefijo documental para una temporada productiva creada en una
     * prueba. Cada llamada usa otro año, de modo que nunca se cruzan entre sí.
     *
     * @return array{fecha_inicio: string, fecha_fin: string, prefijo_documental: string}
     */
    protected function vigenciaProductiva(): array
    {
        static $secuencia = 0;
        $secuencia++;
        $anio = 2100 + $secuencia;

        return [
            'fecha_inicio' => "{$anio}-01-01",
            'fecha_fin' => "{$anio}-12-31",
            'prefijo_documental' => 'Z'.str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT),
        ];
    }
}
