from pathlib import Path


def write(path: str, content: str) -> None:
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content)


def replace(path: str, old: str, new: str) -> None:
    target = Path(path)
    text = target.read_text()
    if old not in text:
        raise RuntimeError(f'No se encontró patrón en {path}: {old[:80]}')
    target.write_text(text.replace(old, new, 1))


write('database/migrations/2026_08_06_124500_crear_modulo_repaletizajes.php', r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repaletizajes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->string('codigo', 24)->unique();
            $table->string('tipo_resultado', 20);
            $table->string('estrategia_folio', 20);
            $table->foreignUuid('folio_resultante_id')->constrained('folios');
            $table->foreignUuid('folio_conservado_id')->nullable()->constrained('folios');
            $table->unsignedInteger('cantidad_objetivo')->nullable();
            $table->unsignedInteger('cantidad_resultante');
            $table->string('condicion_termica', 40);
            $table->boolean('mix_variedad')->default(false);
            $table->boolean('mix_calibre')->default(false);
            $table->boolean('mix_envase')->default(false);
            $table->boolean('mix_categoria')->default(false);
            $table->boolean('mix_csg')->default(false);
            $table->boolean('mix_predio')->default(false);
            $table->boolean('mix_cuartel')->default(false);
            $table->string('estado', 20)->default('confirmado');
            $table->json('snapshot');
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos');
            $table->timestamp('confirmado_at');
            $table->uuid('operacion_anulacion_id')->nullable()->unique();
            $table->foreignId('anulado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->index(['estado', 'confirmado_at']);
            $table->index(['folio_resultante_id', 'estado']);
        });

        Schema::create('repaletizaje_detalles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repaletizaje_id')->constrained('repaletizajes')->cascadeOnDelete();
            $table->foreignUuid('folio_origen_id')->constrained('folios');
            $table->unsignedSmallInteger('orden');
            $table->boolean('es_folio_conservado')->default(false);
            $table->unsignedInteger('cajas_antes');
            $table->unsignedInteger('cajas_aportadas');
            $table->unsignedInteger('cajas_despues');
            $table->string('tipo_bulto_antes', 20);
            $table->string('tipo_bulto_despues', 20)->nullable();
            $table->string('estado_antes', 40);
            $table->string('estado_despues', 40);
            $table->json('snapshot_antes');
            $table->json('snapshot_despues');
            $table->timestamps();
            $table->unique(['repaletizaje_id', 'folio_origen_id']);
            $table->unique(['repaletizaje_id', 'orden']);
        });

        Schema::create('secuencias_repaletizajes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repaletizaje_detalles');
        Schema::dropIfExists('repaletizajes');
        Schema::dropIfExists('secuencias_repaletizajes');
    }
};
''')

write('app/Models/Repaletizaje.php', r'''<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'codigo',
    'tipo_resultado',
    'estrategia_folio',
    'folio_resultante_id',
    'folio_conservado_id',
    'cantidad_objetivo',
    'cantidad_resultante',
    'condicion_termica',
    'mix_variedad',
    'mix_calibre',
    'mix_envase',
    'mix_categoria',
    'mix_csg',
    'mix_predio',
    'mix_cuartel',
    'estado',
    'snapshot',
    'observacion',
    'user_id',
    'dispositivo_id',
    'confirmado_at',
    'operacion_anulacion_id',
    'anulado_por_user_id',
    'anulado_at',
    'motivo_anulacion',
])]
class Repaletizaje extends Model
{
    use HasUuids;

    public function folioResultante(): BelongsTo
    {
        return $this->belongsTo(Folio::class, 'folio_resultante_id');
    }

    public function folioConservado(): BelongsTo
    {
        return $this->belongsTo(Folio::class, 'folio_conservado_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RepaletizajeDetalle::class)->orderBy('orden');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad_objetivo' => 'integer',
            'cantidad_resultante' => 'integer',
            'mix_variedad' => 'boolean',
            'mix_calibre' => 'boolean',
            'mix_envase' => 'boolean',
            'mix_categoria' => 'boolean',
            'mix_csg' => 'boolean',
            'mix_predio' => 'boolean',
            'mix_cuartel' => 'boolean',
            'snapshot' => 'array',
            'confirmado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
''')

write('app/Models/RepaletizajeDetalle.php', r'''<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repaletizaje_id',
    'folio_origen_id',
    'orden',
    'es_folio_conservado',
    'cajas_antes',
    'cajas_aportadas',
    'cajas_despues',
    'tipo_bulto_antes',
    'tipo_bulto_despues',
    'estado_antes',
    'estado_despues',
    'snapshot_antes',
    'snapshot_despues',
])]
class RepaletizajeDetalle extends Model
{
    use HasUuids;

    public function repaletizaje(): BelongsTo
    {
        return $this->belongsTo(Repaletizaje::class);
    }

