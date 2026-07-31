from pathlib import Path
import re


def read(path):
    return Path(path).read_text(encoding='utf-8')


def write(path, content):
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8')


def replace(path, old, new):
    text = read(path)
    if old not in text:
        raise RuntimeError(f'No se encontró patrón en {path}: {old[:100]!r}')
    write(path, text.replace(old, new, 1))


def regex(path, pattern, replacement):
    text = read(path)
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f'Regex {count} en {path}: {pattern[:100]!r}')
    write(path, updated)


write('database/migrations/2026_07_31_150000_permitir_materiales_solo_en_camara.php', '''<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ubicaciones_actuales', function (Blueprint $table): void {
            $table->foreignUuid('camara_id')
                ->nullable()
                ->after('folio_id')
                ->constrained('camaras')
                ->restrictOnDelete();
        });

        DB::statement(
            'UPDATE ubicaciones_actuales ua '
            .'INNER JOIN posiciones p ON p.id = ua.posicion_id '
            .'SET ua.camara_id = p.camara_id '
            .'WHERE ua.camara_id IS NULL'
        );

        Schema::table('ubicaciones_actuales', function (Blueprint $table): void {
            $table->uuid('camara_id')->nullable(false)->change();
            $table->uuid('posicion_id')->nullable()->change();
        });

        Schema::table('retiros_materiales', function (Blueprint $table): void {
            $table->uuid('posicion_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement('DELETE FROM ubicaciones_actuales WHERE posicion_id IS NULL');
        DB::statement(
            'UPDATE retiros_materiales r '
            .'SET r.posicion_id = ('
            .'SELECT MIN(p.id) FROM posiciones p WHERE p.camara_id = r.camara_id'
            .') WHERE r.posicion_id IS NULL'
        );

        Schema::table('retiros_materiales', function (Blueprint $table): void {
            $table->uuid('posicion_id')->nullable(false)->change();
        });

        Schema::table('ubicaciones_actuales', function (Blueprint $table): void {
            $table->uuid('posicion_id')->nullable(false)->change();
            $table->dropForeign(['camara_id']);
            $table->dropColumn('camara_id');
        });
    }
};
''')

replace(
    'app/Models/UbicacionActual.php',
    "#[Fillable(['folio_id', 'posicion_id', 'movimiento_id', 'ubicado_at'])]",
    "#[Fillable(['folio_id', 'camara_id', 'posicion_id', 'movimiento_id', 'ubicado_at'])]",
)
replace(
    'app/Models/UbicacionActual.php',
    "    public function posicion(): BelongsTo\n    {\n        return $this->belongsTo(Posicion::class);\n    }\n",
    "    public function camara(): BelongsTo\n    {\n        return $this->belongsTo(Camara::class);\n    }\n\n    public function posicion(): BelongsTo\n    {\n        return $this->belongsTo(Posicion::class);\n    }\n",
)
replace(
    'app/Models/Camara.php',
    "    public function posiciones(): HasMany\n    {\n        return $this->hasMany(Posicion::class);\n    }\n",
    "    public function posiciones(): HasMany\n    {\n        return $this->hasMany(Posicion::class);\n    }\n\n    public function ubicacionesActuales(): HasMany\n    {\n        return $this->hasMany(UbicacionActual::class);\n    }\n\n    public function ubicacionesSinPosicion(): HasMany\n    {\n        return $this->ubicacionesActuales()->whereNull('posicion_id');\n    }\n",
)

replace(
    'app/Http/Requests/UbicarFolioRequest.php',
    "            'posicion_destino_id' => ['required', 'uuid', 'exists:posiciones,id'],\n",
    "            'camara_destino_id' => ['required', 'uuid', 'exists:camaras,id'],\n            'posicion_destino_id' => ['nullable', 'uuid', 'exists:posiciones,id'],\n",
)

