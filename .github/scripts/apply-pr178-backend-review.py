from pathlib import Path


def replace_between(text: str, start: str, end: str, replacement: str) -> str:
    start_index = text.index(start)
    end_index = text.index(end, start_index)
    return text[:start_index] + replacement + text[end_index:]


service_path = Path('app/Services/MateriaPrima/ServicioRetornoPacking.php')
service = service_path.read_text()
registrar = '''    /** @param array<string, mixed> $datos */
    public function registrar(
        EntregaFrutaProceso $entrega,
        array $datos,
        User $usuario,
    ): LoteMateriaPrima {
        $payload = $this->payload($datos, $entrega->id);
        $hash = $this->hash($payload);

        try {
            return DB::transaction(function () use (
                $entrega,
                $datos,
                $usuario,
                $payload,
                $hash,
            ): LoteMateriaPrima {
                $existente = RetornoPacking::query()
                    ->where('operacion_id', $datos['operacion_id'])
                    ->lockForUpdate()
                    ->first();
                if ($existente) {
                    return $this->resolverRetornoRepetido(
                        $existente,
                        $entrega,
                        $payload,
                        $hash,
                    );
                }
                $this->asegurarOperacionDisponible($datos['operacion_id']);

                $entregaIds = collect($payload['entregas'])
                    ->pluck('entrega_fruta_proceso_id')
                    ->sort()
                    ->values();
                $entregas = EntregaFrutaProceso::query()
                    ->whereIn('id', $entregaIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($entregas->count() !== $entregaIds->count()) {
                    throw ValidationException::withMessages([
                        'entregas' => 'Una de las entregas seleccionadas ya no existe.',
                    ]);
                }

                $loteIds = $entregas->pluck('lote_materia_prima_id')->unique()->sort()->values();
                $lotes = collect();
                foreach ($loteIds as $loteId) {
                    $lote = $this->loteActivo($loteId);
                    $lotes->put($lote->id, $lote);
                }

                foreach ($payload['entregas'] as $origen) {
                    /** @var EntregaFrutaProceso $entregaOrigen */
                    $entregaOrigen = $entregas->get($origen['entrega_fruta_proceso_id']);
                    if ($entregaOrigen->anulado_at !== null) {
                        throw new ConflictoOperacion(
                            'No se puede retornar una entrega anulada.',
                        );
                    }
                    if ($this->entregaCerrada($entregaOrigen->id)) {
                        throw new ConflictoOperacion(sprintf(
                            'El retorno del viaje %s ya fue cerrado por Packing.',
                            $entregaOrigen->numero_orden,
                        ));
                    }
                }

                /** @var EntregaFrutaProceso $entregaPrincipal */
                $entregaPrincipal = $entregas->get($entrega->id);
                $origenPrincipal = collect($payload['entregas'])->firstWhere(
                    'entrega_fruta_proceso_id',
                    $entregaPrincipal->id,
                );
                $lotePrincipal = $lotes->get($entregaPrincipal->lote_materia_prima_id);
                $tipos = $this->tiposResultado($payload['resultados']);
                $token = $usuario->currentAccessToken();
                $retorno = RetornoPacking::create([
                    'operacion_id' => $datos['operacion_id'],
                    'payload_hash' => $hash,
                    'numero' => sprintf(
                        'RP-%06d',
                        $this->secuencias->reservarSiguiente('retornos_packing'),
                    ),
                    'entrega_fruta_proceso_id' => $entregaPrincipal->id,
                    'cierra_entrega' => (bool) $origenPrincipal['cierra_entrega'],
                    'observacion' => $payload['observacion'],
                    'registrado_por_user_id' => $usuario->id,
                    'dispositivo_id' => $token instanceof PersonalAccessToken
                        ? $token->dispositivo_id
                        : null,
                    'registrado_at' => now(),
                ]);
                $retorno->entregas()->attach(
                    collect($payload['entregas'])->mapWithKeys(
                        fn (array $origen): array => [
                            $origen['entrega_fruta_proceso_id'] => [
                                'cierra_entrega' => (bool) $origen['cierra_entrega'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        ],
                    )->all(),
                );

                $detalleEvento = [];
                foreach ($payload['resultados'] as $resultado) {
                    $tipo = $tipos->get($resultado['tipo_resultado_packing_id']);
                    $nombre = $this->nombreResultado($tipo, $resultado);
                    $sublote = SubloteRetornoPacking::create([
                        'retorno_packing_id' => $retorno->id,
                        'tipo_resultado_packing_id' => $tipo->id,
                        'numero_sublote' => sprintf(
                            '%s-%06d',
                            strtoupper($tipo->prefijo_sublote),
                            $this->secuencias->reservarSiguiente('sublotes_packing'),
                        ),
                        'nombre_resultado' => $nombre,
                        'cantidad_bins' => $resultado['cantidad_bins'],
                        'kilos_netos' => $resultado['kilos_netos'],
                        'estado' => EstadoSubloteRetornoPacking::PendienteUbicacion,
                    ]);
                    $detalleEvento[] = [
                        'sublote_id' => $sublote->id,
                        'numero_sublote' => $sublote->numero_sublote,
                        'resultado' => $nombre,
                        'cantidad_bins' => $sublote->cantidad_bins,
                        'kilos_netos' => $sublote->kilos_netos,
                    ];
                }

                $this->registrarEvento(
                    $lotePrincipal,
                    'retorno_packing_registrado',
                    $usuario,
                    $datos['operacion_id'],
                    [
                        'retorno_id' => $retorno->id,
                        'numero' => $retorno->numero,
                        'entrega_id' => $entregaPrincipal->id,
                        'cierra_entrega' => $retorno->cierra_entrega,
                        'entregas' => collect($payload['entregas'])->map(
                            function (array $origen) use ($entregas, $lotes): array {
                                /** @var EntregaFrutaProceso $entregaOrigen */
                                $entregaOrigen = $entregas->get(
                                    $origen['entrega_fruta_proceso_id'],
                                );
                                $loteOrigen = $lotes->get(
                                    $entregaOrigen->lote_materia_prima_id,
                                );

                                return [
                                    'entrega_id' => $entregaOrigen->id,
                                    'lote_id' => $loteOrigen->id,
                                    'numero_lote' => $loteOrigen->numero_lote,
                                    'numero_orden' => $entregaOrigen->numero_orden,
                                    'cierra_entrega' => (bool) $origen['cierra_entrega'],
                                ];
                            },
                        )->values()->all(),
                        'resultados' => $detalleEvento,
                    ],
                );

                return $this->cargarLote($lotePrincipal);
            }, attempts: 3);
        } catch (QueryException $excepcion) {
            $existente = RetornoPacking::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->first();
            if (! $existente) {
                throw $excepcion;
            }

            return $this->resolverRetornoRepetido(
                $existente,
                $entrega,
                $payload,
                $hash,
            );
        }
    }
'''
service = replace_between(
    service,
    '    /** @param array<string, mixed> $datos */\n    public function registrar(',
    '\n    public function ubicar(',
    registrar,
)

