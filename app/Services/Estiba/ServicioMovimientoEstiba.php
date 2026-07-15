<?php

namespace App\Services\Estiba;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionSincronizacion;
use App\Enums\TipoMovimientoInventarioMaterial;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Exceptions\ConflictoMovimiento;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\Movimiento;
use App\Models\MovimientoInventarioMaterial;
use App\Models\OperacionSincronizacion;
use App\Models\Posicion;
use App\Models\SesionEstiba;
use App\Models\UbicacionActual;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class ServicioMovimientoEstiba
{
    private const CAMPOS_FOLIO_PERMITIDOS = [
        'condicion_sag_id',
        'fecha_ingreso',
        'variedad',
        'calibre',
        'marca',
        'exportadora',
        'origen_sistema',
        'identificador_externo',
        'estado_integracion',
        'sincronizado_at',
        'datos_externos',
    ];

    public function __construct(
        private readonly DetectorAdvertenciasMovimiento $detectorAdvertencias,
    ) {}

    /**
     * Ubica un folio por primera vez y lo crea si todavía no existe.
     *
     * @param  array<string, mixed>  $datosFolio
     * @param  array<string, mixed>  $datosMaterial
     * @param  array<int, string>  $advertenciasConfirmadas
     */
    public function ubicar(
        string $operacionId,
        string $numeroFolio,
        TipoBulto $tipoBulto,
        Posicion $posicionDestino,
        SesionEstiba $sesionDestino,
        User $usuario,
        Dispositivo $dispositivo,
        int $versionDestinoConocida,
        DateTimeInterface $generadoDispositivoAt,
        array $datosFolio = [],
        array $datosMaterial = [],
        array $advertenciasConfirmadas = [],
    ): Movimiento {
        $numeroFolio = trim($numeroFolio);
        $this->validarNumeroFolio($numeroFolio);
        sort($advertenciasConfirmadas, SORT_STRING);

        $payload = [
            'numero_folio' => $numeroFolio,
            'tipo_bulto' => $tipoBulto->value,
            'posicion_destino_id' => $posicionDestino->id,
            'sesion_destino_id' => $sesionDestino->id,
            'version_destino_conocida' => $versionDestinoConocida,
            'generado_dispositivo_at' => $generadoDispositivoAt->format(DATE_ATOM),
            'datos_folio' => $this->filtrarDatosFolio($datosFolio),
            'datos_material' => $this->normalizarDatosMaterial($datosMaterial),
            'advertencias_confirmadas' => $advertenciasConfirmadas,
        ];

        return $this->ejecutarOperacion(
            $operacionId,
            TipoMovimiento::UbicacionInicial,
            $usuario,
            $dispositivo,
            $generadoDispositivoAt,
            $payload,
            fn (OperacionSincronizacion $operacion, DateTimeInterface $recibidoServidorAt): Movimiento => $this->procesarUbicacionInicial(
                $operacion,
                $numeroFolio,
                $tipoBulto,
                $posicionDestino,
                $sesionDestino,
                $usuario,
                $dispositivo,
                $versionDestinoConocida,
                $generadoDispositivoAt,
                $recibidoServidorAt,
                $datosFolio,
                $datosMaterial,
                $advertenciasConfirmadas,
            ),
        );
    }

    /**
     * Reubica un folio o lo traslada entre cámaras según el destino indicado.
     *
     * @param  array<int, string>  $advertenciasConfirmadas
     */
    public function mover(
        string $operacionId,
        Folio $folio,
        Posicion $posicionDestino,
        SesionEstiba $sesionOrigen,
        SesionEstiba $sesionDestino,
        User $usuario,
        Dispositivo $dispositivo,
        int $versionOrigenConocida,
        int $versionDestinoConocida,
        DateTimeInterface $generadoDispositivoAt,
        array $advertenciasConfirmadas = [],
    ): Movimiento {
        $tipo = $sesionOrigen->camara_id === $posicionDestino->camara_id
            ? TipoMovimiento::Reubicacion
            : TipoMovimiento::TrasladoEntreCamaras;
        sort($advertenciasConfirmadas, SORT_STRING);

        $payload = [
            'folio_id' => $folio->id,
            'posicion_destino_id' => $posicionDestino->id,
            'sesion_origen_id' => $sesionOrigen->id,
            'sesion_destino_id' => $sesionDestino->id,
            'version_origen_conocida' => $versionOrigenConocida,
            'version_destino_conocida' => $versionDestinoConocida,
            'generado_dispositivo_at' => $generadoDispositivoAt->format(DATE_ATOM),
            'advertencias_confirmadas' => $advertenciasConfirmadas,
        ];

        return $this->ejecutarOperacion(
            $operacionId,
            $tipo,
            $usuario,
            $dispositivo,
            $generadoDispositivoAt,
            $payload,
            fn (OperacionSincronizacion $operacion, DateTimeInterface $recibidoServidorAt): Movimiento => $this->procesarMovimiento(
                $operacion,
                $tipo,
                $folio,
                $posicionDestino,
                $sesionOrigen,
                $sesionDestino,
                $usuario,
                $dispositivo,
                $versionOrigenConocida,
                $versionDestinoConocida,
                $generadoDispositivoAt,
                $recibidoServidorAt,
                $advertenciasConfirmadas,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(OperacionSincronizacion, DateTimeInterface): Movimiento  $procesar
     */
    private function ejecutarOperacion(
        string $operacionId,
        TipoMovimiento $tipo,
        User $usuario,
        Dispositivo $dispositivo,
        DateTimeInterface $generadoDispositivoAt,
        array $payload,
        callable $procesar,
    ): Movimiento {
        if (! Str::isUuid($operacionId)) {
            throw new DomainException('El identificador de la operación debe ser un UUID válido.');
        }

        $payloadNormalizado = $this->normalizar($payload);
        $payloadHash = $this->calcularHash($payloadNormalizado);

        try {
            /** @var array{movimiento?: Movimiento, error?: Throwable} $resultado */
            $resultado = DB::transaction(function () use (
                $operacionId,
                $tipo,
                $usuario,
                $dispositivo,
                $generadoDispositivoAt,
                $payloadNormalizado,
                $payloadHash,
                $procesar,
            ): array {
                $operacion = OperacionSincronizacion::query()
                    ->lockForUpdate()
                    ->find($operacionId);

                if ($operacion) {
                    $movimientoRepetido = $this->resolverOperacionExistente(
                        $operacion,
                        $tipo,
                        $usuario,
                        $dispositivo,
                        $payloadHash,
                    );

                    if ($movimientoRepetido) {
                        return ['movimiento' => $movimientoRepetido];
                    }
                } else {
                    $operacion = OperacionSincronizacion::create([
                        'id' => $operacionId,
                        'user_id' => $usuario->id,
                        'dispositivo_id' => $dispositivo->id,
                        'tipo' => $tipo->value,
                        'estado' => EstadoOperacionSincronizacion::Pendiente,
                        'payload_hash' => $payloadHash,
                        'payload' => $payloadNormalizado,
                        'generada_dispositivo_at' => $generadoDispositivoAt,
                        'recibida_servidor_at' => now(),
                    ]);
                }

                $operacion->update([
                    'estado' => EstadoOperacionSincronizacion::Procesando,
                    'codigo_error' => null,
                    'mensaje_error' => null,
                    'procesada_at' => null,
                ]);

                try {
                    $movimiento = DB::transaction(
                        fn (): Movimiento => $procesar(
                            $operacion,
                            $operacion->recibida_servidor_at,
                        ),
                    );
                } catch (ConflictoMovimiento $exception) {
                    $this->registrarErrorOperacion(
                        $operacion,
                        EstadoOperacionSincronizacion::Conflicto,
                        'conflicto_movimiento',
                        $exception,
                    );

                    return ['error' => $exception];
                } catch (UniqueConstraintViolationException $exception) {
                    $conflicto = new ConflictoMovimiento(
                        'El movimiento entró en conflicto con otro cambio concurrente.',
                        previous: $exception,
                    );
                    $this->registrarErrorOperacion(
                        $operacion,
                        EstadoOperacionSincronizacion::Conflicto,
                        'conflicto_concurrencia',
                        $conflicto,
                    );

                    return ['error' => $conflicto];
                } catch (DomainException $exception) {
                    $this->registrarErrorOperacion(
                        $operacion,
                        EstadoOperacionSincronizacion::Rechazada,
                        'regla_dominio',
                        $exception,
                    );

                    return ['error' => $exception];
                }

                $versionesConocidas = $this->versionesDeMovimiento($movimiento, false);
                $versionesResultantes = $this->versionesDeMovimiento($movimiento, true);

                $operacion->update([
                    'estado' => EstadoOperacionSincronizacion::Aceptada,
                    'resultado' => [
                        'movimiento_id' => $movimiento->id,
                        'folio_id' => $movimiento->folio_id,
                        'tipo_movimiento' => $movimiento->tipo_movimiento->value,
                    ],
                    'versiones_conocidas' => $versionesConocidas,
                    'versiones_resultantes' => $versionesResultantes,
                    'procesada_at' => now(),
                ]);

                return ['movimiento' => $movimiento];
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $operacion = OperacionSincronizacion::query()->find($operacionId);

            if (! $operacion) {
                throw new ConflictoMovimiento(
                    'No fue posible registrar la operación por un conflicto concurrente.',
                    previous: $exception,
                );
            }

            $movimiento = $this->resolverOperacionExistente(
                $operacion,
                $tipo,
                $usuario,
                $dispositivo,
                $payloadHash,
            );

            if (! $movimiento) {
                throw new ConflictoMovimiento(
                    'La operación ya está siendo procesada por otra solicitud.',
                    previous: $exception,
                );
            }

            return $movimiento;
        }

        if (isset($resultado['error'])) {
            throw $resultado['error'];
        }

        return $resultado['movimiento'];
    }

    /**
     * Una operación pendiente o en proceso puede recuperarse; las demás son finales.
     */
    private function resolverOperacionExistente(
        OperacionSincronizacion $operacion,
        TipoMovimiento $tipo,
        User $usuario,
        Dispositivo $dispositivo,
        string $payloadHash,
    ): ?Movimiento {
        $mismaSolicitud = $operacion->user_id === $usuario->id
            && $operacion->dispositivo_id === $dispositivo->id
            && $operacion->tipo === $tipo->value
            && hash_equals($operacion->payload_hash, $payloadHash);

        if (! $mismaSolicitud) {
            throw new ConflictoMovimiento(
                'El UUID de operación ya fue utilizado con datos diferentes.',
            );
        }

        if ($operacion->estado === EstadoOperacionSincronizacion::Aceptada) {
            $movimiento = $operacion->movimiento()->first();

            if (! $movimiento) {
                throw new DomainException(
                    'La operación aceptada no posee el movimiento correspondiente.',
                );
            }

            return $movimiento;
        }

        if (in_array($operacion->estado, [
            EstadoOperacionSincronizacion::Rechazada,
            EstadoOperacionSincronizacion::Conflicto,
        ], true)) {
            $mensaje = $operacion->mensaje_error ?: 'La operación fue rechazada anteriormente.';

            if ($operacion->estado === EstadoOperacionSincronizacion::Conflicto) {
                throw new ConflictoMovimiento($mensaje);
            }

            throw new DomainException($mensaje);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $datosFolio
     * @param  array<string, mixed>  $datosMaterial
     * @param  array<int, string>  $advertenciasConfirmadas
     */
    private function procesarUbicacionInicial(
        OperacionSincronizacion $operacion,
        string $numeroFolio,
        TipoBulto $tipoBulto,
        Posicion $posicionDestino,
        SesionEstiba $sesionDestino,
        User $usuario,
        Dispositivo $dispositivo,
        int $versionDestinoConocida,
        DateTimeInterface $generadoDispositivoAt,
        DateTimeInterface $recibidoServidorAt,
        array $datosFolio,
        array $datosMaterial,
        array $advertenciasConfirmadas,
    ): Movimiento {
        $camara = Camara::query()
            ->lockForUpdate()
            ->findOrFail($posicionDestino->camara_id);
        $posicion = Posicion::query()->lockForUpdate()->findOrFail($posicionDestino->id);
        $sesion = SesionEstiba::query()->lockForUpdate()->findOrFail($sesionDestino->id);

        $this->validarContenidoCamara($camara, $tipoBulto);

        $folio = Folio::query()
            ->where('numero_folio', $numeroFolio)
            ->lockForUpdate()
            ->first();

        if (! $folio) {
            $folio = Folio::create($this->atributosNuevoFolio(
                $numeroFolio,
                $tipoBulto,
                $generadoDispositivoAt,
                $datosFolio,
            ));

            if ($tipoBulto === TipoBulto::Material) {
                $this->crearFichaMaterial(
                    $folio,
                    $datosMaterial,
                    $usuario,
                    $dispositivo,
                    $recibidoServidorAt,
                );
            }
        } elseif ($folio->tipo_bulto !== $tipoBulto) {
            throw new DomainException('El tipo de bulto no coincide con el folio existente.');
        } elseif ($tipoBulto === TipoBulto::Material
            && ! FolioMaterial::query()->whereKey($folio->id)->exists()) {
            throw new DomainException('El folio de material no posee una ficha de inventario válida.');
        }

        $this->validarVersion($camara, $versionDestinoConocida, 'destino');

        if (UbicacionActual::query()
            ->where('folio_id', $folio->id)
            ->lockForUpdate()
            ->exists()) {
            throw new ConflictoMovimiento('El folio ya posee una ubicación actual.');
        }

        if (UbicacionActual::query()
            ->where('posicion_id', $posicion->id)
            ->lockForUpdate()
            ->exists()) {
            throw new ConflictoMovimiento('La posición de destino ya se encuentra ocupada.');
        }

        $advertencias = $this->detectorAdvertencias->paraUbicacion(
            $posicion,
            $advertenciasConfirmadas,
        );

        $versionResultante = $camara->version_plano + 1;
        $movimiento = Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'camara_destino_id' => $camara->id,
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'advertencias_confirmadas' => $advertencias !== [] ? $advertencias : null,
            'version_destino_anterior' => $camara->version_plano,
            'version_destino_resultante' => $versionResultante,
            'generado_dispositivo_at' => $generadoDispositivoAt,
            'recibido_servidor_at' => $recibidoServidorAt,
        ]);

        UbicacionActual::create([
            'folio_id' => $folio->id,
            'posicion_id' => $posicion->id,
            'movimiento_id' => $movimiento->id,
            'ubicado_at' => $recibidoServidorAt,
        ]);

        $this->actualizarVersionCamara($camara, $versionResultante);
        $this->actualizarActividadSesiones(collect([$sesion]), $recibidoServidorAt);

        return $movimiento->load('folio', 'ubicacionActual');
    }

    private function procesarMovimiento(
        OperacionSincronizacion $operacion,
        TipoMovimiento $tipo,
        Folio $folio,
        Posicion $posicionDestino,
        SesionEstiba $sesionOrigen,
        SesionEstiba $sesionDestino,
        User $usuario,
        Dispositivo $dispositivo,
        int $versionOrigenConocida,
        int $versionDestinoConocida,
        DateTimeInterface $generadoDispositivoAt,
        DateTimeInterface $recibidoServidorAt,
        array $advertenciasConfirmadas,
    ): Movimiento {
        $ubicacionLeida = UbicacionActual::query()
            ->where('folio_id', $folio->id)
            ->first();

        if (! $ubicacionLeida) {
            throw new DomainException('El folio no posee una ubicación desde la cual moverlo.');
        }

        $posicionOrigenLeida = Posicion::query()->findOrFail($ubicacionLeida->posicion_id);
        $camaras = $this->bloquearModelos(
            Camara::query(),
            [$posicionOrigenLeida->camara_id, $posicionDestino->camara_id],
        );
        $posiciones = $this->bloquearModelos(
            Posicion::query(),
            [$ubicacionLeida->posicion_id, $posicionDestino->id],
        );
        $sesiones = $this->bloquearModelos(
            SesionEstiba::query(),
            [$sesionOrigen->id, $sesionDestino->id],
        );
        $folioBloqueado = Folio::query()->lockForUpdate()->findOrFail($folio->id);
        $ubicacion = UbicacionActual::query()
            ->where('folio_id', $folioBloqueado->id)
            ->lockForUpdate()
            ->first();

        if (! $ubicacion || $ubicacion->posicion_id !== $ubicacionLeida->posicion_id) {
            throw new ConflictoMovimiento(
                'La ubicación del folio cambió mientras se procesaba el movimiento.',
            );
        }

        $posicionOrigen = $posiciones->get($ubicacion->posicion_id);
        $destino = $posiciones->get($posicionDestino->id);

        if (! $posicionOrigen || ! $destino) {
            throw new DomainException('No fue posible bloquear las posiciones del movimiento.');
        }

        if ($posicionOrigen->id === $destino->id) {
            throw new ConflictoMovimiento('El folio ya se encuentra en la posición de destino.');
        }

        $ocupanteDestino = UbicacionActual::query()
            ->where('posicion_id', $destino->id)
            ->lockForUpdate()
            ->first();

        if ($ocupanteDestino) {
            throw new ConflictoMovimiento('La posición de destino ya se encuentra ocupada.');
        }

        $camaraOrigen = $camaras->get($posicionOrigen->camara_id);
        $camaraDestino = $camaras->get($destino->camara_id);
        $sesionOrigenBloqueada = $sesiones->get($sesionOrigen->id);
        $sesionDestinoBloqueada = $sesiones->get($sesionDestino->id);

        if (! $camaraOrigen || ! $camaraDestino
            || ! $sesionOrigenBloqueada || ! $sesionDestinoBloqueada) {
            throw new DomainException('No fue posible bloquear los extremos del movimiento.');
        }

        $this->validarContenidoCamara($camaraOrigen, $folioBloqueado->tipo_bulto);
        $this->validarContenidoCamara($camaraDestino, $folioBloqueado->tipo_bulto);

        $this->validarVersion($camaraOrigen, $versionOrigenConocida, 'origen');
        $this->validarVersion($camaraDestino, $versionDestinoConocida, 'destino');

        $advertencias = $this->detectorAdvertencias->paraMovimiento(
            $posicionOrigen,
            $destino,
            $advertenciasConfirmadas,
        );

        $versionOrigenResultante = $camaraOrigen->version_plano + 1;
        $versionDestinoResultante = $camaraOrigen->id === $camaraDestino->id
            ? $versionOrigenResultante
            : $camaraDestino->version_plano + 1;

        $movimiento = Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folioBloqueado->id,
            'tipo_movimiento' => $tipo,
            'camara_origen_id' => $camaraOrigen->id,
            'posicion_origen_id' => $posicionOrigen->id,
            'sesion_origen_id' => $sesionOrigenBloqueada->id,
            'camara_destino_id' => $camaraDestino->id,
            'posicion_destino_id' => $destino->id,
            'sesion_destino_id' => $sesionDestinoBloqueada->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'advertencias_confirmadas' => $advertencias !== [] ? $advertencias : null,
            'version_origen_anterior' => $camaraOrigen->version_plano,
            'version_origen_resultante' => $versionOrigenResultante,
            'version_destino_anterior' => $camaraDestino->version_plano,
            'version_destino_resultante' => $versionDestinoResultante,
            'generado_dispositivo_at' => $generadoDispositivoAt,
            'recibido_servidor_at' => $recibidoServidorAt,
        ]);

        $ubicacion->update([
            'posicion_id' => $destino->id,
            'movimiento_id' => $movimiento->id,
            'ubicado_at' => $recibidoServidorAt,
        ]);

        $this->actualizarVersionCamara($camaraOrigen, $versionOrigenResultante);

        if ($camaraOrigen->id !== $camaraDestino->id) {
            $this->actualizarVersionCamara($camaraDestino, $versionDestinoResultante);
        }

        $this->actualizarActividadSesiones($sesiones, $recibidoServidorAt);

        return $movimiento;
    }

    /**
     * @param  array<int, string>  $ids
     * @return Collection<string, Camara|Posicion|SesionEstiba>
     */
    private function bloquearModelos($consulta, array $ids): Collection
    {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        return $consulta
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function validarVersion(Camara $camara, int $versionConocida, string $extremo): void
    {
        if ($versionConocida < 0 || $camara->version_plano !== $versionConocida) {
            throw new ConflictoMovimiento(sprintf(
                'La versión conocida de la cámara de %s está desactualizada.',
                $extremo,
            ));
        }
    }

    private function actualizarVersionCamara(Camara $camara, int $version): void
    {
        $camara->version_plano = $version;
        $camara->save();
    }

    /**
     * @param  Collection<int|string, SesionEstiba>  $sesiones
     */
    private function actualizarActividadSesiones(
        Collection $sesiones,
        DateTimeInterface $fecha,
    ): void {
        $sesiones->unique('id')->each(
            fn (SesionEstiba $sesion) => $sesion->update(['ultima_actividad_at' => $fecha]),
        );
    }

    /**
     * @param  array<string, mixed>  $datosFolio
     * @return array<string, mixed>
     */
    private function atributosNuevoFolio(
        string $numeroFolio,
        TipoBulto $tipoBulto,
        DateTimeInterface $generadoDispositivoAt,
        array $datosFolio,
    ): array {
        $atributos = $this->filtrarDatosFolio($datosFolio);
        $atributos['numero_folio'] = $numeroFolio;
        $atributos['tipo_bulto'] = $tipoBulto;
        $atributos['fecha_ingreso'] ??= $generadoDispositivoAt;
        $atributos['origen_sistema'] ??= 'manual';
        $atributos['estado_integracion'] ??= EstadoIntegracionFolio::NoVinculado;

        return $atributos;
    }

    /**
     * @param  array<string, mixed>  $datosFolio
     * @return array<string, mixed>
     */
    private function filtrarDatosFolio(array $datosFolio): array
    {
        return array_intersect_key($datosFolio, array_flip(self::CAMPOS_FOLIO_PERMITIDOS));
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizarDatosMaterial(array $datos): array
    {
        return array_intersect_key($datos, array_flip([
            'item_material_id',
            'cantidad',
            'lote',
            'proveedor',
            'observacion',
        ]));
    }

    private function validarContenidoCamara(Camara $camara, TipoBulto $tipoBulto): void
    {
        $contenidoEsperado = $tipoBulto === TipoBulto::Material
            ? ContenidoCamara::Materiales
            : ContenidoCamara::Productos;

        if ($camara->contenido !== $contenidoEsperado) {
            throw new DomainException($tipoBulto === TipoBulto::Material
                ? 'Los folios de materiales solo pueden ubicarse en cámaras de materiales.'
                : 'Los pallets y saldos solo pueden ubicarse en cámaras de productos.');
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearFichaMaterial(
        Folio $folio,
        array $datos,
        User $usuario,
        Dispositivo $dispositivo,
        DateTimeInterface $fecha,
    ): void {
        $item = ItemMaterial::query()
            ->whereKey($datos['item_material_id'] ?? null)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();
        $cantidad = round((float) ($datos['cantidad'] ?? 0), 3);

        if (! $item) {
            throw new DomainException('El ítem de material no existe o se encuentra inactivo.');
        }

        if ($cantidad <= 0) {
            throw new DomainException('La cantidad inicial del material debe ser mayor que cero.');
        }

        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'cantidad_inicial' => $cantidad,
            'cantidad_actual' => $cantidad,
            'cantidad_reservada' => 0,
            'unidad_medida' => $item->unidad_medida,
            'lote' => $datos['lote'] ?? null,
            'proveedor' => $datos['proveedor'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
        ]);

        MovimientoInventarioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'tipo' => TipoMovimientoInventarioMaterial::Ingreso,
            'cantidad' => $cantidad,
            'cantidad_anterior' => 0,
            'cantidad_resultante' => $cantidad,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'motivo' => 'Ingreso inicial del folio a cámara de materiales.',
            'ocurrido_at' => $fecha,
        ]);
    }

    private function validarNumeroFolio(string $numeroFolio): void
    {
        if ($numeroFolio === '' || mb_strlen($numeroFolio) > 50) {
            throw new DomainException('El número de folio debe contener entre 1 y 50 caracteres.');
        }
    }

    private function registrarErrorOperacion(
        OperacionSincronizacion $operacion,
        EstadoOperacionSincronizacion $estado,
        string $codigo,
        Throwable $exception,
    ): void {
        $operacion->update([
            'estado' => $estado,
            'codigo_error' => $codigo,
            'mensaje_error' => $exception->getMessage(),
            'procesada_at' => now(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function versionesDeMovimiento(Movimiento $movimiento, bool $resultantes): array
    {
        $sufijo = $resultantes ? 'resultante' : 'anterior';
        $versiones = [];

        if ($movimiento->version_origen_anterior !== null) {
            $versiones['origen'] = $movimiento->{"version_origen_{$sufijo}"};
        }

        if ($movimiento->version_destino_anterior !== null) {
            $versiones['destino'] = $movimiento->{"version_destino_{$sufijo}"};
        }

        return $versiones;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function calcularHash(array $payload): string
    {
        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new DomainException('El contenido de la operación no es serializable.', previous: $exception);
        }

        return hash('sha256', $json);
    }

    private function normalizar(mixed $valor): mixed
    {
        if ($valor instanceof BackedEnum) {
            return $valor->value;
        }

        if ($valor instanceof DateTimeInterface) {
            return $valor->format(DATE_ATOM);
        }

        if (! is_array($valor)) {
            return $valor;
        }

        if (array_is_list($valor)) {
            return array_map(fn (mixed $item): mixed => $this->normalizar($item), $valor);
        }

        ksort($valor, SORT_STRING);

        return array_map(fn (mixed $item): mixed => $this->normalizar($item), $valor);
    }
}
