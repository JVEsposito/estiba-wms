<?php

namespace App\Services\Validacion;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Exceptions\ConflictoOperacion;
use App\Models\CorreccionValidacionPallet;
use App\Models\Folio;
use App\Models\User;
use App\Models\ValidacionPallet;
use DomainException;
use Illuminate\Support\Facades\DB;

class ServicioCorreccionValidacionPallet
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function corregir(
        ValidacionPallet $validacion,
        array $datos,
        User $usuario,
    ): ValidacionPallet {
        $payload = $this->normalizarPayload($datos);
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $validacion,
            $datos,
            $payload,
            $payloadHash,
            $usuario,
        ): ValidacionPallet {
            $correccionExistente = CorreccionValidacionPallet::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();

            if ($correccionExistente) {
                if ($correccionExistente->validacion_pallet_id !== $validacion->id
                    || $correccionExistente->corregido_por_user_id !== $usuario->id
                    || ! hash_equals($correccionExistente->payload_hash, $payloadHash)) {
                    throw new ConflictoOperacion(
                        'El UUID de la corrección ya fue utilizado con datos diferentes.',
                    );
                }

                return $this->cargar(
                    ValidacionPallet::query()->findOrFail($validacion->id),
                );
            }

            $validacionBloqueada = ValidacionPallet::query()
                ->whereKey($validacion->id)
                ->lockForUpdate()
                ->firstOrFail();
            $folio = $validacionBloqueada->folio_id
                ? Folio::query()
                    ->whereKey($validacionBloqueada->folio_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $this->asegurarCorregible($validacionBloqueada, $folio);

            $temporada = DB::table('temporadas')
                ->where('id', $validacionBloqueada->temporada_id)
                ->lockForUpdate()
                ->first();
            $articulo = DB::table('articulos_validacion')
                ->where('id', $datos['articulo_validacion_id'])
                ->where('temporada_id', $validacionBloqueada->temporada_id)
                ->where('activo', true)
                ->first();
            $origen = DB::table('origenes_validacion')
                ->where('id', $datos['origen_validacion_id'])
                ->where('temporada_id', $validacionBloqueada->temporada_id)
                ->where('activo', true)
                ->first();
            $categoria = DB::table('categorias_validacion')
                ->where('id', $datos['categoria_validacion_id'])
                ->where('temporada_id', $validacionBloqueada->temporada_id)
                ->where('activo', true)
                ->first();

            if (! $temporada || ! $articulo || ! $origen || ! $categoria) {
                throw new DomainException(
                    'El artículo, el origen o la categoría no pertenecen al catálogo activo de la temporada.',
                );
            }

            $combinacion = DB::table('combinaciones_validacion')
                ->where('temporada_id', $validacionBloqueada->temporada_id)
                ->where('articulo_validacion_id', $articulo->id)
                ->where('origen_validacion_id', $origen->id)
                ->where('activo', true)
                ->first();

            if (! $combinacion) {
                throw new DomainException(
                    'La combinación de artículo y origen no se encuentra habilitada.',
                );
            }

            $anteriores = $this->datosAuditables($validacionBloqueada, $folio);
            $snapshot = $validacionBloqueada->snapshot ?? [];
            $snapshot['articulo'] = [
                'especie' => $articulo->especie,
                'variedad' => $articulo->variedad,
                'calibre' => $articulo->calibre,
                'envase' => $articulo->envase,
            ];
            $snapshot['origen'] = [
                'cliente' => $origen->cliente,
                'marca' => $origen->marca,
                'csg' => $origen->csg,
                'predio' => $origen->predio,
            ];
            $snapshot['categoria'] = [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'codigo_externo' => $categoria->codigo_externo,
            ];
            $snapshot['jornada'] = [
                'linea_proceso' => $payload['linea_proceso'],
                'turno' => $payload['turno'],
            ];
            $snapshot['combinacion'] = [
                'id' => $combinacion->id,
                'codigo_externo' => $combinacion->codigo_externo,
            ];
            $snapshot['ultima_correccion'] = [
                'operacion_id' => $datos['operacion_id'],
                'motivo' => $payload['motivo_correccion'],
                'corregido_por_user_id' => $usuario->id,
                'corregido_at' => now()->toAtomString(),
            ];

            $validacionBloqueada->forceFill([
                'tipo_bulto' => $payload['tipo_bulto'],
                'cantidad_cajas' => $payload['cantidad_cajas'],
                'linea_proceso' => $payload['linea_proceso'],
                'turno' => $payload['turno'],
                'articulo_validacion_id' => $articulo->id,
                'origen_validacion_id' => $origen->id,
                'categoria_validacion_id' => $categoria->id,
                'catalogo_version_servidor' => (int) $temporada->version_catalogo,
                'snapshot' => $snapshot,
            ])->save();

            $datosExternos = $folio->datos_externos ?? [];
            $folio->forceFill([
                'tipo_bulto' => $payload['tipo_bulto'],
                'variedad' => $articulo->variedad,
                'calibre' => $articulo->calibre,
                'marca' => $origen->marca,
                'exportadora' => $origen->cliente,
                'datos_externos' => [
                    ...$datosExternos,
                    'especie' => $articulo->especie,
                    'categoria' => $categoria->nombre,
                    'envase' => $articulo->envase,
                    'csg' => $origen->csg,
                    'predio' => $origen->predio,
                    'cantidad_cajas' => $payload['cantidad_cajas'],
                    'combinacion_validacion_id' => $combinacion->id,
                ],
            ])->save();

            $nuevos = $this->datosAuditables(
                $validacionBloqueada->refresh(),
                $folio->refresh(),
            );

            CorreccionValidacionPallet::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $payloadHash,
                'validacion_pallet_id' => $validacionBloqueada->id,
                'folio_id' => $folio->id,
                'corregido_por_user_id' => $usuario->id,
                'datos_anteriores' => $anteriores,
                'datos_nuevos' => $nuevos,
                'motivo' => $payload['motivo_correccion'],
                'corregido_at' => now(),
            ]);

            return $this->cargar($validacionBloqueada->refresh());
        }, attempts: 3);
    }

    private function asegurarCorregible(
        ValidacionPallet $validacion,
        ?Folio $folio,
    ): void {
        if ($validacion->estado !== EstadoValidacionPallet::Aceptada
            || $validacion->resultado !== ResultadoValidacionPallet::Aprobado
            || ! $folio
            || ! $folio->activo
            || $folio->estado_operacional !== EstadoOperacionalFolio::PendientePrefrio
            || $folio->condicion_termica !== CondicionTermicaFolio::PendientePrefrio) {
            throw new ConflictoOperacion(
                'La validación solo puede corregirse mientras su folio siga pendiente de Prefrío.',
            );
        }

        $tieneFlujoPosterior = DB::table('procesos_prefrio_folios')
            ->where('folio_id', $folio->id)
            ->exists()
            || DB::table('ubicaciones_actuales')
                ->where('folio_id', $folio->id)
                ->exists()
            || DB::table('movimientos')
                ->where('folio_id', $folio->id)
                ->exists()
            || DB::table('carga_folios')
                ->where('folio_id', $folio->id)
                ->exists()
            || DB::table('reservas_carga_folio')
                ->where('folio_id', $folio->id)
                ->exists();

        if ($tieneFlujoPosterior) {
            throw new ConflictoOperacion(
                'El folio ya posee actividad posterior y no admite correcciones administrativas.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $datos): array
    {
        return [
            'tipo_bulto' => $datos['tipo_bulto'],
            'cantidad_cajas' => (int) $datos['cantidad_cajas'],
            'linea_proceso' => (int) $datos['linea_proceso'],
            'turno' => $datos['turno'],
            'articulo_validacion_id' => $datos['articulo_validacion_id'],
            'origen_validacion_id' => $datos['origen_validacion_id'],
            'categoria_validacion_id' => $datos['categoria_validacion_id'],
            'motivo_correccion' => $datos['motivo_correccion'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosAuditables(
        ValidacionPallet $validacion,
        Folio $folio,
    ): array {
        return [
            'validacion' => [
                'tipo_bulto' => $validacion->tipo_bulto,
                'cantidad_cajas' => $validacion->cantidad_cajas,
                'linea_proceso' => $validacion->linea_proceso,
                'turno' => $validacion->turno,
                'articulo_validacion_id' => $validacion->articulo_validacion_id,
                'origen_validacion_id' => $validacion->origen_validacion_id,
                'categoria_validacion_id' => $validacion->categoria_validacion_id,
                'catalogo_version_servidor' => $validacion->catalogo_version_servidor,
                'snapshot' => $validacion->snapshot,
            ],
            'folio' => [
                'tipo_bulto' => $folio->tipo_bulto->value,
                'variedad' => $folio->variedad,
                'calibre' => $folio->calibre,
                'marca' => $folio->marca,
                'exportadora' => $folio->exportadora,
                'datos_externos' => $folio->datos_externos,
            ],
        ];
    }

    private function cargar(ValidacionPallet $validacion): ValidacionPallet
    {
        return $validacion->load([
            'temporada:id,codigo,nombre,activa',
            'folio:id,numero_folio,estado_operacional,condicion_termica',
            'usuario:id,name',
            'dispositivo:id,codigo,nombre',
            'conflictoCon:id,numero_folio,numero_intento,resultado',
            'correcciones.corregidoPor:id,name',
        ]);
    }
}
