from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    file_path = Path(path)
    content = file_path.read_text(encoding="utf-8")
    if old not in content:
        raise SystemExit(f"No se encontró el bloque esperado en {path}")
    file_path.write_text(content.replace(old, new, 1), encoding="utf-8")


service = "app/Services/Materiales/ServicioPrevisualizacionImportacionRecepcionMaterial.php"
replace_once(
    service,
    """            $tamanoBulto = $this->decimal($filaLeida['unidades_por_bulto'] ?? '');

            if ($aceptada === null || $aceptada < 0) {
""",
    """            $tamanoBulto = $this->decimal($filaLeida['unidades_por_bulto'] ?? '');

            foreach ([
                'La cantidad documental' => $filaLeida['cantidad_documental'] ?? '',
                'La cantidad contada' => $filaLeida['cantidad_contada'] ?? '',
                'La cantidad aceptada' => $filaLeida['cantidad_aceptada'] ?? '',
                'La cantidad rechazada' => $filaLeida['cantidad_rechazada'] ?? '',
                'Las unidades por bulto' => $filaLeida['unidades_por_bulto'] ?? '',
            ] as $etiqueta => $valor) {
                if ($this->excedeTresDecimales($valor)) {
                    $mensajes[] = $etiqueta.' admite como máximo 3 decimales.';
                }
            }

            if ($aceptada === null || $aceptada < 0) {
""",
)
replace_once(
    service,
    """            $motivoBloqueo = $this->opcional($filaLeida['motivo_bloqueo'] ?? '');
            if ($bloqueado && ! $motivoBloqueo) {
                $mensajes[] = 'Un producto bloqueado debe indicar el motivo del bloqueo.';
            }

            $fechaFabricacion = $this->fecha($filaLeida['fecha_fabricacion'] ?? '');
""",
    """            $motivoBloqueo = $this->opcional($filaLeida['motivo_bloqueo'] ?? '');
            if ($bloqueado && ! $motivoBloqueo) {
                $mensajes[] = 'Un producto bloqueado debe indicar el motivo del bloqueo.';
            }
            if ($motivoBloqueo !== null && mb_strlen($motivoBloqueo) > 2000) {
                $mensajes[] = 'El motivo del bloqueo no puede superar 2000 caracteres.';
            }

            $fechaFabricacion = $this->fecha($filaLeida['fecha_fabricacion'] ?? '');
""",
)
replace_once(
    service,
    """            if ($fechaFabricacion && $fechaVencimiento && $fechaVencimiento < $fechaFabricacion) {
                $mensajes[] = 'La fecha de vencimiento no puede ser anterior a la fabricación.';
            }

            $bultos = [];
""",
    """            if ($fechaFabricacion && $fechaVencimiento && $fechaVencimiento < $fechaFabricacion) {
                $mensajes[] = 'La fecha de vencimiento no puede ser anterior a la fabricación.';
            }

            $loteProveedor = $this->opcional($filaLeida['lote_proveedor'] ?? '');
            $observacion = $this->opcional($filaLeida['observacion'] ?? '');
            if ($loteProveedor !== null && mb_strlen($loteProveedor) > 100) {
                $mensajes[] = 'El lote del proveedor no puede superar 100 caracteres.';
            }
            if ($observacion !== null && mb_strlen($observacion) > 2000) {
                $mensajes[] = 'La observación no puede superar 2000 caracteres.';
            }

            $bultos = [];
""",
)
replace_once(
    service,
    """                    $tamanoBulto,
                    $this->opcional($filaLeida['lote_proveedor'] ?? ''),
                    $fechaFabricacion,
""",
    """                    $tamanoBulto,
                    $loteProveedor,
                    $fechaFabricacion,
""",
)
replace_once(
    service,
    """                'unidades_por_bulto' => $tamanoBulto,
                'observacion' => $this->opcional($filaLeida['observacion'] ?? ''),
                'bultos' => $bultos,
""",
    """                'unidades_por_bulto' => $tamanoBulto,
                'observacion' => $observacion,
                'bultos' => $bultos,
""",
)
replace_once(
    service,
    """    private function decimal(mixed $valor): ?float
    {
        $texto = $this->texto($valor);
        if ($texto === '') {
            return null;
        }

        $texto = str_replace(["\\u{00A0}", ' ', "'"], '', $texto);
        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimaComa !== false) {
            $texto = str_replace(',', '.', $texto);
        }

        return is_numeric($texto) ? round((float) $texto, 3) : null;
    }

    private function booleano(mixed $valor): ?bool
""",
    """    private function decimal(mixed $valor): ?float
    {
        $texto = $this->normalizarDecimal($valor);

        return $texto !== null ? round((float) $texto, 3) : null;
    }

    private function excedeTresDecimales(mixed $valor): bool
    {
        $texto = $this->normalizarDecimal($valor);
        if ($texto === null) {
            return false;
        }

        $numero = (float) $texto;

        return abs($numero - round($numero, 3)) > 0.0000001;
    }

    private function normalizarDecimal(mixed $valor): ?string
    {
        $texto = $this->texto($valor);
        if ($texto === '') {
            return null;
        }

        $texto = str_replace(["\\u{00A0}", ' ', "'"], '', $texto);
        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimaComa !== false) {
            $texto = str_replace(',', '.', $texto);
        }

        return is_numeric($texto) ? $texto : null;
    }

    private function booleano(mixed $valor): ?bool
""",
)

