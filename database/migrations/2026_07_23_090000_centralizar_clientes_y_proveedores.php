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
        Schema::create('aliases_clientes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('origen', 40);
            $table->string('codigo', 150)->nullable();
            $table->string('nombre', 180);
            $table->timestamps();
            $table->unique(
                ['cliente_id', 'origen', 'codigo', 'nombre'],
                'alias_cliente_historial_unique',
            );
            $table->index(['codigo', 'nombre'], 'alias_cliente_busqueda_idx');
        });

        Schema::create('proveedores_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 180);
            $table->string('codigo_externo', 150)->nullable()->unique();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('clientes_proveedores_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('proveedor_material_id')->constrained('proveedores_materiales')->restrictOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['cliente_id', 'proveedor_material_id'],
                'cliente_proveedor_material_unique',
            );
        });

        $this->aplicarAutoridadInicialDeBodega();
        $this->normalizarMace();
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_proveedores_materiales');
        Schema::dropIfExists('proveedores_materiales');
        Schema::dropIfExists('aliases_clientes');
    }

    private function aplicarAutoridadInicialDeBodega(): void
    {
        $clientes = DB::table('clientes_materiales as cm')
            ->join('temporadas_materiales as tm', 'tm.id', '=', 'cm.temporada_material_id')
            ->whereNotNull('cm.cliente_id')
            ->select([
                'cm.cliente_id',
                'cm.codigo',
                'cm.nombre',
                'cm.codigo_externo',
                'cm.activo',
                'tm.activa as temporada_activa',
                'cm.updated_at',
            ])
            ->orderByDesc('tm.activa')
            ->orderByDesc('cm.updated_at')
            ->get()
            ->unique('cliente_id');

        foreach ($clientes as $bodega) {
            $maestro = DB::table('clientes')->where('id', $bodega->cliente_id)->first();
            if (! $maestro) {
                continue;
            }

            $this->registrarAlias(
                $maestro->id,
                'fusion_inicial',
                $maestro->codigo,
                $maestro->nombre,
            );

            $codigo = $this->codigoDisponible((string) $bodega->codigo, (string) $maestro->id);
            DB::table('clientes')->where('id', $maestro->id)->update([
                'codigo' => $codigo,
                'nombre' => trim((string) $bodega->nombre),
                'codigo_externo' => filled($bodega->codigo_externo)
                    ? trim((string) $bodega->codigo_externo)
                    : $maestro->codigo_externo,
                'activo' => (bool) $bodega->activo,
                'updated_at' => now(),
            ]);

            DB::table('clientes_materiales')->where('cliente_id', $maestro->id)->update([
                'codigo' => $codigo,
                'nombre' => trim((string) $bodega->nombre),
                'codigo_externo' => filled($bodega->codigo_externo)
                    ? trim((string) $bodega->codigo_externo)
                    : null,
                'activo' => (bool) $bodega->activo,
                'updated_at' => now(),
            ]);
            DB::table('clientes_validacion')->where('cliente_id', $maestro->id)->update([
                'nombre' => trim((string) $bodega->nombre),
                'codigo_externo' => $codigo,
                'activo' => (bool) $bodega->activo,
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizarMace(): void
    {
        $mace = DB::table('clientes')
            ->where(function ($consulta): void {
                $consulta->whereRaw('UPPER(codigo) = ?', ['MC-001'])
                    ->orWhereRaw('UPPER(codigo) = ?', ['MAC'])
                    ->orWhereRaw('UPPER(nombre) = ?', ['MACE']);
            })
            ->orderByRaw("CASE WHEN UPPER(nombre) = 'MACE' THEN 0 WHEN UPPER(codigo) = 'MAC' THEN 1 ELSE 2 END")
            ->first();

        if (! $mace) {
            return;
        }

        $this->registrarAlias($mace->id, 'fusion_inicial', $mace->codigo, $mace->nombre);
        $conflicto = DB::table('clientes')
            ->whereRaw('UPPER(codigo) = ?', ['MC-001'])
            ->where('id', '!=', $mace->id)
            ->first();
        if ($conflicto) {
            $this->registrarAlias(
                $conflicto->id,
                'conflicto_mc_001',
                $conflicto->codigo,
                $conflicto->nombre,
            );
            $codigoConflicto = $this->codigoDisponible(
                $this->normalizarCodigo($conflicto->nombre),
                (string) $conflicto->id,
            );
            DB::table('clientes')->where('id', $conflicto->id)->update([
                'codigo' => $codigoConflicto,
                'updated_at' => now(),
            ]);
            DB::table('clientes_materiales')->where('cliente_id', $conflicto->id)->update([
                'codigo' => $codigoConflicto,
                'updated_at' => now(),
            ]);
            DB::table('clientes_validacion')->where('cliente_id', $conflicto->id)->update([
                'codigo_externo' => $codigoConflicto,
                'updated_at' => now(),
            ]);
        }

        DB::table('clientes')->where('id', $mace->id)->update([
            'codigo' => 'MC-001',
            'nombre' => 'MACE',
            'updated_at' => now(),
        ]);
        DB::table('clientes_materiales')->where('cliente_id', $mace->id)->update([
            'codigo' => 'MC-001',
            'nombre' => 'MACE',
            'updated_at' => now(),
        ]);
        DB::table('clientes_validacion')->where('cliente_id', $mace->id)->update([
            'nombre' => 'MACE',
            'codigo_externo' => 'MC-001',
            'updated_at' => now(),
        ]);
    }

    private function normalizarCodigo(string $valor): string
    {
        $codigo = mb_strtoupper(Str::ascii(trim($valor)));
        $codigo = preg_replace('/[^A-Z0-9._-]+/', '-', $codigo) ?: 'CLIENTE';

        return mb_substr(trim($codigo, '-_.'), 0, 80);
    }

    private function registrarAlias(
        string $clienteId,
        string $origen,
        ?string $codigo,
        string $nombre,
    ): void {
        if (! filled($codigo) && ! filled($nombre)) {
            return;
        }

        DB::table('aliases_clientes')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'cliente_id' => $clienteId,
            'origen' => $origen,
            'codigo' => filled($codigo) ? trim((string) $codigo) : null,
            'nombre' => trim($nombre),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function codigoDisponible(string $preferido, string $clienteId): string
    {
        $base = mb_strtoupper(trim($preferido));
        $base = preg_replace('/[^A-Z0-9._-]+/', '-', Str::ascii($base)) ?: 'CLIENTE';
        $base = mb_substr(trim($base, '-_.'), 0, 80);
        $codigo = $base;
        $secuencia = 2;

        while (DB::table('clientes')
            ->where('codigo', $codigo)
            ->where('id', '!=', $clienteId)
            ->exists()) {
            $sufijo = '-'.$secuencia++;
            $codigo = mb_substr($base, 0, 80 - mb_strlen($sufijo)).$sufijo;
        }

        return $codigo;
    }
};
