<?php

use App\Models\User;
use App\Services\Autorizacion\CatalogoModulosAcceso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULO_OFICINA = 'materia-prima.fruta-proceso';

    private const ROLES_PREDETERMINADOS = [
        'administrador',
        'supervisor_frio',
        'camarero_frio',
        'consulta',
    ];

    public function up(): void
    {
        $perfilesActualizados = [];

        DB::table('perfiles_acceso')
            ->where('predeterminado', true)
            ->whereIn('rol_base', self::ROLES_PREDETERMINADOS)
            ->select(['id', 'modulos', 'modulos_tablet'])
            ->orderBy('id')
            ->each(function (object $perfil) use (&$perfilesActualizados): void {
                $modulos = array_values(array_unique([
                    ...$this->decodificar($perfil->modulos),
                    self::MODULO_OFICINA,
                ]));
                $modulosTablet = array_values(array_unique([
                    ...$this->decodificar($perfil->modulos_tablet),
                    CatalogoModulosAcceso::TABLET_FRUTA_PROCESO,
                ]));

                DB::table('perfiles_acceso')
                    ->where('id', $perfil->id)
                    ->update([
                        'modulos' => json_encode($modulos, JSON_THROW_ON_ERROR),
                        'modulos_tablet' => json_encode($modulosTablet, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                $perfilesActualizados[] = $perfil->id;
            });

        $this->revocarSesiones($perfilesActualizados);
    }

    public function down(): void
    {
        $perfilesActualizados = [];

        DB::table('perfiles_acceso')
            ->where('predeterminado', true)
            ->whereIn('rol_base', self::ROLES_PREDETERMINADOS)
            ->select(['id', 'modulos', 'modulos_tablet'])
            ->orderBy('id')
            ->each(function (object $perfil) use (&$perfilesActualizados): void {
                DB::table('perfiles_acceso')
                    ->where('id', $perfil->id)
                    ->update([
                        'modulos' => json_encode(array_values(array_filter(
                            $this->decodificar($perfil->modulos),
                            fn (string $modulo): bool => $modulo !== self::MODULO_OFICINA,
                        )), JSON_THROW_ON_ERROR),
                        'modulos_tablet' => json_encode(array_values(array_filter(
                            $this->decodificar($perfil->modulos_tablet),
                            fn (string $modulo): bool => (
                                $modulo !== CatalogoModulosAcceso::TABLET_FRUTA_PROCESO
                            ),
                        )), JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                $perfilesActualizados[] = $perfil->id;
            });

        $this->revocarSesiones($perfilesActualizados);
    }

    /** @return array<int, string> */
    private function decodificar(mixed $valor): array
    {
        if (is_array($valor)) {
            return array_values(array_filter($valor, is_string(...)));
        }

        $decodificado = json_decode((string) $valor, true);

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

        $usuarios = DB::table('users')
            ->whereIn('perfil_acceso_id', $perfiles)
            ->pluck('id');

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $usuarios)
            ->delete();
    }
};
