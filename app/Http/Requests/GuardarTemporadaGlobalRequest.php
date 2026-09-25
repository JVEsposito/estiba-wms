<?php

namespace App\Http\Requests;

use App\Enums\TipoTemporada;
use App\Models\Temporada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarTemporadaGlobalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-accesos') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $temporada = $this->route('temporada');
        $temporadaId = $temporada instanceof Temporada ? $temporada->id : null;
        // Al crear se puede elegir el tipo; después solo cambia con la clasificación auditada.
        $esPrueba = $temporada instanceof Temporada
            ? $temporada->esPrueba()
            : $this->input('tipo') === TipoTemporada::Prueba->value;
        $fecha = $esPrueba ? 'nullable' : 'required';

        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('temporadas', 'codigo')->ignore($temporadaId),
            ],
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
            'fecha_inicio' => [$fecha, 'date'],
            'fecha_fin' => [$fecha, 'date', 'after_or_equal:fecha_inicio'],
            'prefijo_documental' => [
                'nullable',
                'string',
                'regex:/^[A-Z0-9]{2,6}$/',
                Rule::unique('temporadas', 'prefijo_documental')->ignore($temporadaId),
            ],
            'tipo' => [
                Rule::prohibitedIf($temporadaId !== null),
                'nullable',
                Rule::enum(TipoTemporada::class),
            ],
            'activa' => ['sometimes', 'boolean'],
            'intervalo_embarques_minutos' => ['sometimes', 'integer', 'min:15', 'max:240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'fecha_inicio' => $this->filled('fecha_inicio') ? $this->input('fecha_inicio') : null,
            'fecha_fin' => $this->filled('fecha_fin') ? $this->input('fecha_fin') : null,
        ];

        if ($this->has('prefijo_documental')) {
            $normalizados['prefijo_documental'] = $this->filled('prefijo_documental')
                ? mb_strtoupper(trim((string) $this->input('prefijo_documental')))
                : null;
        }

        if ($this->has('intervalo_embarques_minutos')) {
            $normalizados['intervalo_embarques_minutos'] = $this->integer('intervalo_embarques_minutos');
        }

        $this->merge($normalizados);
    }
}
