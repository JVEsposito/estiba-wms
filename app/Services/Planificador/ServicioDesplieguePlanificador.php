<?php

namespace App\Services\Planificador;

use App\Models\Camara;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ServicioDesplieguePlanificador
{
    public function modoGlobal(): string
    {
        return (string) config('planificador.mode', 'off');
    }

    /**
     * El modo global es el techo de seguridad. Una lista de rollout solo
     * restringe guided; nunca puede elevar off o shadow.
     *
     * @param  iterable<int, Camara|string|null>  $camaras
     */
    public function modoEfectivo(iterable $camaras = []): string
    {
        $global = $this->modoGlobal();
        if ($global !== 'guided' || ! $this->rolloutLimitado()) {
            return $global;
        }

        $camaras = collect($camaras)->filter()->values();
        if ($camaras->isEmpty()) {
            return 'shadow';
        }

        return $camaras->every(fn (Camara|string $camara): bool => $this->incluida($camara))
            ? 'guided'
            : 'shadow';
    }

    public function modoParaCamara(Camara|string|null $camara): string
    {
        return $this->modoEfectivo([$camara]);
    }

    /** @param iterable<int, Camara|string|null> $camaras */
    public function dirige(iterable $camaras): bool
    {
        return (bool) config('planificador.generacion_automatica')
            && $this->modoEfectivo($camaras) === 'guided'
            && config('planificador.compute') === 'tablet'
            && config('planificador.horizon') === 'rolling';
    }

    /**
     * null significa que guided no está limitado a cámaras concretas.
     *
     * @return array<int, string>|null
     */
    public function idsCamarasDirigidas(): ?array
    {
        if (! $this->dirigidoGlobalActivo()) {
            return [];
        }
        if (! $this->rolloutLimitado()) {
            return null;
        }

        $permitidas = $this->permitidas();

        return Camara::query()
            ->get(['id', 'codigo'])
            ->filter(fn (Camara $camara): bool => $this->coincide($camara, $permitidas))
            ->pluck('id')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        $limitado = $this->rolloutLimitado();
        $camaras = Camara::query()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (Camara $camara): array => [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
                'mode' => $this->modoParaCamara($camara),
            ])
            ->values();

        return [
            'mode_global' => $this->modoGlobal(),
            'compute' => config('planificador.compute'),
            'horizon' => config('planificador.horizon'),
            'generacion_automatica' => (bool) config('planificador.generacion_automatica'),
            'rollout_limitado' => $limitado,
            'camaras_configuradas' => array_values(config('planificador.rollout_camaras', [])),
            'fuera_de_rollout' => $limitado && $this->modoGlobal() === 'guided' ? 'shadow' : null,
            'rollback' => 'WMS_PLANNER_MODE=off',
            'camaras' => $camaras,
        ];
    }

    private function rolloutLimitado(): bool
    {
        return $this->permitidas()->isNotEmpty();
    }

    private function dirigidoGlobalActivo(): bool
    {
        return (bool) config('planificador.generacion_automatica')
            && $this->modoGlobal() === 'guided'
            && config('planificador.compute') === 'tablet'
            && config('planificador.horizon') === 'rolling';
    }

    private function incluida(Camara|string $camara): bool
    {
        $permitidas = $this->permitidas();
        if ($camara instanceof Camara) {
            return $this->coincide($camara, $permitidas);
        }

        $identificador = Str::lower(trim($camara));
        if ($permitidas->contains($identificador)) {
            return true;
        }

        $modelo = Camara::query()->find($camara);

        return $modelo !== null && $this->coincide($modelo, $permitidas);
    }

    /** @return Collection<int, string> */
    private function permitidas(): Collection
    {
        return collect(config('planificador.rollout_camaras', []))
            ->map(fn (mixed $valor): string => Str::lower(trim((string) $valor)))
            ->filter()
            ->unique()
            ->values();
    }

    /** @param Collection<int, string> $permitidas */
    private function coincide(Camara $camara, Collection $permitidas): bool
    {
        return $permitidas->contains(Str::lower((string) $camara->id))
            || $permitidas->contains(Str::lower((string) $camara->codigo));
    }
}
