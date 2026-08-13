<?php

namespace App\Services\IntegridadOperacional\Reglas;

use App\Enums\SeveridadHallazgoIntegridadOperacional;
use App\Services\IntegridadOperacional\HallazgoIntegridadDetectado;
use Illuminate\Support\Facades\DB;

final class ReglaBalanceRepaletizaje implements ReglaIntegridadOperacional
{
    public const CODIGO = 'repaletizaje_desbalanceado';

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function nombre(): string
    {
        return 'Conservación de cajas en repaletizajes';
    }

    public function modulo(): string
    {
        return 'repaletizaje';
    }

    public function evaluar(): iterable
    {
        $entradas = DB::table('repaletizaje_detalles')
            ->selectRaw('repaletizaje_id, SUM(cajas_aportadas) as total_entradas')
            ->groupBy('repaletizaje_id');
        $salidas = DB::table('repaletizaje_resultados')
            ->selectRaw('repaletizaje_id, SUM(cantidad_resultante) as total_salidas')
            ->groupBy('repaletizaje_id');

        return DB::table('repaletizajes as repaletizaje')
            ->leftJoinSub($entradas, 'entradas', 'entradas.repaletizaje_id', '=', 'repaletizaje.id')
            ->leftJoinSub($salidas, 'salidas', 'salidas.repaletizaje_id', '=', 'repaletizaje.id')
            ->where('repaletizaje.estado', 'confirmado')
            ->whereRaw('COALESCE(entradas.total_entradas, 0) <> COALESCE(salidas.total_salidas, 0)')
            ->orderBy('repaletizaje.confirmado_at')
            ->get([
                'repaletizaje.id',
                'repaletizaje.codigo',
                'repaletizaje.modalidad',
                'repaletizaje.confirmado_at',
                'entradas.total_entradas',
                'salidas.total_salidas',
            ])
            ->map(fn (object $fila): HallazgoIntegridadDetectado => new HallazgoIntegridadDetectado(
                reglaCodigo: self::CODIGO,
                severidad: SeveridadHallazgoIntegridadOperacional::Critico,
                modulo: $this->modulo(),
                entidadTipo: 'repaletizaje',
                entidadId: $fila->id,
                referencia: $fila->codigo,
                titulo: 'Repaletizaje con entradas y salidas desbalanceadas',
                detalle: sprintf(
                    '%s registra %d cajas aportadas y %d cajas resultantes.',
                    $fila->codigo,
                    (int) ($fila->total_entradas ?? 0),
                    (int) ($fila->total_salidas ?? 0),
                ),
                contexto: [
                    'modalidad' => $fila->modalidad,
                    'total_entradas' => (int) ($fila->total_entradas ?? 0),
                    'total_salidas' => (int) ($fila->total_salidas ?? 0),
                    'confirmado_at' => $fila->confirmado_at,
                ],
            ));
    }
}
