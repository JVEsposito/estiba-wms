<?php

namespace App\Http\Requests;

use App\Enums\MotivoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Enums\TipoBulto;
use App\Services\Validacion\ProyeccionTrazabilidadFolio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegistrarValidacionPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        if (! $usuario?->can('validar-pallets')) {
            return false;
        }

        return $this->input('resultado') !== ResultadoValidacionPallet::Rechazado->value
            || $usuario->can('rechazar-pallets');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'numero_folio' => ['required', 'string', 'max:50'],
            'tipo_bulto' => ['required', Rule::in([TipoBulto::Pallet->value, TipoBulto::Saldo->value])],
            'cantidad_cajas' => ['required', 'integer', 'min:1'],
            'linea_proceso' => ['required', 'integer', Rule::in([1, 2, 3])],
            'turno' => ['required', 'string', Rule::in(['A', 'B'])],
            'temporada_id' => ['required', 'uuid', 'exists:temporadas,id'],
            'catalogo_version' => ['required', 'integer', 'min:1'],
            'articulo_validacion_id' => ['required', 'uuid', 'exists:articulos_validacion,id'],
            'origen_validacion_id' => ['required', 'uuid', 'exists:origenes_validacion,id'],
            // Compatibilidad: las PDA anteriores continúan enviando solo origen_validacion_id.
            // Las nuevas envían el detalle completo y una única fecha por bulto.
            'fecha_embalaje' => ['nullable', 'date_format:Y-m-d'],
            'composicion' => ['nullable', 'array', 'min:1', 'max:20'],
            // Un mismo CSG puede repetirse cuando aporta cajas de lotes o procesos distintos;
            // la combinación CSG + lote + proceso se valida como única en withValidator().
            'composicion.*.origen_validacion_id' => [
                'required_with:composicion',
                'uuid',
                'exists:origenes_validacion,id',
            ],
            'composicion.*.cantidad_cajas' => [
                'required_with:composicion',
                'integer',
                'min:1',
            ],
            // Lote de materia prima y proceso de packing impresos en la etiqueta del pallet.
            'composicion.*.lote_materia_prima' => ['nullable', 'string', 'max:80'],
            'composicion.*.proceso_packing' => ['nullable', 'string', 'max:80'],
            'categoria_validacion_id' => ['required', 'uuid', 'exists:categorias_validacion,id'],
            'resultado' => ['required', Rule::enum(ResultadoValidacionPallet::class)],
            'motivo' => ['nullable', Rule::enum(MotivoValidacionPallet::class), 'required_unless:resultado,aprobado'],
            'observacion' => ['nullable', 'string', 'max:2000', 'required_if:motivo,otro'],
            'generado_dispositivo_at' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $claves = collect($this->input('composicion', []))
                ->filter(fn (mixed $linea): bool => is_array($linea))
                ->map(fn (array $linea): string => implode('|', [
                    (string) ($linea['origen_validacion_id'] ?? ''),
                    ProyeccionTrazabilidadFolio::normalizarCodigo($linea['lote_materia_prima'] ?? null),
                    ProyeccionTrazabilidadFolio::normalizarCodigo($linea['proceso_packing'] ?? null),
                ]));

            if ($claves->duplicates()->isNotEmpty()) {
                $validator->errors()->add(
                    'composicion',
                    'No repitas la misma combinación de CSG, lote y proceso en el bulto.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_folio' => mb_strtoupper(trim((string) $this->input('numero_folio'))),
            'turno' => mb_strtoupper(trim((string) $this->input('turno'))),
            'observacion' => filled($this->input('observacion'))
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }
}
