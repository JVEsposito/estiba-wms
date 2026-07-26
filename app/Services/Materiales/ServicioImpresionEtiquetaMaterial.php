<?php

namespace App\Services\Materiales;

use App\Enums\EstadoRecepcionMaterial;
use App\Exceptions\ConflictoOperacion;
use App\Models\FolioMaterial;
use App\Models\FolioTrabajoImpresionMaterial;
use App\Models\OrdenTransformacionMaterial;
use App\Models\PerfilImpresionEtiqueta;
use App\Models\RecepcionMaterial;
use App\Models\SalidaTransformacionMaterial;
use App\Models\TrabajoImpresionMaterial;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ServicioImpresionEtiquetaMaterial
{
    public function __construct(
        private readonly GeneradorEtiquetaMaterialPdf $pdf,
        private readonly GeneradorEtiquetaMaterialZpl $zpl,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array{trabajo: TrabajoImpresionMaterial, contenido: string, mime: string, nombre: string}
     */
    public function generar(
        RecepcionMaterial $recepcion,
        array $datos,
        User $usuario,
        ?string $dispositivoId = null,
    ): array {
        $payload = [
            'origen' => 'recepcion',
            'recepcion_material_id' => $recepcion->id,
            'perfil_id' => $datos['perfil_id'],
            'formato' => $datos['formato'],
            'canal' => $datos['canal'],
            'folio_ids' => collect($datos['folio_ids'])->sort()->values()->all(),
            'copias' => (int) $datos['copias'],
            'motivo_reimpresion' => $datos['motivo_reimpresion'] ?? null,
        ];

        return $this->generarTrabajo(
            $payload,
            $datos,
            $usuario,
            $dispositivoId,
            function (array $folioIds) use ($recepcion): array {
                $recepcion = RecepcionMaterial::query()
                    ->with(['cliente', 'proveedor'])
                    ->lockForUpdate()
                    ->findOrFail($recepcion->id);
                if ($recepcion->estado !== EstadoRecepcionMaterial::Confirmada) {
                    throw new DomainException('Solo se pueden generar etiquetas de una recepción confirmada.');
                }

                $materiales = FolioMaterial::query()
                    ->with([
                        'folio',
                        'item.cliente.cliente',
                        'bultoRecepcion.detalle.recepcion.proveedor',
                    ])
                    ->whereIn('folio_id', $folioIds)
                    ->whereHas('bultoRecepcion.detalle', fn ($consulta) => $consulta
                        ->where('recepcion_material_id', $recepcion->id))
                    ->get()
                    ->keyBy('folio_id');
                if ($materiales->count() !== count($folioIds)) {
                    throw new DomainException(
                        'Uno o más folios no pertenecen a la recepción seleccionada.',
                    );
                }

                return [
                    'recepcion_material_id' => $recepcion->id,
                    'orden_transformacion_material_id' => null,
                    'lote_transformacion_material_id' => null,
                    'etiquetas' => collect($folioIds)
                        ->map(fn (string $folioId): array => $this->etiquetaSnapshot(
                            $materiales->get($folioId),
                        ))
                        ->all(),
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{trabajo: TrabajoImpresionMaterial, contenido: string, mime: string, nombre: string}
     */
    public function generarTransformacion(
        OrdenTransformacionMaterial $orden,
        array $datos,
        User $usuario,
        ?string $dispositivoId = null,
    ): array {
        $payload = [
            'origen' => 'transformacion',
            'orden_transformacion_material_id' => $orden->id,
            'perfil_id' => $datos['perfil_id'],
            'formato' => $datos['formato'],
            'canal' => $datos['canal'],
            'folio_ids' => collect($datos['folio_ids'])->sort()->values()->all(),
            'copias' => (int) $datos['copias'],
            'motivo_reimpresion' => $datos['motivo_reimpresion'] ?? null,
        ];

        return $this->generarTrabajo(
            $payload,
            $datos,
            $usuario,
            $dispositivoId,
            function (array $folioIds) use ($orden): array {
                $orden = OrdenTransformacionMaterial::query()
                    ->with('cliente:id,codigo,nombre')
                    ->lockForUpdate()
                    ->findOrFail($orden->id);
                $salidas = SalidaTransformacionMaterial::query()
                    ->with([
                        'folioMaterial.folio',
                        'folioMaterial.item',
                        'item',
                        'lote',
                    ])
                    ->whereIn('folio_id', $folioIds)
                    ->whereHas('lote', fn ($consulta) => $consulta
                        ->where('orden_transformacion_material_id', $orden->id)
                        ->where('estado', 'cerrado'))
                    ->get()
                    ->keyBy('folio_id');
                if ($salidas->count() !== count($folioIds)) {
                    throw new DomainException(
                        'Uno o más folios no son salidas cerradas de la orden seleccionada.',
                    );
                }
                $lotes = $salidas->pluck('lote_transformacion_material_id')->unique()->values();

                return [
                    'recepcion_material_id' => null,
                    'orden_transformacion_material_id' => $orden->id,
                    'lote_transformacion_material_id' => $lotes->count() === 1
                        ? $lotes->first()
                        : null,
                    'etiquetas' => collect($folioIds)
                        ->map(fn (string $folioId): array => $this->etiquetaTransformacionSnapshot(
                            $salidas->get($folioId),
                            $orden,
                        ))
                        ->all(),
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $datos
     * @param  Closure(array<int, string>): array<string, mixed>  $resolver
     * @return array{trabajo: TrabajoImpresionMaterial, contenido: string, mime: string, nombre: string}
     */
    private function generarTrabajo(
        array $payload,
        array $datos,
        User $usuario,
        ?string $dispositivoId,
        Closure $resolver,
    ): array {
        $payloadHash = $this->hash($payload);

        try {
            $trabajo = DB::transaction(function () use (
                $payload,
                $datos,
                $usuario,
                $dispositivoId,
                $resolver,
                $payloadHash,
            ): TrabajoImpresionMaterial {
                $existente = TrabajoImpresionMaterial::query()
                    ->where('operacion_id', $datos['operacion_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    if ($existente->solicitado_por_user_id !== $usuario->id
                        || ! hash_equals($existente->payload_hash, $payloadHash)) {
                        throw new ConflictoOperacion(
                            'El UUID de impresión ya fue utilizado con datos diferentes.',
                        );
                    }

                    return $existente->load('folios');
                }

                $contexto = $resolver($payload['folio_ids']);
                $perfil = PerfilImpresionEtiqueta::query()
                    ->whereKey($datos['perfil_id'])
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->firstOrFail();
                $reimpresos = FolioTrabajoImpresionMaterial::query()
                    ->whereIn('folio_id', $payload['folio_ids'])
                    ->distinct()
                    ->pluck('folio_id')
                    ->all();
                if ($reimpresos !== [] && blank($datos['motivo_reimpresion'] ?? null)) {
                    throw new DomainException(
                        'Debes indicar el motivo para volver a generar una etiqueta.',
                    );
                }

                $perfilSnapshot = $this->perfilSnapshot($perfil);
                $contenidoSnapshot = $contexto['etiquetas'];
                $contenidoHash = $this->hash([
                    'perfil' => $perfilSnapshot,
                    'etiquetas' => $contenidoSnapshot,
                    'copias' => $payload['copias'],
                ]);
                $trabajo = TrabajoImpresionMaterial::create([
                    'operacion_id' => $datos['operacion_id'],
                    'payload_hash' => $payloadHash,
                    'origen' => $payload['origen'],
                    'recepcion_material_id' => $contexto['recepcion_material_id'],
                    'orden_transformacion_material_id' => $contexto['orden_transformacion_material_id'],
                    'lote_transformacion_material_id' => $contexto['lote_transformacion_material_id'],
                    'perfil_impresion_etiqueta_id' => $perfil->id,
                    'formato' => $datos['formato'],
                    'canal' => $datos['canal'],
                    'estado' => 'generado',
                    'copias' => $payload['copias'],
                    'motivo_reimpresion' => $datos['motivo_reimpresion'] ?? null,
                    'perfil_snapshot' => $perfilSnapshot,
                    'contenido_snapshot' => $contenidoSnapshot,
                    'contenido_hash' => $contenidoHash,
                    'solicitado_por_user_id' => $usuario->id,
                    'dispositivo_id' => $dispositivoId,
                    'solicitado_at' => now(),
                ]);

                foreach ($contenidoSnapshot as $etiqueta) {
                    FolioTrabajoImpresionMaterial::create([
                        'trabajo_impresion_material_id' => $trabajo->id,
                        'folio_id' => $etiqueta['folio_id'],
                        'numero_folio_snapshot' => $etiqueta['numero_folio'],
                        'es_reimpresion' => in_array($etiqueta['folio_id'], $reimpresos, true),
                    ]);
                }

                return $trabajo->load('folios');
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $trabajo = TrabajoImpresionMaterial::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->first();
            if (! $trabajo
                || $trabajo->solicitado_por_user_id !== $usuario->id
                || ! hash_equals($trabajo->payload_hash, $payloadHash)) {
                throw new ConflictoOperacion(
                    'La generación de etiquetas entró en conflicto con otra operación.',
                    previous: $exception,
                );
            }
        }

        $contenido = $this->renderizar($trabajo);
        $extension = $trabajo->formato === 'pdf' ? 'pdf' : 'zpl';
        $referencia = $trabajo->origen === 'transformacion'
            ? 'transformacion-'.mb_substr((string) $trabajo->orden_transformacion_material_id, 0, 8)
            : $this->referenciaRecepcion($trabajo);

        return [
            'trabajo' => $trabajo,
            'contenido' => $contenido,
            'mime' => $trabajo->formato === 'pdf'
                ? 'application/pdf'
                : 'application/zpl',
            'nombre' => "etiquetas-{$referencia}.{$extension}",
        ];
    }

    public function renderizar(TrabajoImpresionMaterial $trabajo): string
    {
        return $trabajo->formato === 'pdf'
            ? $this->pdf->generar(
                $trabajo->contenido_snapshot,
                $trabajo->perfil_snapshot,
                $trabajo->copias,
            )
            : $this->zpl->generar(
                $trabajo->contenido_snapshot,
                $trabajo->perfil_snapshot,
                $trabajo->copias,
            );
    }

    /** @return array<string, mixed> */
    private function perfilSnapshot(PerfilImpresionEtiqueta $perfil): array
    {
        return [
            'id' => $perfil->id,
            'codigo' => $perfil->codigo,
            'nombre' => $perfil->nombre,
            'fabricante' => $perfil->fabricante,
            'modelo' => $perfil->modelo,
            'lenguaje' => $perfil->lenguaje,
            'dpi' => $perfil->dpi,
            'ancho_mm' => (float) $perfil->ancho_mm,
            'alto_mm' => (float) $perfil->alto_mm,
            'orientacion' => $perfil->orientacion,
        ];
    }

    /** @return array<string, mixed> */
    private function etiquetaSnapshot(FolioMaterial $material): array
    {
        $bulto = $material->bultoRecepcion;
        $recepcion = $bulto?->detalle?->recepcion;
        $cliente = $material->item?->cliente?->cliente;
        $proveedor = $recepcion?->proveedor;

        return [
            'origen' => 'recepcion',
            'folio_id' => $material->folio_id,
            'numero_folio' => $material->folio?->numero_folio,
            'cliente_codigo' => $cliente?->codigo,
            'cliente_nombre' => $cliente?->nombre,
            'item_codigo' => $material->item?->codigo,
            'item_nombre' => $material->item?->nombre,
            'cantidad' => number_format((float) $bulto?->cantidad, 3, ',', '.'),
            'unidad_medida' => $material->unidad_medida,
            'numero_guia' => $recepcion?->numero_guia_despacho,
            'proveedor_codigo' => $proveedor?->codigo,
            'proveedor_nombre' => $proveedor?->nombre,
            'lote_proveedor' => $bulto?->lote_proveedor,
            'fecha_fabricacion' => $bulto?->fecha_fabricacion?->toDateString(),
            'fecha_vencimiento' => $bulto?->fecha_vencimiento?->toDateString(),
            'bloqueado' => (bool) $bulto?->bloqueado,
            'motivo_bloqueo' => $bulto?->motivo_bloqueo,
            'orden_transformacion' => null,
            'numero_lote_transformacion' => null,
            'fecha_transformacion' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function etiquetaTransformacionSnapshot(
        SalidaTransformacionMaterial $salida,
        OrdenTransformacionMaterial $orden,
    ): array {
        $material = $salida->folioMaterial;
        $lote = $salida->lote;

        return [
            'origen' => 'transformacion',
            'folio_id' => $salida->folio_id,
            'numero_folio' => $material?->folio?->numero_folio,
            'cliente_codigo' => $orden->cliente?->codigo,
            'cliente_nombre' => $orden->cliente?->nombre,
            'item_codigo' => $salida->item?->codigo,
            'item_nombre' => $salida->item?->nombre,
            'cantidad' => number_format((float) $salida->cantidad_producida, 3, ',', '.'),
            'unidad_medida' => $salida->item?->unidad_medida ?? $material?->unidad_medida,
            'numero_guia' => null,
            'proveedor_codigo' => null,
            'proveedor_nombre' => null,
            'lote_proveedor' => $material?->lote,
            'fecha_fabricacion' => null,
            'fecha_vencimiento' => null,
            'bloqueado' => false,
            'motivo_bloqueo' => null,
            'orden_transformacion' => 'OT-'.mb_strtoupper(mb_substr($orden->id, 0, 8)),
            'numero_lote_transformacion' => $lote?->numero_lote,
            'fecha_transformacion' => $lote?->cerrado_at?->toAtomString(),
        ];
    }

    private function referenciaRecepcion(TrabajoImpresionMaterial $trabajo): string
    {
        $recepcion = RecepcionMaterial::query()->find($trabajo->recepcion_material_id);
        $guia = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            (string) $recepcion?->numero_guia_despacho,
        );

        return $guia ?: mb_substr((string) $trabajo->recepcion_material_id, 0, 8);
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }
}
