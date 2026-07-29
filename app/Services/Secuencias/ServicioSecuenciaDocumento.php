<?php

namespace App\Services\Secuencias;

use Illuminate\Support\Facades\DB;
use LogicException;

class ServicioSecuenciaDocumento
{
    public function consultarSiguiente(string $clave): int
    {
        return $this->ultimoNumero($clave) + 1;
    }

    public function reservarSiguiente(string $clave): int
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'La reserva de una secuencia documental requiere una transacción activa.',
            );
        }

        $ultimoNumero = DB::table('secuencias_documentos')
            ->where('clave', $clave)
            ->lockForUpdate()
            ->value('ultimo_numero');

        if ($ultimoNumero === null) {
            throw new LogicException("No existe la secuencia documental [{$clave}].");
        }

        $siguienteNumero = ((int) $ultimoNumero) + 1;
        DB::table('secuencias_documentos')
            ->where('clave', $clave)
            ->update(['ultimo_numero' => $siguienteNumero]);

        return $siguienteNumero;
    }

    private function ultimoNumero(string $clave): int
    {
        $ultimoNumero = DB::table('secuencias_documentos')
            ->where('clave', $clave)
            ->value('ultimo_numero');

        if ($ultimoNumero === null) {
            throw new LogicException("No existe la secuencia documental [{$clave}].");
        }

        return (int) $ultimoNumero;
    }
}
