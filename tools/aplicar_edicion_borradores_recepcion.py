from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{path}: se esperaba 1 coincidencia y se encontraron {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


def write(path: str, content: str) -> None:
    file = Path(path)
    file.parent.mkdir(parents=True, exist_ok=True)
    file.write_text(content, encoding="utf-8")


write(
    "database/migrations/2026_07_27_230000_habilitar_edicion_borradores_recepciones_materiales.php",
    """<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_recepciones_materiales', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('bultos_recepciones_materiales', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('bultos_recepciones_materiales', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('detalles_recepciones_materiales', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
""",
)

write(
    "app/Http/Requests/ActualizarRecepcionMaterialRequest.php",
    """<?php

namespace App\\Http\\Requests;

class ActualizarRecepcionMaterialRequest extends CrearRecepcionMaterialRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version_conocida' => ['required', 'integer', 'min:1'],
        ];
    }
}
""",
)

replace_once(
    "routes/api.php",
    """        Route::middleware('can:gestionar-recepciones-materiales')->group(function () {
            Route::post('/', [RecepcionMaterialController::class, 'store']);
            Route::post('/{recepcionMaterial}/confirmar', [RecepcionMaterialController::class, 'confirmar']);
        });
""",
    """        Route::middleware('can:gestionar-recepciones-materiales')->group(function () {
            Route::post('/', [RecepcionMaterialController::class, 'store']);
            Route::put('/{recepcionMaterial}', [RecepcionMaterialController::class, 'update']);
            Route::post('/{recepcionMaterial}/confirmar', [RecepcionMaterialController::class, 'confirmar']);
        });
""",
)

replace_once(
    "app/Http/Controllers/Api/RecepcionMaterialController.php",
    "use App\\Http\\Requests\\AnularRecepcionMaterialRequest;\n",
    "use App\\Http\\Requests\\ActualizarRecepcionMaterialRequest;\nuse App\\Http\\Requests\\AnularRecepcionMaterialRequest;\n",
)

replace_once(
    "app/Http/Controllers/Api/RecepcionMaterialController.php",
    """    public function confirmar(
        ConfirmarRecepcionMaterialRequest $request,
""",
    """    public function update(
        ActualizarRecepcionMaterialRequest $request,
        RecepcionMaterial $recepcionMaterial,
        ServicioRecepcionMaterial $servicio,
    ): RecepcionMaterialResource {
        abort_unless(
            $this->puedeOperarRecepcion($request, $recepcionMaterial),
            Response::HTTP_NOT_FOUND,
        );

        return new RecepcionMaterialResource($servicio->actualizar(
            $recepcionMaterial,
            $request->validated(),
            $request->user(),
        ));
    }

    public function confirmar(
        ConfirmarRecepcionMaterialRequest $request,
""",
)

for model_path in [
    "app/Models/DetalleRecepcionMaterial.php",
    "app/Models/BultoRecepcionMaterial.php",
]:
    replace_once(
        model_path,
        "use Illuminate\\Database\\Eloquent\\Model;\n",
        "use Illuminate\\Database\\Eloquent\\Model;\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;\n",
    )
    replace_once(
        model_path,
        "    use HasUuids, ImpideEliminacionFisica;\n",
        "    use HasUuids, ImpideEliminacionFisica, SoftDeletes;\n",
    )

service_method = r'''    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(
        RecepcionMaterial $recepcion,
        array $datos,
        User $usuario,
    ): RecepcionMaterial {
        $payloadHash = $this->payloadHash($datos);

        try {
            return DB::transaction(function () use (
                $recepcion,
                $datos,
                $usuario,
                $payloadHash,
            ): RecepcionMaterial {
                $eventoExistente = EventoRecepcionMaterial::query()
                    ->where('operacion_id', $datos['operacion_id'])
                    ->lockForUpdate()
                    ->first();

                if ($eventoExistente) {
                    $hashExistente = (string) data_get($eventoExistente->datos, 'payload_hash', '');
                    $mismaOperacion = $eventoExistente->recepcion_material_id === $recepcion->id
                        && $eventoExistente->tipo === TipoEventoRecepcionMaterial::Actualizada
                        && $eventoExistente->user_id === $usuario->id
                        && $hashExistente !== ''
                        && hash_equals($hashExistente, $payloadHash);

                    if (! $mismaOperacion) {
                        throw new ConflictoOperacion(
                            'El UUID de actualización ya fue utilizado con datos diferentes.',
                        );
                    }

                    return $this->cargar(RecepcionMaterial::query()->findOrFail($recepcion->id));
                }

                $recepcion = RecepcionMaterial::query()
                    ->with(['detalles.bultos.folioMaterial'])
                    ->lockForUpdate()
                    ->findOrFail($recepcion->id);

                if ($recepcion->estado !== EstadoRecepcionMaterial::Borrador) {
                    throw new DomainException('Solo una recepción en borrador puede editarse.');
                }

                if ($recepcion->version !== (int) $datos['version_conocida']) {
                    throw new ConflictoOperacion('La recepción cambió desde la última lectura.');
                }

                $temporada = $this->temporadaActiva->obtener(bloquear: true);

                if ($temporada->id !== $recepcion->temporada_id) {
                    throw new DomainException(
                        'La temporada de la recepción ya no es la temporada global activa.',
                    );
                }

                [$cliente, $proveedor, $vinculoProveedor] = $this->validarCabecera(
                    $datos['cliente_id'],
                    $datos['proveedor_material_id'],
                );
                $bultosActuales = $recepcion->detalles
                    ->flatMap(fn (DetalleRecepcionMaterial $detalle) => $detalle->bultos);

                if ($bultosActuales->contains(
                    fn (BultoRecepcionMaterial $bulto): bool => $bulto->folioMaterial !== null,
                )) {
                    throw new DomainException(
                        'La recepción ya posee inventario asociado y no puede volver a editarse.',
                    );
                }

                $versionAnterior = $recepcion->version;
                $ahora = now();
                $bultoIds = $bultosActuales->pluck('id');
                $detalleIds = $recepcion->detalles->pluck('id');

                if ($bultoIds->isNotEmpty()) {
                    BultoRecepcionMaterial::query()
                        ->whereIn('id', $bultoIds)
                        ->update([
                            'deleted_at' => $ahora,
                            'updated_at' => $ahora,
                        ]);
                }

                if ($detalleIds->isNotEmpty()) {
                    DetalleRecepcionMaterial::query()
                        ->whereIn('id', $detalleIds)
                        ->update([
                            'deleted_at' => $ahora,
                            'updated_at' => $ahora,
                        ]);
                }

                $recepcion->update([
                    'cliente_id' => $cliente->id,
                    'proveedor_material_id' => $proveedor->id,
                    'numero_guia_despacho' => trim($datos['numero_guia_despacho']),
                    'fecha_documento' => $datos['fecha_documento'] ?? null,
                    'orden_compra' => $this->textoOpcional($datos['orden_compra'] ?? null),
                    'patente' => $this->textoOpcional($datos['patente'] ?? null),
                    'transportista' => $this->textoOpcional($datos['transportista'] ?? null),
                    'observacion' => $this->textoOpcional($datos['observacion'] ?? null),
                    'version' => $versionAnterior + 1,
                ]);

                foreach ($datos['detalles'] as $linea) {
                    $this->crearDetalle(
                        $recepcion,
                        $linea,
                        $cliente,
                        $proveedor,
                        $vinculoProveedor,
                        $temporada->id,
                    );
                }

                $this->registrarEvento(
                    $recepcion,
                    TipoEventoRecepcionMaterial::Actualizada,
                    $usuario,
                    $datos['operacion_id'],
                    [
                        'payload_hash' => $payloadHash,
                        'version_anterior' => $versionAnterior,
                        'version_resultante' => $recepcion->version,
                        'cantidad_detalles' => count($datos['detalles']),
                        'cantidad_bultos' => collect($datos['detalles'])
                            ->sum(fn (array $linea): int => count($linea['bultos'] ?? [])),
                    ],
                );

                return $this->cargar($recepcion->refresh());
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $evento = EventoRecepcionMaterial::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->first();
            $hashExistente = (string) data_get($evento?->datos, 'payload_hash', '');

            if ($evento
                && $evento->recepcion_material_id === $recepcion->id
                && $evento->tipo === TipoEventoRecepcionMaterial::Actualizada
                && $evento->user_id === $usuario->id
                && $hashExistente !== ''
                && hash_equals($hashExistente, $payloadHash)) {
                return $this->cargar(RecepcionMaterial::query()->findOrFail($recepcion->id));
            }

            throw new ConflictoOperacion(
                'La actualización entró en conflicto con otra operación concurrente.',
                previous: $exception,
            );
        }
    }

'''
replace_once(
    "app/Services/Materiales/ServicioRecepcionMaterial.php",
    "    public function confirmar(\n",
    service_method + "    public function confirmar(\n",
)

replace_once(
    "mobile/src/domain/materialReception.ts",
    """export type CreateMaterialReceptionPayload = {
""",
    """export type CreateMaterialReceptionPayload = {
""",
)
Path("mobile/src/domain/materialReception.ts").write_text(
    Path("mobile/src/domain/materialReception.ts").read_text(encoding="utf-8")
    + "\nexport type UpdateMaterialReceptionPayload = CreateMaterialReceptionPayload & {\n  version_conocida: number;\n};\n",
    encoding="utf-8",
)

replace_once(
    "mobile/src/services/materialReceptionApi.ts",
    "  PendingReceptionFolio,\n",
    "  PendingReceptionFolio,\n  UpdateMaterialReceptionPayload,\n",
)
replace_once(
    "mobile/src/services/materialReceptionApi.ts",
    """    async confirm(id: string, operationId: string, knownVersion: number): Promise<MaterialReception> {
""",
    """    async update(id: string, payload: UpdateMaterialReceptionPayload): Promise<MaterialReception> {
      return (await request<ApiItem<MaterialReception>>(
        `/api/materiales/recepciones/${encodeURIComponent(id)}`,
        {
          method: 'PUT',
          body: JSON.stringify(payload),
        },
      )).data;
    },

    async confirm(id: string, operationId: string, knownVersion: number): Promise<MaterialReception> {
""",
)

screen = "mobile/src/screens/MaterialReceptionScreen.tsx"
replace_once(
    screen,
    "  const [operationId, setOperationId] = useState(() => Crypto.randomUUID());\n",
    "  const [operationId, setOperationId] = useState(() => Crypto.randomUUID());\n  const [editingDraft, setEditingDraft] = useState<{ id: string; version: number; guide: string } | null>(null);\n",
)
replace_once(
    screen,
    """  function switchTab(next: Tab) {
    setTab(next);
    setSelected(null);
    setError('');
    setMessage('');
    void refresh(next);
  }
""",
    """  function switchTab(next: Tab) {
    if (next === 'nueva') {
      setEditingDraft(null);
      setForm(emptyForm());
      setOperationId(Crypto.randomUUID());
    }
    setTab(next);
    setSelected(null);
    setError('');
    setMessage('');
    void refresh(next);
  }
""",
)
replace_once(
    screen,
    "      reception = await api.create(payload);\n",
    """      reception = editingDraft
        ? await api.update(editingDraft.id, {
          ...payload,
          version_conocida: editingDraft.version,
        })
        : await api.create(payload);
""",
)
replace_once(
    screen,
    """      setForm(emptyForm());
      setOperationId(Crypto.randomUUID());
      setFilter('todas');
""",
    """      setForm(emptyForm());
      setEditingDraft(null);
      setOperationId(Crypto.randomUUID());
      setFilter('todas');
""",
)
replace_once(
    screen,
    """      setMessage(confirmImmediately
        ? 'Recepción confirmada. Los folios quedaron disponibles para ubicación.'
        : 'Borrador guardado correctamente.');
""",
    """      setMessage(confirmImmediately
        ? 'Recepción confirmada. Los folios quedaron disponibles para ubicación.'
        : editingDraft
          ? 'Borrador actualizado correctamente.'
          : 'Borrador guardado correctamente.');
""",
)
replace_once(
    screen,
    """        setForm(emptyForm());
        setOperationId(Crypto.randomUUID());
        setFilter('todas');
""",
    """        setForm(emptyForm());
        setEditingDraft(null);
        setOperationId(Crypto.randomUUID());
        setFilter('todas');
""",
)
replace_once(
    screen,
    """        } else {
          setMessage('Borrador guardado correctamente.');
        }
""",
    """        } else {
          setMessage(editingDraft
            ? 'Borrador actualizado correctamente.'
            : 'Borrador guardado correctamente.');
        }
""",
)
replace_once(
    screen,
    """  function requestConfirm(reception: MaterialReception) {
""",
    """  async function editDraft(reception: MaterialReception) {
    setActionBusy(true);
    setError('');
    setMessage('');
    try {
      const [draft, loadedCatalog] = await Promise.all([
        api.show(reception.id),
        api.catalog(),
      ]);
      if (draft.estado !== 'borrador') {
        throw new Error('La recepción ya no se encuentra en borrador.');
      }
      setCatalog(loadedCatalog);
      setForm(formFromReception(draft));
      setEditingDraft({ id: draft.id, version: draft.version, guide: draft.numero_guia_despacho });
      setOperationId(Crypto.randomUUID());
      setSelected(null);
      setTab('nueva');
      setMessage(`Editando borrador de la guía ${draft.numero_guia_despacho}.`);
    } catch (reason) {
      setError(errorMessage(reason));
    } finally {
      setActionBusy(false);
    }
  }

  function requestConfirm(reception: MaterialReception) {
""",
)
replace_once(
    screen,
    """        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {!catalog.temporada ? (
""",
    """        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {editingDraft ? (
            <View style={styles.infoCard}>
              <View style={styles.between}>
                <View style={styles.headerCopy}>
                  <Text style={styles.eyebrow}>EDITANDO BORRADOR</Text>
                  <Text style={styles.cardTitle}>Guía {editingDraft.guide} · versión {editingDraft.version}</Text>
                  <Text style={styles.muted}>Los cambios reemplazarán el contenido provisional. Los folios se crearán únicamente al confirmar.</Text>
                </View>
                <Button label="Cancelar edición" onPress={() => switchTab('nueva')} secondary />
              </View>
            </View>
          ) : null}
          {!catalog.temporada ? (
""",
)
replace_once(
    screen,
    """                  <Button label="Guardar borrador" onPress={() => void submit(false)} secondary />
                  <Button label="Crear y confirmar" onPress={() => void submit(true)} />
""",
    """                  <Button label={editingDraft ? 'Actualizar borrador' : 'Guardar borrador'} onPress={() => void submit(false)} secondary />
                  <Button label={editingDraft ? 'Actualizar y confirmar' : 'Crear y confirmar'} onPress={() => void submit(true)} />
""",
)
replace_once(
    screen,
    """             onBack={() => setSelected(null)}
             onConfirm={() => requestConfirm(selected)}
""",
    """             onBack={() => setSelected(null)}
             onEdit={() => void editDraft(selected)}
             onConfirm={() => requestConfirm(selected)}
""",
)
replace_once(
    screen,
    """  onBack,
  onConfirm,
""",
    """  onBack,
  onEdit,
  onConfirm,
""",
)
replace_once(
    screen,
    """  onBack: () => void;
  onConfirm: () => void;
""",
    """  onBack: () => void;
  onEdit: () => void;
  onConfirm: () => void;
""",
)
replace_once(
    screen,
    """      {reception.estado === 'borrador' && canManage ? <Button label="Confirmar y generar folios" onPress={onConfirm} /> : null}
""",
    """      {reception.estado === 'borrador' && canManage ? (
        <View style={styles.row}>
          <Button label="Editar borrador" onPress={onEdit} secondary />
          <Button label="Confirmar y generar folios" onPress={onConfirm} />
        </View>
      ) : null}
""",
)
replace_once(
    screen,
    """function emptyForm(): Form {
""",
    """function formFromReception(reception: MaterialReception): Form {
  return {
    cliente_id: reception.cliente?.id ?? '',
    proveedor_material_id: reception.proveedor?.id ?? '',
    numero_guia_despacho: reception.numero_guia_despacho,
    fecha_documento: reception.fecha_documento ?? '',
    orden_compra: reception.orden_compra ?? '',
    patente: reception.patente ?? '',
    transportista: reception.transportista ?? '',
    observacion: reception.observacion ?? '',
    detalles: (reception.detalles ?? []).map((detail) => ({
      local_id: Crypto.randomUUID(),
      item_material_id: detail.item?.id ?? '',
      cantidad_documental: detail.cantidad_documental,
      cantidad_contada: detail.cantidad_contada,
      observacion: detail.observacion ?? '',
      bultos: detail.bultos.map((itemPackage) => ({
        local_id: Crypto.randomUUID(),
        cantidad: itemPackage.cantidad,
        lote_proveedor: itemPackage.lote_proveedor ?? '',
        fecha_fabricacion: itemPackage.fecha_fabricacion ?? '',
        fecha_vencimiento: itemPackage.fecha_vencimiento ?? '',
        bloqueado: itemPackage.bloqueado,
        motivo_bloqueo: itemPackage.motivo_bloqueo ?? '',
      })),
    })),
  };
}

function emptyForm(): Form {
""",
)

# Regression test: insert before the existing annulment test.
test_method = r'''    public function test_borrador_puede_reabrirse_actualizarse_y_confirmarse(): void
    {
        [, $token, $cliente, $proveedor, $item] = $this->prepararCatalogo();
        $creada = $this->conToken($token)
            ->postJson('/api/materiales/recepciones', $this->payloadRecepcion(
                $cliente,
                $proveedor,
                $item,
                [
                    ['cantidad' => 6, 'lote_proveedor' => 'LOTE-ORIGINAL-01'],
                    ['cantidad' => 4, 'lote_proveedor' => 'LOTE-ORIGINAL-02'],
                ],
            ))
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.version', 1)
            ->json('data');
        $detalleAnterior = $creada['detalles'][0]['id'];
        $bultosAnteriores = collect($creada['detalles'][0]['bultos'])->pluck('id')->all();
        $actualizacion = $this->payloadRecepcion(
            $cliente,
            $proveedor,
            $item,
            [['cantidad' => 7, 'lote_proveedor' => 'LOTE-CORREGIDO-01']],
        );
        $actualizacion['version_conocida'] = 1;
        $actualizacion['numero_guia_despacho'] = 'GD-REC-EDITADA';
        $actualizacion['observacion'] = 'Borrador corregido antes de crear inventario.';
        $actualizacion['detalles'][0]['cantidad_documental'] = 8;
        $actualizacion['detalles'][0]['cantidad_contada'] = 8;
        $actualizacion['detalles'][0]['cantidad_aceptada'] = 7;
        $actualizacion['detalles'][0]['cantidad_recibida'] = 7;
        $actualizacion['detalles'][0]['cantidad_rechazada'] = 1;

        $editada = $this->conToken($token)
            ->putJson("/api/materiales/recepciones/{$creada['id']}", $actualizacion)
            ->assertOk()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.numero_guia_despacho', 'GD-REC-EDITADA')
            ->assertJsonPath('data.observacion', 'Borrador corregido antes de crear inventario.')
            ->assertJsonPath('data.detalles.0.cantidad_documental', '8.000')
            ->assertJsonPath('data.detalles.0.cantidad_contada', '8.000')
            ->assertJsonPath('data.detalles.0.cantidad_aceptada', '7.000')
            ->assertJsonPath('data.detalles.0.cantidad_rechazada', '1.000')
            ->assertJsonCount(1, 'data.detalles.0.bultos')
            ->assertJsonPath('data.detalles.0.bultos.0.lote_proveedor', 'LOTE-CORREGIDO-01')
            ->assertJsonPath('data.eventos.1.tipo', 'actualizada')
            ->json('data');

        $this->conToken($token)
            ->putJson("/api/materiales/recepciones/{$creada['id']}", $actualizacion)
            ->assertOk()
            ->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('detalles_recepciones_materiales', [
            'id' => $detalleAnterior,
            'deleted_at' => DB::raw('IS NOT NULL'),
        ]);
        foreach ($bultosAnteriores as $bultoId) {
            $this->assertNotNull(DB::table('bultos_recepciones_materiales')
                ->where('id', $bultoId)
                ->value('deleted_at'));
        }
        $this->assertSame(1, DB::table('detalles_recepciones_materiales')
            ->where('recepcion_material_id', $creada['id'])
            ->whereNull('deleted_at')
            ->count());
        $this->assertSame(1, DB::table('bultos_recepciones_materiales')
            ->whereNull('deleted_at')
            ->count());

        $actualizacionObsoleta = $actualizacion;
        $actualizacionObsoleta['operacion_id'] = (string) Str::uuid();
        $actualizacionObsoleta['numero_guia_despacho'] = 'GD-REC-OBSOLETA';
        $this->conToken($token)
            ->putJson("/api/materiales/recepciones/{$creada['id']}", $actualizacionObsoleta)
            ->assertConflict();

        $this->conToken($token)
            ->postJson("/api/materiales/recepciones/{$creada['id']}/confirmar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => $editada['version'],
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.version', 3)
            ->assertJsonCount(1, 'data.detalles.0.bultos')
            ->assertJsonPath('data.detalles.0.bultos.0.folio.numero_folio', 'FGE0000001');
        $this->assertSame(1, Folio::query()
            ->where('origen_sistema', 'recepcion_materiales')
            ->count());
        $this->assertDatabaseHas('folios_materiales', [
            'cantidad_inicial' => 7,
            'lote' => 'LOTE-CORREGIDO-01',
        ]);
        $this->assertDatabaseCount('eventos_recepciones_materiales', 3);
    }

'''
replace_once(
    "tests/Feature/Api/RecepcionMaterialApiTest.php",
    "    public function test_anulacion_intacta_compensa_saldos_y_es_idempotente(): void\n",
    test_method + "    public function test_anulacion_intacta_compensa_saldos_y_es_idempotente(): void\n",
)

# Fix the nullable assertion with a portable query instead of a raw value matcher.
test_file = Path("tests/Feature/Api/RecepcionMaterialApiTest.php")
test_text = test_file.read_text(encoding="utf-8")
test_text = test_text.replace(
    """        $this->assertDatabaseHas('detalles_recepciones_materiales', [
            'id' => $detalleAnterior,
            'deleted_at' => DB::raw('IS NOT NULL'),
        ]);
""",
    """        $this->assertNotNull(DB::table('detalles_recepciones_materiales')
            ->where('id', $detalleAnterior)
            ->value('deleted_at'));
""",
)
test_file.write_text(test_text, encoding="utf-8")

print("Corrección de borradores aplicada.")
