<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULO_BUSQUEDA = 'consultas.busqueda';

    public function up(): void
    {
        // Auditoría: quién dejó sin PIN al operador (administrador o supervisor de frío).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pin_operacional_restablecido_por_user_id')
                ->nullable()
                ->after('pin_operacional_bloqueado_hasta')
                ->constrained('users')
                ->nullOnDelete();
        });

        // El despachador predeterminado recibe el buscador global. Solo se agrega un permiso de
        // lectura, por eso no se revocan sesiones: el menú se actualiza en el próximo ingreso.
        $this->perfilesDespachador()->each(function (object $perfil): void {
            $this->guardarModulos($perfil->id, array_values(array_unique([
                ...$this->decodificar($perfil->modulos),
                self::MODULO_BUSQUEDA,
            ])));
        });
    }

    public function down(): void
    {
        $this->perfilesDespachador()->each(function (object $perfil): void {
            $this->guardarModulos($perfil->id, array_values(array_filter(
                $this->decodificar($perfil->modulos),
                fn (string $modulo): bool => $modulo !== self::MODULO_BUSQUEDA,
            )));
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pin_operacional_restablecido_por_user_id');
        });
    }

    private function perfilesDespachador()
    {
        return DB::table('perfiles_acceso')
            ->where('predeterminado', true)
            ->where('rol_base', 'despachador')
            ->select(['id', 'modulos'])
            ->orderBy('id')
            ->get();
    }

    /** @param array<int, string> $modulos */
    private function guardarModulos(string $perfilId, array $modulos): void
    {
        DB::table('perfiles_acceso')
            ->where('id', $perfilId)
            ->update([
                'modulos' => json_encode($modulos, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    /** @return array<int, string> */
    private function decodificar(mixed $valor): array
    {
        $decodificado = is_array($valor) ? $valor : json_decode((string) $valor, true);

        return is_array($decodificado)
            ? array_values(array_filter($decodificado, is_string(...)))
            : [];
    }
};