anular = '''    public function anular(
        RetornoPacking $retorno,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use (
            $retorno,
            $operacionId,
            $motivo,
            $usuario,
        ): LoteMateriaPrima {
            $retorno = RetornoPacking::query()
                ->with(['entregas.lote', 'resultados'])
                ->lockForUpdate()
                ->findOrFail($retorno->id);
            $entregaPrincipal = $retorno->entregas->firstWhere(
                'id',
                $retorno->entrega_fruta_proceso_id,
            ) ?? EntregaFrutaProceso::query()
                ->lockForUpdate()
                ->findOrFail($retorno->entrega_fruta_proceso_id);
            $lote = $this->loteActivo($entregaPrincipal->lote_materia_prima_id);

            if ($retorno->anulado_at !== null) {
                if ($retorno->operacion_anulacion_id !== $operacionId) {
                    throw new ConflictoOperacion('El retorno ya fue anulado con otra operación.');
                }

                return $this->cargarLote($lote);
            }
            $this->asegurarOperacionDisponible($operacionId);
            if (! $this->puedeAnular($retorno, $usuario)) {
                abort(403, 'No puedes anular este retorno o uno de sus sublotes ya fue ubicado.');
            }

            $retorno->update([
                'operacion_anulacion_id' => $operacionId,
                'anulado_por_user_id' => $usuario->id,
                'anulado_at' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);
            $retorno->resultados()->update([
                'estado' => EstadoSubloteRetornoPacking::Anulado->value,
                'updated_at' => now(),
            ]);
            $this->registrarEvento(
                $lote,
                'retorno_packing_anulado',
                $usuario,
                $operacionId,
                [
                    'retorno_id' => $retorno->id,
                    'numero' => $retorno->numero,
                    'entrega_id' => $entregaPrincipal->id,
                    'entregas' => $retorno->entregas->map(
                        fn (EntregaFrutaProceso $entrega): array => [
                            'entrega_id' => $entrega->id,
                            'lote_id' => $entrega->lote_materia_prima_id,
                            'numero_orden' => $entrega->numero_orden,
                            'cierra_entrega' => (bool) $entrega->pivot->cierra_entrega,
                        ],
                    )->values()->all(),
                    'motivo' => trim($motivo),
                ],
            );

            return $this->cargarLote($lote);
        }, attempts: 3);
    }
'''
service = replace_between(
    service,
    '    public function anular(',
    '\n    public function puedeRegistrar(',
    anular,
)
service = service.replace(
    "            && ! $entrega->retornos\n                ->whereNull('anulado_at')\n                ->contains(fn (RetornoPacking $retorno): bool => $retorno->cierra_entrega);",
    "            && ! $entrega->retornos\n                ->whereNull('anulado_at')\n                ->contains(fn (RetornoPacking $retorno): bool => (bool) (\n                    $retorno->pivot?->cierra_entrega ?? $retorno->cierra_entrega\n                ));",
)

