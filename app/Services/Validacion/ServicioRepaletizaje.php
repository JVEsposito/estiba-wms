<?php

namespace App\Services\Validacion;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\TipoBulto;
use App\Exceptions\ConflictoOperacion;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Repaletizaje;
use App\Models\RepaletizajeDetalle;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;

class ServicioRepaletizaje
{
    /** @param array<string, mixed> $datos */
    public function registrar(
        array $datos,
        User $usuario,
        ?Dispositivo $dispositivo = null,
    ): Repaletizaje {
        $payload = $this->normalizar($datos);
        $hash = $this->hash($payload);

        return DB::transaction(function () use (
            $datos,
            $usuario,
            $dispositivo,
            $payload,
            $hash,
        ): Repaletizaje {
            $existente = Repaletizaje::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if (! hash_equals($existente->payload_hash, $hash)) {
                    throw new ConflictoOperacion(
                        'El UUID del repaletizaje ya fue utilizado con datos diferentes.',
                    );
                }

                return $this->cargar($existente);
            }

            $ids = collect($payload['origenes'])
                ->pluck('folio_id')
                ->sort()
                ->values();
            $folios = Folio::query()
                ->whereIn('id', $ids)
                ->with('ubicacionActual')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($folios->count() !== $ids->count()) {
                throw new DomainException('Uno de los folios ya no existe.');
            }

            $origenes = collect($payload['origenes'])->map(function (array $origen) use ($folios): array {
                /** @var Folio $folio */
                $folio = $folios->get($origen['folio_id']);
                $this->validarFolioOperable($folio);
                $cantidadAntes = $this->cantidad($folio);
                $aporte = (int) $origen['cantidad_aportada'];

                if ($aporte > $cantidadAntes) {
                    throw new DomainException(sprintf(
                        'El folio %s solo dispone de %d cajas.',
                        $folio->numero_folio,
                        $cantidadAntes,
                    ));
                }

                return [
                    'folio' => $folio,
                    'cantidad_antes' => $cantidadAntes,
                    'cantidad_aportada' => $aporte,
                    'cantidad_despues' => $cantidadAntes - $aporte,
                ];
            });

            $this->validarCompatibilidadDura($origenes->pluck('folio'));
            $cantidadResultado = (int) $origenes->sum('cantidad_aportada');
            $this->validarCantidadResultado($payload, $cantidadResultado);

            $folioConservado = null;
            if ($payload['estrategia_folio'] === 'conservar') {
                /** @var Folio|null $folioConservado */
                $folioConservado = $folios->get($payload['folio_conservado_id']);
                if (! $folioConservado) {
                    throw new DomainException(
                        'El folio que se desea conservar no participa en el repaletizaje.',
                    );
                }

                $detalleConservado = $origenes->first(
                    fn (array $origen): bool => $origen['folio']->id === $folioConservado->id,
                );
                if ($detalleConservado['cantidad_aportada'] !== $detalleConservado['cantidad_antes']) {
                    throw new DomainException(
                        'El folio conservado debe aportar la totalidad de sus cajas al resultado.',
                    );
                }
                if ($folioConservado->numero_folio !== $payload['numero_folio_resultante']) {
                    throw new DomainException(
                        'El número resultante debe coincidir con el folio que se conserva.',
                    );
                }
            } else {
                $numeroOcupado = Folio::query()
                    ->where('numero_folio', $payload['numero_folio_resultante'])
                    ->lockForUpdate()
                    ->exists();
                if ($numeroOcupado) {
                    throw new ConflictoOperacion(
                        'El número de folio resultante ya existe en el sistema.',
                    );
                }
            }

            $especificaciones = $this->especificacionesResultado($origenes->pluck('folio'));
            $camposMix = collect($especificaciones)
                ->filter(fn (mixed $valor): bool => $valor === 'MIX')
                ->keys()
                ->values()
                ->all();
            /** @var Folio $primero */
            $primero = $origenes->first()['folio'];
            $condicion = $primero->condicion_termica;
            $estadoResultado = $condicion === CondicionTermicaFolio::PendientePrefrio
                ? EstadoOperacionalFolio::PendientePrefrio
                : EstadoOperacionalFolio::Disponible;
            $habilitacionResultado = $condicion === CondicionTermicaFolio::PendientePrefrio
                ? HabilitacionAlmacenamientoFolio::NoHabilitado
                : HabilitacionAlmacenamientoFolio::Habilitado;
            $codigo = $this->siguienteCodigo();
            $composicion = $origenes->map(fn (array $origen): array => [
                'folio_id' => $origen['folio']->id,
                'numero_folio' => $origen['folio']->numero_folio,
                'cajas_aportadas' => $origen['cantidad_aportada'],
                'especificaciones' => $this->especificaciones($origen['folio']),
            ])->values()->all();
            $snapshotResultado = [
                'especificaciones' => $especificaciones,
                'campos_mix' => $camposMix,
                'advertencias' => collect($camposMix)->map(fn (string $campo): array => [
                    'campo' => $campo,
                    'mensaje' => 'Se está generando un MIX de '.mb_strtoupper($campo).'.',
                ])->values()->all(),
                'composicion' => $composicion,
            ];

            $folioResultado = $folioConservado ?? Folio::create([
                'temporada_id' => $primero->temporada_id,
                'numero_folio' => $payload['numero_folio_resultante'],
                'tipo_bulto' => TipoBulto::from($payload['tipo_resultado']),
                'estado_operacional' => $estadoResultado,
                'condicion_termica' => $condicion,
                'habilitacion_almacenamiento' => $habilitacionResultado,
                'fecha_ingreso' => now(),
                'activo' => true,
                'variedad' => $especificaciones['variedad'],
                'calibre' => $especificaciones['calibre'],
                'marca' => $especificaciones['marca'],
                'exportadora' => $especificaciones['cliente'],
                'origen_sistema' => 'repaletizaje',
                'identificador_externo' => $datos['operacion_id'],
                'estado_integracion' => EstadoIntegracionFolio::NoVinculado,
                'datos_externos' => [
                    'especie' => $especificaciones['especie'],
                    'categoria' => $especificaciones['categoria'],
                    'envase' => $especificaciones['envase'],
                    'csg' => $especificaciones['csg'],
                    'predio' => $especificaciones['predio'],
                    'cuartel' => $especificaciones['cuartel'],
                    'cantidad_cajas' => $cantidadResultado,
                    'repaletizaje_codigo' => $codigo,
                    'campos_mix' => $camposMix,
                    'composicion' => $composicion,
                ],
            ]);

            $repa = Repaletizaje::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $hash,
                'codigo' => $codigo,
                'tipo_resultado' => $payload['tipo_resultado'],
                'estrategia_folio' => $payload['estrategia_folio'],
                'folio_resultante_id' => $folioResultado->id,
                'folio_conservado_id' => $folioConservado?->id,
                'cantidad_objetivo' => $payload['cantidad_objetivo'],
                'cantidad_resultante' => $cantidadResultado,
                'condicion_termica' => $condicion->value,
                'campos_mix' => $camposMix,
                'snapshot' => $snapshotResultado,
                'estado' => 'confirmado',
                'observacion' => $payload['observacion'],
                'user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo?->id,
                'confirmado_at' => now(),
            ]);

