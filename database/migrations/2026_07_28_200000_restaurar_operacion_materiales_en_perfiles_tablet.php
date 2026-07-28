<?php

use App\Models\User;
use App\Services\Autorizacion\CatalogoModulosAcceso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $catalogo = app(CatalogoModulosAcceso::class);
        $perfilesActualizados = [];

        DB::table('perfiles_acceso')
            ->where('predeterminado', true)
            ->select(['id', 'modulos', 'modulos_tablet'])
            ->orderBy('id')
            ->each(function (object $perfil) use ($catalogo, &$perfilesActualizados): void {
                $modulosOficina = $this->decodificar($perfil->modulos);

                if (! in_array('materiales.inventario', $modulosOficina, true)) {
                    return;
                }

                $seleccionados = array_values(array_unique([
                    ...$this->decodificar($perfil->modulos_tablet),
                    CatalogoModulosAcceso::TABLET_OPERACION_MATERIALES,
                ]));
                $ordenados = array_values(array_intersect(
                    $catalogo->clavesTablet(),
                    $seleccionados,
                ));

                DB::table('perfiles_acceso')
                    ->where('id', $perfil->id)
                    ->update([
                        'modulos_tablet' => json_encode($ordenados, JSON_THROW_ON_ERROR),
                    ]);
                $perfilesActualizados[] = $perfil->id;
            });

        $this->revocarSesionesTablet($perfilesActualizados);
    }

    public function down(): void
    {
        $perfilesActualizados = [];

        DB::table('perfiles_acceso')
            ->select(['id', 'modulos_tablet'])
            ->orderBy('id')
            ->each(function (object $perfil) use (&$perfilesActualizados): void {
                $seleccionados = $this->decodificar($perfil->modulos_tablet);
                if (! in_array(
                    CatalogoModulosAcceso::TABLET_OPERACION_MATERIALES,
                    $seleccionados,
                    true,
                )) {
                    return;
                }

                $modulosTablet = array_values(array_filter(
                    $seleccionados,
                    fn (string $modulo): bool => (
                        $modulo !== CatalogoModulosAcceso::TABLET_OPERACION_MATERIALES
                    ),
                ));

                DB::table('perfiles_acceso')
                    ->where('id', $perfil->id)
                    ->update([
                        'modulos_tablet' => json_encode($modulosTablet, JSON_THROW_ON_ERROR),
                    ]);
                $perfilesActualizados[] = $perfil->id;
            });

        $this->revocarSesionesTablet($perfilesActualizados);
    }

    /**
     * @return array<int, string>
     */
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

    /**
     * @param  array<int, string>  $perfiles
     */
    private function revocarSesionesTablet(array $perfiles): void
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
            ->whereNotNull('dispositivo_id')
            ->delete();
    }
};