puede_anular_start = service.index('    public function puedeAnular(')
puede_anular_end = service.index('\n    public function entregaTieneRetornos(', puede_anular_start)
puede_anular = '''    public function puedeAnular(
        RetornoPacking $retorno,
        User $usuario,
        ?string $ultimoRetornoVigenteId = null,
    ): bool {
        if ($retorno->anulado_at !== null
            || $retorno->resultados->contains(
                fn (SubloteRetornoPacking $sublote): bool => (
                    $sublote->estado === EstadoSubloteRetornoPacking::UbicadoCamara
                ),
            )) {
            return false;
        }
        if ($this->alcance->puedeCorregirEntregasFrutaProceso($usuario)) {
            return true;
        }
        if (! $this->alcance->puedeEntregarFrutaProceso($usuario)
            || $retorno->registrado_por_user_id !== $usuario->id) {
            return false;
        }

        $entregaIds = $retorno->relationLoaded('entregas')
            ? $retorno->entregas->pluck('id')
            : $retorno->entregas()->pluck('entregas_fruta_proceso.id');
        if ($entregaIds->isEmpty()) {
            $entregaIds = collect([$retorno->entrega_fruta_proceso_id]);
        }

        foreach ($entregaIds as $indice => $entregaId) {
            $ultimoId = $entregaIds->count() === 1 && $indice === 0
                ? $ultimoRetornoVigenteId
                : null;
            $ultimoId ??= RetornoPacking::query()
                ->whereHas('entregas', fn ($consulta) => $consulta
                    ->where('entregas_fruta_proceso.id', $entregaId))
                ->whereNull('retornos_packing.anulado_at')
                ->latest('retornos_packing.registrado_at')
                ->latest('retornos_packing.created_at')
                ->latest('retornos_packing.id')
                ->value('retornos_packing.id');
            if ($ultimoId !== $retorno->id) {
                return false;
            }
        }

        return true;
    }
'''
service = service[:puede_anular_start] + puede_anular + service[puede_anular_end:]
service = service.replace(
    "        return $entrega->retornos()->whereNull('anulado_at')->exists();",
    "        return $entrega->retornos()\n            ->whereNull('retornos_packing.anulado_at')\n            ->exists();",
)

