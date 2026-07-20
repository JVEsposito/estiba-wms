<?php

namespace App\Services\Materiales;

use App\Models\ImportacionCatalogoMaterial;
use App\Models\ItemMaterial;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioImportacionCatalogoMaterial
{
    private const MAX_FILAS = 5000;

    public function __construct(
        private readonly LectorPlanillaMaterial $lector,
    ) {}

    public function previsualizar(UploadedFile $archivo, User $usuario): ImportacionCatalogoMaterial
    {
        $filasLeidas = $this->lector->leer($archivo);

        if (count($filasLeidas) > self::MAX_FILAS) {
            throw new DomainException('La planilla supera el máximo de 5.000 filas permitidas.');
        }

        $codigos = collect($filasLeidas)
            ->map(fn (array $fila): string => mb_strtoupper($this->texto($fila['codigo'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        $codigosExternos = collect($filasLeidas)
            ->map(fn (array $fila): string => $this->texto($fila['codigo_externo'] ?? ''))
            ->filter()
            ->unique()
            ->values();

        $existentesPorCodigo = ItemMaterial::query()
            ->withCount('foliosMateriales')
            ->whereIn('codigo', $codigos)
            ->get()
            ->keyBy(fn (ItemMaterial $item): string => mb_strtolower($item->codigo));
        $existentesPorExterno = ItemMaterial::query()
            ->whereIn('codigo_externo', $codigosExternos)
            ->get()
            ->keyBy(fn (ItemMaterial $item): string => mb_strtolower((string) $item->codigo_externo));

        $filas = [];
        $errores = [];
        $codigosVistos = [];
        $externosVistos = [];

        foreach ($filasLeidas as $filaLeida) {
            $fila = $this->normalizarFila($filaLeida);
            $mensajes = $this->validarFila($fila);
            $claveCodigo = mb_strtolower((string) $fila['codigo']);
            $claveExterno = mb_strtolower((string) ($fila['codigo_externo'] ?? ''));

            if ($claveCodigo !== '' && isset($codigosVistos[$claveCodigo])) {
                $mensajes[] = sprintf('El código ya fue declarado en la fila %d.', $codigosVistos[$claveCodigo]);
            } elseif ($claveCodigo !== '') {
                $codigosVistos[$claveCodigo] = $fila['fila'];
            }

            if ($claveExterno !== '' && isset($externosVistos[$claveExterno])) {
                $mensajes[] = sprintf('El código externo ya fue declarado en la fila %d.', $externosVistos[$claveExterno]);
            } elseif ($claveExterno !== '') {
                $externosVistos[$claveExterno] = $fila['fila'];
            }

            /** @var ItemMaterial|null $existente */
            $existente = $existentesPorCodigo->get($claveCodigo);
            /** @var ItemMaterial|null $duenoCodigoExterno */
            $duenoCodigoExterno = $claveExterno === '' ? null : $existentesPorExterno->get($claveExterno);

            if ($duenoCodigoExterno && (! $existente || $duenoCodigoExterno->id !== $existente->id)) {
                $mensajes[] = 'El código externo ya pertenece al ítem '.$duenoCodigoExterno->codigo.'.';
            }

            if ($existente
                && $existente->unidad_medida !== $fila['unidad_medida']
                && $existente->folios_materiales_count > 0) {
                $mensajes[] = 'La unidad de medida no puede cambiar porque el ítem ya posee folios asociados.';
            }

            if ($mensajes !== []) {
                $errores[] = [
                    'fila' => $fila['fila'],
                    'codigo' => $fila['codigo'],
                    'mensaje' => implode(' ', array_unique($mensajes)),
                ];

                continue;
            }

            $fila['accion'] = $this->accionEstimada($fila, $existente);
            $fila['huella_catalogo'] = $existente ? $this->huellaItem($existente) : null;
            $filas[] = $fila;
        }

        if ($filasLeidas === []) {
            $errores[] = [
                'fila' => 1,
                'codigo' => null,
                'mensaje' => 'La planilla no contiene filas de datos.',
            ];
        }

        $resumen = $this->resumen($filasLeidas, $filas, $errores);

        return ImportacionCatalogoMaterial::create([
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'tipo_archivo' => mb_strtolower($archivo->getClientOriginalExtension()),
            'checksum' => hash_file('sha256', $archivo->getRealPath()),
            'estado' => $errores === [] ? 'borrador' : 'con_errores',
            'resumen' => $resumen,
            'filas' => $filas,
            'errores' => $errores !== [] ? $errores : null,
            'creado_por_user_id' => $usuario->id,
        ])->load(['creadoPor:id,name', 'confirmadoPor:id,name']);
    }

    public function confirmar(
        ImportacionCatalogoMaterial $importacion,
        User $usuario,
    ): ImportacionCatalogoMaterial {
        return DB::transaction(function () use ($importacion, $usuario): ImportacionCatalogoMaterial {
            $importacion = ImportacionCatalogoMaterial::query()
                ->lockForUpdate()
                ->findOrFail($importacion->id);

            if ($importacion->estado === 'confirmada') {
                return $importacion->load(['creadoPor:id,name', 'confirmadoPor:id,name']);
            }

            if (($importacion->errores ?? []) !== []) {
                throw new DomainException('La importación posee errores y no puede confirmarse.');
            }

            $creados = 0;
            $actualizados = 0;
            $sinCambios = 0;

            foreach ($importacion->filas as $fila) {
                $item = ItemMaterial::query()
                    ->where('codigo', $fila['codigo'])
                    ->lockForUpdate()
                    ->first();

                $this->validarHuellaCatalogo($fila, $item);

                if ($item
                    && $item->unidad_medida !== $fila['unidad_medida']
                    && $item->foliosMateriales()->exists()) {
                    throw new DomainException(
                        'La unidad de '.$item->codigo.' cambió después de la previsualización y ya posee folios asociados.',
                    );
                }

                if (($fila['codigo_externo'] ?? null) !== null) {
                    $codigoExternoOcupado = ItemMaterial::query()
                        ->where('codigo_externo', $fila['codigo_externo'])
                        ->when($item, fn ($consulta) => $consulta->where('id', '!=', $item->id))
                        ->lockForUpdate()
                        ->first();

                    if ($codigoExternoOcupado) {
                        throw new DomainException(
                            'El código externo de '.$fila['codigo'].' fue asignado después de la previsualización.',
                        );
                    }
                }

                $nuevo = $item === null;
                $item ??= new ItemMaterial(['codigo' => $fila['codigo']]);
                $datos = [
                    'nombre' => $fila['nombre'],
                    'unidad_medida' => $fila['unidad_medida'],
                ];

                if ($nuevo || ($fila['categoria'] ?? null) !== null) {
                    $datos['categoria'] = $fila['categoria'];
                }
                if ($nuevo || ($fila['codigo_externo'] ?? null) !== null) {
                    $datos['codigo_externo'] = $fila['codigo_externo'];
                }
                if ($nuevo || ($fila['activo'] ?? null) !== null) {
                    $datos['activo'] = $fila['activo'] ?? true;
                }

                $item->fill($datos);
                $cambio = $nuevo || $item->isDirty();

                if (! $cambio) {
                    $sinCambios++;

                    continue;
                }

                $item->fill([
                    'origen_sistema' => 'importacion_catalogo',
                    'sincronizado_at' => now(),
                    'actualizado_por_user_id' => $usuario->id,
                ]);
                if ($nuevo) {
                    $item->creado_por_user_id = $usuario->id;
                }
                $item->save();
                $nuevo ? $creados++ : $actualizados++;
            }

            $importacion->update([
                'estado' => 'confirmada',
                'resumen' => [
                    ...$importacion->resumen,
                    'creados' => $creados,
                    'actualizados' => $actualizados,
                    'sin_cambios' => $sinCambios,
                ],
                'confirmado_por_user_id' => $usuario->id,
                'confirmado_at' => now(),
            ]);

            return $importacion->refresh()
                ->load(['creadoPor:id,name', 'confirmadoPor:id,name']);
        }, attempts: 3);
    }

    /**
     * @param  array<string, string|int>  $fila
     * @return array<string, string|int|bool|null>
     */
    private function normalizarFila(array $fila): array
    {
        return [
            'fila' => (int) $fila['fila'],
            'codigo' => mb_strtoupper($this->texto($fila['codigo'] ?? '')),
            'nombre' => $this->texto($fila['nombre'] ?? ''),
            'categoria' => $this->opcional($fila['categoria'] ?? ''),
            'unidad_medida' => mb_strtolower($this->texto($fila['unidad_medida'] ?? '')),
            'codigo_externo' => $this->opcional($fila['codigo_externo'] ?? ''),
            'activo' => $this->activo($fila['activo'] ?? ''),
            'activo_original' => $this->texto($fila['activo'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string|int|bool|null>  $fila
     * @return array<int, string>
     */
    private function validarFila(array $fila): array
    {
        $errores = [];

        if ($fila['codigo'] === '') {
            $errores[] = 'Falta el código.';
        } elseif (mb_strlen((string) $fila['codigo']) > 80
            || preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', (string) $fila['codigo']) !== 1) {
            $errores[] = 'El código debe usar hasta 80 caracteres: letras, números, punto, guion o guion bajo.';
        }
        if (mb_strlen((string) $fila['nombre']) < 3 || mb_strlen((string) $fila['nombre']) > 180) {
            $errores[] = 'El nombre debe contener entre 3 y 180 caracteres.';
        }
        if (mb_strlen((string) ($fila['categoria'] ?? '')) > 100) {
            $errores[] = 'La categoría admite hasta 100 caracteres.';
        }
        if ($fila['unidad_medida'] === '' || mb_strlen((string) $fila['unidad_medida']) > 40) {
            $errores[] = 'La unidad de medida es obligatoria y admite hasta 40 caracteres.';
        }
        if (mb_strlen((string) ($fila['codigo_externo'] ?? '')) > 150) {
            $errores[] = 'El código externo admite hasta 150 caracteres.';
        }
        if ($fila['activo_original'] !== '' && $fila['activo'] === null) {
            $errores[] = 'Activo debe indicar sí/no, activo/inactivo, 1/0 o verdadero/falso.';
        }

        return $errores;
    }

    /**
     * @param  array<string, string|int|bool|null>  $fila
     */
    private function accionEstimada(array $fila, ?ItemMaterial $existente): string
    {
        if (! $existente) {
            return 'crear';
        }

        $cambio = $existente->nombre !== $fila['nombre']
            || $existente->unidad_medida !== $fila['unidad_medida']
            || (($fila['categoria'] ?? null) !== null && $existente->categoria !== $fila['categoria'])
            || (($fila['codigo_externo'] ?? null) !== null && $existente->codigo_externo !== $fila['codigo_externo'])
            || (($fila['activo'] ?? null) !== null && $existente->activo !== $fila['activo']);

        return $cambio ? 'actualizar' : 'sin_cambios';
    }

    /**
     * @param  array<string, string|int|bool|null>  $fila
     */
    private function validarHuellaCatalogo(array $fila, ?ItemMaterial $item): void
    {
        if (! array_key_exists('huella_catalogo', $fila)) {
            throw new DomainException(
                'La previsualización del ítem '.$fila['codigo'].' ya no es vigente. Vuelve a previsualizar la planilla.',
            );
        }

        $huellaEsperada = $fila['huella_catalogo'] ?? null;
        $catalogoCambio = ($huellaEsperada === null && $item !== null)
            || ($huellaEsperada !== null && (
                ! is_string($huellaEsperada)
                || $item === null
                || ! hash_equals($huellaEsperada, $this->huellaItem($item))
            ));

        if ($catalogoCambio) {
            throw new DomainException(
                'El catálogo cambió después de la previsualización para el ítem '.$fila['codigo']
                .'. Vuelve a previsualizar la planilla.',
            );
        }
    }

    private function huellaItem(ItemMaterial $item): string
    {
        return hash('sha256', json_encode([
            'id' => (string) $item->id,
            'codigo' => $item->codigo,
            'nombre' => $item->nombre,
            'categoria' => $item->categoria,
            'unidad_medida' => $item->unidad_medida,
            'codigo_externo' => $item->codigo_externo,
            'activo' => (bool) $item->activo,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function texto(mixed $valor): string
    {
        return Str::of((string) $valor)->squish()->toString();
    }

    private function opcional(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        return $texto === '' ? null : $texto;
    }

    private function activo(mixed $valor): ?bool
    {
        $texto = Str::of((string) $valor)->ascii()->lower()->trim()->toString();

        return match ($texto) {
            '' => null,
            '1', 'si', 's', 'true', 'verdadero', 'activo' => true,
            '0', 'no', 'n', 'false', 'falso', 'inactivo' => false,
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, string|int>>  $leidas
     * @param  array<int, array<string, string|int|bool|null>>  $filas
     * @param  array<int, array<string, string|int|null>>  $errores
     * @return array<string, int>
     */
    private function resumen(array $leidas, array $filas, array $errores): array
    {
        $acciones = collect($filas)->countBy('accion');

        return [
            'filas_leidas' => count($leidas),
            'filas_validas' => count($filas),
            'filas_con_error' => count($errores),
            'nuevos_estimados' => (int) $acciones->get('crear', 0),
            'actualizaciones_estimadas' => (int) $acciones->get('actualizar', 0),
            'sin_cambios_estimados' => (int) $acciones->get('sin_cambios', 0),
        ];
    }
}
