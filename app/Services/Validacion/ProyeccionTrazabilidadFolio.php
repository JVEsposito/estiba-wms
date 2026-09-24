<?php

namespace App\Services\Validacion;

use App\Enums\EstadoLoteMateriaPrima;
use App\Models\Folio;
use App\Models\LoteMateriaPrima;
use App\Models\TrazabilidadFolioOrigen;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene trazabilidad_folio_origenes a partir de la composición guardada en el folio.
 * Validación, correcciones y repaletizajes solo modifican datos_externos; esta proyección
 * los vuelve consultables por lote de materia prima o proceso de packing.
 */
class ProyeccionTrazabilidadFolio
{
    /** Normaliza el número impreso en la etiqueta del pallet, o null si viene vacío. */
    public static function normalizarCodigo(mixed $valor): ?string
    {
        $texto = mb_strtoupper(trim((string) $valor));

        return $texto === '' ? null : $texto;
    }

    public function sincronizar(Folio $folio): void
    {
        $lineas = collect($folio->datos_externos['composicion'] ?? [])
            ->filter(fn (mixed $linea): bool => is_array($linea) && (int) ($linea['cantidad_cajas'] ?? 0) > 0)
            ->values();

        DB::transaction(function () use ($folio, $lineas): void {
            TrazabilidadFolioOrigen::query()->where('folio_id', $folio->id)->delete();

            foreach ($lineas as $linea) {
                $numeroLote = self::normalizarCodigo($linea['lote_materia_prima'] ?? null);

                TrazabilidadFolioOrigen::create([
                    'folio_id' => $folio->id,
                    'temporada_id' => $folio->temporada_id,
                    'csg' => self::normalizarCodigo($linea['csg'] ?? null),
                    'predio' => filled($linea['predio'] ?? null) ? trim((string) $linea['predio']) : null,
                    'fecha_embalaje' => filled($linea['fecha_embalaje'] ?? null) ? $linea['fecha_embalaje'] : null,
                    'numero_lote_materia_prima' => $numeroLote,
                    'lote_materia_prima_id' => $linea['lote_materia_prima_id']
                        ?? $this->loteRegistrado($folio->temporada_id, $numeroLote),
                    'numero_proceso_packing' => self::normalizarCodigo($linea['proceso_packing'] ?? null),
                    'cantidad_cajas' => (int) $linea['cantidad_cajas'],
                ]);
            }
        });
    }

    /**
     * Busca el lote digitado en Materia Prima con el mismo número y temporada. Si existe
     * más de uno vigente (clientes distintos) no se adivina: queda solo el número impreso.
     */
    public function loteRegistrado(?string $temporadaId, ?string $numeroLote): ?string
    {
        if ($temporadaId === null || $numeroLote === null) {
            return null;
        }

        $lotes = LoteMateriaPrima::query()
            ->where('temporada_id', $temporadaId)
            ->whereRaw('UPPER(TRIM(numero_lote)) = ?', [$numeroLote])
            ->where('estado', '!=', EstadoLoteMateriaPrima::Anulado->value)
            ->limit(2)
            ->pluck('id');

        return $lotes->count() === 1 ? $lotes->first() : null;
    }
}
