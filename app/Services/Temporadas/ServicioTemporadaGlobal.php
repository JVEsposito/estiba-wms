<?php

namespace App\Services\Temporadas;

use App\Models\Temporada;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioTemporadaGlobal
{
    /** @param array<string, mixed> $datos */
    public function guardar(array $datos, ?Temporada $temporada = null): Temporada
    {
        $codigo = mb_strtoupper(Str::of($datos['codigo'])->squish()->toString());
        $duplicada = Temporada::query()
            ->where('codigo', $codigo)
            ->when($temporada, fn ($consulta) => $consulta->whereKeyNot($temporada->id))
            ->exists();

        if ($duplicada) {
            throw new DomainException('Ya existe una temporada con ese código.');
        }

        return DB::transaction(function () use ($datos, $codigo, $temporada): Temporada {
            $temporada ??= new Temporada;
            $temporada->fill([
                'codigo' => $codigo,
                'nombre' => Str::of($datos['nombre'])->squish()->toString(),
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_fin' => $datos['fecha_fin'] ?? null,
                'activa' => (bool) ($datos['activa'] ?? false),
            ]);
            $temporada->save();

            if ($temporada->activa) {
                $this->activarDentroDeTransaccion($temporada);
            } else {
                $this->reflejarEnMateriales($temporada);
            }

            return $temporada->refresh();
        });
    }

    public function activar(Temporada $temporada): Temporada
    {
        return DB::transaction(function () use ($temporada): Temporada {
            $this->activarDentroDeTransaccion($temporada);

            return $temporada->refresh();
        });
    }

    private function activarDentroDeTransaccion(Temporada $temporada): void
    {
        Temporada::query()->whereKeyNot($temporada->id)->update(['activa' => false]);
        $temporada->update(['activa' => true]);
        DB::table('temporadas_materiales')->update(['activa' => false]);
        $this->reflejarEnMateriales($temporada);
    }

    private function reflejarEnMateriales(Temporada $temporada): void
    {
        DB::table('temporadas_materiales')
            ->where('temporada_id', $temporada->id)
            ->update([
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
                'fecha_inicio' => $temporada->fecha_inicio,
                'fecha_fin' => $temporada->fecha_fin,
                'activa' => $temporada->activa,
                'updated_at' => now(),
            ]);
    }
}
