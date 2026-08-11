<?php

namespace App\Services\InspeccionSag;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoFolioInspeccionSag;
use App\Enums\EstadoLoteInspeccionSag;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\ResultadoInspeccionSag;
use App\Enums\TipoAprobacionSag;
use App\Enums\TipoBulto;
use App\Enums\TipoDestinoSag;
use App\Enums\TipoLoteInspeccionSag;
use App\Models\AutorizacionSagFolio;
use App\Models\BloqueMercado;
use App\Models\DestinoLoteInspeccionSag;
use App\Models\Folio;
use App\Models\LoteInspeccionSag;
use App\Models\LoteInspeccionSagFolio;
use App\Models\Pais;
use App\Models\ResultadoDestinoInspeccionSag;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Secuencias\ServicioSecuenciaDocumento;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioInspeccionSag
{
    public function __construct(
        private readonly ServicioEstadoSagFolio $estadoSag,
        private readonly ServicioSecuenciaDocumento $secuencias,
    ) {}

    /** @param array<string, mixed> $datos */
    public function crear(array $datos, User $usuario): LoteInspeccionSag
    {
        return DB::transaction(function () use ($datos, $usuario): LoteInspeccionSag {
            $operacionId = $datos['operacion_id'] ?? (string) Str::uuid();
            $hash = hash('sha256', json_encode(Arr::except($datos, ['operacion_id']), JSON_THROW_ON_ERROR));
            $existente = LoteInspeccionSag::query()->where('operacion_id', $operacionId)->first();

            if ($existente) {
                if (! hash_equals($existente->payload_hash, $hash)) {
                    throw new DomainException('La operación ya fue utilizada con datos diferentes.');
                }

                return $this->cargar($existente);
            }

            $temporada = Temporada::query()->where('activa', true)->lockForUpdate()->first()
                ?? throw new DomainException('No existe una temporada activa.');
            $folioIds = array_values(array_unique($datos['folios']));

            if (($datos['cantidad_solicitada'] ?? count($folioIds)) !== count($folioIds)) {
                throw new DomainException('La cantidad solicitada debe coincidir con los pallets seleccionados.');
            }

            $folios = $this->consultaFoliosElegibles()
                ->whereIn('id', $folioIds)
                ->lockForUpdate()
                ->get();

            if ($folios->count() !== count($folioIds)) {
                throw new DomainException('Uno o más pallets ya no están disponibles para esta inspección.');
            }

            $destinos = $this->resolverDestinos($datos['destinos']);
            $numero = $this->secuencias->reservarSiguiente('lotes_inspeccion_sag');
            $lote = LoteInspeccionSag::query()->create([
                'temporada_id' => $temporada->id,
                'codigo' => 'SAG-'.now()->format('Y').'-'.str_pad((string) $numero, 6, '0', STR_PAD_LEFT),
                'operacion_id' => $operacionId,
                'payload_hash' => $hash,
                'tipo' => TipoLoteInspeccionSag::from($datos['tipo']),
                'estado' => EstadoLoteInspeccionSag::Preparacion,
                'cantidad_solicitada' => count($folioIds),
                'referencia_correo' => $datos['referencia_correo'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'creado_por_user_id' => $usuario->id,
            ]);

            $destinosCreados = collect($destinos)->map(fn (array $destino) => $lote->destinos()->create($destino));

            foreach ($folios as $folio) {
                $asignacion = $lote->folios()->create([
                    'folio_id' => $folio->id,
                    'estado' => EstadoFolioInspeccionSag::Pendiente,
                    'estado_sag_anterior' => $this->estadoSag->resumir($folio),
                ]);

                foreach ($destinosCreados as $destino) {
                    $asignacion->resultados()->create([
                        'destino_lote_inspeccion_sag_id' => $destino->id,
                        'resultado' => ResultadoInspeccionSag::Pendiente,
                    ]);
                }
            }

            return $this->cargar($lote);
        });
    }

    public function iniciar(LoteInspeccionSag $lote, User $usuario): LoteInspeccionSag
    {
        return DB::transaction(function () use ($lote, $usuario): LoteInspeccionSag {
            $lote = LoteInspeccionSag::query()->lockForUpdate()->findOrFail($lote->id);
            $this->exigirEstado($lote, [EstadoLoteInspeccionSag::Preparacion]);
            $lote->update([
                'estado' => EstadoLoteInspeccionSag::EnInspeccion,
                'iniciado_por_user_id' => $usuario->id,
                'iniciado_at' => now(),
            ]);

            return $this->cargar($lote);
        });
    }

    /** @param array<string, mixed> $datos */
    public function resolver(
        LoteInspeccionSag $lote,
        ResultadoDestinoInspeccionSag $resultado,
        array $datos,
        User $usuario,
    ): LoteInspeccionSag {
        return DB::transaction(function () use ($lote, $resultado, $datos, $usuario): LoteInspeccionSag {
            $lote = LoteInspeccionSag::query()->lockForUpdate()->findOrFail($lote->id);
            $this->exigirEstado($lote, [
                EstadoLoteInspeccionSag::EnInspeccion,
                EstadoLoteInspeccionSag::ResultadoParcial,
            ]);
            $resultado = ResultadoDestinoInspeccionSag::query()
                ->with(['asignacion', 'destino'])
                ->lockForUpdate()
                ->findOrFail($resultado->id);

            if ($resultado->asignacion->lote_inspeccion_sag_id !== $lote->id) {
                throw new DomainException('El resultado no pertenece al lote indicado.');
            }

            $decision = ResultadoInspeccionSag::from($datos['resultado']);
            $tipoAprobacion = isset($datos['tipo_aprobacion'])
                ? TipoAprobacionSag::from($datos['tipo_aprobacion'])
                : null;

            if ($decision === ResultadoInspeccionSag::Aprobado && $tipoAprobacion === null) {
                throw new DomainException('Una aprobación SAG debe indicar AO, AU o AF.');
            }

            $resultado->update([
                'resultado' => $decision,
                'tipo_aprobacion' => $tipoAprobacion,
                'observacion' => $datos['observacion'] ?? null,
                'resuelto_por_user_id' => $usuario->id,
                'resuelto_at' => now(),
            ]);

            if ($decision === ResultadoInspeccionSag::Aprobado) {
                $destino = $resultado->destino;
                AutorizacionSagFolio::query()->firstOrCreate([
                    'folio_id' => $resultado->asignacion->folio_id,
                    'tipo_aprobacion' => $tipoAprobacion,
                    'tipo_destino' => $destino->tipo_destino,
                    'pais_id' => $destino->pais_id,
                    'bloque_mercado_id' => $destino->bloque_mercado_id,
                    'activa' => true,
                ], [
                    'resultado_origen_id' => $resultado->id,
                    'destino_snapshot' => $destino->destino_snapshot,
                    'miembros_snapshot' => $destino->miembros_snapshot,
                    'aprobado_por_user_id' => $usuario->id,
                    'aprobado_at' => now(),
                ]);
            }

            $asignacion = $resultado->asignacion;
            if (! $asignacion->resultados()->where('resultado', ResultadoInspeccionSag::Pendiente)->exists()) {
                $asignacion->update([
                    'estado' => EstadoFolioInspeccionSag::Resuelto,
                    'resuelto_por_user_id' => $usuario->id,
                    'resuelto_at' => now(),
                ]);
            }

            $lote->update(['estado' => EstadoLoteInspeccionSag::ResultadoParcial]);

            return $this->cargar($lote);
        });
    }

    public function finalizar(LoteInspeccionSag $lote, User $usuario): LoteInspeccionSag
    {
        return DB::transaction(function () use ($lote, $usuario): LoteInspeccionSag {
            $lote = LoteInspeccionSag::query()->lockForUpdate()->findOrFail($lote->id);
            $this->exigirEstado($lote, [
                EstadoLoteInspeccionSag::EnInspeccion,
                EstadoLoteInspeccionSag::ResultadoParcial,
            ]);

            if ($lote->folios()->whereHas('resultados', fn ($consulta) => $consulta
                ->where('resultado', ResultadoInspeccionSag::Pendiente))->exists()) {
                throw new DomainException('Aún existen destinos pendientes de resolución.');
            }

            $lote->update([
                'estado' => EstadoLoteInspeccionSag::Finalizado,
                'finalizado_por_user_id' => $usuario->id,
                'finalizado_at' => now(),
            ]);

            return $this->cargar($lote);
        });
    }

    public function cancelar(LoteInspeccionSag $lote, User $usuario): LoteInspeccionSag
    {
        return DB::transaction(function () use ($lote, $usuario): LoteInspeccionSag {
            $lote = LoteInspeccionSag::query()->lockForUpdate()->findOrFail($lote->id);

            if (! $lote->estado->esActivo()) {
                throw new DomainException('El lote SAG ya está cerrado.');
            }

            $lote->update([
                'estado' => EstadoLoteInspeccionSag::Cancelado,
                'cancelado_por_user_id' => $usuario->id,
                'cancelado_at' => now(),
            ]);

            return $this->cargar($lote);
        });
    }

    public function cargar(LoteInspeccionSag $lote): LoteInspeccionSag
    {
        return $lote->load([
            'temporada:id,codigo,nombre',
            'creadoPor:id,name',
            'destinos',
            'folios.folio.ubicacionActual.camara',
            'folios.folio.ubicacionActual.posicion',
            'folios.folio.autorizacionesSagActivas',
            'folios.folio.inspeccionesSag.lote',
            'folios.resultados.destino',
        ]);
    }

    private function consultaFoliosElegibles()
    {
        $estadosTerminales = [
            EstadoOperacionalFolio::Anulado,
            EstadoOperacionalFolio::RetiradoDefinitivo,
            EstadoOperacionalFolio::Despachado,
            EstadoOperacionalFolio::Agotado,
        ];
        $estadosLoteActivos = collect(EstadoLoteInspeccionSag::cases())
            ->filter->esActivo()
            ->map->value
            ->all();

        return Folio::query()
            ->where('activo', true)
            ->where('tipo_bulto', TipoBulto::Pallet)
            ->whereNotIn('estado_operacional', $estadosTerminales)
            ->whereDoesntHave('material')
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->whereHas('ubicacionActual.camara', fn ($consulta) => $consulta
                ->where('contenido', ContenidoCamara::Productos)
                ->where('estado', EstadoCamara::Activa))
            ->whereDoesntHave('inspeccionesSag.lote', fn ($consulta) => $consulta
                ->whereIn('estado', $estadosLoteActivos));
    }

    /**
     * @param array<int, array{tipo: string, id: string}> $seleccionados
     * @return array<int, array<string, mixed>>
     */
    private function resolverDestinos(array $seleccionados): array
    {
        $seleccionados = collect($seleccionados)
            ->unique(fn (array $destino): string => $destino['tipo'].'|'.$destino['id'])
            ->values();

        if ($seleccionados->isEmpty()) {
            throw new DomainException('Selecciona al menos un destino para la inspección.');
        }

        $bloques = BloqueMercado::query()
            ->with(['paises' => fn ($consulta) => $consulta->where('paises.activo', true)])
            ->whereIn('id', $seleccionados->where('tipo', TipoDestinoSag::Bloque->value)->pluck('id'))
            ->where('activo', true)
            ->get()
            ->keyBy('id');
        $idsPaisesCubiertos = $bloques->flatMap->paises->pluck('id')->unique();
        $paises = Pais::query()
            ->whereIn('id', $seleccionados->where('tipo', TipoDestinoSag::Pais->value)->pluck('id'))
            ->whereNotIn('id', $idsPaisesCubiertos)
            ->where('activo', true)
            ->get()
            ->keyBy('id');

        $resultado = [];
        foreach ($seleccionados as $seleccionado) {
            if ($seleccionado['tipo'] === TipoDestinoSag::Bloque->value) {
                $bloque = $bloques->get($seleccionado['id'])
                    ?? throw new DomainException('Uno de los bloques de mercado no está disponible.');
                $resultado[] = [
                    'tipo_destino' => TipoDestinoSag::Bloque,
                    'bloque_mercado_id' => $bloque->id,
                    'pais_id' => null,
                    'destino_snapshot' => ['codigo' => $bloque->codigo, 'nombre' => $bloque->nombre],
                    'miembros_snapshot' => $bloque->paises->map(fn (Pais $pais): array => [
                        'iso_alpha2' => $pais->iso_alpha2,
                        'nombre' => $pais->nombre_es,
                    ])->values()->all(),
                ];
            } elseif (! $idsPaisesCubiertos->contains($seleccionado['id'])) {
                $pais = $paises->get($seleccionado['id'])
                    ?? throw new DomainException('Uno de los países no está disponible.');
                $resultado[] = [
                    'tipo_destino' => TipoDestinoSag::Pais,
                    'pais_id' => $pais->id,
                    'bloque_mercado_id' => null,
                    'destino_snapshot' => ['codigo' => $pais->iso_alpha2, 'nombre' => $pais->nombre_es],
                    'miembros_snapshot' => null,
                ];
            }
        }

        return $resultado;
    }

    /** @param array<int, EstadoLoteInspeccionSag> $estados */
    private function exigirEstado(LoteInspeccionSag $lote, array $estados): void
    {
        if (! in_array($lote->estado, $estados, true)) {
            throw new DomainException('El estado actual del lote no permite esta acción.');
        }
    }
}
