<?php

namespace App\Services\Planificador;

use App\Models\CicloArbitrajeManiobras;
use App\Models\DecisionArbitrajeManiobra;
use App\Models\Temporada;
use Illuminate\Support\Collection;

final class ServicioComparacionCiclosArbitraje
{
    private const LIMITE_CAMBIOS_PUBLICADOS = 100;

    public function __construct(
        private readonly ServicioEstadoArbitrajePlanificador $estadoArbitraje,
    ) {}

    /** @return array<string, mixed> */
    public function obtener(Temporada $temporada): array
    {
        $proyeccion = $this->estadoArbitraje->consultar($temporada);
        $actual = $proyeccion['ciclo'];

        if (! $actual) {
            return $this->sinComparacion(
                'sin_ciclo_actual',
                'Todavía no existe un ciclo confirmado para comparar.',
            );
        }

        $anterior = CicloArbitrajeManiobras::query()
            ->where('temporada_id', $temporada->id)
            ->whereKeyNot($actual->id)
            ->where('created_at', '<=', $actual->created_at)
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $anterior) {
            return $this->sinComparacion(
                'sin_ciclo_anterior',
                'Este es el primer ciclo confirmado de la temporada.',
                $this->metadatosCiclo($actual),
            );
        }

        $this->cargarDecisiones($actual);
        $this->cargarDecisiones($anterior);

