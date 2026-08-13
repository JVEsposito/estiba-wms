<?php

namespace App\Observers;

use App\Enums\TipoCambioTransicionOperacional;
use App\Models\CambioTransicionOperacional;
use App\Models\TransicionOperacional;
use App\Services\Transiciones\ContextoEjecucionTransicionOperacional;
use App\Services\Transiciones\NormalizadorTransicionOperacional;
use Illuminate\Database\Eloquent\Model;

class AuditarCambioTransicionOperacionalObserver
{
    private const CAMPOS_TECNICOS = ['created_at', 'updated_at'];

    public function __construct(
        private readonly ContextoEjecucionTransicionOperacional $contexto,
        private readonly NormalizadorTransicionOperacional $normalizador,
    ) {}

    public function created(Model $modelo): void
    {
        $nuevos = $this->sinCamposTecnicos($modelo->getAttributes());

        $this->registrar(
            $modelo,
            TipoCambioTransicionOperacional::Creacion,
            array_keys($nuevos),
            null,
            $nuevos,
        );
    }

    public function updated(Model $modelo): void
    {
        $cambios = $this->sinCamposTecnicos($modelo->getChanges());
        if ($cambios === []) {
            return;
        }

        $campos = array_keys($cambios);
        $anteriores = [];
        $nuevos = [];

        foreach ($campos as $campo) {
            $anteriores[$campo] = $modelo->getRawOriginal($campo);
            $nuevos[$campo] = $modelo->getAttribute($campo);
        }

        $this->registrar(
            $modelo,
            TipoCambioTransicionOperacional::Actualizacion,
            $campos,
            $anteriores,
            $nuevos,
        );
    }

    public function deleted(Model $modelo): void
    {
        $anteriores = $this->sinCamposTecnicos($modelo->getRawOriginal());

        $this->registrar(
            $modelo,
            TipoCambioTransicionOperacional::Eliminacion,
            array_keys($anteriores),
            $anteriores,
            null,
        );
    }

    /**
     * @param  array<int, string>  $campos
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    private function registrar(
        Model $modelo,
        TipoCambioTransicionOperacional $tipo,
        array $campos,
        ?array $anteriores,
        ?array $nuevos,
    ): void {
        $transicion = $this->contexto->actual();
        $secuencia = $this->contexto->siguienteSecuencia();

        if (! $transicion || $secuencia === null) {
            return;
        }

        CambioTransicionOperacional::create([
            'transicion_operacional_id' => $transicion->id,
            'secuencia' => $secuencia,
            'modelo_tipo' => $modelo::class,
            'modelo_id' => (string) $modelo->getKey(),
            'tipo' => $tipo,
            'campos' => array_values($campos),
            'datos_anteriores' => $anteriores === null
                ? null
                : $this->normalizador->normalizar($anteriores),
            'datos_nuevos' => $nuevos === null
                ? null
                : $this->normalizador->normalizar($nuevos),
        ]);

        TransicionOperacional::query()
            ->whereKey($transicion->id)
            ->increment('cantidad_cambios');
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function sinCamposTecnicos(array $atributos): array
    {
        return array_diff_key($atributos, array_flip(self::CAMPOS_TECNICOS));
    }
}
