<?php

namespace App\Services\Materiales;

use App\Models\Cliente;
use App\Models\CorrelativoMaterialCliente;
use App\Models\FolioMaterialLiberado;
use App\Models\RecepcionMaterial;
use App\Models\User;
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
        $liberado = FolioMaterialLiberado::query()
            ->where('cliente_id', $cliente->id)
            ->orderBy('numero_correlativo')
            ->lockForUpdate()
            ->first();

        if ($liberado) {
            $numeroFolio = $liberado->numero_folio;
            $liberado->delete();

            return $numeroFolio;
        }

        $siguiente = $correlativo->ultimo_numero + 1;

        if ($siguiente > self::MAXIMO_CORRELATIVO) {
            throw new DomainException(
                'El cliente agotó el correlativo disponible para folios de materiales.',
            );
        }

        $correlativo->update(['ultimo_numero' => $siguiente]);

        return sprintf('F%s%07d', $codigoCliente, $siguiente);
    }

    public function liberar(
        Cliente $cliente,
        string $numeroFolio,
        RecepcionMaterial $recepcion,
        User $usuario,
        string $motivo,
    ): void {
        $codigoCliente = mb_strtoupper(trim((string) $cliente->codigo_folio_materiales));
        $patron = sprintf('/^F%s([0-9]{7})$/', preg_quote($codigoCliente, '/'));

        if (! preg_match($patron, $numeroFolio, $coincidencias)) {
            throw new DomainException(sprintf(
                'El folio %s no pertenece al correlativo del cliente %s.',
                $numeroFolio,
                $cliente->codigo,
            ));
        }

        FolioMaterialLiberado::query()->firstOrCreate(
            [
                'cliente_id' => $cliente->id,
                'numero_correlativo' => (int) $coincidencias[1],
            ],
            [
                'numero_folio' => $numeroFolio,
                'recepcion_material_id_original' => $recepcion->id,
                'motivo' => trim($motivo),
                'liberado_por_user_id' => $usuario->id,
            ],
        );
    }
}
