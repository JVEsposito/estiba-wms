<?php

namespace Tests\Unit\Services\Operacion;

use App\Services\Operacion\ServicioRedPlanta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ServicioRedPlantaTest extends TestCase
{
    /**
     * Los mismos casos se prueban en tests/JavaScript/plant-network.test.mjs
     * para que el editor y el servidor apliquen idéntica regla de contacto.
     *
     * @return array<string, array{array<string, int>, array<string, int>, ?array{x: int, y: int}}>
     */
    public static function contactos(): array
    {
        return [
            'bordes verticales pegados' => [self::caja(0, 0, 1000, 1000), self::caja(1000, 200, 1000, 400), ['x' => 1000, 'y' => 400]],
            'separación dentro de tolerancia' => [self::caja(0, 0, 1000, 1000), self::caja(1080, 0, 500, 1000), ['x' => 1040, 'y' => 500]],
            'separación excesiva' => [self::caja(0, 0, 1000, 1000), self::caja(1200, 0, 500, 1000), null],
            'solo tocan una esquina' => [self::caja(0, 0, 1000, 1000), self::caja(1000, 1000, 500, 500), null],
            'borde compartido muy corto' => [self::caja(0, 0, 1000, 1000), self::caja(1000, 950, 500, 500), null],
            'pasillo bajo la cámara' => [self::caja(0, 0, 1000, 2000), self::caja(0, 2000, 4000, 200), ['x' => 500, 'y' => 2000]],
            'andén dentro del patio' => [self::caja(100, 5000, 3000, 4000), self::caja(300, 6000, 500, 3000), ['x' => 550, 'y' => 6000]],
        ];
    }

    #[DataProvider('contactos')]
    public function test_detecta_el_borde_compartido_donde_va_la_puerta(array $a, array $b, ?array $esperado): void
    {
        $red = new ServicioRedPlanta;

        $this->assertSame($esperado, $red->puntoContacto($a, $b));
        $this->assertSame($esperado === null, $red->puntoContacto($b, $a) === null);
    }

    public function test_valida_conexiones_inexistentes_repetidas_lejanas_y_sin_transito(): void
    {
        $elementos = [
            self::elemento('camara', 'a', 0, 0, 1000, 1000, 'CAM-01'),
            self::elemento('pasillo', 'p', 0, 1000, 4000, 200, 'Pasillo'),
            self::elemento('camara', 'b', 3000, 0, 1000, 1000, 'CAM-02'),
            self::elemento('zona', 'm', 0, 1200, 1000, 1000, 'Sala de máquinas', 'no_operativo'),
        ];
        $conexiones = [
            ['id' => 'c1', 'desde' => 'a', 'hacia' => 'p'],
            ['id' => 'c2', 'desde' => 'p', 'hacia' => 'a'],
            ['id' => 'c3', 'desde' => 'a', 'hacia' => 'b'],
            ['id' => 'c4', 'desde' => 'p', 'hacia' => 'm'],
            ['id' => 'c5', 'desde' => 'a', 'hacia' => 'x'],
            ['id' => 'c6', 'desde' => 'b', 'hacia' => 'b'],
        ];

        $errores = (new ServicioRedPlanta)->validar($elementos, $conexiones);

        $this->assertArrayNotHasKey('conexiones.0', $errores);
        $this->assertStringContainsString('ya están conectados', $errores['conexiones.1'][0]);
        $this->assertStringContainsString('CAM-01 y CAM-02 no comparten un borde', $errores['conexiones.2'][0]);
        $this->assertStringContainsString('no operativas', $errores['conexiones.3'][0]);
        $this->assertStringContainsString('no está en el plano', $errores['conexiones.4'][0]);
        $this->assertStringContainsString('consigo mismo', $errores['conexiones.5'][0]);
    }

    public function test_calcula_el_recorrido_mas_corto_y_los_recintos_sin_acceso(): void
    {
        $elementos = [
            self::elemento('camara', 'c1', 0, 0, 1000, 1000, 'CAM-01'),
            self::elemento('camara', 'c2', 3000, 0, 1000, 1000, 'CAM-02'),
            self::elemento('tunel', 't1', 3000, 1200, 1000, 1000, 'TUN-01'),
            self::elemento('camara', 'c3', 6000, 6000, 1000, 1000, 'CAM-03'),
            self::elemento('pasillo', 'p', 0, 1000, 4000, 200, 'Pasillo'),
        ];
        $conexiones = [
            ['id' => '1', 'desde' => 'c1', 'hacia' => 'p'],
            ['id' => '2', 'desde' => 'c2', 'hacia' => 'p'],
            ['id' => '3', 'desde' => 't1', 'hacia' => 'p'],
            ['id' => '4', 'desde' => 't1', 'hacia' => 'c2'],
        ];
        $red = new ServicioRedPlanta;

        $recorrido = $red->recorrido($elementos, $conexiones, 'c1', 't1');
        $this->assertSame(['c1', 'p', 't1'], $recorrido['elementos']);
        $this->assertGreaterThan(0, $recorrido['distancia']);
        $this->assertSame(['c2', 'p'], array_slice($red->recorrido($elementos, $conexiones, 'c2', 'p')['elementos'], 0, 2));
        $this->assertNull($red->recorrido($elementos, $conexiones, 'c1', 'c3'));
        $this->assertSame(['elementos' => ['c1'], 'distancia' => 0], $red->recorrido($elementos, $conexiones, 'c1', 'c1'));

        $resumen = $red->resumen($elementos, $conexiones);
        $this->assertSame(4, $resumen['conexiones']);
        $this->assertSame(1, $resumen['pasillos']);
        $this->assertSame(4, $resumen['recintos']);
        $this->assertSame(3, $resumen['recintos_conectados']);
        $this->assertSame(['c3'], $resumen['recintos_sin_acceso']);
        $this->assertSame(1, $resumen['redes']);
    }

    /** @return array{x: int, y: int, ancho: int, alto: int} */
    private static function caja(int $x, int $y, int $ancho, int $alto): array
    {
        return ['x' => $x, 'y' => $y, 'ancho' => $ancho, 'alto' => $alto];
    }

    /** @return array<string, mixed> */
    private static function elemento(string $tipo, string $id, int $x, int $y, int $ancho, int $alto, string $nombre, ?string $categoria = null): array
    {
        return [...self::caja($x, $y, $ancho, $alto), 'id' => $id, 'tipo' => $tipo, 'nombre' => $nombre, 'categoria' => $categoria];
    }
}
