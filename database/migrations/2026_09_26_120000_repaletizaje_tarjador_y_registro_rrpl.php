<?php

use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Autorizacion\CatalogoModulosAcceso;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Perfiles predeterminados que reciben el módulo de repaletizaje. */
    private const ROLES_CON_MODULO = ['administrador', 'supervisor_frio'];

    public function up(): void
    {
        // RRPL-01 agrupa por fecha operacional, turno y tarjador.
        Schema::table('repaletizajes', function (Blueprint $table): void {
            $table->char('turno', 1)->nullable()->after('observacion');
            $table->date('fecha_operacional')->nullable()->after('turno');
            $table->index(['fecha_operacional', 'turno', 'user_id'], 'repaletizajes_registro_rrpl_index');
        });

        $zona = config('app.operational_timezone');
        DB::table('repaletizajes')
            ->whereNull('fecha_operacional')
            ->select(['id', 'confirmado_at', 'created_at'])
            ->orderBy('id')
            ->each(function (object $repa) use ($zona): void {
                $momento = $repa->confirmado_at ?? $repa->created_at;
                if ($momento === null) {
                    return;
                }

                DB::table('repaletizajes')->where('id', $repa->id)->update([
                    'fecha_operacional' => CarbonImmutable::parse($momento, 'UTC')->setTimezone($zona)->toDateString(),
                ]);
            });

        $perfilesActualizados = [];
        $this->asegurarPerfilTarjador();

        DB::table('perfiles_acceso')
            ->where('predeterminado', true)
            ->whereIn('rol_base', self::ROLES_CON_MODULO)
            ->select(['id', 'modulos', 'modulos_tablet'])
            ->orderBy('id')
            ->each(function (object $perfil) use (&$perfilesActualizados): void {
                $modulos = $this->decodificar($perfil->modulos);
                $modulosTablet = $this->decodificar($perfil->modulos_tablet);
                if (in_array(CatalogoModulosAcceso::OFICINA_REPALETIZAJE, $modulos, true)
                    && in_array(CatalogoModulosAcceso::TABLET_REPALETIZAJE, $modulosTablet, true)) {
                    return;
                }

                DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                    'modulos' => json_encode(array_values(array_unique([
                        ...$modulos,
                        CatalogoModulosAcceso::OFICINA_REPALETIZAJE,
                    ])), JSON_THROW_ON_ERROR),
                    'modulos_tablet' => json_encode(array_values(array_unique([
                        ...$modulosTablet,
                        CatalogoModulosAcceso::TABLET_REPALETIZAJE,
                    ])), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
                $perfilesActualizados[] = $perfil->id;
            });

        // Los tokens vigentes no conocen el módulo nuevo: esos usuarios vuelven a iniciar sesión.
        $this->revocarSesiones($perfilesActualizados);
    }

    public function down(): void
    {
        DB::table('perfiles_acceso')
            ->where('predeterminado', true)
            ->whereIn('rol_base', self::ROLES_CON_MODULO)
            ->select(['id', 'modulos', 'modulos_tablet'])
            ->orderBy('id')
            ->each(function (object $perfil): void {
                DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                    'modulos' => json_encode(array_values(array_diff(
                        $this->decodificar($perfil->modulos),
                        [CatalogoModulosAcceso::OFICINA_REPALETIZAJE],
                    )), JSON_THROW_ON_ERROR),
                    'modulos_tablet' => json_encode(array_values(array_diff(
                        $this->decodificar($perfil->modulos_tablet),
                        [CatalogoModulosAcceso::TABLET_REPALETIZAJE],
                    )), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('repaletizajes', function (Blueprint $table): void {
            $table->dropIndex('repaletizajes_registro_rrpl_index');
            $table->dropColumn(['turno', 'fecha_operacional']);
        });
    }

    /**
     * Crea el perfil predeterminado del tarjador si la base ya existía. En una
     * base nueva lo crea la migración original de perfiles.
     */
    private function asegurarPerfilTarjador(): void
    {
        if (DB::table('perfiles_acceso')->where('rol_base', RolUsuario::Tarjador->value)->where('predeterminado', true)->exists()) {
            return;
        }

        $ahora = now();
        DB::table('perfiles_acceso')->insert([
            'id' => (string) Str::uuid(),
            'codigo' => mb_strtoupper(RolUsuario::Tarjador->value),
            'nombre' => 'Tarjador',
            'descripcion' => 'Perfil operacional inicial compatible con el rol Tarjador.',
            'rol_base' => RolUsuario::Tarjador->value,
            'modulos' => json_encode([CatalogoModulosAcceso::OFICINA_REPALETIZAJE], JSON_THROW_ON_ERROR),
            'modulos_tablet' => json_encode([CatalogoModulosAcceso::TABLET_REPALETIZAJE], JSON_THROW_ON_ERROR),
            'activo' => true,
            'predeterminado' => true,
            'protegido' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
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

    /** @param array<int, string> $perfiles */
    private function revocarSesiones(array $perfiles): void
    {
        if ($perfiles === []) {
            return;
        }

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', DB::table('users')->whereIn('perfil_acceso_id', $perfiles)->select('id'))
            ->delete();
    }
};