js = "resources/js/office-material-reception-import.js"
replace_once(
    js,
    """const receptionImportState = {
    preview: null,
};
""",
    """const receptionImportState = {
    preview: null,
    previewFingerprint: null,
    requestSequence: 0,
};
""",
)
replace_once(
    js,
    """function receptionImportToken() {
    return localStorage.getItem('estiba_wms_office_token');
}

function receptionImportError(data, fallback) {
""",
    """function receptionImportToken() {
    return localStorage.getItem('estiba_wms_office_token');
}

function receptionImportSelectedFile(form) {
    return form.elements.archivo.files?.[0] || null;
}

function receptionImportFileFingerprint(file) {
    return file ? `${file.name}:${file.size}:${file.lastModified}` : null;
}

function receptionImportInvalidate(elements) {
    receptionImportState.preview = null;
    receptionImportState.previewFingerprint = null;
    receptionImportState.requestSequence += 1;
    elements.preview.classList.add('is-hidden');
    elements.apply.disabled = true;
}

function receptionImportError(data, fallback) {
""",
)
replace_once(
    js,
    """async function receptionImportPreview(elements) {
    const contextApi = window.estibaMaterialReceptionImportContext;
    const context = contextApi?.context();
    if (!context?.clienteId || !context?.proveedorId) {
        throw new Error('Selecciona primero el cliente y el proveedor de la recepción.');
    }

    const formData = new FormData(elements.form);
""",
    """async function receptionImportPreview(elements) {
    const contextApi = window.estibaMaterialReceptionImportContext;
    const context = contextApi?.context();
    if (!context?.clienteId || !context?.proveedorId) {
        throw new Error('Selecciona primero el cliente y el proveedor de la recepción.');
    }

    const file = receptionImportSelectedFile(elements.form);
    if (!file) {
        throw new Error('Selecciona una planilla CSV o XLSX.');
    }
    const fingerprint = receptionImportFileFingerprint(file);
    receptionImportInvalidate(elements);
    const requestSequence = ++receptionImportState.requestSequence;
    const formData = new FormData(elements.form);
""",
)
replace_once(
    js,
    """    if (!response.ok) {
        throw new Error(receptionImportError(payload, 'No fue posible leer la planilla.'));
    }

    receptionImportRender(elements, payload.data || {});
}
""",
    """    if (!response.ok) {
        throw new Error(receptionImportError(payload, 'No fue posible leer la planilla.'));
    }
    if (requestSequence !== receptionImportState.requestSequence
        || fingerprint !== receptionImportFileFingerprint(receptionImportSelectedFile(elements.form))) {
        return;
    }

    receptionImportState.previewFingerprint = fingerprint;
    receptionImportRender(elements, payload.data || {});
}
""",
)
replace_once(
    js,
    """        elements.form.reset();
        elements.form.elements.reemplazar.checked = true;
        elements.preview.classList.add('is-hidden');
        receptionImportState.preview = null;
        elements.dialog.showModal();
""",
    """        elements.form.reset();
        elements.form.elements.reemplazar.checked = true;
        receptionImportInvalidate(elements);
        elements.dialog.showModal();
""",
)
replace_once(
    js,
    """    elements.close.addEventListener('click', () => elements.dialog.close());
    elements.template.addEventListener('click', receptionImportDownloadTemplate);
    elements.form.addEventListener('submit', async (event) => {
""",
    """    elements.close.addEventListener('click', () => elements.dialog.close());
    elements.template.addEventListener('click', receptionImportDownloadTemplate);
    elements.form.elements.archivo.addEventListener('change', () => {
        receptionImportInvalidate(elements);
        elements.error.textContent = '';
    });
    elements.form.addEventListener('submit', async (event) => {
""",
)
replace_once(
    js,
    """        try {
            const rows = receptionImportState.preview?.filas || [];
            const replace = elements.form.elements.reemplazar.checked;
""",
    """        try {
            const fingerprint = receptionImportFileFingerprint(receptionImportSelectedFile(elements.form));
            if (!receptionImportState.preview
                || !fingerprint
                || receptionImportState.previewFingerprint !== fingerprint) {
                throw new Error('La planilla seleccionada cambió; vuelve a previsualizarla antes de cargar.');
            }
            const rows = receptionImportState.preview.filas || [];
            const replace = elements.form.elements.reemplazar.checked;
""",
)