    public function folioOrigen(): BelongsTo
    {
        return $this->belongsTo(Folio::class, 'folio_origen_id');
    }

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'es_folio_conservado' => 'boolean',
            'cajas_antes' => 'integer',
            'cajas_aportadas' => 'integer',
            'cajas_despues' => 'integer',
            'snapshot_antes' => 'array',
            'snapshot_despues' => 'array',
        ];
    }
}
''')

write('app/Http/Requests/RegistrarRepaletizajeRequest.php', r'''<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarRepaletizajeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'tipo_resultado' => ['required', Rule::in(['pallet', 'saldo'])],
            'estrategia_folio' => ['required', Rule::in(['conservar', 'nuevo'])],
            'numero_folio_resultante' => ['required', 'string', 'max:80'],
            'folio_conservado_id' => [
                'nullable',
                'uuid',
                'required_if:estrategia_folio,conservar',
                Rule::exists('folios', 'id'),
            ],
            'cantidad_objetivo' => [
                'nullable',
                'integer',
                'min:2',
                'max:100000',
                'required_if:tipo_resultado,pallet',
            ],
            'origenes' => ['required', 'array', 'min:2', 'max:20'],
            'origenes.*.folio_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('folios', 'id'),
            ],
            'origenes.*.cantidad_aportada' => ['required', 'integer', 'min:1', 'max:100000'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
''')

write('app/Http/Requests/AnularRepaletizajeRequest.php', r'''<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularRepaletizajeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
''')

write('app/Http/Resources/RepaletizajeResource.php', r'''<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepaletizajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo_resultado' => $this->tipo_resultado,
            'estrategia_folio' => $this->estrategia_folio,
            'cantidad_objetivo' => $this->cantidad_objetivo,
            'cantidad_resultante' => $this->cantidad_resultante,
            'condicion_termica' => $this->condicion_termica,
            'estado' => $this->estado,
            'mix' => [
                'variedad' => $this->mix_variedad,
                'calibre' => $this->mix_calibre,
                'envase' => $this->mix_envase,
                'categoria' => $this->mix_categoria,
                'csg' => $this->mix_csg,
                'predio' => $this->mix_predio,
                'cuartel' => $this->mix_cuartel,
            ],
            'advertencias' => $this->snapshot['advertencias'] ?? [],
            'folio_resultante' => $this->whenLoaded('folioResultante', fn (): array => [
                'id' => $this->folioResultante->id,
                'numero_folio' => $this->folioResultante->numero_folio,
                'tipo_bulto' => $this->folioResultante->tipo_bulto?->value,
                'cantidad_cajas' => (int) ($this->folioResultante->datos_externos['cantidad_cajas'] ?? 0),
                'estado_operacional' => $this->folioResultante->estado_operacional?->value,
                'condicion_termica' => $this->folioResultante->condicion_termica?->value,
                'cliente' => $this->folioResultante->exportadora,
                'especie' => $this->folioResultante->datos_externos['especie'] ?? null,
                'marca' => $this->folioResultante->marca,
                'variedad' => $this->folioResultante->variedad,
                'calibre' => $this->folioResultante->calibre,
                'csg' => $this->folioResultante->datos_externos['csg'] ?? null,
                'predio' => $this->folioResultante->datos_externos['predio'] ?? null,
            ]),
            'origenes' => RepaletizajeDetalleResource::collection($this->whenLoaded('detalles')),
            'operador' => $this->whenLoaded('usuario', fn (): ?array => $this->usuario ? [
                'id' => $this->usuario->id,
                'nombre' => $this->usuario->name,
            ] : null),
            'dispositivo' => $this->whenLoaded('dispositivo', fn (): ?array => $this->dispositivo ? [
                'id' => $this->dispositivo->id,
                'codigo' => $this->dispositivo->codigo,
                'nombre' => $this->dispositivo->nombre,
            ] : null),
            'observacion' => $this->observacion,
            'confirmado_at' => $this->confirmado_at?->toAtomString(),
            'anulado_at' => $this->anulado_at?->toAtomString(),
            'motivo_anulacion' => $this->motivo_anulacion,
            'puede_anular' => $this->estado === 'confirmado',
        ];
    }
}
''')

write('app/Http/Resources/RepaletizajeDetalleResource.php', r'''<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepaletizajeDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orden' => $this->orden,
            'es_folio_conservado' => $this->es_folio_conservado,
            'folio' => $this->whenLoaded('folioOrigen', fn (): array => [
                'id' => $this->folioOrigen->id,
                'numero_folio' => $this->folioOrigen->numero_folio,
            ]),
            'cajas_antes' => $this->cajas_antes,
            'cajas_aportadas' => $this->cajas_aportadas,
            'cajas_despues' => $this->cajas_despues,
            'tipo_bulto_antes' => $this->tipo_bulto_antes,
            'tipo_bulto_despues' => $this->tipo_bulto_despues,
            'estado_antes' => $this->estado_antes,
            'estado_despues' => $this->estado_despues,
            'especificaciones' => $this->snapshot_antes['especificaciones'] ?? [],
        ];
    }
}
''')

write('app/Services/Validacion/ServicioRepaletizaje.php', r'''<?php

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
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicioRepaletizaje
{
    public function __construct(
        private readonly AlcanceOperacionalUsuario $alcance,
    ) {}

    /** @param array<string, mixed> $datos */
    public function registrar(
        array $datos,
        User $usuario,
        ?Dispositivo $dispositivo = null,
    ): Repaletizaje {
        if (! $this->alcance->puedeOperarRepaletizajes($usuario)) {
            abort(403, 'No puedes operar repaletizajes.');
        }

        $payload = $this->normalizar($datos);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $usuario, $dispositivo, $payload, $hash): Repaletizaje {
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

            $ids = collect($payload['origenes'])->pluck('folio_id')->sort()->values();
            $folios = Folio::query()
                ->whereIn('id', $ids)
                ->with('ubicacionActual')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($folios->count() !== $ids->count()) {
                throw new DomainException('Uno de los folios ya no existe.');
            }

            $ordenados = collect($payload['origenes'])->map(function (array $origen) use ($folios): array {
                $folio = $folios->get($origen['folio_id']);
                $this->validarFolioOperable($folio);
                $cantidad = $this->cantidad($folio);
                if ($origen['cantidad_aportada'] > $cantidad) {
                    throw new DomainException(sprintf(
                        'El folio %s solo dispone de %d cajas.',
                        $folio->numero_folio,
                        $cantidad,
                    ));
                }

                return [
                    'folio' => $folio,
                    'cantidad_antes' => $cantidad,
                    'cantidad_aportada' => $origen['cantidad_aportada'],
                    'cantidad_despues' => $cantidad - $origen['cantidad_aportada'],
                ];
            });

            $this->validarCompatibilidadDura($ordenados->pluck('folio'));
            $cantidadResultante = (int) $ordenados->sum('cantidad_aportada');
            $this->validarResultado($payload, $cantidadResultante);

            $conservado = null;
            if ($payload['estrategia_folio'] === 'conservar') {
                $conservado = $folios->get($payload['folio_conservado_id']);
                if (! $conservado) {
                    throw new DomainException('El folio que se desea conservar no participa en la repa.');
                }
                $aporteConservado = $ordenados->first(
                    fn (array $origen): bool => $origen['folio']->id === $conservado->id,
                );
                if ($aporteConservado['cantidad_aportada'] !== $aporteConservado['cantidad_antes']) {
                    throw new DomainException(
                        'El folio conservado debe aportar la totalidad de sus cajas al resultado.',
                    );
                }
                if ($conservado->numero_folio !== $payload['numero_folio_resultante']) {
                    throw new DomainException(
                        'El número resultante debe coincidir con el folio que se conserva.',
                    );
                }
            } elseif (Folio::query()
                ->where('numero_folio', $payload['numero_folio_resultante'])
                ->lockForUpdate()
                ->exists()) {
                throw new ConflictoOperacion('El folio resultante ya existe en el sistema.');
            }

            $especificaciones = $this->especificacionesResultado($ordenados);
            $condicion = $ordenados->first()['folio']->condicion_termica;
            $estadoResultado = $condicion === CondicionTermicaFolio::PendientePrefrio
                ? EstadoOperacionalFolio::PendientePrefrio
                : EstadoOperacionalFolio::Disponible;
            $habilitacion = $condicion === CondicionTermicaFolio::PendientePrefrio
                ? HabilitacionAlmacenamientoFolio::NoHabilitado
                : HabilitacionAlmacenamientoFolio::Habilitado;
            $codigo = $this->siguienteCodigo();
            $snapshotResultado = [
                'especificaciones' => $especificaciones,
                'advertencias' => $this->advertencias($especificaciones),
                'composicion' => $ordenados->map(fn (array $origen): array => [
                    'folio_id' => $origen['folio']->id,
                    'numero_folio' => $origen['folio']->numero_folio,
                    'cajas_aportadas' => $origen['cantidad_aportada'],
                    'especificaciones' => $this->especificaciones($origen['folio']),
                ])->values()->all(),
            ];

            $folioResultado = $conservado ?? Folio::create([
                'temporada_id' => $ordenados->first()['folio']->temporada_id,
                'numero_folio' => $payload['numero_folio_resultante'],
                'tipo_bulto' => TipoBulto::from($payload['tipo_resultado']),
                'estado_operacional' => $estadoResultado,
                'condicion_termica' => $condicion,
                'habilitacion_almacenamiento' => $habilitacion,
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
                    'cantidad_cajas' => $cantidadResultante,
                    'repaletizaje_codigo' => $codigo,
                    'composicion' => $snapshotResultado['composicion'],
                ],
            ]);

            $repa = Repaletizaje::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $hash,
                'codigo' => $codigo,
                'tipo_resultado' => $payload['tipo_resultado'],
                'estrategia_folio' => $payload['estrategia_folio'],
                'folio_resultante_id' => $folioResultado->id,
                'folio_conservado_id' => $conservado?->id,
                'cantidad_objetivo' => $payload['cantidad_objetivo'],
                'cantidad_resultante' => $cantidadResultante,
                'condicion_termica' => $condicion->value,
                'mix_variedad' => $especificaciones['variedad'] === 'MIX',
                'mix_calibre' => $especificaciones['calibre'] === 'MIX',
                'mix_envase' => $especificaciones['envase'] === 'MIX',
                'mix_categoria' => $especificaciones['categoria'] === 'MIX',
                'mix_csg' => $especificaciones['csg'] === 'MIX',
                'mix_predio' => $especificaciones['predio'] === 'MIX',
                'mix_cuartel' => $especificaciones['cuartel'] === 'MIX',
                'estado' => 'confirmado',
                'snapshot' => $snapshotResultado,
                'observacion' => $payload['observacion'],
                'user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo?->id,
                'confirmado_at' => now(),
            ]);

            $ubicacionTransferida = false;
            foreach ($ordenados as $indice => $origen) {
                /** @var Folio $folio */
                $folio = $origen['folio'];
                $snapshotAntes = $this->snapshotFolio($folio);
                $esConservado = $conservado?->id === $folio->id;

                if ($esConservado) {
                    $this->actualizarResultado(
                        $folio,
                        $payload,
                        $cantidadResultante,
                        $estadoResultado,
                        $habilitacion,
                        $especificaciones,
                        $codigo,
                        $snapshotResultado['composicion'],
                    );
                } elseif ($origen['cantidad_despues'] === 0) {
                    if (! $conservado && ! $ubicacionTransferida && $folio->ubicacionActual) {
                        $folio->ubicacionActual->update(['folio_id' => $folioResultado->id]);
                        $ubicacionTransferida = true;
                    } else {
                        $folio->ubicacionActual?->delete();
                    }
                    $externos = $folio->datos_externos ?? [];
                    $externos['cantidad_cajas'] = 0;
                    $externos['consumido_en_repaletizaje'] = $codigo;
                    $folio->update([
                        'tipo_bulto' => TipoBulto::Saldo,
                        'estado_operacional' => EstadoOperacionalFolio::Agotado,
                        'activo' => false,
                        'datos_externos' => $externos,
                    ]);
                } else {
                    $externos = $folio->datos_externos ?? [];
                    $externos['cantidad_cajas'] = $origen['cantidad_despues'];
                    $externos['ultimo_repaletizaje'] = $codigo;
                    $folio->update([
                        'tipo_bulto' => TipoBulto::Saldo,
                        'datos_externos' => $externos,
                    ]);
                }

                RepaletizajeDetalle::create([
                    'repaletizaje_id' => $repa->id,
                    'folio_origen_id' => $folio->id,
                    'orden' => $indice + 1,
                    'es_folio_conservado' => $esConservado,
                    'cajas_antes' => $origen['cantidad_antes'],
                    'cajas_aportadas' => $origen['cantidad_aportada'],
                    'cajas_despues' => $esConservado ? $cantidadResultante : $origen['cantidad_despues'],
                    'tipo_bulto_antes' => $snapshotAntes['atributos']['tipo_bulto'],
                    'tipo_bulto_despues' => $folio->fresh()->tipo_bulto?->value,
                    'estado_antes' => $snapshotAntes['atributos']['estado_operacional'],
                    'estado_despues' => $folio->fresh()->estado_operacional?->value,
                    'snapshot_antes' => $snapshotAntes,
                    'snapshot_despues' => $this->snapshotFolio($folio->fresh()),
                ]);
            }

            if (! $conservado) {
                $folioResultado->refresh();
                $externos = $folioResultado->datos_externos ?? [];
                $externos['repaletizaje_id'] = $repa->id;
                $folioResultado->update(['datos_externos' => $externos]);
            }

            return $this->cargar($repa->refresh());
        }, attempts: 3);
    }

    public function anular(
        Repaletizaje $repa,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): Repaletizaje {
        if (! $this->alcance->puedeAnularRepaletizajes($usuario)) {
            abort(403, 'No puedes anular repaletizajes.');
        }

        return DB::transaction(function () use ($repa, $operacionId, $motivo, $usuario): Repaletizaje {
            $repa = Repaletizaje::query()
                ->with(['detalles.folioOrigen', 'folioResultante'])
                ->lockForUpdate()
                ->findOrFail($repa->id);
            if ($repa->estado === 'anulado') {
                if ($repa->operacion_anulacion_id !== $operacionId) {
                    throw new ConflictoOperacion('La repa ya fue anulada con otra operación.');
                }

                return $this->cargar($repa);
            }
            if (Repaletizaje::query()
                ->where('operacion_anulacion_id', $operacionId)
                ->whereKeyNot($repa->id)
                ->exists()) {
                throw new ConflictoOperacion('El UUID de anulación ya fue utilizado.');
            }

            $folios = Folio::query()
                ->whereIn('id', $repa->detalles->pluck('folio_origen_id')->push($repa->folio_resultante_id))
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

            $folioResultado = $folios->get($repa->folio_resultante_id);
            $folioResultado?->ubicacionActual?->delete();
            foreach ($repa->detalles as $detalle) {
                $folio = $folios->get($detalle->folio_origen_id);
                $snapshot = $detalle->snapshot_antes;
                $folio->update($snapshot['atributos']);
                $this->restaurarUbicacion($folio, $snapshot['ubicacion'] ?? null);
            }

            if ($repa->estrategia_folio === 'nuevo') {
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

    public function cargar(Repaletizaje $repa): Repaletizaje
    {
        return $repa->load([
            'folioResultante',
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
            'numero_folio_resultante' => mb_strtoupper(trim((string) $datos['numero_folio_resultante'])),
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

    private function validarFolioOperable(Folio $folio): void
    {
        if (! $folio->activo || $folio->tipo_bulto !== TipoBulto::Saldo) {
            throw new DomainException(
                "El folio {$folio->numero_folio} no es un saldo activo.",
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
            'especie' => $folios->map(fn (Folio $folio): mixed => $folio->datos_externos['especie'] ?? null),
            'marca' => $folios->map(fn (Folio $folio): mixed => $folio->marca),
            'estado térmico' => $folios->map(fn (Folio $folio): mixed => $folio->condicion_termica?->value),
        ];
        foreach ($campos as $nombre => $valores) {
            $normalizados = $valores->map(fn (mixed $valor): string => mb_strtoupper(trim((string) $valor)))->unique();
            if ($normalizados->contains('') || $normalizados->count() !== 1) {
                throw new DomainException("No se puede mezclar diferente {$nombre} en una repa.");
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function validarResultado(array $payload, int $cantidad): void
    {
        if ($payload['tipo_resultado'] === 'pallet' && $cantidad !== $payload['cantidad_objetivo']) {
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

    /** @param Collection<int, array<string, mixed>> $origenes */
    private function especificacionesResultado(Collection $origenes): array
    {
        $folios = $origenes->pluck('folio');

        return [
            'cliente' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->exportadora)),
            'especie' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->datos_externos['especie'] ?? null)),
            'marca' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->marca)),
            'variedad' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->variedad)),
            'calibre' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->calibre)),
            'envase' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->datos_externos['envase'] ?? null)),
            'categoria' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->datos_externos['categoria'] ?? null)),
            'csg' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->datos_externos['csg'] ?? null)),
            'predio' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->datos_externos['predio'] ?? null)),
            'cuartel' => $this->valorComun($folios->map(fn (Folio $folio): mixed => $folio->datos_externos['cuartel'] ?? null)),
        ];
    }

    private function valorComun(Collection $valores): ?string
    {
        $limpios = $valores->map(fn (mixed $valor): ?string => filled($valor) ? trim((string) $valor) : null);
        $unicos = $limpios->map(fn (?string $valor): string => mb_strtoupper((string) $valor))->unique();

        return $unicos->count() === 1 ? $limpios->first() : 'MIX';
    }

    /** @param array<string, mixed> $especificaciones */
    private function advertencias(array $especificaciones): array
    {
        return collect($especificaciones)
            ->filter(fn (mixed $valor): bool => $valor === 'MIX')
            ->keys()
            ->map(fn (string $campo): array => [
                'campo' => $campo,
                'mensaje' => 'Se está generando un MIX de '.mb_strtoupper($campo).'.',
            ])
            ->values()
            ->all();
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

    /** @return array<string, mixed> */
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

    /** @param array<string, mixed> $payload */
    private function actualizarResultado(
        Folio $folio,
        array $payload,
        int $cantidad,
        EstadoOperacionalFolio $estado,
        HabilitacionAlmacenamientoFolio $habilitacion,
        array $especificaciones,
        string $codigo,
        array $composicion,
    ): void {
        $externos = $folio->datos_externos ?? [];
        $externos = array_merge($externos, [
            'especie' => $especificaciones['especie'],
            'categoria' => $especificaciones['categoria'],
            'envase' => $especificaciones['envase'],
            'csg' => $especificaciones['csg'],
            'predio' => $especificaciones['predio'],
            'cuartel' => $especificaciones['cuartel'],
            'cantidad_cajas' => $cantidad,
            'repaletizaje_codigo' => $codigo,
            'composicion' => $composicion,
        ]);
        $folio->update([
            'tipo_bulto' => TipoBulto::from($payload['tipo_resultado']),
            'estado_operacional' => $estado,
            'habilitacion_almacenamiento' => $habilitacion,
            'activo' => true,
            'variedad' => $especificaciones['variedad'],
            'calibre' => $especificaciones['calibre'],
            'marca' => $especificaciones['marca'],
            'exportadora' => $especificaciones['cliente'],
            'origen_sistema' => 'repaletizaje',
            'identificador_externo' => $codigo,
            'datos_externos' => $externos,
        ]);
    }

    private function restaurarUbicacion(Folio $folio, ?array $ubicacion): void
    {
        $folio->ubicacionActual?->delete();
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
            ->update(['ultimo_numero' => $numero, 'updated_at' => now()]);

        return sprintf('REPA-%d-%06d', $anio, $numero);
    }
}
''')

write('app/Http/Controllers/Api/RepaletizajeController.php', r'''<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularRepaletizajeRequest;
use App\Http\Requests\RegistrarRepaletizajeRequest;
use App\Http\Resources\RepaletizajeResource;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PersonalAccessToken;
use App\Models\Repaletizaje;
use App\Services\Validacion\ServicioRepaletizaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepaletizajeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $consulta = Repaletizaje::query()
            ->with(['folioResultante', 'detalles.folioOrigen', 'usuario:id,name', 'dispositivo:id,codigo,nombre'])
            ->when($request->string('folio')->trim()->value(), function ($consulta, string $folio): void {
                $consulta->where(function ($subconsulta) use ($folio): void {
                    $subconsulta->whereHas('folioResultante', fn ($folios) => $folios
                        ->where('numero_folio', 'like', "%{$folio}%"))
                        ->orWhereHas('detalles.folioOrigen', fn ($folios) => $folios
                            ->where('numero_folio', 'like', "%{$folio}%"));
                });
            })
            ->latest('confirmado_at');

        return RepaletizajeResource::collection(
            $consulta->paginate(min(100, max(10, $request->integer('per_page', 25))))
                ->withQueryString(),
        );
    }

    public function show(Repaletizaje $repaletizaje, ServicioRepaletizaje $servicio): RepaletizajeResource
    {
        return new RepaletizajeResource($servicio->cargar($repaletizaje));
    }

    public function buscarFolio(string $numeroFolio): JsonResponse
    {
        $numero = mb_strtoupper(trim($numeroFolio));
        $folio = Folio::query()
            ->where('numero_folio', $numero)
            ->with(['ubicacionActual.camara:id,codigo,nombre', 'ubicacionActual.posicion:id,etiqueta'])
            ->first();
        if (! $folio) {
            return response()->json(['existe' => false, 'numero_folio' => $numero]);
        }

        return response()->json([
            'existe' => true,
            'id' => $folio->id,
            'numero_folio' => $folio->numero_folio,
            'tipo_bulto' => $folio->tipo_bulto?->value,
            'cantidad_cajas' => (int) ($folio->datos_externos['cantidad_cajas'] ?? 0),
            'activo' => $folio->activo,
            'estado_operacional' => $folio->estado_operacional?->value,
            'condicion_termica' => $folio->condicion_termica?->value,
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
            'ubicacion' => $folio->ubicacionActual ? [
                'camara' => $folio->ubicacionActual->camara?->only(['id', 'codigo', 'nombre']),
                'posicion' => $folio->ubicacionActual->posicion?->only(['id', 'etiqueta']),
            ] : null,
        ]);
    }

    public function store(
        RegistrarRepaletizajeRequest $request,
        ServicioRepaletizaje $servicio,
    ): RepaletizajeResource {
        $usuario = $request->user();
        $token = $usuario->currentAccessToken();
        $dispositivo = $token instanceof PersonalAccessToken && $token->dispositivo_id
            ? Dispositivo::query()->find($token->dispositivo_id)
            : null;
        $repa = $servicio->registrar($request->validated(), $usuario, $dispositivo);

        return new RepaletizajeResource($repa);
    }

    public function anular(
        AnularRepaletizajeRequest $request,
        Repaletizaje $repaletizaje,
        ServicioRepaletizaje $servicio,
    ): RepaletizajeResource {
        return new RepaletizajeResource($servicio->anular(
            $repaletizaje,
            $request->validated('operacion_id'),
            $request->validated('motivo'),
            $request->user(),
        ));
    }
}
''')

# Folio relationships.
replace(
    'app/Models/Folio.php',
    "    public function historialHabilitacionesAlmacenamiento(): HasMany\n    {\n        return $this->hasMany(RegistroHabilitacionAlmacenamiento::class);\n    }",
    "    public function historialHabilitacionesAlmacenamiento(): HasMany\n    {\n        return $this->hasMany(RegistroHabilitacionAlmacenamiento::class);\n    }\n\n    public function repaletizajesComoResultado(): HasMany\n    {\n        return $this->hasMany(Repaletizaje::class, 'folio_resultante_id');\n    }\n\n    public function detallesRepaletizaje(): HasMany\n    {\n        return $this->hasMany(RepaletizajeDetalle::class, 'folio_origen_id');\n    }",
)

# Permissions.
replace(
    'app/Services/Autorizacion/CatalogoModulosAcceso.php',
    "    public const TABLET_VALIDACION_PT = 'validacion';\n",
    "    public const TABLET_VALIDACION_PT = 'validacion';\n\n    public const TABLET_REPALETIZAJE = 'repaletizaje';\n",
)
replace(
    'app/Services/Autorizacion/CatalogoModulosAcceso.php',
    "                    $this->moduloTablet(\n                        self::TABLET_PREFRIO,",
    "                    $this->moduloTablet(\n                        self::TABLET_REPALETIZAJE,\n                        'Repaletizajes',\n                        'Consolidar saldos antes o después de prefrío.',\n                        ['frigorifico.validacion'],\n                    ),\n                    $this->moduloTablet(\n                        self::TABLET_PREFRIO,",
)
replace(
    'app/Services/Autorizacion/AlcanceOperacionalUsuario.php',
    "    public function puedeRechazarPallets(User $usuario): bool\n    {",
    "    public function puedeOperarRepaletizajes(User $usuario): bool\n    {\n        return $this->permiteModuloTablet(\n            $usuario,\n            CatalogoModulosAcceso::TABLET_REPALETIZAJE,\n        ) && $this->puedeValidarPallets($usuario);\n    }\n\n    public function puedeConsultarRepaletizajes(User $usuario): bool\n    {\n        return $this->puedeConsultarValidacionesPallet($usuario);\n    }\n\n    public function puedeAnularRepaletizajes(User $usuario): bool\n    {\n        return $this->rolActivoEnModulo(\n            $usuario,\n            [RolUsuario::Administrador, RolUsuario::SupervisorFrio],\n            'frigorifico.validacion',\n        );\n    }\n\n    public function puedeRechazarPallets(User $usuario): bool\n    {",
)
replace(
    'app/Services/Autorizacion/AlcanceOperacionalUsuario.php',
    "            'puede_validar_pallets' => $this->puedeValidarPallets($usuario),\n",
    "            'puede_validar_pallets' => $this->puedeValidarPallets($usuario),\n            'puede_operar_repaletizajes' => $this->puedeOperarRepaletizajes($usuario),\n            'puede_consultar_repaletizajes' => $this->puedeConsultarRepaletizajes($usuario),\n            'puede_anular_repaletizajes' => $this->puedeAnularRepaletizajes($usuario),\n",
)
replace(
    'app/Providers/AppServiceProvider.php',
    "        Gate::define(\n            'rechazar-pallets',",
    "        Gate::define(\n            'operar-repaletizajes',\n            fn (User $usuario): bool => $alcance->puedeOperarRepaletizajes($usuario),\n        );\n        Gate::define(\n            'consultar-repaletizajes',\n            fn (User $usuario): bool => $alcance->puedeConsultarRepaletizajes($usuario),\n        );\n        Gate::define(\n            'anular-repaletizajes',\n            fn (User $usuario): bool => $alcance->puedeAnularRepaletizajes($usuario),\n        );\n        Gate::define(\n            'rechazar-pallets',",
)

# Routes.
replace(
    'routes/api.php',
    "use App\\Http\\Controllers\\Api\\ReinicioOperacionalController;\n",
    "use App\\Http\\Controllers\\Api\\ReinicioOperacionalController;\nuse App\\Http\\Controllers\\Api\\RepaletizajeController;\n",
)
replace(
    'routes/api.php',
    "    Route::middleware('can:validar-mp')->prefix('validacion-mp')->group(function () {",
    "    Route::middleware('can:consultar-repaletizajes')->prefix('validacion/repaletizajes')->group(function () {\n        Route::get('/', [RepaletizajeController::class, 'index']);\n        Route::get('/folios/{numeroFolio}', [RepaletizajeController::class, 'buscarFolio']);\n        Route::get('/{repaletizaje}', [RepaletizajeController::class, 'show']);\n    });\n    Route::post('/validacion/repaletizajes', [RepaletizajeController::class, 'store'])\n        ->middleware('can:operar-repaletizajes');\n    Route::post('/validacion/repaletizajes/{repaletizaje}/anular', [RepaletizajeController::class, 'anular'])\n        ->middleware('can:anular-repaletizajes');\n\n    Route::middleware('can:validar-mp')->prefix('validacion-mp')->group(function () {",
)

write('tests/Feature/Api/RepaletizajeApiTest.php', r'''<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepaletizajeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_pallet_nuevo_con_mix_y_conserva_saldo_residual(): void
    {
        [$token, $temporada] = $this->contexto();
        $primero = $this->folio($temporada, 'SAL-001', 90, calibre: '2J', csg: '111');
        $segundo = $this->folio($temporada, 'SAL-002', 40, calibre: '3J', csg: '222');

        $respuesta = $this->conToken($token)->postJson('/api/validacion/repaletizajes', [
            'operacion_id' => (string) Str::uuid(),
            'tipo_resultado' => 'pallet',
            'estrategia_folio' => 'nuevo',
            'numero_folio_resultante' => 'PAL-900',
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 90],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 30],
            ],
        ])->assertOk()
            ->assertJsonPath('data.folio_resultante.numero_folio', 'PAL-900')
            ->assertJsonPath('data.folio_resultante.cantidad_cajas', 120)
            ->assertJsonPath('data.folio_resultante.tipo_bulto', 'pallet')
            ->assertJsonPath('data.folio_resultante.calibre', 'MIX')
            ->assertJsonPath('data.folio_resultante.csg', 'MIX')
            ->assertJsonPath('data.mix.calibre', true)
            ->assertJsonPath('data.mix.csg', true);

        $this->assertDatabaseHas('folios', [
            'id' => $primero->id,
            'activo' => false,
            'estado_operacional' => 'agotado',
        ]);
        $this->assertSame(10, (int) Folio::query()->findOrFail($segundo->id)->datos_externos['cantidad_cajas']);
        $this->assertDatabaseCount('repaletizaje_detalles', 2);
        $this->assertNotEmpty($respuesta->json('data.advertencias'));
    }

    public function test_consolida_saldo_post_prefrio_conservando_folio_y_estado_disponible(): void
    {
        [$token, $temporada] = $this->contexto();
        $primero = $this->folio(
            $temporada,
            'SAL-FRIO-1',
            30,
            condicion: CondicionTermicaFolio::PrefrioAprobado,
            estado: EstadoOperacionalFolio::Disponible,
        );
        $segundo = $this->folio(
            $temporada,
            'SAL-FRIO-2',
            25,
            condicion: CondicionTermicaFolio::PrefrioAprobado,
            estado: EstadoOperacionalFolio::Disponible,
        );

        $this->conToken($token)->postJson('/api/validacion/repaletizajes', [
            'operacion_id' => (string) Str::uuid(),
            'tipo_resultado' => 'saldo',
            'estrategia_folio' => 'conservar',
            'numero_folio_resultante' => 'SAL-FRIO-1',
            'folio_conservado_id' => $primero->id,
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 30],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 25],
            ],
        ])->assertOk()
            ->assertJsonPath('data.folio_resultante.numero_folio', 'SAL-FRIO-1')
            ->assertJsonPath('data.folio_resultante.cantidad_cajas', 55)
            ->assertJsonPath('data.folio_resultante.tipo_bulto', 'saldo')
            ->assertJsonPath('data.folio_resultante.estado_operacional', 'disponible')
            ->assertJsonPath('data.folio_resultante.condicion_termica', 'prefrio_aprobado');
    }

    public function test_bloquea_cliente_especie_marca_y_estado_termico_diferentes(): void
    {
        foreach ([
            ['cliente', 'OTRO', null, null, null],
            ['especie', null, 'Kiwi', null, null],
            ['marca', null, null, 'OTRA', null],
            ['estado térmico', null, null, null, CondicionTermicaFolio::PrefrioAprobado],
        ] as [$mensaje, $cliente, $especie, $marca, $condicion]) {
            $this->refreshApplication();
            $this->artisan('migrate:fresh', ['--force' => true]);
            [$token, $temporada] = $this->contexto();
            $primero = $this->folio($temporada, 'SAL-A', 20);
            $segundo = $this->folio(
                $temporada,
                'SAL-B',
                20,
                cliente: $cliente ?? 'CLIENTE',
                especie: $especie ?? 'Cereza',
                marca: $marca ?? 'MARCA',
                condicion: $condicion ?? CondicionTermicaFolio::PendientePrefrio,
                estado: $condicion === CondicionTermicaFolio::PrefrioAprobado
                    ? EstadoOperacionalFolio::Disponible
                    : EstadoOperacionalFolio::PendientePrefrio,
            );

            $this->conToken($token)->postJson('/api/validacion/repaletizajes', [
                'operacion_id' => (string) Str::uuid(),
                'tipo_resultado' => 'saldo',
                'estrategia_folio' => 'nuevo',
                'numero_folio_resultante' => 'SAL-MIX',
                'cantidad_objetivo' => 120,
                'origenes' => [
                    ['folio_id' => $primero->id, 'cantidad_aportada' => 20],
                    ['folio_id' => $segundo->id, 'cantidad_aportada' => 20],
                ],
            ])->assertUnprocessable()
                ->assertJsonPath('message', "No se puede mezclar diferente {$mensaje} en una repa.");
        }
    }

    public function test_es_idempotente_y_anulacion_restaura_los_folios(): void
    {
        [$token, $temporada] = $this->contexto();
        $primero = $this->folio($temporada, 'SAL-R1', 60);
        $segundo = $this->folio($temporada, 'SAL-R2', 70);
        $operacion = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacion,
            'tipo_resultado' => 'pallet',
            'estrategia_folio' => 'conservar',
            'numero_folio_resultante' => 'SAL-R1',
            'folio_conservado_id' => $primero->id,
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 60],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 60],
            ],
        ];

        $id = $this->conToken($token)->postJson('/api/validacion/repaletizajes', $payload)
            ->assertOk()->json('data.id');
        $this->conToken($token)->postJson('/api/validacion/repaletizajes', $payload)
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('repaletizajes', 1);

        [, $supervisorToken] = $this->acceso(RolUsuario::SupervisorFrio, 'SUP-REPA');
        $this->conToken($supervisorToken)->postJson("/api/validacion/repaletizajes/{$id}/anular", [
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Error operacional confirmado.',
        ])->assertOk()->assertJsonPath('data.estado', 'anulado');

        $this->assertSame(60, (int) Folio::query()->findOrFail($primero->id)->datos_externos['cantidad_cajas']);
        $this->assertSame(70, (int) Folio::query()->findOrFail($segundo->id)->datos_externos['cantidad_cajas']);
        $this->assertSame('saldo', Folio::query()->findOrFail($primero->id)->tipo_bulto->value);
    }

    /** @return array{string, Temporada} */
    private function contexto(): array
    {
        $temporada = Temporada::create([
            'codigo' => '2026-2027',
            'nombre' => 'Temporada',
            'activa' => true,
            'version_catalogo' => 1,
        ]);
        [, $token] = $this->acceso(RolUsuario::Validador, 'VAL-REPA');

        return [$token, $temporada];
    }

    /** @return array{User, string} */
    private function acceso(RolUsuario $rol, string $codigo): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo,
            'nombre' => "PDA {$codigo}",
            'plataforma' => 'android',
            'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo($dispositivo, "test-{$codigo}")->plainTextToken;

        return [$usuario, $token];
    }

    private function folio(
        Temporada $temporada,
        string $numero,
        int $cantidad,
        string $cliente = 'CLIENTE',
        string $especie = 'Cereza',
        string $marca = 'MARCA',
        string $calibre = '2J',
        string $csg = '111',
        CondicionTermicaFolio $condicion = CondicionTermicaFolio::PendientePrefrio,
        EstadoOperacionalFolio $estado = EstadoOperacionalFolio::PendientePrefrio,
    ): Folio {
        return Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Saldo,
            'estado_operacional' => $estado,
            'condicion_termica' => $condicion,
            'habilitacion_almacenamiento' => $condicion === CondicionTermicaFolio::PrefrioAprobado
                ? HabilitacionAlmacenamientoFolio::Habilitado
                : HabilitacionAlmacenamientoFolio::NoHabilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'variedad' => 'Santina',
            'calibre' => $calibre,
            'marca' => $marca,
            'exportadora' => $cliente,
            'origen_sistema' => 'validacion',
            'identificador_externo' => (string) Str::uuid(),
            'estado_integracion' => EstadoIntegracionFolio::NoVinculado,
            'datos_externos' => [
                'especie' => $especie,
                'categoria' => 'Exportación',
                'envase' => 'Caja 5 kg',
                'csg' => $csg,
                'predio' => 'Predio',
                'cuartel' => 'Cuartel',
                'cantidad_cajas' => $cantidad,
            ],
        ]);
    }

    private function conToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
''')