replace(
    'app/Http/Controllers/Api/MovimientoController.php',
    "                'ubicacionActual.posicion.camara:id,codigo,nombre',\n",
    "                'ubicacionActual.camara:id,codigo,nombre,contenido,estado',\n                'ubicacionActual.posicion:id,camara_id,etiqueta,estado',\n",
)
replace(
    'app/Http/Controllers/Api/MovimientoController.php',
    "        $ubicacion = $folio->ubicacionActual;\n        $material = $folio->material;\n",
    "        $ubicacion = $folio->ubicacionActual;\n        $camaraUbicacion = $ubicacion?->camara ?? $ubicacion?->posicion?->camara;\n        $material = $folio->material;\n",
)
replace(
    'app/Http/Controllers/Api/MovimientoController.php',
    """                'ubicacion_actual' => $ubicacion ? [
                    'camara' => [
                        'id' => $ubicacion->posicion->camara->id,
                        'codigo' => $ubicacion->posicion->camara->codigo,
                        'nombre' => $ubicacion->posicion->camara->nombre,
                    ],
                    'posicion' => [
                        'id' => $ubicacion->posicion->id,
                        'etiqueta' => $ubicacion->posicion->etiqueta,
                    ],
                ] : null,
""",
    """                'ubicacion_actual' => $ubicacion && $camaraUbicacion ? [
                    'camara' => [
                        'id' => $camaraUbicacion->id,
                        'codigo' => $camaraUbicacion->codigo,
                        'nombre' => $camaraUbicacion->nombre,
                    ],
                    'posicion' => $ubicacion->posicion ? [
                        'id' => $ubicacion->posicion->id,
                        'etiqueta' => $ubicacion->posicion->etiqueta,
                    ] : null,
                ] : null,
""",
)
replace(
    'app/Http/Controllers/Api/MovimientoController.php',
    """            posicionDestino: Posicion::query()->findOrFail($datos['posicion_destino_id']),
            sesionDestino: SesionEstiba::query()->findOrFail($datos['sesion_destino_id']),
""",
    """            camaraDestino: Camara::query()->findOrFail($datos['camara_destino_id']),
            posicionDestino: isset($datos['posicion_destino_id'])
                ? Posicion::query()->findOrFail($datos['posicion_destino_id'])
                : null,
            sesionDestino: SesionEstiba::query()->findOrFail($datos['sesion_destino_id']),
""",
)
regex(
    'app/Http/Controllers/Api/MovimientoController.php',
    r"        if \(\$folio->ubicacionActual\) \{.*?\n        \}\n\n        if \(\$folio->tipo_bulto === TipoBulto::Material",
    """        if ($folio->ubicacionActual) {
            $ubicacion = $folio->ubicacionActual;
            $posicion = $ubicacion->posicion;
            $camara = $ubicacion->camara ?? $posicion?->camara;

            if ($folio->tipo_bulto === TipoBulto::Material && $camara && ! $posicion) {
                return [
                    true,
                    \"El folio está en {$camara->codigo} sin posición. Puede completar una ubicación exacta.\",
                ];
            }

            $detalle = $posicion?->etiqueta ? \" · {$posicion->etiqueta}\" : '';

            return [
                false,
                \"El folio ya está ubicado en {$camara?->codigo}{$detalle}.\",
            ];
        }

        if ($folio->tipo_bulto === TipoBulto::Material""",
)