            $ubicacionTransferida = false;
            foreach ($origenes as $indice => $origen) {
                /** @var Folio $folio */
                $folio = $origen['folio'];
                $snapshotAntes = $this->snapshotFolio($folio);
                $esConservado = $folioConservado?->id === $folio->id;

                if ($esConservado) {
                    $this->actualizarFolioResultado(
                        $folio,
                        $payload['tipo_resultado'],
                        $cantidadResultado,
                        $estadoResultado,
                        $habilitacionResultado,
                        $especificaciones,
                        $codigo,
                        $camposMix,
                        $composicion,
                    );
                } elseif ($origen['cantidad_despues'] === 0) {
                    if (! $folioConservado && ! $ubicacionTransferida && $folio->ubicacionActual) {
                        $folio->ubicacionActual->update(['folio_id' => $folioResultado->id]);
                        $ubicacionTransferida = true;
                    } else {
                        $folio->ubicacionActual?->delete();
                    }
                    $datosExternos = $folio->datos_externos ?? [];
                    $datosExternos['cantidad_cajas'] = 0;
                    $datosExternos['consumido_en_repaletizaje'] = $codigo;
                    $folio->update([
                        'tipo_bulto' => TipoBulto::Saldo,
                        'estado_operacional' => EstadoOperacionalFolio::Agotado,
                        'activo' => false,
                        'datos_externos' => $datosExternos,
                    ]);
                } else {
                    $datosExternos = $folio->datos_externos ?? [];
                    $datosExternos['cantidad_cajas'] = $origen['cantidad_despues'];
                    $datosExternos['ultimo_repaletizaje'] = $codigo;
                    $folio->update([
                        'tipo_bulto' => TipoBulto::Saldo,
                        'estado_operacional' => $estadoResultado,
                        'datos_externos' => $datosExternos,
                    ]);
                }

                $folio->refresh();
                RepaletizajeDetalle::create([
                    'repaletizaje_id' => $repa->id,
                    'folio_origen_id' => $folio->id,
                    'orden' => $indice + 1,
                    'es_folio_conservado' => $esConservado,
                    'cajas_antes' => $origen['cantidad_antes'],
                    'cajas_aportadas' => $origen['cantidad_aportada'],
                    'cajas_despues' => $esConservado
                        ? $cantidadResultado
                        : $origen['cantidad_despues'],
                    'tipo_bulto_antes' => $snapshotAntes['atributos']['tipo_bulto'],
                    'tipo_bulto_despues' => $folio->tipo_bulto?->value,
                    'estado_antes' => $snapshotAntes['atributos']['estado_operacional'],
                    'estado_despues' => $folio->estado_operacional?->value,
                    'snapshot_antes' => $snapshotAntes,
                    'snapshot_despues' => $this->snapshotFolio($folio),
                ]);
            }

