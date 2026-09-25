<?php

namespace App\Services\Temporadas;

use App\Enums\TipoTemporada;
use App\Models\ClasificacionTemporada;
use App\Models\Temporada;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Declara una temporada como productiva o de prueba.
 *
 * Solo cambia el tipo de esa temporada y deja un registro inmutable con el
 * motivo. No mueve, copia ni borra datos: ni de la temporada activa ni de
 * Materiales, cuyo historial en una temporada de prueba sigue consultable.
 */
class ServicioClasificacionTemporada
{
    public function __construct(private readonly ServicioTemporadaGlobal $temporadas) {}

    public function clasificar(
        Temporada $temporada,
        TipoTemporada $tipo,
        string $motivo,
        User $usuario,
    ): Temporada {
        return DB::transaction(function () use ($temporada, $tipo, $motivo, $usuario): Temporada {
            $temporada = Temporada::query()->lockForUpdate()->findOrFail($temporada->id);

            if ($temporada->tipo === $tipo) {
                throw new DomainException(
                    "La temporada {$temporada->codigo} ya es {$this->nombre($tipo)}.",
                );
            }

            if ($temporada->activa) {
                throw new DomainException(
                    'La temporada activa no puede declararse de prueba. Activa primero la temporada que la reemplaza.',
                );
            }

            if ($tipo === TipoTemporada::Productiva) {
                // Volver a productiva exige las mismas condiciones que cualquier productiva.
                $temporada->tipo = TipoTemporada::Productiva;
                $this->temporadas->asegurarVigenciaProductiva($temporada);
            }

            $anterior = $temporada->getOriginal('tipo');
            $temporada->tipo = $tipo;
            $temporada->save();

            ClasificacionTemporada::create([
                'temporada_id' => $temporada->id,
                'tipo_anterior' => $anterior instanceof TipoTemporada ? $anterior->value : (string) $anterior,
                'tipo_nuevo' => $tipo->value,
                'motivo' => trim($motivo),
                'clasificado_por_user_id' => $usuario->id,
                'clasificado_at' => now(),
            ]);

            return $temporada->refresh();
        });
    }

    private function nombre(TipoTemporada $tipo): string
    {
        return $tipo === TipoTemporada::Prueba ? 'de prueba' : 'productiva';
    }
}