api_test = "tests/Feature/Api/ImportacionProductosRecepcionMaterialApiTest.php"
replace_once(
    api_test,
    """    /**
     * @return array{User, Cliente, ProveedorMaterial}
     */
    private function prepararCatalogo(): array
""",
    """    public function test_previsualizacion_rechaza_precision_y_textos_que_el_guardado_no_admite(): void
    {
        [$administrador, $cliente, $proveedor] = $this->prepararCatalogo();
        $lote = str_repeat('L', 101);
        $motivo = str_repeat('M', 2001);
        $observacion = str_repeat('O', 2001);
        $contenido = "codigo_item;cantidad_aceptada;cantidad_rechazada;cantidad_contada;unidades_por_bulto;lote_proveedor;bloqueado;motivo_bloqueo;observacion\\n".
            "FILM-REC;10,0004;0;10,0004;5;{$lote};si;{$motivo};{$observacion}\\n";

        $respuesta = $this->actingAs($administrador, 'sanctum')
            ->post('/api/materiales/recepciones/importaciones/previsualizar', [
                'archivo' => UploadedFile::fake()->createWithContent('recepcion-limites.csv', $contenido),
                'cliente_id' => $cliente->id,
                'proveedor_material_id' => $proveedor->id,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.resumen.filas_leidas', 1)
            ->assertJsonPath('data.resumen.filas_validas', 0)
            ->assertJsonPath('data.resumen.filas_con_error', 1)
            ->assertJsonCount(0, 'data.filas');

        $mensaje = (string) $respuesta->json('data.errores.0.mensaje');
        $this->assertStringContainsString('La cantidad aceptada admite como máximo 3 decimales.', $mensaje);
        $this->assertStringContainsString('La cantidad contada admite como máximo 3 decimales.', $mensaje);
        $this->assertStringContainsString('El lote del proveedor no puede superar 100 caracteres.', $mensaje);
        $this->assertStringContainsString('El motivo del bloqueo no puede superar 2000 caracteres.', $mensaje);
        $this->assertStringContainsString('La observación no puede superar 2000 caracteres.', $mensaje);
        $this->assertDatabaseCount('recepciones_materiales', 0);
        $this->assertDatabaseCount('folios', 0);
    }

    /**
     * @return array{User, Cliente, ProveedorMaterial}
     */
    private function prepararCatalogo(): array
""",
)

ui_test = Path("tests/Feature/InterfazImportacionRecepcionMaterialesTest.php")
ui_test.write_text(
    """<?php

namespace Tests\\Feature;

use Tests\\TestCase;

class InterfazImportacionRecepcionMaterialesTest extends TestCase
{
    public function test_la_previsualizacion_se_invalida_al_cambiar_la_planilla(): void
    {
        $script = file_get_contents(resource_path('js/office-material-reception-import.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('previewFingerprint', $script);
        $this->assertStringContainsString('requestSequence', $script);
        $this->assertStringContainsString(
            "elements.form.elements.archivo.addEventListener('change'",
            $script,
        );
        $this->assertStringContainsString(
            'La planilla seleccionada cambió; vuelve a previsualizarla antes de cargar.',
            $script,
        );
    }
}
""",
    encoding="utf-8",
)
