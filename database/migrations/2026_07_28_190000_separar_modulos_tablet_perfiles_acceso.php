<?php

use App\Services\Autorizacion\CatalogoModulosAcceso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfiles_acceso', function (Blueprint $table): void {
            $table->json('modulos_tablet')->nullable()->after('modulos');
        });

        $catalogo = app(CatalogoModulosAcceso::class);

        DB::table('perfiles_acceso')
            ->select(['id', 'modulos'])
            ->orderBy('id')
            ->each(function (object $perfil) use ($catalogo): void {
                $modulosOficina = json_decode((string) $perfil->modulos, true);
                $modulosTablet = $catalogo->modulosTabletCompatiblesCon(
                    is_array($modulosOficina) ? $modulosOficina : [],
                );

                DB::table('perfiles_acceso')
                    ->where('id', $perfil->id)
                    ->update([
                        'modulos_tablet' => json_encode($modulosTablet, JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('perfiles_acceso', function (Blueprint $table): void {
            $table->dropColumn('modulos_tablet');
        });
    }
};
