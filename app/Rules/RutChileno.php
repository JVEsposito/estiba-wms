<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RutChileno implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rut = strtoupper(preg_replace('/[^0-9K]/i', '', (string) $value) ?? '');
        if (strlen($rut) < 2) {
            $fail('Ingresa un RUT de conductor válido.');

            return;
        }

        $cuerpo = substr($rut, 0, -1);
        $digito = substr($rut, -1);
        if (! ctype_digit($cuerpo)) {
            $fail('Ingresa un RUT de conductor válido.');

            return;
        }

        $suma = 0;
        $multiplicador = 2;
        for ($indice = strlen($cuerpo) - 1; $indice >= 0; $indice--) {
            $suma += ((int) $cuerpo[$indice]) * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        $resultado = 11 - ($suma % 11);
        $esperado = match ($resultado) {
            11 => '0',
            10 => 'K',
            default => (string) $resultado,
        };

        if ($digito !== $esperado) {
            $fail('Ingresa un RUT de conductor válido.');
        }
    }
}