resolver_start = service.index('    private function resolverRetornoRepetido(')
resolver_end = service.index('\n    private function asegurarOperacionDisponible(', resolver_start)
resolver = '''    /** @param array<string, mixed> $payload */
    private function resolverRetornoRepetido(
        RetornoPacking $retorno,
        EntregaFrutaProceso $entrega,
        array $payload,
        string $hash,
    ): LoteMateriaPrima {
        $coincide = hash_equals($retorno->payload_hash, $hash);
        if (! $coincide && count($payload['entregas']) === 1) {
            $origen = $payload['entregas'][0];
            $coincide = hash_equals($retorno->payload_hash, $this->hash([
                'cierra_entrega' => (bool) $origen['cierra_entrega'],
                'observacion' => $payload['observacion'],
                'resultados' => $payload['resultados'],
            ]));
        }
        if ($retorno->entrega_fruta_proceso_id !== $entrega->id || ! $coincide) {
            throw new ConflictoOperacion(
                'El identificador de retorno ya fue utilizado con datos diferentes.',
            );
        }

        return $this->cargarLote($retorno->entrega->lote);
    }

    private function entregaCerrada(string $entregaId): bool
    {
        return DB::table('retorno_packing_entregas as origen')
            ->join('retornos_packing as retorno', 'retorno.id', '=', 'origen.retorno_packing_id')
            ->where('origen.entrega_fruta_proceso_id', $entregaId)
            ->where('origen.cierra_entrega', true)
            ->whereNull('retorno.anulado_at')
            ->lockForUpdate()
            ->exists();
    }
'''
service = service[:resolver_start] + resolver + service[resolver_end:]

payload_start = service.index('    /** @param array<string, mixed> $datos */\n    private function payload(')
payload_end = service.index('\n    /** @param array<string, mixed> $payload */\n    private function hash(', payload_start)
payload_method = '''    /** @param array<string, mixed> $datos */
    private function payload(array $datos, string $entregaPrincipalId): array
    {
        $entregas = collect($datos['entregas'] ?? [])
            ->map(fn (array $origen): array => [
                'entrega_fruta_proceso_id' => (string) $origen['entrega_fruta_proceso_id'],
                'cierra_entrega' => (bool) $origen['cierra_entrega'],
            ]);
        if ($entregas->isEmpty()) {
            $entregas = collect([[
                'entrega_fruta_proceso_id' => $entregaPrincipalId,
                'cierra_entrega' => (bool) $datos['cierra_entrega'],
            ]]);
        }
        if (! $entregas->contains(
            fn (array $origen): bool => (
                $origen['entrega_fruta_proceso_id'] === $entregaPrincipalId
            ),
        )) {
            throw ValidationException::withMessages([
                'entregas' => 'El retorno debe incluir el viaje desde el que fue abierto.',
            ]);
        }

        return [
            'entregas' => $entregas
                ->sortBy('entrega_fruta_proceso_id')
                ->values()
                ->all(),
            'observacion' => filled($datos['observacion'] ?? null)
                ? trim((string) $datos['observacion'])
                : null,
            'resultados' => collect($datos['resultados'])
                ->map(fn (array $resultado): array => [
                    'tipo_resultado_packing_id' => $resultado['tipo_resultado_packing_id'],
                    'nombre_resultado' => filled($resultado['nombre_resultado'] ?? null)
                        ? trim((string) $resultado['nombre_resultado'])
                        : null,
                    'cantidad_bins' => (int) $resultado['cantidad_bins'],
                    'kilos_netos' => filled($resultado['kilos_netos'] ?? null)
                        ? round((float) $resultado['kilos_netos'], 3)
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }
'''
service = service[:payload_start] + payload_method + service[payload_end:]
service = service.replace(
    "            'entregasProceso.retornos.dispositivo',\n            'entregasProceso.retornos.resultados.tipoResultado',",
    "            'entregasProceso.retornos.dispositivo',\n            'entregasProceso.retornos.entregas.lote',\n            'entregasProceso.retornos.resultados.tipoResultado',",
)
service_path.write_text(service)

