<?php

namespace App\Services\Consultas;

use App\Models\Folio;
use App\Models\LoteMateriaPrima;
use App\Models\TrazabilidadFolioOrigen;
use App\Services\Validacion\ProyeccionTrazabilidadFolio;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Responde en ambos sentidos: qué folios contienen un lote o proceso de packing
 * (retiro de mercado) y de qué lotes y recepciones proviene un folio (auditoría).
 */
class ServicioTrazabilidadLotes
{
    private const LIMITE_FOLIOS = 200;

    /** @return array<string, mixed> */
    public function consultar(string $termino): array
    {
        $codigo = ProyeccionTrazabilidadFolio::normalizarCodigo($termino) ?? '';

        $folioIds = TrazabilidadFolioOrigen::query()
            ->where(fn (Builder $consulta) => $consulta
                ->where('numero_lote_materia_prima', $codigo)
                ->orWhere('numero_proceso_packing', $codigo))
            ->pluck('folio_id')
            ->merge(Folio::query()->where('numero_folio', $codigo)->pluck('id'))
            ->unique()
            ->take(self::LIMITE_FOLIOS)
            ->values();

        $folios = Folio::query()
            ->whereIn('id', $folioIds)
            ->with([
                'temporada',
                'ubicacionActual.posicion.camara',
                'asignacionesCarga' => fn ($consulta) => $consulta->latest()->with('carga'),
            ])
            ->orderBy('numero_folio')
            ->get();
        $lineas = TrazabilidadFolioOrigen::query()
            ->whereIn('folio_id', $folioIds)
            ->with('loteMateriaPrima.recepcion')
            ->get()
            ->groupBy('folio_id');

        $lotesConsultados = LoteMateriaPrima::query()
            ->whereRaw('UPPER(TRIM(numero_lote)) = ?', [$codigo])
            ->with(['recepcion', 'cliente', 'temporada'])
            ->get();

        return [
            'termino' => $codigo,
            'lotes' => $lotesConsultados->map(fn (LoteMateriaPrima $lote): array => $this->lote($lote))->all(),
            'folios' => $folios->map(fn (Folio $folio): array => $this->folio(
                $folio,
                $lineas->get($folio->id, collect()),
                $codigo,
            ))->all(),
            'resumen' => [
                'folios' => $folios->count(),
                'folios_activos' => $folios->where('activo', true)->count(),
                'cajas_coincidentes' => $lineas->flatten()
                    ->filter(fn (TrazabilidadFolioOrigen $linea): bool => $this->coincide($linea, $codigo))
                    ->sum('cantidad_cajas'),
                'limite_alcanzado' => $folioIds->count() >= self::LIMITE_FOLIOS,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function folio(Folio $folio, Collection $lineas, string $codigo): array
    {
        $posicion = $folio->ubicacionActual?->posicion;
        $carga = $folio->asignacionesCarga->first()?->carga;

        return [
            'id' => $folio->id,
            'numero' => $folio->numero_folio,
            'activo' => (bool) $folio->activo,
            'estado' => $this->valor($folio->estado_operacional),
            'temporada' => $folio->temporada?->codigo,
            'variedad' => $folio->variedad,
            'calibre' => $folio->calibre,
            'exportadora' => $folio->exportadora,
            'ubicacion' => $posicion ? [
                'camara' => $posicion->camara?->codigo,
                'posicion' => $posicion->etiqueta,
            ] : null,
            'carga' => $carga ? [
                'codigo' => $carga->codigo,
                'estado' => $this->valor($carga->estado),
            ] : null,
            'lineas' => $lineas->map(fn (TrazabilidadFolioOrigen $linea): array => [
                'csg' => $linea->csg,
                'predio' => $linea->predio,
                'fecha_embalaje' => $linea->fecha_embalaje?->toDateString(),
                'lote_materia_prima' => $linea->numero_lote_materia_prima,
                'lote_registrado' => $linea->lote_materia_prima_id !== null,
                'recepcion' => $linea->loteMateriaPrima?->recepcion?->numero_recepcion,
                'proceso_packing' => $linea->numero_proceso_packing,
                'cantidad_cajas' => $linea->cantidad_cajas,
                'coincide' => $this->coincide($linea, $codigo),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function lote(LoteMateriaPrima $lote): array
    {
        return [
            'id' => $lote->id,
            'numero' => $lote->numero_lote,
            'estado' => $this->valor($lote->estado),
            'cliente' => $lote->cliente?->nombre,
            'temporada' => $lote->temporada?->codigo,
            'recepcion' => $lote->recepcion?->numero_recepcion,
            'guia' => $lote->recepcion?->numero_guia_despacho,
            'csg' => $lote->csg_snapshot,
            'predio' => $lote->predio,
            'variedad' => $lote->variedad_snapshot,
            'fecha_cosecha' => $lote->fecha_cosecha?->toDateString(),
            'kilos_netos' => (float) $lote->kilos_netos_confirmados,
        ];
    }

    private function coincide(TrazabilidadFolioOrigen $linea, string $codigo): bool
    {
        return $linea->numero_lote_materia_prima === $codigo || $linea->numero_proceso_packing === $codigo;
    }

    private function valor(mixed $valor): mixed
    {
        return $valor instanceof BackedEnum ? $valor->value : $valor;
    }
}
