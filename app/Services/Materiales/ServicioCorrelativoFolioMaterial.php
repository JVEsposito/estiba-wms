<?php

namespace App\Services\Materiales;

use App\Models\Cliente;
use App\Models\CorrelativoMaterialCliente;
use DomainException;

class ServicioCorrelativoFolioMaterial
{
    private const MAXIMO_CORRELATIVO = 9_999_999;

    public function siguiente(Cliente $cliente): string
    {
        $codigoCliente = mb_strtoupper(trim((string) $cliente->codigo_folio_materiales));

        if (! preg_match('/^[A-Z]{2}$/', $codigoCliente)) {
            throw new DomainException(
                'El cliente debe tener un código de dos letras para generar folios de materiales.',
            );
        }

        $ahora = now();
        CorrelativoMaterialCliente::query()->insertOrIgnore([
            'cliente_id' => $cliente->id,
            'ultimo_numero' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $correlativo = CorrelativoMaterialCliente::query()
            ->lockForUpdate()
            ->findOrFail($cliente->id);
        $siguiente = $correlativo->ultimo_numero + 1;

        if ($siguiente > self::MAXIMO_CORRELATIVO) {
            throw new DomainException(
                'El cliente agotó el correlativo disponible para folios de materiales.',
            );
        }

        $correlativo->update(['ultimo_numero' => $siguiente]);

        return sprintf('F%s%07d', $codigoCliente, $siguiente);
    }
}
