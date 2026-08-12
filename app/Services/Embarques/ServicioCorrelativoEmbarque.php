<?php

namespace App\Services\Embarques;

use App\Models\Cliente;
use App\Models\CorrelativoEmbarqueCliente;
use DomainException;

class ServicioCorrelativoEmbarque
{
    private const MAXIMO = 9_999_999;

    /** @return array{numero: int, codigo: string} */
    public function siguiente(Cliente $cliente): array
    {
        $sigla = mb_strtoupper(trim((string) $cliente->codigo_folio_materiales));

        if (! preg_match('/^[A-Z]{2}$/', $sigla)) {
            throw new DomainException(
                'El cliente debe tener una sigla documental de dos letras para generar embarques.',
            );
        }

        $ahora = now();
        CorrelativoEmbarqueCliente::query()->insertOrIgnore([
            'cliente_id' => $cliente->id,
            'ultimo_numero' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $correlativo = CorrelativoEmbarqueCliente::query()
            ->lockForUpdate()
            ->findOrFail($cliente->id);
        $siguiente = $correlativo->ultimo_numero + 1;

        if ($siguiente > self::MAXIMO) {
            throw new DomainException('El cliente agotó su correlativo de embarques.');
        }

        $correlativo->update(['ultimo_numero' => $siguiente]);

        return [
            'numero' => $siguiente,
            'codigo' => sprintf('E%s%07d', $sigla, $siguiente),
        ];
    }
}
