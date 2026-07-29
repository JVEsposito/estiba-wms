<?php

namespace Tests\Feature\Database;

use App\Models\Camara;
use App\Models\TunelPrefrio;
use App\Models\User;
use App\Services\Secuencias\ServicioSecuenciaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SecuenciasConfiguracionFisicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_sincroniza_correlativos_historicos_sin_retroceder_contadores(): void
    {
        $usuario = User::factory()->create();
        Camara::create(['codigo' => 'CAM-09', 'nombre' => 'Cámara histórica']);
        Camara::create(['codigo' => 'CAM-EXTERNA', 'nombre' => 'Cámara externa']);
        TunelPrefrio::create([
            'codigo' => 'TUN-12',
            'nombre' => 'Túnel histórico',
            'capacidad_posiciones' => 20,
            'creado_por_user_id' => $usuario->id,
        ]);
        TunelPrefrio::create([
            'codigo' => 'TUN-EXTERNO',
            'nombre' => 'Túnel externo',
            'capacidad_posiciones' => 20,
            'creado_por_user_id' => $usuario->id,
        ]);

        DB::table('secuencias_documentos')
            ->whereIn('clave', ['camaras', 'tuneles_prefrio'])
            ->delete();
        $migracion = require database_path(
            'migrations/2026_07_29_100000_agregar_secuencias_configuracion_fisica.php',
        );
        $migracion->up();

        $this->assertDatabaseHas('secuencias_documentos', [
            'clave' => 'camaras',
            'ultimo_numero' => 9,
        ]);
        $this->assertDatabaseHas('secuencias_documentos', [
            'clave' => 'tuneles_prefrio',
            'ultimo_numero' => 12,
        ]);

        DB::table('secuencias_documentos')
            ->where('clave', 'camaras')
            ->update(['ultimo_numero' => 20]);
        $migracion->up();

        $this->assertDatabaseHas('secuencias_documentos', [
            'clave' => 'camaras',
            'ultimo_numero' => 20,
        ]);

        try {
            DB::transaction(function (): void {
                $numero = app(ServicioSecuenciaDocumento::class)
                    ->reservarSiguiente('camaras');
                $this->assertSame(21, $numero);

                throw new RuntimeException('Forzar reversa de la operación.');
            });
        } catch (RuntimeException) {
            // La reserva forma parte de la misma transacción que crea el documento.
        }

        $this->assertDatabaseHas('secuencias_documentos', [
            'clave' => 'camaras',
            'ultimo_numero' => 20,
        ]);
    }
}
