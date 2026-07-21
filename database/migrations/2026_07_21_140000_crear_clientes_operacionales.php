<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 180);
            $table->string('codigo_externo', 150)->nullable()->index();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('clientes_validacion', function (Blueprint $table): void {
            $table->uuid('cliente_id')->nullable()->after('id')->index();
        });
        Schema::table('clientes_materiales', function (Blueprint $table): void {
            $table->uuid('cliente_id')->nullable()->after('id')->index();
        });

        foreach (DB::table('clientes_validacion')->orderBy('created_at')->get() as $origen) {
            $clienteId = $this->resolverCliente(
                $origen->codigo_externo ?: $origen->nombre,
                $origen->nombre,
                $origen->codigo_externo,
                (bool) $origen->activo,
            );
            DB::table('clientes_validacion')->where('id', $origen->id)->update(['cliente_id' => $clienteId]);
        }

        foreach (DB::table('clientes_materiales')->orderBy('created_at')->get() as $origen) {
            $clienteId = $this->resolverCliente(
                $origen->codigo,
                $origen->nombre,
                $origen->codigo_externo,
                (bool) $origen->activo,
            );
            DB::table('clientes_materiales')->where('id', $origen->id)->update(['cliente_id' => $clienteId]);
        }

        Schema::table('clientes_validacion', function (Blueprint $table): void {
            $table->foreign('cliente_id', 'clientes_validacion_cliente_fk')
                ->references('id')->on('clientes')->restrictOnDelete();
        });
        Schema::table('clientes_materiales', function (Blueprint $table): void {
            $table->foreign('cliente_id', 'clientes_materiales_cliente_fk')
                ->references('id')->on('clientes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientes_validacion', function (Blueprint $table): void {
            $table->dropForeign('clientes_validacion_cliente_fk');
            $table->dropColumn('cliente_id');
        });
        Schema::table('clientes_materiales', function (Blueprint $table): void {
            $table->dropForeign('clientes_materiales_cliente_fk');
            $table->dropColumn('cliente_id');
        });
        Schema::dropIfExists('clientes');
    }

    private function resolverCliente(
        string $codigoPreferido,
        string $nombre,
        ?string $codigoExterno,
        bool $activo,
    ): string {
        $existente = null;
        if (filled($codigoExterno)) {
            $existente = DB::table('clientes')
                ->whereRaw('UPPER(codigo_externo) = ?', [mb_strtoupper(trim((string) $codigoExterno))])
                ->first();
        }

        $codigo = $this->normalizarCodigo($codigoPreferido);
        $existente ??= DB::table('clientes')->where('codigo', $codigo)->first();
        if (! $existente) {
            $claveNombre = $this->claveNombre($nombre);
            $existente = DB::table('clientes')->get()->first(
                fn (object $cliente): bool => $this->claveNombre($cliente->nombre) === $claveNombre,
            );
        }

        if ($existente) {
            if ($activo && ! $existente->activo) {
                DB::table('clientes')->where('id', $existente->id)->update([
                    'activo' => true,
                    'updated_at' => now(),
                ]);
            }

            return $existente->id;
        }

        $id = (string) Str::uuid();
        DB::table('clientes')->insert([
            'id' => $id,
            'codigo' => $this->codigoDisponible($codigo),
            'nombre' => trim($nombre),
            'codigo_externo' => filled($codigoExterno) ? trim((string) $codigoExterno) : null,
            'activo' => $activo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function codigoDisponible(string $base): string
    {
        $codigo = $base;
        $secuencia = 2;
        while (DB::table('clientes')->where('codigo', $codigo)->exists()) {
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
};