controller_path = Path('app/Http/Controllers/Api/FrutaProcesoController.php')
controller = controller_path.read_text()
controller = controller.replace(
    "                'sublotes_pendientes_ubicacion' => 0,\n",
    "                'sublotes_pendientes_ubicacion' => 0,\n                'retornos_registrados' => 0,\n                'desglose_resultados' => [],\n",
)
controller = controller.replace(
    "            ->whereHas('retorno', fn (Builder $consulta) => $consulta\n                ->whereNull('anulado_at')\n                ->whereHas('entrega', fn (Builder $entrega) => $entrega\n                    ->whereNull('anulado_at')\n                    ->whereHas('lote.temporada', fn (Builder $temporada) => $temporada\n                        ->where('activa', true))));\n\n        return response()->json([",
    "            ->whereHas('retorno', fn (Builder $consulta) => $consulta\n                ->whereNull('retornos_packing.anulado_at')\n                ->whereHas('entregas', fn (Builder $entrega) => $entrega\n                    ->whereNull('entregas_fruta_proceso.anulado_at')\n                    ->whereHas('lote.temporada', fn (Builder $temporada) => $temporada\n                        ->where('activa', true))));\n        $sublotesResumen = (clone $sublotesVigentes)\n            ->with('tipoResultado')\n            ->get();\n        $desgloseResultados = $sublotesResumen\n            ->groupBy('tipo_resultado_packing_id')\n            ->map(function ($grupo): array {\n                $primero = $grupo->first();\n\n                return [\n                    'tipo' => [\n                        'id' => $primero->tipoResultado?->id,\n                        'codigo' => $primero->tipoResultado?->codigo,\n                        'nombre' => $primero->tipoResultado?->nombre,\n                    ],\n                    'sublotes' => $grupo->count(),\n                    'bins' => (int) $grupo->sum('cantidad_bins'),\n                    'kilos' => round((float) $grupo->sum('kilos_netos'), 3),\n                ];\n            })\n            ->values();\n\n        return response()->json([",
)
controller = controller.replace(
    "                ->whereDoesntHave('retornos', fn (Builder $consulta) => $consulta\n                    ->whereNull('anulado_at')\n                    ->where('cierra_entrega', true))",
    "                ->whereDoesntHave('retornos', fn (Builder $consulta) => $consulta\n                    ->whereNull('retornos_packing.anulado_at')\n                    ->where('retorno_packing_entregas.cierra_entrega', true))",
)
controller = controller.replace(
    "            'bins_retornados' => (int) (clone $sublotesVigentes)->sum('cantidad_bins'),\n            'kilos_recuperados' => round(\n                (float) (clone $sublotesVigentes)->sum('kilos_netos'),\n                3,\n            ),\n            'sublotes_pendientes_ubicacion' => (clone $sublotesVigentes)\n                ->where('estado', 'pendiente_ubicacion')\n                ->count(),",
    "            'bins_retornados' => (int) $sublotesResumen->sum('cantidad_bins'),\n            'kilos_recuperados' => round(\n                (float) $sublotesResumen->sum('kilos_netos'),\n                3,\n            ),\n            'sublotes_pendientes_ubicacion' => $sublotesResumen\n                ->where('estado', 'pendiente_ubicacion')\n                ->count(),\n            'retornos_registrados' => $sublotesResumen\n                ->pluck('retorno_packing_id')\n                ->unique()\n                ->count(),\n            'desglose_resultados' => $desgloseResultados,",
)
controller = controller.replace(
    "            'entregasProceso.retornos.dispositivo',\n            'entregasProceso.retornos.resultados.tipoResultado',",
    "            'entregasProceso.retornos.dispositivo',\n            'entregasProceso.retornos.entregas.lote',\n            'entregasProceso.retornos.resultados.tipoResultado',",
)
controller_path.write_text(controller)

