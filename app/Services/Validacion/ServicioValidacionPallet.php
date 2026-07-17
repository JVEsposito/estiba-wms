<?php

namespace App\Services\Validacion;

use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Enums\TipoBulto;
use App\Exceptions\ConflictoOperacion;
use App\Exceptions\OperacionNoAutorizada;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\User;
use App\Models\ValidacionPallet;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class ServicioValidacionPallet
{
    public function __construct(
        private readonly AlcanceOperacionalUsuario $alcance,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array{ValidacionPallet, bool, bool}
     */
    public function registrar(array $datos, User $usuario, Dispositivo $dispositivo): array
    {
        $payload = $this->normalizarPayload($datos);
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $payload, $payloadHash, $usuario, $dispositivo): array {
            $existente = ValidacionPallet::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->user_id !== $usuario->id
                    || $existente->dispositivo_id !== $dispositivo->id
                    || ! hash_equals($existente->payload_hash, $payloadHash)) {
                    throw new ConflictoOperacion('El UUID de la validación ya fue utilizado con datos diferentes.');
                }

                return [$this->cargar($existente), false, $existente->estado === EstadoValidacionPallet::Conflicto];
            }

            $resultado = ResultadoValidacionPallet::from($datos['resultado']);
            if ($resultado === ResultadoValidacionPallet::Rechazado
                && ! $this->alcance->puedeRechazarPallets($usuario)) {
                throw new OperacionNoAutorizada(
                    'El rechazo definitivo requiere supervisor de frío o administrador.',
                );
            }

            $temporada = DB::table('temporadas')
                ->where('id', $datos['temporada_id'])
                ->lockForUpdate()
                ->first();
            if (! $temporada || ! $temporada->activa) {
                throw new DomainException('La temporada no existe o no se encuentra activa.');
            }

            $articulo = DB::table('articulos_validacion')
                ->where('id', $datos['articulo_validacion_id'])
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->first();
            $origen = DB::table('origenes_validacion')
                ->where('id', $datos['origen_validacion_id'])
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->first();

            if (! $articulo || ! $origen) {
                throw new DomainException('El artículo o el origen no pertenecen al catálogo activo de la temporada.');
            }

            $combinacion = DB::table('combinaciones_validacion')
                ->where('temporada_id', $temporada->id)
                ->where('articulo_validacion_id', $articulo->id)
                ->where('origen_validacion_id', $origen->id)
                ->where('activo', true)
                ->first();

            if (! $combinacion) {
                throw new DomainException('La combinación de artículo y origen no se encuentra habilitada.');
            }

            $numeroFolio = $payload['numero_folio'];
            DB::table('secuencias_validacion_folio')->insertOrIgnore([
                'numero_folio' => $numeroFolio,
                'ultimo_intento' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $secuencia = DB::table('secuencias_validacion_folio')
                ->where('numero_folio', $numeroFolio)
                ->lockForUpdate()
                ->first();
            $numeroIntento = ((int) $secuencia->ultimo_intento) + 1;
            DB::table('secuencias_validacion_folio')
                ->where('numero_folio', $numeroFolio)
                ->update(['ultimo_intento' => $numeroIntento, 'updated_at' => now()]);

            $folioExistente = Folio::query()
                ->where('numero_folio', $numeroFolio)
                ->lockForUpdate()
                ->first();
            $decisionFinalPrevia = ValidacionPallet::query()
                ->where('numero_folio', $numeroFolio)
                ->whereIn('resultado', [
                    ResultadoValidacionPallet::Aprobado->value,
                    ResultadoValidacionPallet::Rechazado->value,
                ])
                ->where('estado', EstadoValidacionPallet::Aceptada->value)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();
            $hayConflicto = $folioExistente !== null || $decisionFinalPrevia !== null;

            $snapshot = [
                'temporada' => ['codigo' => $temporada->codigo, 'nombre' => $temporada->nombre],
                'articulo' => [
                    'especie' => $articulo->especie,
                    'variedad' => $articulo->variedad,
                    'calibre' => $articulo->calibre,
                    'envase' => $articulo->envase,
                ],
                'origen' => [
                    'cliente' => $origen->cliente,
                    'marca' => $origen->marca,
                    'csg' => $origen->csg,
                    'predio' => $origen->predio,
                ],
                'combinacion' => [
                    'id' => $combinacion->id,
                    'codigo_externo' => $combinacion->codigo_externo,
                ],
                'payload' => $payload,
            ];

            $validacion = ValidacionPallet::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $payloadHash,
                'numero_folio' => $numeroFolio,
                'numero_intento' => $numeroIntento,
                'tipo_bulto' => $datos['tipo_bulto'],
                'cantidad_cajas' => $datos['cantidad_cajas'],
                'temporada_id' => $temporada->id,
                'articulo_validacion_id' => $articulo->id,
                'origen_validacion_id' => $origen->id,
                'resultado' => $resultado,
                'estado' => $hayConflicto ? EstadoValidacionPallet::Conflicto : EstadoValidacionPallet::Aceptada,
                'motivo' => $datos['motivo'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'catalogo_version_dispositivo' => $datos['catalogo_version'],
                'catalogo_version_servidor' => $temporada->version_catalogo,
                'snapshot' => $snapshot,
                'user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo->id,
                'validacion_conflicto_id' => $decisionFinalPrevia?->id,
                'generado_dispositivo_at' => CarbonImmutable::parse($datos['generado_dispositivo_at']),
                'recibido_servidor_at' => now(),
            ]);

            if ($resultado === ResultadoValidacionPallet::Aprobado && ! $hayConflicto) {
                $folio = Folio::create([
                    'numero_folio' => $numeroFolio,
                    'tipo_bulto' => TipoBulto::from($datos['tipo_bulto']),
                    'estado_operacional' => EstadoOperacionalFolio::PendientePrefrio,
                    'fecha_ingreso' => now(),
                    'activo' => true,
                    'variedad' => $articulo->variedad,
                    'calibre' => $articulo->calibre,
                    'marca' => $origen->marca,
                    'exportadora' => $origen->cliente,
                    'origen_sistema' => 'validacion',
                    'identificador_externo' => $datos['operacion_id'],
                    'estado_integracion' => EstadoIntegracionFolio::NoVinculado,
                    'datos_externos' => [
                        'especie' => $articulo->especie,
                        'envase' => $articulo->envase,
                        'csg' => $origen->csg,
                        'predio' => $origen->predio,
                        'cantidad_cajas' => $datos['cantidad_cajas'],
                        'validacion_id' => $validacion->id,
                        'combinacion_validacion_id' => $combinacion->id,
                    ],
                ]);
                $validacion->update(['folio_id' => $folio->id]);
            }

            return [$this->cargar($validacion->refresh()), true, $hayConflicto];
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function normalizarPayload(array $datos): array
    {
        return [
            'numero_folio' => mb_strtoupper(trim((string) $datos['numero_folio'])),
            'tipo_bulto' => $datos['tipo_bulto'],
            'cantidad_cajas' => (int) $datos['cantidad_cajas'],
            'temporada_id' => $datos['temporada_id'],
            'catalogo_version' => (int) $datos['catalogo_version'],
            'articulo_validacion_id' => $datos['articulo_validacion_id'],
            'origen_validacion_id' => $datos['origen_validacion_id'],
            'resultado' => $datos['resultado'],
            'motivo' => $datos['motivo'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
            'generado_dispositivo_at' => CarbonImmutable::parse($datos['generado_dispositivo_at'])->toAtomString(),
        ];
    }

    private function cargar(ValidacionPallet $validacion): ValidacionPallet
    {
        return $validacion->load([
            'folio:id,numero_folio,estado_operacional',
            'usuario:id,name',
            'dispositivo:id,codigo,nombre',
            'conflictoCon:id,numero_folio,numero_intento,resultado',
        ]);
    }
}
