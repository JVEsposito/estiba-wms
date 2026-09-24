<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoCarga;
use App\Enums\EstadoCargaFolio;
use App\Enums\PrioridadCarga;
use App\Enums\RolUsuario;
use App\Models\ArchivoTemporada;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\Folio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Temporadas\Archivo\ServicioArchivoTemporada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ArchivoTemporadaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('archivo_temporadas');
        $this->administrador = User::factory()->create(['rol' => RolUsuario::Administrador, 'activo' => true]);
    }

    public function test_solo_se_archiva_una_temporada_cerrada_hace_mas_de_sesenta_dias(): void
    {
        $activa = Temporada::query()->where('activa', true)->firstOrFail();
        $reciente = $this->temporada('ARCH-RECIENTE', now()->subDays(10)->toDateString());
        $sinFin = $this->temporada('ARCH-SIN-FIN', null);

        $this->actingAs($this->administrador, 'sanctum')
            ->getJson("/api/administracion/temporadas/{$activa->id}/archivos")
            ->assertOk()
            ->assertJsonPath('data.elegible', false)
            ->assertJsonPath('data.motivo', 'La temporada activa no se archiva.');
        $this->getJson("/api/administracion/temporadas/{$reciente->id}/archivos")
            ->assertOk()
            ->assertJsonPath('data.elegible', false)
            ->assertJsonPath('data.motivo', 'La temporada se conserva al menos 60 días después de su cierre: podrá archivarse desde el '.now()->subDays(10)->addDays(60)->format('d-m-Y').'.');
        $this->postJson("/api/administracion/temporadas/{$sinFin->id}/archivos")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Registra la fecha de fin de la temporada antes de archivarla.');
        $this->assertDatabaseCount('archivos_temporada', 0);
    }

    public function test_solo_el_administrador_accede_al_archivo(): void
    {
        $cerrada = $this->temporada('ARCH-PERMISOS', now()->subDays(90)->toDateString());
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio, 'activo' => true]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/administracion/temporadas/{$cerrada->id}/archivos")
            ->assertForbidden();
        $this->postJson("/api/administracion/temporadas/{$cerrada->id}/archivos")
            ->assertForbidden();
    }

    public function test_genera_un_archivo_verificado_y_restaurable_sin_datos_de_otras_temporadas(): void
    {
        $cerrada = $this->temporada('ARCH-2025', now()->subDays(90)->toDateString());
        $folio = $this->folio($cerrada, 'PAL-ARCH-PROPIO');
        $this->folio(Temporada::query()->where('activa', true)->firstOrFail(), 'PAL-ARCH-AJENO');
        $carga = Carga::create([
            'temporada_id' => $cerrada->id,
            'codigo' => 'CAR-ARCH-0001',
            'estado' => EstadoCarga::Cerrada,
            'prioridad' => PrioridadCarga::Normal,
            'creada_por_user_id' => $this->administrador->id,
            'actualizada_por_user_id' => $this->administrador->id,
        ]);
        CargaFolio::create([
            'carga_id' => $carga->id,
            'folio_id' => $folio->id,
            'estado' => EstadoCargaFolio::EnAnden,
            'asignado_por_user_id' => $this->administrador->id,
            'asignado_at' => now()->subDays(100),
        ]);

        $archivoId = $this->actingAs($this->administrador, 'sanctum')
            ->postJson("/api/administracion/temporadas/{$cerrada->id}/archivos")
            ->assertAccepted()
            ->json('data.id');

        $archivo = ArchivoTemporada::query()->findOrFail($archivoId);
        $this->assertSame(ServicioArchivoTemporada::VERIFICADO, $archivo->estado, (string) $archivo->mensaje_error);
        $tablas = $archivo->manifiesto['tablas'];
        $this->assertSame(1, $tablas['folios']['filas']);
        $this->assertSame(1, $tablas['cargas']['filas']);
        $this->assertSame(1, $tablas['carga_folios']['filas']);
        $this->assertSame(1, $tablas['temporadas']['filas']);
        $this->assertSame(1, $tablas['users']['referencias']);
        $this->assertContains('folios', array_keys($archivo->manifiesto['excel']));
        $this->assertDatabaseCount('archivo_temporada_claves', 0);
        // Archivar no borra ningún dato.
        $this->assertDatabaseHas('folios', ['id' => $folio->id]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('archivo_temporadas')->path($archivo->ruta)));
        $folios = $zip->getFromName('datos/folios.sql');
        $this->assertStringContainsString('PAL-ARCH-PROPIO', $folios);
        $this->assertStringNotContainsString('PAL-ARCH-AJENO', $folios);
        $this->assertStringContainsString('CREATE TABLE `carga_folios`', $zip->getFromName('esquema.sql'));
        $this->assertNotFalse($zip->getFromName('excel/folios.xlsx'));
        $zip->close();

        $this->getJson("/api/administracion/temporadas/{$cerrada->id}/archivos")
            ->assertOk()
            ->assertJsonPath('data.archivos.0.estado', 'verificado')
            ->assertJsonPath('data.archivos.0.solicitado_por', $this->administrador->name);
        $this->get("/api/administracion/archivos-temporada/{$archivo->id}/descargar")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');
    }

    public function test_la_verificacion_detecta_un_paquete_alterado(): void
    {
        $cerrada = $this->temporada('ARCH-ALTERADO', now()->subDays(90)->toDateString());
        $this->folio($cerrada, 'PAL-ARCH-ALTERADO');
        $servicio = app(ServicioArchivoTemporada::class);
        $archivo = $servicio->procesar($servicio->solicitar($cerrada, $this->administrador));
        $this->assertSame(ServicioArchivoTemporada::VERIFICADO, $archivo->estado, (string) $archivo->mensaje_error);

        Storage::disk('archivo_temporadas')->append($archivo->ruta, 'alterado');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La huella del paquete no coincide con la registrada.');
        $servicio->verificar($archivo);
    }

    private function temporada(string $codigo, ?string $fin): Temporada
    {
        return Temporada::query()->create([
            'codigo' => $codigo,
            'nombre' => 'Temporada '.$codigo,
            'activa' => false,
            'fecha_inicio' => $fin ? now()->parse($fin)->subMonths(5)->toDateString() : null,
            'fecha_fin' => $fin,
        ]);
    }

    private function folio(Temporada $temporada, string $numero): Folio
    {
        return Folio::query()->create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numero,
            'tipo_bulto' => 'pallet',
            'estado_operacional' => 'despachado',
            'fecha_ingreso' => now()->subDays(120),
            'activo' => false,
        ]);
    }
}