resource_path = Path('app/Http/Resources/FrutaProcesoLoteResource.php')
resource = resource_path.read_text()
resource = resource.replace(
    "        $cerrado = $retornosVigentes->contains(\n            fn (RetornoPacking $retorno): bool => $retorno->cierra_entrega,\n        );",
    "        $cerrado = $retornosVigentes->contains(\n            fn (RetornoPacking $retorno): bool => $this->cierraEntrega(\n                $retorno,\n                $entrega,\n            ),\n        );",
)
resource = resource.replace(
    "                    fn (RetornoPacking $retorno): array => $this->retorno(\n                        $retorno,\n                        $request,",
    "                    fn (RetornoPacking $retorno): array => $this->retorno(\n                        $retorno,\n                        $entrega,\n                        $request,",
)
resource = resource.replace(
    "    private function retorno(\n        RetornoPacking $retorno,\n        Request $request,",
    "    private function retorno(\n        RetornoPacking $retorno,\n        EntregaFrutaProceso $entrega,\n        Request $request,",
)
resource = resource.replace(
    "            'cierra_entrega' => $retorno->cierra_entrega,\n            'observacion' => $retorno->observacion,",
    "            'cierra_entrega' => $this->cierraEntrega($retorno, $entrega),\n            'origenes' => $retorno->relationLoaded('entregas')\n                ? $retorno->entregas->map(fn (EntregaFrutaProceso $origen): array => [\n                    'entrega_id' => $origen->id,\n                    'lote_id' => $origen->lote_materia_prima_id,\n                    'numero_lote' => $origen->lote?->numero_lote,\n                    'linea_proceso' => $origen->linea_proceso,\n                    'turno' => $origen->turno,\n                    'numero_orden' => $origen->numero_orden,\n                    'cierra_entrega' => (bool) $origen->pivot->cierra_entrega,\n                ])->values()\n                : collect(),\n            'observacion' => $retorno->observacion,",
)
resource = resource.replace(
    "    }\n}\n",
    "    }\n\n    private function cierraEntrega(\n        RetornoPacking $retorno,\n        EntregaFrutaProceso $entrega,\n    ): bool {\n        if ($retorno->pivot\n            && $retorno->pivot->entrega_fruta_proceso_id === $entrega->id) {\n            return (bool) $retorno->pivot->cierra_entrega;\n        }\n\n        return $retorno->entrega_fruta_proceso_id === $entrega->id\n            && (bool) $retorno->cierra_entrega;\n    }\n}\n",
)
resource_path.write_text(resource)

