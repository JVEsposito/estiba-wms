<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoValidacionPallet;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\ResultadoValidacionPallet;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\ArticuloValidacion;
use App\Models\BloqueMercado;
use App\Models\Camara;
use App\Models\CategoriaValidacion;
use App\Models\Cliente;
use App\Models\ClienteValidacion;
use App\Models\CombinacionValidacion;
use App\Models\CondicionSag;
use App\Models\CsgValidacion;
use App\Models\Dispositivo;
use App\Models\EspecieValidacion;
use App\Models\Folio;
use App\Models\OrigenValidacion;
use App\Models\Pais;
use App\Models\Posicion;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Models\ValidacionPallet;
use App\Models\VariedadValidacion;
use App\Services\Existencias\ServicioExistencias;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InspeccionSagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_contiene_todos_los_destinos_y_bloque_ue_con_fotografia_de_27_miembros(): void
    {
        $administrador = $this->administradorConTemporada();

        $this->assertSame(250, Pais::query()->count());
        $this->assertSame(249, Pais::query()->where('es_iso_oficial', true)->count());
        $this->assertSame(27, BloqueMercado::query()->where('codigo', 'UE')->firstOrFail()->paises()->count());

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/inspeccion-sag/catalogos')
            ->assertOk()
            ->assertJsonCount(250, 'paises')
            ->assertJsonPath('bloques.0.codigo', 'UE')
            ->assertJsonCount(27, 'bloques.0.paises')
            ->assertJsonCount(4, 'tipos_lote')
            ->assertJsonFragment(['value' => 'muestreo_usda', 'label' => 'Muestreo USDA', 'tipo_aprobacion' => 'AU'])
            ->assertJsonFragment(['value' => 'inspeccion_origen', 'label' => 'Inspección Origen', 'tipo_aprobacion' => 'AO'])
            ->assertJsonFragment(['value' => 'fumigacion', 'label' => 'Fumigación', 'tipo_aprobacion' => 'AF'])
            ->assertJsonFragment(['value' => 'cambio_mercado', 'label' => 'Cambio de mercado', 'tipo_aprobacion' => null])
            ->assertJsonFragment(['value' => 'AO', 'label' => 'Aprobado Origen'])
            ->assertJsonFragment(['value' => 'AU', 'label' => 'Aprobado USDA'])
            ->assertJsonFragment(['value' => 'AF', 'label' => 'Aprobado Fumigación']);
    }

    public function test_cliente_y_especie_son_obligatorios_y_los_otros_cinco_filtros_se_combinan(): void
    {
        $administrador = $this->administradorConTemporada();
        $condicion = CondicionSag::create(['codigo' => 'SAG-OK', 'nombre' => 'Con condición', 'activo' => true]);
        $esperado = $this->crearFolioUbicado('SAG-FILTRO-001', [
            'exportadora' => 'EX-01',
            'variedad' => 'Regina',
            'condicion_sag_id' => $condicion->id,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'fecha_ingreso' => '2026-08-11 08:00:00',
            'datos_externos' => ['especie' => 'Cereza', 'csg' => 'CSG-001', 'cantidad_cajas' => 60],
        ]);
        $this->crearFolioUbicado('SAG-FILTRO-002', [
            'exportadora' => 'EX-01',
            'variedad' => 'Lapins',
            'datos_externos' => ['especie' => 'Cereza', 'csg' => 'CSG-002'],
        ]);
        $this->crearFolioUbicado('SAG-FILTRO-SALDO', [
            'exportadora' => 'EX-01',
            'tipo_bulto' => TipoBulto::Saldo,
            'datos_externos' => ['especie' => 'Cereza', 'csg' => 'CSG-001'],
        ]);
        $esperado->load([
            'validacionPallet.articulo.especieCatalogo',
            'validacionPallet.articulo.variedadCatalogo',
            'validacionPallet.origen.clienteCatalogo.cliente',
            'validacionPallet.origen.csgCatalogo',
        ]);
        $clienteId = $esperado->validacionPallet->origen->clienteCatalogo->cliente->id;
        $especieId = $esperado->validacionPallet->articulo->especieCatalogo->id;
        $variedadId = $esperado->validacionPallet->articulo->variedadCatalogo->id;
        $csgId = $esperado->validacionPallet->origen->csgCatalogo->id;

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/inspeccion-sag/folios/opciones')
            ->assertOk()
            ->assertJsonPath('clientes.0.id', $clienteId)
            ->assertJsonPath('clientes.0.especies.0.id', $especieId)
            ->assertJsonFragment(['id' => $variedadId, 'nombre' => 'Regina'])
            ->assertJsonFragment(['id' => $csgId, 'codigo' => 'CSG-001']);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/inspeccion-sag/folios')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente', 'especie']);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/inspeccion-sag/folios?'.http_build_query([
                'cliente' => $clienteId,
                'especie' => $especieId,
                'variedad' => $variedadId,
                'condicion_sag' => 'con',
                'csg' => $csgId,
                'fecha_ingreso' => '2026-08-11',
                'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado->value,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $esperado->id)
            ->assertJsonPath('data.0.sag.estado', 'SI');
    }

    public function test_cambio_de_mercado_agrega_destino_y_resultados_no_aprobatorios_conservan_aprobaciones_previas(): void
    {
        $administrador = $this->administradorConTemporada();
        $folio = $this->crearFolioUbicado('SAG-MERCADO-001', [
            'exportadora' => 'EX-MERCADO',
            'datos_externos' => ['especie' => 'Kiwi', 'csg' => 'CSG-M'],
        ]);
        $chile = Pais::query()->where('iso_alpha2', 'CL')->firstOrFail();
        $usa = Pais::query()->where('iso_alpha2', 'US')->firstOrFail();

        $primerLote = $this->crearLote($administrador, $folio, 'inspeccion_origen', 'pais', $chile->id);
        $this->resolverLote($administrador, $primerLote, 'aprobado');

        $segundoLote = $this->crearLote($administrador, $folio, 'cambio_mercado', 'pais', $usa->id);
        $this->resolverLote($administrador, $segundoLote, 'aprobado', 'AU');

        $tercerLote = $this->crearLote($administrador, $folio, 'muestreo_usda', 'pais', $usa->id);
        $this->resolverLote($administrador, $tercerLote, 'sin_resolucion');

        $cuartoLote = $this->crearLote($administrador, $folio, 'fumigacion', 'pais', $chile->id);
        $this->resolverLote($administrador, $cuartoLote, 'segregado');

        $this->assertDatabaseCount('autorizaciones_sag_folio', 2);
        $this->assertDatabaseHas('autorizaciones_sag_folio', [
            'folio_id' => $folio->id,
            'tipo_aprobacion' => 'AO',
            'pais_id' => $chile->id,
            'activa' => true,
        ]);
        $this->assertDatabaseHas('autorizaciones_sag_folio', [
            'folio_id' => $folio->id,
            'tipo_aprobacion' => 'AU',
            'pais_id' => $usa->id,
            'activa' => true,
        ]);

        $fila = app(ServicioExistencias::class)
            ->filas(ServicioExistencias::PRODUCTO_TERMINADO)
            ->firstWhere('folio', $folio->numero_folio);
        $this->assertSame('AO · AU', $fila['estado_sag']);
        $this->assertStringContainsString('Chile', $fila['destinos_sag']);
        $this->assertStringContainsString('Estados Unidos', $fila['destinos_sag']);
    }

    public function test_bloque_ue_reemplaza_paises_miembro_redundantes_y_no_admite_folio_sin_ubicacion(): void
    {
        $administrador = $this->administradorConTemporada();
        $ubicado = $this->crearFolioUbicado('SAG-UE-001', [
            'exportadora' => 'EX-UE',
            'datos_externos' => ['especie' => 'Arándano'],
        ]);
        $sinUbicacion = Folio::create([
            'temporada_id' => $ubicado->temporada_id,
            'numero_folio' => 'SAG-SIN-UBICACION',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'exportadora' => 'EX-UE',
            'datos_externos' => ['especie' => 'Arándano'],
        ]);
        $ue = BloqueMercado::query()->where('codigo', 'UE')->firstOrFail();
        $francia = Pais::query()->where('iso_alpha2', 'FR')->firstOrFail();

        $respuesta = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/inspeccion-sag/lotes', [
                'tipo' => 'muestreo_usda',
                'folios' => [$ubicado->id],
                'destinos' => [
                    ['tipo' => 'bloque', 'id' => $ue->id],
                    ['tipo' => 'pais', 'id' => $francia->id],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'destinos')
            ->assertJsonPath('destinos.0.codigo', 'UE')
            ->assertJsonCount(27, 'destinos.0.miembros');

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/inspeccion-sag/lotes', [
                'tipo' => 'muestreo_usda',
                'folios' => [$sinUbicacion->id],
                'destinos' => [['tipo' => 'bloque', 'id' => $ue->id]],
            ])
            ->assertStatus(409);
    }

    private function administradorConTemporada(): User
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'TEMP-SAG',
            'nombre' => 'Temporada SAG',
            'activa' => true,
        ], usuarioId: $administrador->id);

        return $administrador;
    }

    /** @param array<string, mixed> $atributos */
    private function crearFolioUbicado(string $numero, array $atributos = []): Folio
    {
        $camara = Camara::query()->firstOrCreate(['codigo' => 'CAM-SAG'], [
            'nombre' => 'Cámara SAG',
            'contenido' => ContenidoCamara::Productos,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 100,
            'cantidad_niveles' => 1,
        ]);
        $numeroPosicion = Posicion::query()->where('camara_id', $camara->id)->count() + 1;
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => $numeroPosicion,
            'nivel' => 1,
            'etiqueta' => 'B01-P'.str_pad((string) $numeroPosicion, 2, '0', STR_PAD_LEFT).'-N1',
        ]);
        $folio = Folio::create(array_merge([
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'exportadora' => 'EX-SAG',
            'variedad' => 'Estándar',
            'datos_externos' => ['especie' => 'Cereza'],
        ], $atributos));
        UbicacionActual::create([
            'folio_id' => $folio->id,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
            'movimiento_id' => null,
            'ubicado_at' => now(),
        ]);
        $this->vincularCatalogoValidacion($folio);

        return $folio;
    }

    private function vincularCatalogoValidacion(Folio $folio): void
    {
        $datos = $folio->datos_externos ?? [];
        $especieNombre = (string) ($datos['especie'] ?? 'Cereza');
        $variedadNombre = (string) ($folio->variedad ?? 'Estándar');
        $csgCodigo = (string) ($datos['csg'] ?? 'CSG-SAG');
        $cliente = Cliente::query()->firstOrCreate([
            'codigo' => (string) $folio->exportadora,
        ], [
            'nombre' => (string) $folio->exportadora,
            'activo' => true,
        ]);
        $clienteCatalogo = ClienteValidacion::query()->firstOrCreate([
            'temporada_id' => $folio->temporada_id,
            'cliente_id' => $cliente->id,
        ], [
            'nombre' => $cliente->nombre,
            'activo' => true,
        ]);
        $especie = EspecieValidacion::query()->firstOrCreate([
            'temporada_id' => $folio->temporada_id,
            'nombre' => $especieNombre,
        ], ['activo' => true]);
        $variedad = VariedadValidacion::query()->firstOrCreate([
            'especie_validacion_id' => $especie->id,
            'nombre' => $variedadNombre,
        ], ['activo' => true]);
        $csg = CsgValidacion::query()->firstOrCreate([
            'temporada_id' => $folio->temporada_id,
            'codigo' => $csgCodigo,
        ], ['activo' => true]);
        $csg->variedades()->syncWithoutDetaching([$variedad->id]);
        $articulo = ArticuloValidacion::query()->firstOrCreate([
            'temporada_id' => $folio->temporada_id,
            'cliente_validacion_id' => $clienteCatalogo->id,
            'especie' => $especieNombre,
            'variedad' => $variedadNombre,
            'calibre' => (string) ($folio->calibre ?? 'S/C'),
            'envase' => (string) ($datos['envase'] ?? 'Pallet'),
        ], [
            'especie_validacion_id' => $especie->id,
            'variedad_validacion_id' => $variedad->id,
            'activo' => true,
        ]);
        $origen = OrigenValidacion::query()->firstOrCreate([
            'temporada_id' => $folio->temporada_id,
            'cliente' => $cliente->nombre,
            'marca' => (string) ($folio->marca ?? 'Marca SAG'),
            'csg' => $csgCodigo,
        ], [
            'cliente_validacion_id' => $clienteCatalogo->id,
            'csg_validacion_id' => $csg->id,
            'activo' => true,
        ]);
        CombinacionValidacion::query()->firstOrCreate([
            'temporada_id' => $folio->temporada_id,
            'articulo_validacion_id' => $articulo->id,
            'origen_validacion_id' => $origen->id,
        ], ['activo' => true]);
        $categoria = CategoriaValidacion::query()->firstOrCreate([
            'temporada_id' => $folio->temporada_id,
            'nombre' => 'Exportación',
        ], ['activo' => true]);
        $dispositivo = Dispositivo::query()->firstOrCreate([
            'codigo' => 'SAG-TEST',
        ], [
            'nombre' => 'PDA SAG Test',
            'plataforma' => 'android',
            'activo' => true,
        ]);

        ValidacionPallet::query()->create([
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', $folio->numero_folio),
            'numero_folio' => $folio->numero_folio,
            'numero_intento' => 1,
            'tipo_bulto' => $folio->tipo_bulto->value,
            'cantidad_cajas' => (int) ($datos['cantidad_cajas'] ?? 60),
            'linea_proceso' => 1,
            'turno' => 'A',
            'temporada_id' => $folio->temporada_id,
            'articulo_validacion_id' => $articulo->id,
            'origen_validacion_id' => $origen->id,
            'categoria_validacion_id' => $categoria->id,
            'resultado' => ResultadoValidacionPallet::Aprobado,
            'estado' => EstadoValidacionPallet::Aceptada,
            'catalogo_version_dispositivo' => 1,
            'catalogo_version_servidor' => 1,
            'snapshot' => [],
            'user_id' => User::query()->value('id'),
            'dispositivo_id' => $dispositivo->id,
            'folio_id' => $folio->id,
            'generado_dispositivo_at' => now(),
            'recibido_servidor_at' => now(),
        ]);
    }

    private function crearLote(User $usuario, Folio $folio, string $tipo, string $tipoDestino, string $destinoId): array
    {
        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/inspeccion-sag/lotes', [
                'operacion_id' => (string) Str::uuid(),
                'tipo' => $tipo,
                'folios' => [$folio->id],
                'destinos' => [['tipo' => $tipoDestino, 'id' => $destinoId]],
            ])
            ->assertCreated()
            ->json();

        return $this->actingAs($usuario, 'sanctum')
            ->postJson("/api/inspeccion-sag/lotes/{$respuesta['id']}/iniciar")
            ->assertOk()
            ->json();
    }

    private function resolverLote(User $usuario, array $lote, string $decision, ?string $tipoAprobacion = null): void
    {
        $resultadoId = $lote['folios'][0]['resultados'][0]['id'];
        $this->actingAs($usuario, 'sanctum')
            ->postJson("/api/inspeccion-sag/lotes/{$lote['id']}/resultados/{$resultadoId}/resolver", [
                'resultado' => $decision,
                'tipo_aprobacion' => $tipoAprobacion,
            ])
            ->assertOk();
        $this->actingAs($usuario, 'sanctum')
            ->postJson("/api/inspeccion-sag/lotes/{$lote['id']}/finalizar")
            ->assertOk();
    }
}
