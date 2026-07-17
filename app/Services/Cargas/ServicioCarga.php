<?php

namespace App\Services\Cargas;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoCarga;
use App\Enums\EstadoCargaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\PrioridadCarga;
use App\Enums\TipoBulto;
use App\Enums\TipoEventoCarga;
use App\Exceptions\ConflictoOperacion;
use App\Exceptions\FoliosCargaInvalidos;
use App\Exceptions\OperacionNoAutorizada;
use App\Models\Anden;
use App\Models\Camara;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\EventoCarga;
use App\Models\Folio;
use App\Models\ReservaCargaFolio;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

class ServicioCarga
{
    public function __construct(
        private readonly AlcanceOperacionalUsuario $alcance,
        private readonly ServicioTareasCarga $servicioTareas,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $usuario): Carga
    {
        $this->asegurarGestionAutorizada($usuario);

        return DB::transaction(function () use ($datos, $usuario): Carga {
            $camaraObjetivoId = $datos['camara_objetivo_id'] ?? null;
            $this->asegurarCamaraObjetivoValida($camaraObjetivoId);
            $andenPrevistoId = $datos['anden_previsto_id'] ?? null;
            $this->asegurarAndenValido($andenPrevistoId);

            $carga = Carga::create([
                'codigo' => $this->siguienteCodigoBloqueado(),
                'numero_orden_externa' => $this->textoOpcional($datos['numero_orden_externa'] ?? null),
                'estado' => EstadoCarga::Borrador,
                'prioridad' => PrioridadCarga::from(
                    $datos['prioridad'] ?? PrioridadCarga::Normal->value,
                ),
                'camara_objetivo_id' => $camaraObjetivoId,
                'anden_previsto_id' => $andenPrevistoId,
                'observacion' => $this->textoOpcional($datos['observacion'] ?? null),
                'version' => 1,
                'creada_por_user_id' => $usuario->id,
                'actualizada_por_user_id' => $usuario->id,
            ]);

            $this->registrarEvento($carga, TipoEventoCarga::Creada, $usuario);

            return $carga;
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(
        Carga $carga,
        array $datos,
        User $usuario,
        int $versionEsperada,
    ): Carga {
        $this->asegurarGestionAutorizada($usuario);

        return DB::transaction(function () use ($carga, $datos, $usuario, $versionEsperada): Carga {
            $cargaBloqueada = $this->bloquearCarga($carga);
            $this->asegurarEditable($cargaBloqueada);
            $this->asegurarVersion($cargaBloqueada, $versionEsperada);
            $camaraObjetivoId = $datos['camara_objetivo_id'] ?? null;
            $this->asegurarCamaraObjetivoValida($camaraObjetivoId);
            $andenPrevistoId = array_key_exists('anden_previsto_id', $datos)
                ? $datos['anden_previsto_id']
                : $cargaBloqueada->anden_previsto_id;
            $this->asegurarAndenValido($andenPrevistoId);

            $cargaBloqueada->update([
                'numero_orden_externa' => $this->textoOpcional(
                    $datos['numero_orden_externa'] ?? null,
                ),
                'prioridad' => PrioridadCarga::from(
                    $datos['prioridad'] ?? $cargaBloqueada->prioridad->value,
                ),
                'camara_objetivo_id' => $camaraObjetivoId,
                'anden_previsto_id' => $andenPrevistoId,
                'observacion' => $this->textoOpcional($datos['observacion'] ?? null),
                'version' => $cargaBloqueada->version + 1,
                'actualizada_por_user_id' => $usuario->id,
            ]);

            $this->registrarEvento(
                $cargaBloqueada,
                TipoEventoCarga::Actualizada,
                $usuario,
            );

            return $cargaBloqueada->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<int, string>  $numerosFolio
     */
    public function agregarFolios(
        Carga $carga,
        array $numerosFolio,
        User $usuario,
        int $versionEsperada,
    ): Carga {
        $this->asegurarGestionAutorizada($usuario);

        return DB::transaction(function () use (
            $carga,
            $numerosFolio,
            $usuario,
            $versionEsperada,
        ): Carga {
            $cargaBloqueada = $this->bloquearCarga($carga);
            $this->asegurarEditable($cargaBloqueada);
            $this->asegurarVersion($cargaBloqueada, $versionEsperada);

            $numeros = $this->normalizarNumerosFolio($numerosFolio);
            $cantidadActual = CargaFolio::query()
                ->where('carga_id', $cargaBloqueada->id)
                ->whereHas('reservaActiva')
                ->lockForUpdate()
                ->count();

            if ($cantidadActual + count($numeros) > 26) {
                throw new DomainException(
                    'Una carga no puede contener más de 26 folios.',
                );
            }

            $folios = Folio::query()
                ->whereIn('numero_folio', $numeros)
                ->with('ubicacionActual.posicion.camara')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('numero_folio');
            $reservas = ReservaCargaFolio::query()
                ->whereIn('folio_id', $folios->pluck('id'))
                ->with('asignacion.carga:id,codigo')
                ->orderBy('folio_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('folio_id');
            $errores = [];

            foreach ($numeros as $numero) {
                /** @var Folio|null $folio */
                $folio = $folios->get($numero);

                if (! $folio) {
                    $errores[] = $this->errorFolio(
                        $numero,
                        'no_existe',
                        "El folio {$numero} no existe en el sistema.",
                    );

                    continue;
                }

                $reserva = $reservas->get($folio->id);

                if ($reserva) {
                    $asignacion = $reserva->asignacion;
                    $codigo = $asignacion->carga_id === $cargaBloqueada->id
                        ? 'ya_asignado_carga'
                        : 'asignado_otra_carga';
                    $errores[] = $this->errorFolio(
                        $numero,
                        $codigo,
                        sprintf(
                            'El folio %s ya está asignado a la carga %s.',
                            $numero,
                            $asignacion->carga->codigo,
                        ),
                    );

                    continue;
                }

                $error = $this->motivoFolioNoAsignable($folio);

                if ($error) {
                    $errores[] = $this->errorFolio(
                        $numero,
                        $error['codigo'],
                        $error['mensaje'],
                    );
                }
            }

            if ($errores !== []) {
                throw new FoliosCargaInvalidos($errores);
            }

            $asignados = [];

            foreach ($numeros as $numero) {
                /** @var Folio $folio */
                $folio = $folios->get($numero);

                try {
                    $asignacion = CargaFolio::create([
                        'carga_id' => $cargaBloqueada->id,
                        'folio_id' => $folio->id,
                        'estado' => EstadoCargaFolio::Pendiente,
                        'asignado_por_user_id' => $usuario->id,
                        'asignado_at' => now(),
                    ]);
                    ReservaCargaFolio::create([
                        'folio_id' => $folio->id,
                        'carga_folio_id' => $asignacion->id,
                    ]);
                } catch (QueryException $exception) {
                    if ($exception->getCode() === '23000') {
                        throw new ConflictoOperacion(sprintf(
                            'El folio %s fue asignado a otra carga mientras se procesaba la orden.',
                            $folio->numero_folio,
                        ), previous: $exception);
                    }

                    throw $exception;
                }

                $asignados[] = $folio;
            }

            $this->incrementarVersion($cargaBloqueada, $usuario);

            foreach ($asignados as $folio) {
                $this->registrarEvento(
                    $cargaBloqueada,
                    TipoEventoCarga::FolioAsignado,
                    $usuario,
                    $folio,
                );
            }

            if ($cargaBloqueada->estado === EstadoCarga::Pendiente) {
                $this->servicioTareas->sincronizar($cargaBloqueada);
            }

            return $cargaBloqueada->refresh();
        }, attempts: 3);
    }

    public function quitarFolio(
        Carga $carga,
        Folio $folio,
        User $usuario,
        int $versionEsperada,
        ?string $motivo = null,
    ): Carga {
        $this->asegurarGestionAutorizada($usuario);

        return DB::transaction(function () use (
            $carga,
            $folio,
            $usuario,
            $versionEsperada,
            $motivo,
        ): Carga {
            $cargaBloqueada = $this->bloquearCarga($carga);
            $this->asegurarEditable($cargaBloqueada);
            $this->asegurarVersion($cargaBloqueada, $versionEsperada);

            $asignaciones = CargaFolio::query()
                ->where('carga_id', $cargaBloqueada->id)
                ->whereHas('reservaActiva')
                ->orderBy('folio_id')
                ->lockForUpdate()
                ->get();
            $asignacion = $asignaciones->firstWhere('folio_id', $folio->id);

            if (! $asignacion) {
                throw new DomainException(
                    'El folio no pertenece actualmente a esta carga.',
                );
            }

            if ($cargaBloqueada->estado === EstadoCarga::Pendiente
                && $asignaciones->count() === 1) {
                throw new DomainException(
                    'Una carga publicada debe conservar al menos un folio.',
                );
            }

            $asignacion->reservaActiva()->delete();
            $asignacion->update([
                'estado' => EstadoCargaFolio::Descartado,
                'finalizado_por_user_id' => $usuario->id,
                'finalizado_at' => now(),
                'motivo_finalizacion' => $this->textoOpcional($motivo),
            ]);
            $this->incrementarVersion($cargaBloqueada, $usuario);
            $this->registrarEvento(
                $cargaBloqueada,
                TipoEventoCarga::FolioDesasignado,
                $usuario,
                $folio,
                ['motivo' => $this->textoOpcional($motivo)],
            );

            if ($cargaBloqueada->estado === EstadoCarga::Pendiente) {
                $this->servicioTareas->sincronizar($cargaBloqueada);
            }

            return $cargaBloqueada->refresh();
        }, attempts: 3);
    }

    public function publicar(
        Carga $carga,
        User $usuario,
        int $versionEsperada,
    ): Carga {
        $this->asegurarGestionAutorizada($usuario);

        return DB::transaction(function () use ($carga, $usuario, $versionEsperada): Carga {
            $cargaBloqueada = $this->bloquearCarga($carga);
            $this->asegurarBorrador($cargaBloqueada);
            $this->asegurarVersion($cargaBloqueada, $versionEsperada);
            $this->asegurarCamaraObjetivoValida($cargaBloqueada->camara_objetivo_id);

            $asignaciones = CargaFolio::query()
                ->where('carga_id', $cargaBloqueada->id)
                ->whereIn('estado', [
                    EstadoCargaFolio::Pendiente->value,
                    EstadoCargaFolio::ConIncidencia->value,
                    EstadoCargaFolio::EnAnden->value,
                ])
                ->with('reservaActiva')
                ->orderBy('folio_id')
                ->lockForUpdate()
                ->get();

            if ($asignaciones->count() < 1 || $asignaciones->count() > 26) {
                throw new DomainException(
                    'Una carga debe contener entre 1 y 26 folios antes de publicarse.',
                );
            }

            $folios = Folio::query()
                ->whereIn('id', $asignaciones->pluck('folio_id'))
                ->with('ubicacionActual.posicion.camara')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $errores = [];

            foreach ($folios as $folio) {
                $error = $this->motivoFolioNoAsignable($folio);

                if ($error) {
                    $errores[] = $this->errorFolio(
                        $folio->numero_folio,
                        $error['codigo'],
                        $error['mensaje'],
                    );
                }

                $asignacion = $asignaciones->firstWhere('folio_id', $folio->id);

                if ($asignacion && ! $asignacion->reservaActiva) {
                    $errores[] = $this->errorFolio(
                        $folio->numero_folio,
                        'reserva_inconsistente',
                        "El folio {$folio->numero_folio} no posee una reserva de carga vigente.",
                    );
                }
            }

            if ($errores !== []) {
                throw new FoliosCargaInvalidos($errores);
            }

            $cargaBloqueada->update([
                'estado' => EstadoCarga::Pendiente,
                'version' => $cargaBloqueada->version + 1,
                'actualizada_por_user_id' => $usuario->id,
                'publicada_por_user_id' => $usuario->id,
                'publicada_at' => now(),
            ]);

            $this->registrarEvento(
                $cargaBloqueada,
                TipoEventoCarga::Publicada,
                $usuario,
                datos: ['cantidad_folios' => $asignaciones->count()],
            );

            $this->servicioTareas->sincronizar($cargaBloqueada);
            $this->registrarEvento(
                $cargaBloqueada,
                TipoEventoCarga::TareasGeneradas,
                $usuario,
                datos: [
                    'camaras_origen' => $cargaBloqueada->tareas()
                        ->where('estado', '!=', 'completada')
                        ->pluck('camara_origen_id')
                        ->values()
                        ->all(),
                ],
            );

            return $cargaBloqueada->refresh();
        }, attempts: 3);
    }

    public function cancelar(
        Carga $carga,
        User $usuario,
        int $versionEsperada,
        ?string $motivo = null,
    ): Carga {
        $this->asegurarGestionAutorizada($usuario);

        return DB::transaction(function () use (
            $carga,
            $usuario,
            $versionEsperada,
            $motivo,
        ): Carga {
            $cargaBloqueada = $this->bloquearCarga($carga);
            $this->asegurarVersion($cargaBloqueada, $versionEsperada);

            if (! in_array($cargaBloqueada->estado, [
                EstadoCarga::Borrador,
                EstadoCarga::Pendiente,
            ], true)) {
                throw new DomainException(
                    'Solo una carga en borrador o pendiente puede cancelarse.',
                );
            }

            $asignaciones = CargaFolio::query()
                ->where('carga_id', $cargaBloqueada->id)
                ->whereHas('reservaActiva')
                ->with('folio:id,numero_folio')
                ->orderBy('folio_id')
                ->lockForUpdate()
                ->get();
            $motivoNormalizado = $this->textoOpcional($motivo);

            $cargaBloqueada->update([
                'estado' => EstadoCarga::Cancelada,
                'version' => $cargaBloqueada->version + 1,
                'actualizada_por_user_id' => $usuario->id,
                'cancelada_por_user_id' => $usuario->id,
                'cancelada_at' => now(),
            ]);

            foreach ($asignaciones as $asignacion) {
                $this->registrarEvento(
                    $cargaBloqueada,
                    TipoEventoCarga::FolioDesasignado,
                    $usuario,
                    $asignacion->folio,
                    [
                        'motivo' => $motivoNormalizado,
                        'causa' => 'cancelacion_carga',
                    ],
                );

                $asignacion->reservaActiva()->delete();
                $asignacion->update([
                    'estado' => EstadoCargaFolio::Descartado,
                    'finalizado_por_user_id' => $usuario->id,
                    'finalizado_at' => now(),
                    'motivo_finalizacion' => $motivoNormalizado,
                ]);
            }

            $this->registrarEvento(
                $cargaBloqueada,
                TipoEventoCarga::Cancelada,
                $usuario,
                datos: [
                    'motivo' => $motivoNormalizado,
                    'folios_liberados' => $asignaciones
                        ->pluck('folio.numero_folio')
                        ->filter()
                        ->values()
                        ->all(),
                ],
            );

            $this->servicioTareas->sincronizar($cargaBloqueada);

            return $cargaBloqueada->refresh();
        }, attempts: 3);
    }

    private function asegurarGestionAutorizada(User $usuario): void
    {
        if (! $this->alcance->puedeGestionarCargas($usuario)) {
            throw new OperacionNoAutorizada(
                'El usuario no está autorizado para gestionar cargas de productos.',
            );
        }
    }

    private function bloquearCarga(Carga $carga): Carga
    {
        return Carga::query()
            ->lockForUpdate()
            ->findOrFail($carga->id);
    }

    private function asegurarBorrador(Carga $carga): void
    {
        if ($carga->estado !== EstadoCarga::Borrador) {
            throw new DomainException('Solo una carga en borrador puede publicarse.');
        }
    }

    private function asegurarEditable(Carga $carga): void
    {
        if (! in_array($carga->estado, [
            EstadoCarga::Borrador,
            EstadoCarga::Pendiente,
        ], true)) {
            throw new DomainException(
                'La carga ya inició su separación y no admite cambios.',
            );
        }
    }

    private function asegurarVersion(Carga $carga, int $versionEsperada): void
    {
        if ($carga->version !== $versionEsperada) {
            throw new ConflictoOperacion(sprintf(
                'La carga %s fue modificada por otro usuario. Se esperaba la versión %d y la versión actual es %d.',
                $carga->codigo,
                $versionEsperada,
                $carga->version,
            ));
        }
    }

    private function incrementarVersion(Carga $carga, User $usuario): void
    {
        $carga->update([
            'version' => $carga->version + 1,
            'actualizada_por_user_id' => $usuario->id,
        ]);
    }

    /**
     * @return array{codigo: string, mensaje: string}|null
     */
    private function motivoFolioNoAsignable(Folio $folio): ?array
    {
        if (! in_array($folio->tipo_bulto, [
            TipoBulto::Pallet,
            TipoBulto::Saldo,
        ], true)) {
            return [
                'codigo' => 'tipo_bulto_no_permitido',
                'mensaje' => "El folio {$folio->numero_folio} corresponde a materiales y no puede incorporarse a una carga CAR-*.",
            ];
        }

        if (! $folio->activo) {
            return [
                'codigo' => 'inactivo',
                'mensaje' => "El folio {$folio->numero_folio} está inactivo.",
            ];
        }

        if ($folio->estado_operacional !== EstadoOperacionalFolio::Disponible) {
            return [
                'codigo' => 'estado_no_disponible',
                'mensaje' => sprintf(
                    'El folio %s no está disponible; su estado es %s.',
                    $folio->numero_folio,
                    $folio->estado_operacional->value,
                ),
            ];
        }

        if (! $folio->ubicacionActual) {
            return [
                'codigo' => 'sin_ubicacion',
                'mensaje' => "El folio {$folio->numero_folio} no posee una ubicación actual.",
            ];
        }

        $camara = $folio->ubicacionActual->posicion?->camara;

        if (! $camara
            || $camara->estado !== EstadoCamara::Activa
            || $camara->contenido !== ContenidoCamara::Productos) {
            return [
                'codigo' => 'camara_no_productos',
                'mensaje' => "El folio {$folio->numero_folio} no está ubicado en una cámara de productos.",
            ];
        }

        return null;
    }

    private function asegurarCamaraObjetivoValida(?string $camaraId): void
    {
        if ($camaraId === null) {
            return;
        }

        $camara = Camara::query()
            ->whereKey($camaraId)
            ->where('estado', EstadoCamara::Activa->value)
            ->where('contenido', ContenidoCamara::Productos->value)
            ->lockForUpdate()
            ->first(['id']);

        if (! $camara) {
            throw new DomainException(
                'La cámara objetivo debe estar activa y clasificada como cámara de productos.',
            );
        }
    }

    private function asegurarAndenValido(?string $andenId): void
    {
        if ($andenId === null) {
            return;
        }

        $existe = Anden::query()
            ->whereKey($andenId)
            ->where('activo', true)
            ->lockForUpdate()
            ->exists();

        if (! $existe) {
            throw new DomainException('El andén previsto debe existir y estar activo.');
        }
    }

    /**
     * @return array{folio: string, codigo: string, mensaje: string}
     */
    private function errorFolio(string $folio, string $codigo, string $mensaje): array
    {
        return compact('folio', 'codigo', 'mensaje');
    }

    /**
     * @param  array<int, string>  $numerosFolio
     * @return array<int, string>
     */
    private function normalizarNumerosFolio(array $numerosFolio): array
    {
        $numeros = collect($numerosFolio)
            ->map(fn (string $numero): string => trim($numero))
            ->filter()
            ->unique()
            ->values();

        if ($numeros->isEmpty()) {
            throw new DomainException('Debe indicar al menos un folio.');
        }

        if ($numeros->count() > 26) {
            throw new DomainException(
                'Una carga no puede recibir más de 26 folios por operación.',
            );
        }

        return $numeros->all();
    }

    private function siguienteCodigoBloqueado(): string
    {
        $ultimoNumero = DB::table('secuencias_documentos')
            ->where('clave', 'cargas')
            ->lockForUpdate()
            ->value('ultimo_numero');

        if ($ultimoNumero === null) {
            throw new LogicException('No existe la secuencia configurada para las cargas.');
        }

        $siguienteNumero = ((int) $ultimoNumero) + 1;
        DB::table('secuencias_documentos')
            ->where('clave', 'cargas')
            ->update(['ultimo_numero' => $siguienteNumero]);

        return sprintf('CAR-%06d', $siguienteNumero);
    }

    /**
     * @param  array<string, mixed>|null  $datos
     */
    private function registrarEvento(
        Carga $carga,
        TipoEventoCarga $tipo,
        User $usuario,
        ?Folio $folio = null,
        ?array $datos = null,
    ): void {
        EventoCarga::create([
            'carga_id' => $carga->id,
            'folio_id' => $folio?->id,
            'user_id' => $usuario->id,
            'tipo' => $tipo,
            'datos' => [
                'version' => $carga->version,
                ...($datos ?? []),
            ],
        ]);
    }

    private function textoOpcional(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
