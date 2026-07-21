<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\ClienteMaterial;
use App\Models\ClienteValidacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioCliente
{
    public function sincronizarValidacion(
        ClienteValidacion $origen,
        ?int $usuarioId = null,
    ): Cliente {
        return $this->sincronizar(
            $origen,
            $origen->codigo_externo ?: $origen->nombre,
            $origen->nombre,
            $origen->codigo_externo,
            $origen->activo,
            $usuarioId,
        );
    }

    public function sincronizarMaterial(
        ClienteMaterial $origen,
        ?int $usuarioId = null,
    ): Cliente {
        return $this->sincronizar(
            $origen,
            $origen->codigo,
            $origen->nombre,
            $origen->codigo_externo,
            $origen->activo,
            $usuarioId,
        );
    }

    private function sincronizar(
        Model $origen,
        string $codigoPreferido,
        string $nombre,
        ?string $codigoExterno,
        bool $activo,
        ?int $usuarioId,
    ): Cliente {
        return DB::transaction(function () use (
            $origen,
            $codigoPreferido,
            $nombre,
            $codigoExterno,
            $activo,
            $usuarioId,
        ): Cliente {
            $cliente = $origen->cliente_id
                ? Cliente::query()->lockForUpdate()->find($origen->cliente_id)
                : null;
            $cliente ??= $this->buscarCoincidencia($codigoPreferido, $nombre, $codigoExterno);

            if (! $cliente) {
                $cliente = Cliente::create([
                    'codigo' => $this->codigoDisponible($codigoPreferido),
                    'nombre' => trim($nombre),
                    'codigo_externo' => $this->opcional($codigoExterno),
                    'activo' => $activo,
                    'creado_por_user_id' => $usuarioId,
                    'actualizado_por_user_id' => $usuarioId,
                ]);
            } else {
                $cliente->update([
                    'nombre' => trim($nombre),
                    'codigo_externo' => $this->opcional($codigoExterno) ?? $cliente->codigo_externo,
                    'actualizado_por_user_id' => $usuarioId ?? $cliente->actualizado_por_user_id,
                ]);
            }

            if ($origen->cliente_id !== $cliente->id) {
                $origen->forceFill(['cliente_id' => $cliente->id])->saveQuietly();
            }

            $cliente->update([
                'activo' => ClienteValidacion::query()
                    ->where('cliente_id', $cliente->id)
                    ->where('activo', true)
                    ->exists()
                    || ClienteMaterial::query()
                        ->where('cliente_id', $cliente->id)
                        ->where('activo', true)
                        ->exists(),
            ]);

            return $cliente->refresh();
        }, attempts: 3);
    }

    private function buscarCoincidencia(
        string $codigoPreferido,
        string $nombre,
        ?string $codigoExterno,
    ): ?Cliente {
        if (filled($codigoExterno)) {
            $cliente = Cliente::query()
                ->whereRaw('UPPER(codigo_externo) = ?', [mb_strtoupper(trim((string) $codigoExterno))])
                ->lockForUpdate()
                ->first();
            if ($cliente) {
                return $cliente;
            }
        }

        $codigo = $this->normalizarCodigo($codigoPreferido);
        $cliente = Cliente::query()->where('codigo', $codigo)->lockForUpdate()->first();
        if ($cliente) {
            return $cliente;
        }

        $claveNombre = $this->claveNombre($nombre);

        return Cliente::query()
            ->lockForUpdate()
            ->get()
            ->first(fn (Cliente $candidato): bool => $this->claveNombre($candidato->nombre) === $claveNombre);
    }

    private function codigoDisponible(string $preferido): string
    {
        $base = $this->normalizarCodigo($preferido);
        $codigo = $base;
        $secuencia = 2;
        while (Cliente::query()->where('codigo', $codigo)->exists()) {
            $sufijo = '-'.$secuencia++;
            $codigo = mb_substr($base, 0, 80 - mb_strlen($sufijo)).$sufijo;
        }

        return $codigo;
    }

    private function normalizarCodigo(string $valor): string
    {
        $codigo = mb_strtoupper(Str::ascii(trim($valor)));
        $codigo = preg_replace('/[^A-Z0-9._-]+/', '-', $codigo) ?? '';
        $codigo = trim($codigo, '-_.');

        return mb_substr($codigo !== '' ? $codigo : 'CLIENTE', 0, 80);
    }

    private function claveNombre(string $valor): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', Str::ascii($valor)) ?? $valor));
    }

    private function opcional(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }
}