        return $this->comparar($actual, $anterior);
    }

    private function cargarDecisiones(CicloArbitrajeManiobras $ciclo): void
    {
        $ciclo->load([
            'decisiones' => fn ($consulta) => $consulta
                ->select([
                    'id',
                    'ciclo_arbitraje_id',
                    'maniobra_operacional_id',
                    'orden',
                    'decision',
                    'puntaje',
                    'beneficio_neto',
                    'motivo',
                    'explicacion',
                ])
                ->with('maniobraOperacional:id,titulo'),
        ]);
    }

    /** @return array<string, mixed> */
    private function comparar(
        CicloArbitrajeManiobras $actual,
        CicloArbitrajeManiobras $anterior,
    ): array {
        $actuales = $this->decisionesPorManiobra($actual);
        $anteriores = $this->decisionesPorManiobra($anterior);
        $resumen = [
            'incorporadas' => 0,
            'retiradas' => 0,
            'modificadas' => 0,
            'sin_cambios' => 0,
        ];
        $cambios = collect();

        $actuales->keys()
            ->merge($anteriores->keys())
            ->unique()
            ->each(function (string $maniobraId) use (
                $actuales,
                $anteriores,
                &$resumen,
                $cambios,
            ): void {
                $actual = $actuales->get($maniobraId);
                $anterior = $anteriores->get($maniobraId);

                if (! $anterior) {
                    $resumen['incorporadas']++;
                    $cambios->push($this->cambioIncorporado($actual));

                    return;
                }

                if (! $actual) {
                    $resumen['retiradas']++;
                    $cambios->push($this->cambioRetirado($anterior));

                    return;
                }

                $diferencias = $this->diferencias($anterior, $actual);
                if ($diferencias === []) {
                    $resumen['sin_cambios']++;

                    return;
                }

                $resumen['modificadas']++;
                $cambios->push($this->cambioModificado($anterior, $actual, $diferencias));
            });

        $cambios = $cambios
            ->sortBy(fn (array $cambio): string => sprintf(
                '%d-%08d-%s',
                ['modificada' => 0, 'incorporada' => 1, 'retirada' => 2][$cambio['tipo']] ?? 9,
                $cambio['actual']['orden'] ?? $cambio['anterior']['orden'] ?? PHP_INT_MAX,
                $cambio['maniobra']['titulo'],
            ))
            ->values();
        $totalCambios = $cambios->count();

        return [
            'disponible' => true,
            'motivo' => null,
            'detalle' => $totalCambios > 0
                ? 'Se comparó el ciclo vigente con su antecedente confirmado inmediato.'
                : 'Ambos ciclos conservan las mismas decisiones operacionales.',
            'actual' => $this->metadatosCiclo($actual),
            'anterior' => $this->metadatosCiclo($anterior),
            'resumen' => [
                ...$resumen,
                'total_cambios' => $totalCambios,
            ],
            'cambios' => $cambios
                ->take(self::LIMITE_CAMBIOS_PUBLICADOS)
                ->all(),
            'truncada' => $totalCambios > self::LIMITE_CAMBIOS_PUBLICADOS,
            'limite' => self::LIMITE_CAMBIOS_PUBLICADOS,
        ];
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function decisionesPorManiobra(CicloArbitrajeManiobras $ciclo): Collection
    {
        return $ciclo->decisiones
            ->mapWithKeys(fn (DecisionArbitrajeManiobra $decision): array => [
                $decision->maniobra_operacional_id => $this->instantaneaDecision($decision),
            ]);
    }

    /** @return array<string, mixed> */
    private function instantaneaDecision(DecisionArbitrajeManiobra $decision): array
    {
        $explicacion = is_array($decision->explicacion) ? $decision->explicacion : [];
        $componentes = is_array($explicacion['componentes'] ?? null)
            ? $explicacion['componentes']
            : [];
        $snapshot = is_array($explicacion['snapshot'] ?? null)
            ? $explicacion['snapshot']
            : [];
        $maniobra = is_array($snapshot['maniobra'] ?? null) ? $snapshot['maniobra'] : [];
        $objetivo = is_array($snapshot['objetivo'] ?? null) ? $snapshot['objetivo'] : [];
        $pasos = collect(is_array($snapshot['pasos'] ?? null) ? $snapshot['pasos'] : [])
            ->filter(fn ($paso): bool => is_array($paso))
            ->sortBy(fn (array $paso): int => (int) ($paso['secuencia'] ?? PHP_INT_MAX))
            ->values();
        $paso = $pasos->first();
        $factor = is_array($explicacion['factor_decisivo'] ?? null)
            ? $explicacion['factor_decisivo']
            : [];

        return [
            'titulo' => (string) ($maniobra['titulo'] ?? $decision->maniobraOperacional?->titulo ?? 'Maniobra sin título'),
            'objetivo' => (string) ($objetivo['titulo'] ?? $componentes['objetivo']['titulo'] ?? ''),
            'folio' => (string) ($paso['folio']['numero_folio'] ?? ''),
            'ruta' => $this->rutaPaso(is_array($paso) ? $paso : []),
            'orden' => $decision->orden,
            'decision' => $decision->decision->value,
            'prioridad' => (string) ($maniobra['prioridad'] ?? $componentes['prioridad']['valor'] ?? ''),
            'puntaje' => $decision->puntaje,
            'beneficio_neto' => $decision->beneficio_neto,
            'factor_codigo' => (string) ($factor['codigo'] ?? ''),
            'factor_etiqueta' => (string) ($factor['etiqueta'] ?? ''),
            'razon' => (string) ($explicacion['resumen'] ?? $decision->motivo),
        ];
    }

    /** @param array<string, mixed> $paso */
    private function rutaPaso(array $paso): string
    {
        $origen = trim((string) ($paso['origen']['etiqueta'] ?? ''));
        $destino = trim((string) ($paso['destino']['etiqueta'] ?? ''));

        return match (true) {
            $origen !== '' && $destino !== '' => $origen.' → '.$destino,
            $destino !== '' => 'Inicio → '.$destino,
            $origen !== '' => $origen.' → Sin destino',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $anterior
     * @param  array<string, mixed>  $actual
     * @return array<int, array{campo:string,anterior:int|string,actual:int|string}>
     */
    private function diferencias(array $anterior, array $actual): array
    {
        $campos = [
            'decision',
            'orden',
            'prioridad',
            'puntaje',
            'beneficio_neto',
            'factor_etiqueta',
            'objetivo',
            'folio',
            'ruta',
        ];

        return collect($campos)
            ->filter(fn (string $campo): bool => $anterior[$campo] !== $actual[$campo])
            ->map(fn (string $campo): array => [
                'campo' => $campo,
                'anterior' => $anterior[$campo],
                'actual' => $actual[$campo],
            ])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $actual */
    private function cambioIncorporado(array $actual): array
    {
        return [
            'tipo' => 'incorporada',
            'maniobra' => $this->identidadPublica($actual),
            'anterior' => null,
            'actual' => $this->estadoPublico($actual),
            'diferencias' => [],
            'razon_anterior' => null,
            'razon_actual' => $actual['razon'],
        ];
    }

    /** @param array<string, mixed> $anterior */
    private function cambioRetirado(array $anterior): array
    {
        return [
            'tipo' => 'retirada',
            'maniobra' => $this->identidadPublica($anterior),
            'anterior' => $this->estadoPublico($anterior),
            'actual' => null,
            'diferencias' => [],
            'razon_anterior' => $anterior['razon'],
            'razon_actual' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $anterior
     * @param  array<string, mixed>  $actual
     * @param  array<int, array<string, int|string>>  $diferencias
     */
    private function cambioModificado(array $anterior, array $actual, array $diferencias): array
    {
        return [
            'tipo' => 'modificada',
            'maniobra' => $this->identidadPublica($actual),
            'anterior' => $this->estadoPublico($anterior),
            'actual' => $this->estadoPublico($actual),
            'diferencias' => $diferencias,
            'razon_anterior' => $anterior['razon'],
            'razon_actual' => $actual['razon'],
        ];
    }

    /** @param array<string, mixed> $decision */
    private function identidadPublica(array $decision): array
    {
        return [
            'titulo' => $decision['titulo'],
            'objetivo' => $decision['objetivo'],
            'folio' => $decision['folio'],
            'ruta' => $decision['ruta'],
        ];
    }

    /** @param array<string, mixed> $decision */
    private function estadoPublico(array $decision): array
    {
        return [
            'orden' => $decision['orden'],
            'decision' => $decision['decision'],
            'prioridad' => $decision['prioridad'],
            'puntaje' => $decision['puntaje'],
            'beneficio_neto' => $decision['beneficio_neto'],
            'factor_decisivo' => $decision['factor_etiqueta'],
        ];
    }

    /** @return array<string, mixed> */
    private function metadatosCiclo(CicloArbitrajeManiobras $ciclo): array
    {
        return [
            'generado_at' => $ciclo->created_at?->toIso8601String(),
            'capacidad_ejecucion' => $ciclo->capacidad_ejecucion,
            'frontera_max' => $ciclo->frontera_max,
        ];
    }

    /** @return array<string, mixed> */
    private function sinComparacion(
        string $motivo,
        string $detalle,
        ?array $actual = null,
    ): array {
        return [
            'disponible' => false,
            'motivo' => $motivo,
            'detalle' => $detalle,
            'actual' => $actual,
            'anterior' => null,
            'resumen' => [
                'incorporadas' => 0,
                'retiradas' => 0,
                'modificadas' => 0,
                'sin_cambios' => 0,
                'total_cambios' => 0,
            ],
            'cambios' => [],
            'truncada' => false,
            'limite' => self::LIMITE_CAMBIOS_PUBLICADOS,
        ];
    }
}
