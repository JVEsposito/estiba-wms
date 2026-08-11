<?php

namespace App\Services\InspeccionSag;

use App\Models\Cliente;
use App\Models\CorrelativoInspeccionSagCliente;
use DomainException;

class ServicioCorrelativoInspeccionSag
{
    private const MAXIMO_CORRELATIVO = 9_999_999;

    /** @return array{numero: int, codigo: string} */
    public function siguiente(Cliente $cliente): array
    {
        $codigoCliente = mb_strtoupper(trim((string) $cliente->codigo_folio_materiales));

        if (! preg_match('/^[A-Z]{2}$/', $codigoCliente)) {
            throw new DomainException(
                'El cliente debe tener un código de dos letras para generar inspecciones SAG.',
            );
        }

        $ahora = now();
        CorrelativoInspeccionSagCliente::query()->insertOrIgnore([
            'cliente_id' => $cliente->id,
            'ultimo_numero' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $correlativo = CorrelativoInspeccionSagCliente::query()
            ->lockForUpdate()
            ->findOrFail($cliente->id);
        $siguiente = $correlativo->ultimo_numero + 1;

        if ($siguiente > self::MAXIMO_CORRELATIVO) {
            throw new DomainException(
                'El cliente agotó el correlativo disponible para inspecciones SAG.',
            );
        }

        $correlativo->update(['ultimo_numero' => $siguiente]);

        return [
            'numero' => $siguiente,
            'codigo' => sprintf('I%s%07d', $codigoCliente, $siguiente),
        ];
    }
}
