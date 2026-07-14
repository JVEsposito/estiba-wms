<?php

namespace App\Services\Estiba;

use App\Enums\EstadoPosicion;
use App\Exceptions\AdvertenciasMovimientoPendientes;
use App\Models\Posicion;
use Illuminate\Support\Collection;

class DetectorAdvertenciasMovimiento
{
    public const POSICIONES_FONDO_LIBRES = 'posiciones_fondo_libres';

    public const NIVEL_SIN_SOPORTE = 'nivel_sin_soporte';

    public const NIVEL_SUPERIOR_OCUPADO = 'nivel_superior_ocupado';

    /**
     * @param  array<int, string>  $confirmadas
     * @return array<int, array<string, mixed>>
     */
    public function paraUbicacion(Posicion $destino, array $confirmadas): array
    {
        return $this->exigirConfirmacion(
            $this->advertenciasDestino($destino),
            $confirmadas,
        );
    }

    /**
     * @param  array<int, string>  $confirmadas
     * @return array<int, array<string, mixed>>
     */
    public function paraMovimiento(
        Posicion $origen,
        Posicion $destino,
        array $confirmadas,
    ): array {
        return $this->exigirConfirmacion(
            [
                ...$this->advertenciasDestino($destino, $origen),
                ...$this->advertenciasOrigen($origen, $destino),
            ],
            $confirmadas,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function advertenciasDestino(
        Posicion $destino,
        ?Posicion $origen = null,
    ): array {
        $advertencias = [];
        $profundasLibres = Posicion::query()
            ->where('camara_id', $destino->camara_id)
            ->where('banda', $destino->banda)
            ->where('nivel', $destino->nivel)
            ->where('posicion', '<', $destino->posicion)
            ->where('estado', EstadoPosicion::Activa->value)
            ->whereDoesntHave('ubicacionActual')
            ->orderBy('posicion')
            ->get();

        if ($origen
            && $origen->camara_id === $destino->camara_id
            && $origen->banda === $destino->banda
            && $origen->nivel === $destino->nivel
            && $origen->posicion < $destino->posicion) {
            $profundasLibres->push($origen);
        }

        $profundasLibres = $profundasLibres->unique('id')->sortBy('posicion')->values();

        if ($profundasLibres->isNotEmpty()) {
            $advertencias[] = [
                'codigo' => self::POSICIONES_FONDO_LIBRES,
                'titulo' => 'Existen posiciones libres hacia el fondo',
                'mensaje' => sprintf(
                    'Antes de %s están libres: %s. ¿Deseas continuar de todas formas?',
                    $destino->etiqueta,
                    $this->etiquetas($profundasLibres),
                ),
                'posiciones' => $profundasLibres->pluck('etiqueta')->values()->all(),
            ];
        }

        if ($destino->nivel > 1) {
            $soporte = Posicion::query()
                ->where('camara_id', $destino->camara_id)
                ->where('banda', $destino->banda)
                ->where('posicion', $destino->posicion)
                ->where('nivel', $destino->nivel - 1)
                ->where('estado', EstadoPosicion::Activa->value)
                ->first();
            $soporteQuedaraLibre = $soporte && (
                ! $soporte->ubicacionActual()->exists()
                || $origen?->id === $soporte->id
            );

            if ($soporteQuedaraLibre) {
                $advertencias[] = [
                    'codigo' => self::NIVEL_SIN_SOPORTE,
                    'titulo' => 'Nivel inferior libre',
                    'mensaje' => sprintf(
                        '%s quedará sin un bulto de soporte en %s. ¿Deseas continuar?',
                        $destino->etiqueta,
                        $soporte->etiqueta,
                    ),
                    'posiciones' => [$soporte->etiqueta],
                ];
            }
        }

        return $advertencias;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function advertenciasOrigen(
        Posicion $origen,
        Posicion $destino,
    ): array {
        $superiores = Posicion::query()
            ->where('camara_id', $origen->camara_id)
            ->where('banda', $origen->banda)
            ->where('posicion', $origen->posicion)
            ->where('nivel', '>', $origen->nivel)
            ->whereHas('ubicacionActual')
            ->orderBy('nivel')
            ->get();

        if ($destino->camara_id === $origen->camara_id
            && $destino->banda === $origen->banda
            && $destino->posicion === $origen->posicion
            && $destino->nivel > $origen->nivel) {
            $superiores->push($destino);
        }

        $superiores = $superiores->unique('id')->sortBy('nivel')->values();

        if ($superiores->isEmpty()) {
            return [];
        }

        return [[
            'codigo' => self::NIVEL_SUPERIOR_OCUPADO,
            'titulo' => 'Existen bultos en niveles superiores',
            'mensaje' => sprintf(
                'Al retirar %s quedarán bultos encima: %s. ¿Deseas continuar?',
                $origen->etiqueta,
                $this->etiquetas($superiores),
            ),
            'posiciones' => $superiores->pluck('etiqueta')->values()->all(),
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $advertencias
     * @param  array<int, string>  $confirmadas
     * @return array<int, array<string, mixed>>
     */
    private function exigirConfirmacion(array $advertencias, array $confirmadas): array
    {
        $confirmadas = array_unique($confirmadas);
        $pendientes = array_values(array_filter(
            $advertencias,
            fn (array $advertencia): bool => ! in_array(
                $advertencia['codigo'],
                $confirmadas,
                true,
            ),
        ));

        if ($pendientes !== []) {
            throw new AdvertenciasMovimientoPendientes($pendientes);
        }

        return $advertencias;
    }

    /**
     * @param  Collection<int, Posicion>  $posiciones
     */
    private function etiquetas(Collection $posiciones): string
    {
        return $posiciones
            ->pluck('etiqueta')
            ->filter()
            ->implode(', ');
    }
}