# Fortalece la regresión existente y agrega cobertura multiorigen real.
test_path = Path('tests/Feature/Api/MateriaPrimaApiTest.php')
test = test_path.read_text()
test = test.replace(
    "        $this->assertDatabaseCount('retornos_packing', 2);\n        $this->assertDatabaseCount('sublotes_retorno_packing', 4);",
    "        $this->assertDatabaseCount('retornos_packing', 2);\n        $this->assertDatabaseCount('retorno_packing_entregas', 2);\n        $this->assertDatabaseHas('retorno_packing_entregas', [\n            'retorno_packing_id' => $respuesta['id'],\n            'entrega_fruta_proceso_id' => $entrega['id'],\n            'cierra_entrega' => true,\n        ]);\n        $this->assertDatabaseCount('sublotes_retorno_packing', 4);",
)
new_test = r'''
    public function test_retorno_multiorigen_cierra_cada_viaje_por_separado_y_no_duplica_el_resumen(): void
    {
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $camara = Camara::create([
            'codigo' => 'MP-MULTIORIGEN',
            'nombre' => 'Cámara multiorigen',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);

        $lote = $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/materia-prima/lotes', $this->payloadLote($contexto, [
                'numero_lote' => 'LOTE-MULTIORIGEN',
                'requiere_hidrocooler' => false,
            ]))->assertCreated()->json('data');
        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])->assertOk()->json('data');
        $this->postJson("/api/materia-prima/lotes/{$lote['id']}/asignar-camara", [
            'operacion_id' => (string) Str::uuid(),
            'camara_id' => $camara->id,
        ])->assertOk();

        $primera = $this->actingAs($camarero, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
                'operacion_id' => (string) Str::uuid(),
                'cantidad_envases' => 20,
                'kilos_enviados' => 7500,
                'linea_proceso' => 'Línea 1',
                'turno' => 'A',
                'numero_orden' => 'OP-MULTI-001',
            ])->assertOk()->json('data.entregas.0');
        $segunda = $this->postJson(
            "/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas",
            [
                'operacion_id' => (string) Str::uuid(),
                'cantidad_envases' => 10,
                'kilos_enviados' => 3750,
                'linea_proceso' => 'Línea 2',
                'turno' => 'A',
                'numero_orden' => 'OP-MULTI-002',
            ],
        )->assertOk()->json('data.entregas.0');
        $tipo = TipoResultadoPacking::query()->where('codigo', 'comercial')->firstOrFail();

        $respuesta = $this->postJson(
            "/api/materia-prima/fruta-proceso/entregas/{$primera['id']}/retornos",
            [
                'operacion_id' => (string) Str::uuid(),
                'entregas' => [
                    [
                        'entrega_fruta_proceso_id' => $primera['id'],
                        'cierra_entrega' => true,
                    ],
                    [
                        'entrega_fruta_proceso_id' => $segunda['id'],
                        'cierra_entrega' => false,
                    ],
                ],
                'resultados' => [[
                    'tipo_resultado_packing_id' => $tipo->id,
                    'cantidad_bins' => 8,
                    'kilos_netos' => 3000,
                ]],
            ],
        )->assertOk()->json('data');

        $primeraActual = collect($respuesta['entregas'])->firstWhere('id', $primera['id']);
        $segundaActual = collect($respuesta['entregas'])->firstWhere('id', $segunda['id']);
        $this->assertSame('completado', $primeraActual['retorno']['estado']);
        $this->assertSame('parcial', $segundaActual['retorno']['estado']);
        $this->assertCount(2, $primeraActual['retorno']['movimientos'][0]['origenes']);
        $this->assertDatabaseCount('retorno_packing_entregas', 2);
        $this->assertDatabaseHas('retorno_packing_entregas', [
            'entrega_fruta_proceso_id' => $primera['id'],
            'cierra_entrega' => true,
        ]);
        $this->assertDatabaseHas('retorno_packing_entregas', [
            'entrega_fruta_proceso_id' => $segunda['id'],
            'cierra_entrega' => false,
        ]);

        $this->getJson('/api/materia-prima/fruta-proceso/resumen')
            ->assertOk()
            ->assertJsonPath('entregas_pendientes_retorno', 1)
            ->assertJsonPath('retornos_registrados', 1)
            ->assertJsonPath('bins_retornados', 8)
            ->assertJsonPath('kilos_recuperados', 3000)
            ->assertJsonPath('desglose_resultados.0.tipo.codigo', 'comercial')
            ->assertJsonPath('desglose_resultados.0.bins', 8);
    }

'''
test = test.replace(
    '    /** @return array<string, mixed> */\n    private function prepararRecepcionValidada(): array',
    new_test + '    /** @return array<string, mixed> */\n    private function prepararRecepcionValidada(): array',
)
test_path.write_text(test)