replace(
    'app/Services/Estiba/ServicioMovimientoEstiba.php',
    'use App\\Enums\\EstadoOperacionSincronizacion;\n',
    'use App\\Enums\\EstadoOperacionSincronizacion;\nuse App\\Enums\\EstadoOperacionalFolio;\n',
)
regex(
    'app/Services/Estiba/ServicioMovimientoEstiba.php',
    r"    public function ubicar\(.*?\n    \}\n\n    /\*\*\n     \* Reubica un folio",
    '''    public function ubicar(
        string $operacionId,
        string $numeroFolio,
        TipoBulto $tipoBulto,
        Camara $camaraDestino,
        ?Posicion $posicionDestino,
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
            'camara_destino_id' => $camaraDestino->id,
            'posicion_destino_id' => $posicionDestino?->id,
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
                $camaraDestino,
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
     * Reubica un folio''',
)
regex(
    'app/Services/Estiba/ServicioMovimientoEstiba.php',
    r"    private function procesarUbicacionInicial\(.*?\n    \}\n\n    private function procesarMovimiento\(",
    '''    private function procesarUbicacionInicial(
        OperacionSincronizacion $operacion,
        string $numeroFolio,
        TipoBulto $tipoBulto,
        Camara $camaraDestino,
        ?Posicion $posicionDestino,
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
        $camara = Camara::query()->lockForUpdate()->findOrFail($camaraDestino->id);
        $posicion = $posicionDestino
            ? Posicion::query()->lockForUpdate()->findOrFail($posicionDestino->id)
            : null;
        $sesion = SesionEstiba::query()->lockForUpdate()->findOrFail($sesionDestino->id);

        if ($sesion->camara_id !== $camara->id) {
            throw new DomainException('La sesión de estiba no pertenece a la cámara de destino.');
        }
        if ($posicion && $posicion->camara_id !== $camara->id) {
            throw new DomainException('La posición seleccionada no pertenece a la cámara de destino.');
        }
        if (! $posicion && $tipoBulto !== TipoBulto::Material) {
            throw new DomainException('Los pallets y saldos requieren una posición exacta.');
        }

        $this->validarContenidoCamara($camara, $tipoBulto);
        $folio = Folio::query()->where('numero_folio', $numeroFolio)->lockForUpdate()->first();

        if (! $folio) {
            if ($tipoBulto === TipoBulto::Material) {
                throw new DomainException('El folio de material debe nacer desde Recepción o Transformación antes de asignarlo.');
            }
            $folio = Folio::create($this->atributosNuevoFolio(
                $numeroFolio,
                $tipoBulto,
                $generadoDispositivoAt,
                $datosFolio,
            ));
        } elseif ($folio->tipo_bulto !== $tipoBulto) {
            throw new DomainException('El tipo de bulto no coincide con el folio existente.');
        } elseif ($tipoBulto === TipoBulto::Material
            && ! FolioMaterial::query()->whereKey($folio->id)->exists()) {
            throw new DomainException('El folio de material no posee una ficha de inventario válida.');
        }

        $this->validarVersion($camara, $versionDestinoConocida, 'destino');
        $ubicacion = UbicacionActual::query()
            ->where('folio_id', $folio->id)
            ->lockForUpdate()
            ->first();

        if ($ubicacion) {
            if ($tipoBulto !== TipoBulto::Material
                || $ubicacion->camara_id !== $camara->id
                || $ubicacion->posicion_id !== null
                || ! $posicion) {
                throw new ConflictoMovimiento('El folio ya posee una ubicación actual.');
            }

            $this->validarCompatibilidadPosicionDestino($folio, $posicion);
            $advertencias = $this->detectorAdvertencias->paraUbicacion($posicion, $advertenciasConfirmadas);
            $versionResultante = $camara->version_plano + 1;
            $movimiento = Movimiento::create([
                'operacion_id' => $operacion->id,
                'folio_id' => $folio->id,
                'tipo_movimiento' => TipoMovimiento::Reubicacion,
                'camara_origen_id' => $camara->id,
                'posicion_origen_id' => null,
                'sesion_origen_id' => $sesion->id,
                'camara_destino_id' => $camara->id,
                'posicion_destino_id' => $posicion->id,
                'sesion_destino_id' => $sesion->id,
                'user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo->id,
                'advertencias_confirmadas' => $advertencias !== [] ? $advertencias : null,
                'version_origen_anterior' => $camara->version_plano,
                'version_origen_resultante' => $versionResultante,
                'version_destino_anterior' => $camara->version_plano,
                'version_destino_resultante' => $versionResultante,
                'generado_dispositivo_at' => $generadoDispositivoAt,
                'recibido_servidor_at' => $recibidoServidorAt,
            ]);
            $ubicacion->update([
                'posicion_id' => $posicion->id,
                'movimiento_id' => $movimiento->id,
                'ubicado_at' => $recibidoServidorAt,
            ]);
            $this->actualizarVersionCamara($camara, $versionResultante);
            $this->actualizarActividadSesiones(collect([$sesion]), $recibidoServidorAt);

            return $movimiento->load('folio');
        }

        if ($posicion) {
            $this->validarCompatibilidadPosicionDestino($folio, $posicion);
        }
        $advertencias = $posicion
            ? $this->detectorAdvertencias->paraUbicacion($posicion, $advertenciasConfirmadas)
            : [];
        $versionResultante = $camara->version_plano + 1;
        $movimiento = Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'camara_destino_id' => $camara->id,
            'posicion_destino_id' => $posicion?->id,
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
            'camara_id' => $camara->id,
            'posicion_id' => $posicion?->id,
            'movimiento_id' => $movimiento->id,
            'ubicado_at' => $recibidoServidorAt,
        ]);

        if ($tipoBulto === TipoBulto::Material
            && $folio->estado_operacional === EstadoOperacionalFolio::PendienteUbicacion) {
            $folio->update(['estado_operacional' => EstadoOperacionalFolio::Disponible]);
        }

        $this->actualizarVersionCamara($camara, $versionResultante);
        $this->actualizarActividadSesiones(collect([$sesion]), $recibidoServidorAt);

        return $movimiento->load('folio');
    }

    private function procesarMovimiento(''',
)
replace(
    'app/Services/Estiba/ServicioMovimientoEstiba.php',
    """        $ubicacion->update([
            'posicion_id' => $destino->id,
            'movimiento_id' => $movimiento->id,
            'ubicado_at' => $recibidoServidorAt,
        ]);
""",
    """        $ubicacion->update([
            'camara_id' => $camaraDestino->id,
            'posicion_id' => $destino->id,
            'movimiento_id' => $movimiento->id,
            'ubicado_at' => $recibidoServidorAt,
        ]);
""",
)

replace(
    'app/Http/Resources/MovimientoResource.php',
    """        if ($camaraId === null || $posicionId === null) {
            return null;
        }
""",
    """        if ($camaraId === null) {
            return null;
        }
""",
)
replace(
    'app/Http/Resources/MovimientoResource.php',
    """            'posicion' => [
                'id' => $posicionId,
                'banda' => $posicion?->banda,
                'posicion' => $posicion?->posicion,
                'nivel' => $posicion?->nivel,
                'etiqueta' => $posicion?->etiqueta,
            ],
""",
    """            'posicion' => $posicionId ? [
                'id' => $posicionId,
                'banda' => $posicion?->banda,
                'posicion' => $posicion?->posicion,
                'nivel' => $posicion?->nivel,
                'etiqueta' => $posicion?->etiqueta,
            ] : null,
""",
)

print('Bloque 1 aplicado')
