<?php

namespace App\Services\Operacion;

use SplPriorityQueue;

/**
 * Red física del plano: qué recintos se comunican entre sí y por dónde.
 *
 * Una conexión une dos elementos del plano que comparten un borde (una puerta
 * de cámara hacia un pasillo, la boca de un túnel hacia una cámara, dos tramos
 * de pasillo). La geometría es la del plano guardado en unidades 0..10000; los
 * recorridos se miden entre centros de elementos y sirven como distancia
 * relativa, no como metros.
 *
 * La misma regla de contacto existe en resources/js/shared/plant-layout.js para
 * la vista previa del editor; el servidor es la autoridad al guardar.
 */
class ServicioRedPlanta
{
    /** Separación máxima entre bordes que todavía se considera contacto. */
    public const TOLERANCIA_CONTACTO = 100;

    /** Largo mínimo de borde compartido para ubicar una puerta. */
    public const BORDE_MINIMO = 100;

    /** Áreas que existen físicamente pero no admiten tránsito. */
    public const CATEGORIAS_SIN_TRANSITO = ['no_operativo'];

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array{x: int, y: int}|null
     */
    public function puntoContacto(array $a, array $b): ?array
    {
        $ax2 = $a['x'] + $a['ancho'];
        $ay2 = $a['y'] + $a['alto'];
        $bx2 = $b['x'] + $b['ancho'];
        $by2 = $b['y'] + $b['alto'];

        $solapeX = min($ax2, $bx2) - max($a['x'], $b['x']);
        $solapeY = min($ay2, $by2) - max($a['y'], $b['y']);
        $separacionX = max(0, -$solapeX);
        $separacionY = max(0, -$solapeY);

        // Elementos superpuestos, como un andén dentro del patio: la puerta se
        // ubica en el borde superior de la intersección.
        if ($solapeX > 0 && $solapeY > 0) {
            return [
                'x' => (int) round((max($a['x'], $b['x']) + min($ax2, $bx2)) / 2),
                'y' => (int) max($a['y'], $b['y']),
            ];
        }

        if ($separacionX <= self::TOLERANCIA_CONTACTO && $solapeY >= self::BORDE_MINIMO) {
            $borde = $a['x'] < $b['x'] ? ($ax2 + $b['x']) / 2 : ($bx2 + $a['x']) / 2;

            return [
                'x' => (int) round($borde),
                'y' => (int) round((max($a['y'], $b['y']) + min($ay2, $by2)) / 2),
            ];
        }

        if ($separacionY <= self::TOLERANCIA_CONTACTO && $solapeX >= self::BORDE_MINIMO) {
            $borde = $a['y'] < $b['y'] ? ($ay2 + $b['y']) / 2 : ($by2 + $a['y']) / 2;

            return [
                'x' => (int) round((max($a['x'], $b['x']) + min($ax2, $bx2)) / 2),
                'y' => (int) round($borde),
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $elementos
     * @param  array<int, array<string, mixed>>  $conexiones
     * @return array<string, array<int, string>> Errores por clave de validación.
     */
    public function validar(array $elementos, array $conexiones): array
    {
        $porId = collect($elementos)->keyBy('id');
        $errores = [];
        $pares = [];

        foreach ($conexiones as $indice => $conexion) {
            $clave = "conexiones.$indice";
            $desde = $porId->get($conexion['desde']);
            $hacia = $porId->get($conexion['hacia']);

            if (! $desde || ! $hacia) {
                $errores[$clave][] = 'La conexión referencia un elemento que no está en el plano.';

                continue;
            }
            if ($desde['id'] === $hacia['id']) {
                $errores[$clave][] = 'Un elemento no puede conectarse consigo mismo.';

                continue;
            }
            if (! $this->admiteTransito($desde) || ! $this->admiteTransito($hacia)) {
                $errores[$clave][] = 'Las áreas no operativas no admiten conexiones de tránsito.';

                continue;
            }

            $par = collect([$desde['id'], $hacia['id']])->sort()->implode('|');
            if (isset($pares[$par])) {
                $errores[$clave][] = 'Los mismos elementos ya están conectados.';

                continue;
            }
            $pares[$par] = true;

            if ($this->puntoContacto($desde, $hacia) === null) {
                $errores[$clave][] = sprintf(
                    '%s y %s no comparten un borde; acércalos o conecta ambos a un pasillo.',
                    $desde['nombre'],
                    $hacia['nombre'],
                );
            }
        }

        return $errores;
    }

    /**
     * Resumen publicado junto al plano.
     *
     * @param  array<int, array<string, mixed>>  $elementos
     * @param  array<int, array<string, mixed>>  $conexiones
     * @return array{conexiones: int, pasillos: int, recintos: int, recintos_conectados: int, recintos_sin_acceso: array<int, string>, redes: int}
     */
    public function resumen(array $elementos, array $conexiones): array
    {
        $adyacencia = $this->adyacencia($elementos, $conexiones);
        $recintos = collect($elementos)->reject(fn (array $elemento): bool => in_array($elemento['tipo'], ['zona', 'pasillo'], true));
        $sinAcceso = $recintos
            ->filter(fn (array $elemento): bool => ($adyacencia[$elemento['id']] ?? []) === [])
            ->pluck('id')
            ->values()
            ->all();

        return [
            'conexiones' => count($conexiones),
            'pasillos' => collect($elementos)->where('tipo', 'pasillo')->count(),
            'recintos' => $recintos->count(),
            'recintos_conectados' => $recintos->count() - count($sinAcceso),
            'recintos_sin_acceso' => $sinAcceso,
            'redes' => $this->componentes($recintos->pluck('id')->all(), $adyacencia),
        ];
    }

    /**
     * Camino más corto entre dos elementos siguiendo las conexiones.
     *
     * @param  array<int, array<string, mixed>>  $elementos
     * @param  array<int, array<string, mixed>>  $conexiones
     * @return array{elementos: array<int, string>, distancia: int}|null
     */
    public function recorrido(array $elementos, array $conexiones, string $desdeId, string $haciaId): ?array
    {
        $porId = collect($elementos)->keyBy('id');
        if (! $porId->has($desdeId) || ! $porId->has($haciaId)) {
            return null;
        }
        if ($desdeId === $haciaId) {
            return ['elementos' => [$desdeId], 'distancia' => 0];
        }

        $adyacencia = $this->adyacencia($elementos, $conexiones);
        $distancias = [$desdeId => 0.0];
        $previo = [];
        $cola = new SplPriorityQueue;
        $cola->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $cola->insert($desdeId, 0.0);

        while (! $cola->isEmpty()) {
            ['data' => $actual, 'priority' => $prioridad] = $cola->extract();
            if (-$prioridad > ($distancias[$actual] ?? INF)) {
                continue;
            }
            if ($actual === $haciaId) {
                break;
            }

            foreach ($adyacencia[$actual] ?? [] as $vecino) {
                $candidata = $distancias[$actual] + $this->distanciaCentros($porId[$actual], $porId[$vecino]);
                if ($candidata < ($distancias[$vecino] ?? INF)) {
                    $distancias[$vecino] = $candidata;
                    $previo[$vecino] = $actual;
                    $cola->insert($vecino, -$candidata);
                }
            }
        }

        if (! isset($distancias[$haciaId])) {
            return null;
        }

        $camino = [$haciaId];
        while (end($camino) !== $desdeId) {
            $camino[] = $previo[end($camino)];
        }

        return [
            'elementos' => array_reverse($camino),
            'distancia' => (int) round($distancias[$haciaId]),
        ];
    }

    /** @param  array<string, mixed>  $elemento */
    private function admiteTransito(array $elemento): bool
    {
        return ! ($elemento['tipo'] === 'zona'
            && in_array($elemento['categoria'] ?? null, self::CATEGORIAS_SIN_TRANSITO, true));
    }

    /**
     * @param  array<int, array<string, mixed>>  $elementos
     * @param  array<int, array<string, mixed>>  $conexiones
     * @return array<string, array<int, string>>
     */
    private function adyacencia(array $elementos, array $conexiones): array
    {
        $ids = collect($elementos)->pluck('id')->flip();
        $adyacencia = [];
        foreach ($conexiones as $conexion) {
            if (! $ids->has($conexion['desde']) || ! $ids->has($conexion['hacia'])) {
                continue;
            }
            $adyacencia[$conexion['desde']][] = $conexion['hacia'];
            $adyacencia[$conexion['hacia']][] = $conexion['desde'];
        }

        return $adyacencia;
    }

    /**
     * Cantidad de redes independientes que contienen al menos un recinto.
     *
     * @param  array<int, string>  $recintos
     * @param  array<string, array<int, string>>  $adyacencia
     */
    private function componentes(array $recintos, array $adyacencia): int
    {
        $visitados = [];
        $redes = 0;
        foreach ($recintos as $inicio) {
            if (isset($visitados[$inicio]) || ($adyacencia[$inicio] ?? []) === []) {
                continue;
            }
            $redes++;
            $pila = [$inicio];
            while ($pila !== []) {
                $actual = array_pop($pila);
                if (isset($visitados[$actual])) {
                    continue;
                }
                $visitados[$actual] = true;
                array_push($pila, ...($adyacencia[$actual] ?? []));
            }
        }

        return $redes;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function distanciaCentros(array $a, array $b): float
    {
        return hypot(
            ($a['x'] + $a['ancho'] / 2) - ($b['x'] + $b['ancho'] / 2),
            ($a['y'] + $a['alto'] / 2) - ($b['y'] + $b['alto'] / 2),
        );
    }
}
