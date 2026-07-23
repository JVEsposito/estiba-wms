<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('guias_despacho_envases')
            ->orderBy('id')
            ->get()
            ->each(function (object $guia): void {
                $temporada = DB::table('temporadas')->where('id', $guia->temporada_id)->first();
                $cliente = DB::table('clientes')->where('id', $guia->cliente_id)->first();

                $actualizacion = [
                    'temporada_codigo_snapshot' => $guia->temporada_codigo_snapshot
                        ?: $temporada?->codigo,
                    'temporada_nombre_snapshot' => $guia->temporada_nombre_snapshot
                        ?: $temporada?->nombre,
                    'cliente_codigo_snapshot' => $guia->cliente_codigo_snapshot
                        ?: $cliente?->codigo,
                    'cliente_nombre_snapshot' => $guia->cliente_nombre_snapshot
                        ?: $cliente?->nombre,
                ];

                if (in_array($guia->estado, ['confirmada', 'anulada'], true)
                    && $guia->documento_snapshot === null) {
                    $snapshot = $this->reconstruirDocumento($guia, $actualizacion);
                    $actualizacion['documento_snapshot'] = json_encode(
                        $snapshot,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    );
                    $actualizacion['documento_hash'] = hash(
                        'sha256',
                        json_encode(
                            $snapshot,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                        ),
                    );
                    $actualizacion['documento_generado_at'] = $guia->confirmado_at
                        ?: $guia->updated_at;
                }

                DB::table('guias_despacho_envases')
                    ->where('id', $guia->id)
                    ->update($actualizacion);

                $this->reconstruirEventos($guia);
            });
    }

    public function down(): void
    {
        // La reconstrucción documental y de auditoría es deliberadamente irreversible.
    }

    /**
     * @param  array<string, mixed>  $snapshots
     * @return array<string, mixed>
     */
    private function reconstruirDocumento(object $guia, array $snapshots): array
    {
        $detalles = DB::table('detalles_guias_despacho_envases')
            ->where('guia_despacho_envase_id', $guia->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (object $detalle): array => [
                'tipo_envase' => $detalle->tipo_envase,
                'cantidad' => (int) $detalle->cantidad,
                'propiedad' => $detalle->propiedad,
                'movimiento_origen_id' => $detalle->movimiento_origen_id,
                'origen' => $detalle->origen_snapshot,
            ])
            ->values()
            ->all();
        $creadoPor = DB::table('users')->where('id', $guia->creado_por_user_id)->value('name');
        $confirmadoPor = $guia->confirmado_por_user_id
            ? DB::table('users')->where('id', $guia->confirmado_por_user_id)->value('name')
            : null;

        return [
            'numero' => $guia->numero,
            'estado' => 'confirmada',
            'historico_reconstruido' => true,
            'temporada' => [
                'id' => $guia->temporada_id,
                'codigo' => $snapshots['temporada_codigo_snapshot'],
                'nombre' => $snapshots['temporada_nombre_snapshot'],
            ],
            'cliente' => [
                'id' => $guia->cliente_id,
                'codigo' => $snapshots['cliente_codigo_snapshot'],
                'nombre' => $snapshots['cliente_nombre_snapshot'],
            ],
            'salida_at' => $this->fechaIso($guia->salida_at),
            'confirmado_at' => $this->fechaIso($guia->confirmado_at),
            'patente_camion' => $guia->patente_camion,
            'conductor' => [
                'rut' => $guia->rut_conductor,
                'nombre' => $guia->nombre_conductor,
            ],
            'observacion' => $guia->observacion,
            'detalles' => $detalles,
            'creado_por' => $creadoPor,
            'confirmado_por' => $confirmadoPor,
        ];
    }

    private function reconstruirEventos(object $guia): void
    {
        $this->registrarEventoSiFalta(
            $guia,
            'creada',
            null,
            'borrador',
            $guia->creado_por_user_id,
            $guia->created_at,
        );

        if (in_array($guia->estado, ['confirmada', 'anulada'], true)
            && $guia->confirmado_por_user_id
            && $guia->confirmado_at) {
            $this->registrarEventoSiFalta(
                $guia,
                'confirmada',
                'borrador',
                'confirmada',
                $guia->confirmado_por_user_id,
                $guia->confirmado_at,
            );
        }

        if ($guia->estado === 'anulada'
            && $guia->anulado_por_user_id
            && $guia->anulado_at) {
            $this->registrarEventoSiFalta(
                $guia,
                'anulada',
                'confirmada',
                'anulada',
                $guia->anulado_por_user_id,
                $guia->anulado_at,
                ['motivo' => $guia->motivo_anulacion],
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $datos
     */
    private function registrarEventoSiFalta(
        object $guia,
        string $tipo,
        ?string $anterior,
        string $nuevo,
        int $usuarioId,
        mixed $ocurridoAt,
        ?array $datos = null,
    ): void {
        $existe = DB::table('eventos_guias_despacho_envases')
            ->where('guia_despacho_envase_id', $guia->id)
            ->where('tipo', $tipo)
            ->exists();
        if ($existe) {
            return;
        }

        DB::table('eventos_guias_despacho_envases')->insert([
            'id' => (string) Str::uuid(),
            'guia_despacho_envase_id' => $guia->id,
            'tipo' => $tipo,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'user_id' => $usuarioId,
            'ocurrido_at' => $ocurridoAt,
            'datos' => json_encode([
                'historico_reconstruido' => true,
                ...($datos ?? []),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fechaIso(mixed $fecha): ?string
    {
        return $fecha ? CarbonImmutable::parse($fecha)->toAtomString() : null;
    }
};
