<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaDespachoEnvasesTest extends TestCase
{
    public function test_muestra_reservas_confirmacion_y_respaldos_del_despacho_de_envases(): void
    {
        $this->get('/oficina/envases/despachos')
            ->assertOk()
            ->assertSee('El borrador reserva disponibilidad')
            ->assertSee('RESERVADO EN BORRADORES')
            ->assertSee('Crear borrador y reservar')
            ->assertSee('HISTORIAL Y RESPALDOS')
            ->assertSee('Fecha y hora efectiva de salida');
    }

    public function test_cuenta_corriente_distingue_reservas_de_movimientos_confirmados(): void
    {
        $this->get('/oficina/envases/cuenta-corriente')
            ->assertOk()
            ->assertSee('ENVASES RESERVADOS')
            ->assertSee('Borradores de despacho')
            ->assertSee('No modifican el saldo hasta confirmar la salida.');
    }
}
