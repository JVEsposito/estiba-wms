<?php

namespace App\Http\Requests;

use App\Models\ItemMaterial;
use App\Models\ProveedorMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarProveedorMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-catalogos-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $proveedor = $this->route('proveedorMaterial');
        $proveedorId = $proveedor instanceof ProveedorMaterial ? $proveedor->id : null;

        return [
            'codigo' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('proveedores_materiales', 'codigo')->ignore($proveedorId),
            ],
            'nombre' => ['required', 'string', 'min:2', 'max:180'],
            'codigo_externo' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('proveedores_materiales', 'codigo_externo')->ignore($proveedorId),
            ],
            'activo' => ['required', 'boolean'],
            'cliente_ids' => ['required', 'array', 'min:1'],
            'cliente_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('clientes', 'id')->where('activo', true),
            ],
            'categorias' => ['required', 'array', 'min:1'],
            'categorias.*.cliente_id' => ['required', 'uuid'],
            'categorias.*.categoria' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $clienteIds = collect($this->input('cliente_ids', []))->filter()->values();
            $categorias = collect($this->input('categorias', []));
            $claves = [];

            foreach ($categorias as $indice => $asignacion) {
                $clienteId = (string) ($asignacion['cliente_id'] ?? '');
                $categoria = trim((string) ($asignacion['categoria'] ?? ''));
                $clave = $clienteId.'|'.mb_strtolower($categoria);

                if (! $clienteIds->contains($clienteId)) {
                    $validator->errors()->add("categorias.$indice.cliente_id", 'La categoría pertenece a un cliente que no está asociado al proveedor.');
                }
                if (isset($claves[$clave])) {
                    $validator->errors()->add("categorias.$indice.categoria", 'La categoría está repetida para el mismo cliente.');
                }
                $claves[$clave] = true;

                $existe = $clienteId !== '' && $categoria !== '' && ItemMaterial::query()
                    ->where('activo', true)
                    ->whereRaw('LOWER(TRIM(categoria)) = ?', [mb_strtolower($categoria)])
                    ->whereHas('cliente', fn ($consulta) => $consulta
                        ->where('cliente_id', $clienteId)
                        ->where('activo', true))
                    ->exists();

                if (! $existe) {
                    $validator->errors()->add("categorias.$indice.categoria", 'La categoría no existe en el catálogo activo del cliente.');
                }
            }

            foreach ($clienteIds as $clienteId) {
                if (! $categorias->contains(fn ($asignacion): bool => ($asignacion['cliente_id'] ?? null) === $clienteId)) {
                    $validator->errors()->add('categorias', 'Cada cliente asociado debe tener al menos una categoría habilitada.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $categorias = collect($this->input('categorias', []))
            ->map(fn ($asignacion): array => [
                'cliente_id' => (string) ($asignacion['cliente_id'] ?? ''),
                'categoria' => trim((string) ($asignacion['categoria'] ?? '')),
            ])
            ->values()
            ->all();

        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'codigo_externo' => $this->filled('codigo_externo')
                ? trim((string) $this->input('codigo_externo'))
                : null,
            'activo' => $this->boolean('activo'),
            'categorias' => $categorias,
        ]);
    }
}
