<?php

namespace App\Services\Clientes;

use App\Models\AliasCliente;
use App\Models\Cliente;
use App\Models\ClienteMaterial;
use App\Models\ClienteValidacion;
use App\Models\Temporada;
use App\Models\TemporadaMaterial;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioCliente
{
    /** @param array<string, mixed> $datos */
    public function guardarMaestro(
        array $datos,
        User $usuario,
        ?Cliente $cliente = null,
    ): Cliente {
        return DB::transaction(function () use ($datos, $usuario, $cliente): Cliente {
            $codigo = $this->normalizarCodigo($datos['codigo']);
            $nombre = Str::of($datos['nombre'])->squish()->toString();
            $codigoExterno = $this->opcional($datos['codigo_externo'] ?? null);
            $codigoFolioMateriales = $this->normalizarCodigoFolioMateriales(
                $datos['codigo_folio_materiales'] ?? null,
            );

            $duplicado = Cliente::query()
                ->where('codigo', $codigo)
                ->when($cliente, fn ($consulta) => $consulta->whereKeyNot($cliente->id))
                ->exists();
            if ($duplicado) {
                throw new DomainException('Ya existe un cliente global con ese código.');
            }

            if ($cliente?->exists
                && $cliente->codigo_folio_materiales !== null
                && $cliente->codigo_folio_materiales !== $codigoFolioMateriales
                && $cliente->correlativoMateriales()->exists()) {
                throw new DomainException(
                    'El código corto de folios no puede cambiar porque el cliente ya posee correlativos de materiales.',
                );
            }

            if ($cliente?->exists
                && ($cliente->codigo !== $codigo || $cliente->nombre !== $nombre)) {
                $this->registrarAlias($cliente, 'edicion_accesos', $cliente->codigo, $cliente->nombre);
            }

            $cliente ??= new Cliente;
            $cliente->fill([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'codigo_externo' => $codigoExterno,
                'codigo_folio_materiales' => $codigoFolioMateriales,
                'activo' => (bool) ($datos['activo'] ?? true),
                'creado_por_user_id' => $cliente->creado_por_user_id ?? $usuario->id,
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $cliente->save();

            $this->sincronizarProyecciones($cliente, $usuario->id);
            $this->asegurarProyeccionesActivas($cliente, $usuario->id);

            return $cliente->refresh()->load([
                'aliases' => fn ($consulta) => $consulta->orderByDesc('created_at'),
            ]);
        }, attempts: 3);
    }

    public function asegurarProyeccionesActivas(
        Cliente $cliente,
        ?int $usuarioId = null,
    ): void {
        $temporada = Temporada::query()->where('activa', true)->first();
        if (! $temporada) {
            return;
        }

        $material = TemporadaMaterial::query()
            ->where('temporada_id', $temporada->id)
            ->first();
        if ($material) {
            ClienteMaterial::query()->updateOrCreate(
                [
                    'temporada_material_id' => $material->id,
                    'cliente_id' => $cliente->id,
                ],
                [
                    'codigo' => $cliente->codigo,
                    'nombre' => $cliente->nombre,
                    'codigo_externo' => $cliente->codigo_externo,
                    'activo' => $cliente->activo,
                    'creado_por_user_id' => $usuarioId,
                    'actualizado_por_user_id' => $usuarioId,
                ],
            );
        }

        ClienteValidacion::query()->updateOrCreate(
            [
                'temporada_id' => $temporada->id,
                'cliente_id' => $cliente->id,
            ],
            [
                'nombre' => $cliente->nombre,
                'codigo_externo' => $cliente->codigo,
                'activo' => $cliente->activo,
            ],
        );
    }

    public function asegurarClientesEnTemporada(
        Temporada $temporada,
        TemporadaMaterial $material,
        ?int $usuarioId = null,
    ): void {
        Cliente::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->each(function (Cliente $cliente) use ($temporada, $material, $usuarioId): void {
                ClienteMaterial::query()->updateOrCreate(
                    [
                        'temporada_material_id' => $material->id,
                        'cliente_id' => $cliente->id,
                    ],
                    [
                        'codigo' => $cliente->codigo,
                        'nombre' => $cliente->nombre,
                        'codigo_externo' => $cliente->codigo_externo,
                        'activo' => true,
                        'creado_por_user_id' => $usuarioId,
                        'actualizado_por_user_id' => $usuarioId,
                    ],
                );
                ClienteValidacion::query()->updateOrCreate(
                    [
                        'temporada_id' => $temporada->id,
                        'cliente_id' => $cliente->id,
                    ],
                    [
                        'nombre' => $cliente->nombre,
                        'codigo_externo' => $cliente->codigo,
                        'activo' => true,
                    ],
                );
            });
    }

    public function buscarPorReferencia(string $referencia): ?Cliente
    {
        $referencia = trim($referencia);
        if ($referencia === '') {
            return null;
        }

        $codigo = mb_strtoupper($referencia);
        $cliente = Cliente::query()
            ->where(function ($consulta) use ($codigo): void {
                $consulta->whereRaw('UPPER(codigo) = ?', [$codigo])
                    ->orWhereRaw('UPPER(codigo_externo) = ?', [$codigo]);
            })
            ->first();
        if ($cliente) {
            return $cliente;
        }

        $alias = AliasCliente::query()
            ->whereRaw('UPPER(codigo) = ?', [$codigo])
            ->with('cliente')
            ->first();
        if ($alias?->cliente) {
            return $alias->cliente;
        }

        $clave = $this->claveNombre($referencia);
        $cliente = Cliente::query()->get()
            ->first(fn (Cliente $candidato): bool => $this->claveNombre($candidato->nombre) === $clave);
        if ($cliente) {
            return $cliente;
        }

        return AliasCliente::query()
            ->with('cliente')
            ->get()
            ->first(fn (AliasCliente $candidato): bool => $this->claveNombre($candidato->nombre) === $clave)
            ?->cliente;
    }

    private function sincronizarProyecciones(Cliente $cliente, int $usuarioId): void
    {
        ClienteMaterial::query()->where('cliente_id', $cliente->id)->update([
            'codigo' => $cliente->codigo,
            'nombre' => $cliente->nombre,
            'codigo_externo' => $cliente->codigo_externo,
            'activo' => $cliente->activo,
            'actualizado_por_user_id' => $usuarioId,
            'updated_at' => now(),
        ]);
        ClienteValidacion::query()->where('cliente_id', $cliente->id)->update([
            'nombre' => $cliente->nombre,
            'codigo_externo' => $cliente->codigo,
            'activo' => $cliente->activo,
            'updated_at' => now(),
        ]);
    }

    private function registrarAlias(
        Cliente $cliente,
        string $origen,
        ?string $codigo,
        string $nombre,
    ): void {
        AliasCliente::query()->firstOrCreate([
            'cliente_id' => $cliente->id,
            'origen' => $origen,
            'codigo' => $this->opcional($codigo),
            'nombre' => trim($nombre),
        ]);
    }

    private function normalizarCodigo(string $valor): string
    {
        $codigo = mb_strtoupper(Str::ascii(trim($valor)));
        $codigo = preg_replace('/[^A-Z0-9._-]+/', '-', $codigo) ?? '';
        $codigo = trim($codigo, '-_.');

        return mb_substr($codigo !== '' ? $codigo : 'CLIENTE', 0, 80);
    }

    private function normalizarCodigoFolioMateriales(?string $valor): ?string
    {
        $codigo = mb_strtoupper(trim((string) $valor));

        if ($codigo === '') {
            return null;
        }

        if (! preg_match('/^[A-Z0-9]{2}$/', $codigo)) {
            throw new DomainException(
                'El código corto de folios de materiales debe tener exactamente dos caracteres alfanuméricos.',
            );
        }

        return $codigo;
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