            if (! $folioConservado) {
                $datosExternos = $folioResultado->datos_externos ?? [];
                $datosExternos['repaletizaje_id'] = $repa->id;
                $folioResultado->update(['datos_externos' => $datosExternos]);
            }

            return $this->cargar($repa->refresh());
        }, attempts: 3);
    }

    public function anular(
        Repaletizaje $repaletizaje,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): Repaletizaje {
        return DB::transaction(function () use (
            $repaletizaje,
            $operacionId,
            $motivo,
            $usuario,
        ): Repaletizaje {
            $repa = Repaletizaje::query()
                ->with(['detalles.folioOrigen', 'folioResultante'])
                ->lockForUpdate()
                ->findOrFail($repaletizaje->id);

            if ($repa->estado === 'anulado') {
                if ($repa->operacion_anulacion_id !== $operacionId) {
                    throw new ConflictoOperacion(
                        'El repaletizaje ya fue anulado con otra operación.',
                    );
                }

                return $this->cargar($repa);
            }

            if (Repaletizaje::query()
                ->where('operacion_anulacion_id', $operacionId)
                ->where('id', '!=', $repa->id)
                ->exists()) {
                throw new ConflictoOperacion('El UUID de anulación ya fue utilizado.');
            }

            $folioIds = $repa->detalles
                ->pluck('folio_origen_id')
                ->push($repa->folio_resultante_id)
                ->unique();
            $folios = Folio::query()
                ->whereIn('id', $folioIds)
                ->with('ubicacionActual')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($folios as $folio) {
                if ($folio->asignacionCargaActual()->exists()
                    || $folio->reservaCargaActual()->exists()
                    || $folio->movimientos()->where('created_at', '>', $repa->confirmado_at)->exists()
                    || $folio->procesosPrefrio()->where('created_at', '>', $repa->confirmado_at)->exists()) {
                    throw new ConflictoOperacion(
                        'No se puede anular porque uno de los folios posee movimientos posteriores.',
                    );
                }
            }

            foreach ($folios as $folio) {
                $folio->ubicacionActual?->delete();
            }

            foreach ($repa->detalles as $detalle) {
                /** @var Folio $folio */
                $folio = $folios->get($detalle->folio_origen_id);
                $snapshot = $detalle->snapshot_antes;
                $folio->update($snapshot['atributos']);
                $this->restaurarUbicacion($folio, $snapshot['ubicacion'] ?? null);
            }

            if ($repa->estrategia_folio === 'nuevo') {
                /** @var Folio $folioResultado */
                $folioResultado = $folios->get($repa->folio_resultante_id);
                $folioResultado->update([
                    'estado_operacional' => EstadoOperacionalFolio::Anulado,
                    'activo' => false,
                ]);
            }

            $repa->update([
                'estado' => 'anulado',
                'operacion_anulacion_id' => $operacionId,
                'anulado_por_user_id' => $usuario->id,
                'anulado_at' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);

            return $this->cargar($repa->refresh());
        }, attempts: 3);
    }

    public function cargar(Repaletizaje $repaletizaje): Repaletizaje
    {
        return $repaletizaje->load([
            'folioResultante',
            'folioConservado',
            'detalles.folioOrigen',
            'usuario:id,name',
            'dispositivo:id,codigo,nombre',
            'anuladoPor:id,name',
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function normalizar(array $datos): array
    {
        return [
            'tipo_resultado' => $datos['tipo_resultado'],
            'estrategia_folio' => $datos['estrategia_folio'],
            'numero_folio_resultante' => mb_strtoupper(
                trim((string) $datos['numero_folio_resultante']),
            ),
            'folio_conservado_id' => $datos['folio_conservado_id'] ?? null,
            'cantidad_objetivo' => isset($datos['cantidad_objetivo'])
                ? (int) $datos['cantidad_objetivo']
                : null,
            'origenes' => collect($datos['origenes'])->map(fn (array $origen): array => [
                'folio_id' => $origen['folio_id'],
                'cantidad_aportada' => (int) $origen['cantidad_aportada'],
            ])->values()->all(),
            'observacion' => filled($datos['observacion'] ?? null)
                ? trim((string) $datos['observacion'])
                : null,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new DomainException(
                'No fue posible preparar la operación de repaletizaje.',
                previous: $exception,
            );
        }
    }

    private function validarFolioOperable(Folio $folio): void
    {
        if (! $folio->activo || $folio->tipo_bulto !== TipoBulto::Saldo) {
            throw new DomainException(
                "El folio {$folio->numero_folio} no es un saldo activo.",
            );
        }
        if ($this->cantidad($folio) < 1) {
            throw new DomainException(
                "El folio {$folio->numero_folio} no posee cajas disponibles.",
            );
        }
        if (! in_array($folio->condicion_termica, [
            CondicionTermicaFolio::PendientePrefrio,
            CondicionTermicaFolio::PrefrioAprobado,
        ], true)) {
            throw new DomainException(
                "El folio {$folio->numero_folio} posee un estado térmico transitorio o retenido.",
            );
        }
        if ($folio->asignacionCargaActual()->exists() || $folio->reservaCargaActual()->exists()) {
            throw new ConflictoOperacion(
                "El folio {$folio->numero_folio} está reservado o asignado a una carga.",
            );
        }
        if ($folio->procesosPrefrio()
            ->whereIn('estado', ['cargado', 'en_proceso'])
            ->exists()) {
            throw new ConflictoOperacion(
                "El folio {$folio->numero_folio} participa en un proceso de prefrío activo.",
            );
        }
    }

    /** @param Collection<int, Folio> $folios */
    private function validarCompatibilidadDura(Collection $folios): void
    {
        $campos = [
            'cliente' => $folios->map(fn (Folio $folio): mixed => $folio->exportadora),
            'especie' => $folios->map(
                fn (Folio $folio): mixed => $folio->datos_externos['especie'] ?? null,
            ),
            'marca' => $folios->map(fn (Folio $folio): mixed => $folio->marca),
            'estado térmico' => $folios->map(
                fn (Folio $folio): mixed => $folio->condicion_termica?->value,
            ),
        ];

        foreach ($campos as $nombre => $valores) {
            $unicos = $valores
                ->map(fn (mixed $valor): string => mb_strtoupper(trim((string) $valor)))
                ->unique();
            if ($unicos->contains('') || $unicos->count() !== 1) {
                throw new DomainException(
                    "No se puede mezclar diferente {$nombre} en un repaletizaje.",
                );
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function validarCantidadResultado(array $payload, int $cantidad): void
    {
        if ($payload['tipo_resultado'] === 'pallet'
            && $cantidad !== $payload['cantidad_objetivo']) {
            throw new DomainException(sprintf(
                'El pallet debe completar exactamente %d cajas; la selección aporta %d.',
                $payload['cantidad_objetivo'],
                $cantidad,
            ));
        }

        if ($payload['tipo_resultado'] === 'saldo'
            && $payload['cantidad_objetivo'] !== null
            && $cantidad >= $payload['cantidad_objetivo']) {
            throw new DomainException(
                'Un saldo consolidado debe quedar bajo la capacidad del pallet completo.',
            );
        }
    }

    /** @param Collection<int, Folio> $folios */
    private function especificacionesResultado(Collection $folios): array
    {
        return [
            'cliente' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->exportadora,
            )),
            'especie' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->datos_externos['especie'] ?? null,
            )),
            'marca' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->marca,
            )),
            'variedad' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->variedad,
            )),
            'calibre' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->calibre,
            )),
            'envase' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->datos_externos['envase'] ?? null,
            )),
            'categoria' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->datos_externos['categoria'] ?? null,
            )),
            'csg' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->datos_externos['csg'] ?? null,
            )),
            'predio' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->datos_externos['predio'] ?? null,
            )),
            'cuartel' => $this->valorComun($folios->map(
                fn (Folio $folio): mixed => $folio->datos_externos['cuartel'] ?? null,
            )),
        ];
    }

    private function valorComun(Collection $valores): ?string
    {
        $limpios = $valores->map(
            fn (mixed $valor): ?string => filled($valor) ? trim((string) $valor) : null,
        );
        $normalizados = $limpios
            ->map(fn (?string $valor): string => mb_strtoupper((string) $valor))
            ->unique();

        return $normalizados->count() === 1 ? $limpios->first() : 'MIX';
    }

    private function especificaciones(Folio $folio): array
    {
        return [
            'cliente' => $folio->exportadora,
            'especie' => $folio->datos_externos['especie'] ?? null,
            'marca' => $folio->marca,
            'variedad' => $folio->variedad,
            'calibre' => $folio->calibre,
            'envase' => $folio->datos_externos['envase'] ?? null,
            'categoria' => $folio->datos_externos['categoria'] ?? null,
            'csg' => $folio->datos_externos['csg'] ?? null,
            'predio' => $folio->datos_externos['predio'] ?? null,
            'cuartel' => $folio->datos_externos['cuartel'] ?? null,
            'condicion_termica' => $folio->condicion_termica?->value,
        ];
    }

    private function cantidad(Folio $folio): int
    {
        return max(0, (int) ($folio->datos_externos['cantidad_cajas'] ?? 0));
    }

    private function actualizarFolioResultado(
        Folio $folio,
        string $tipoResultado,
        int $cantidad,
        EstadoOperacionalFolio $estado,
        HabilitacionAlmacenamientoFolio $habilitacion,
        array $especificaciones,
        string $codigo,
        array $camposMix,
        array $composicion,
    ): void {
        $datosExternos = array_merge($folio->datos_externos ?? [], [
            'especie' => $especificaciones['especie'],
            'categoria' => $especificaciones['categoria'],
            'envase' => $especificaciones['envase'],
            'csg' => $especificaciones['csg'],
            'predio' => $especificaciones['predio'],
            'cuartel' => $especificaciones['cuartel'],
            'cantidad_cajas' => $cantidad,
            'repaletizaje_codigo' => $codigo,
            'campos_mix' => $camposMix,
            'composicion' => $composicion,
        ]);

        $folio->update([
            'tipo_bulto' => TipoBulto::from($tipoResultado),
            'estado_operacional' => $estado,
            'habilitacion_almacenamiento' => $habilitacion,
            'activo' => true,
            'variedad' => $especificaciones['variedad'],
            'calibre' => $especificaciones['calibre'],
            'marca' => $especificaciones['marca'],
            'exportadora' => $especificaciones['cliente'],
            'origen_sistema' => 'repaletizaje',
            'identificador_externo' => $codigo,
            'datos_externos' => $datosExternos,
        ]);
    }

    private function snapshotFolio(Folio $folio): array
    {
        $folio->loadMissing('ubicacionActual');

        return [
            'atributos' => [
                'tipo_bulto' => $folio->tipo_bulto?->value,
                'estado_operacional' => $folio->estado_operacional?->value,
                'condicion_termica' => $folio->condicion_termica?->value,
                'habilitacion_almacenamiento' => $folio->habilitacion_almacenamiento?->value,
                'activo' => $folio->activo,
                'variedad' => $folio->variedad,
                'calibre' => $folio->calibre,
                'marca' => $folio->marca,
                'exportadora' => $folio->exportadora,
                'origen_sistema' => $folio->origen_sistema,
                'identificador_externo' => $folio->identificador_externo,
                'datos_externos' => $folio->datos_externos,
            ],
            'especificaciones' => $this->especificaciones($folio),
            'ubicacion' => $folio->ubicacionActual ? [
                'id' => $folio->ubicacionActual->id,
                'camara_id' => $folio->ubicacionActual->camara_id,
                'posicion_id' => $folio->ubicacionActual->posicion_id,
                'movimiento_id' => $folio->ubicacionActual->movimiento_id,
                'ubicado_at' => $folio->ubicacionActual->ubicado_at?->toDateTimeString(),
                'created_at' => $folio->ubicacionActual->created_at?->toDateTimeString(),
                'updated_at' => $folio->ubicacionActual->updated_at?->toDateTimeString(),
            ] : null,
        ];
    }

    private function restaurarUbicacion(Folio $folio, ?array $ubicacion): void
    {
        if (! $ubicacion) {
            return;
        }

        DB::table('ubicaciones_actuales')->insert([
            'id' => $ubicacion['id'],
            'folio_id' => $folio->id,
            'camara_id' => $ubicacion['camara_id'],
            'posicion_id' => $ubicacion['posicion_id'],
            'movimiento_id' => $ubicacion['movimiento_id'],
            'ubicado_at' => $ubicacion['ubicado_at'],
            'created_at' => $ubicacion['created_at'] ?? now(),
            'updated_at' => now(),
        ]);
    }

    private function siguienteCodigo(): string
    {
        $anio = (int) now()->format('Y');
        DB::table('secuencias_repaletizajes')->insertOrIgnore([
            'anio' => $anio,
            'ultimo_numero' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secuencia = DB::table('secuencias_repaletizajes')
            ->where('anio', $anio)
            ->lockForUpdate()
            ->first();
        $numero = ((int) $secuencia->ultimo_numero) + 1;
        DB::table('secuencias_repaletizajes')
            ->where('anio', $anio)
            ->update([
                'ultimo_numero' => $numero,
                'updated_at' => now(),
            ]);

        return sprintf('REPA-%d-%06d', $anio, $numero);
    }
}
