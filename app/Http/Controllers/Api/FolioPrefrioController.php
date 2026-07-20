<?php

namespace App\Http\Controllers\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoProcesoPrefrio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\TipoBulto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarFoliosPrefrioRequest;
use App\Http\Resources\FolioPrefrioResource;
use App\Models\Folio;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FolioPrefrioController extends Controller
{
    public function index(ConsultarFoliosPrefrioRequest $request): AnonymousResourceCollection
    {
        $datos = $request->validated();
        $estadosActivos = collect(EstadoProcesoPrefrio::cases())
            ->filter->esActivo()
            ->map->value
            ->all();
        $folio = mb_strtoupper(trim((string) ($datos['folio'] ?? '')));

        $folios = Folio::query()
            ->where('activo', true)
            ->whereIn('tipo_bulto', [TipoBulto::Pallet->value, TipoBulto::Saldo->value])
            ->whereIn('condicion_termica', [
                CondicionTermicaFolio::PendientePrefrio->value,
                CondicionTermicaFolio::RequiereReproceso->value,
                CondicionTermicaFolio::Retenido->value,
            ])
            ->whereIn('habilitacion_almacenamiento', [
                HabilitacionAlmacenamientoFolio::NoHabilitado->value,
                HabilitacionAlmacenamientoFolio::Retenido->value,
            ])
            ->whereDoesntHave('ubicacionActual')
            ->whereDoesntHave('procesosPrefrio.proceso', fn ($consulta) => $consulta
                ->whereIn('estado', $estadosActivos))
            ->when($folio !== '', fn ($consulta) => $consulta
                ->where('numero_folio', 'like', "%{$folio}%"))
            ->orderBy('fecha_ingreso')
            ->orderBy('numero_folio')
            ->limit($datos['limit'] ?? 500)
            ->get();

        return FolioPrefrioResource::collection($folios);
    }
}
