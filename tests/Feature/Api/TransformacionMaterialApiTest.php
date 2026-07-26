<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\Cliente;
use App\Models\ClienteMaterial;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\MovimientoInventarioMaterial;
use App\Models\PerfilImpresionEtiqueta;
use App\Models\Posicion;
use App\Models\ProveedorMaterial;
use App\Models\ReservaTransformacionMaterial;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransformacionMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_receta_planifica_fifo_y_cancela_liberando_reservas(): void
    {
        [$administrador, $tokenOficina, $cliente, $proveedor, $entradaPrincipal, $entradaAuxiliar, $salida] =
            $this->prepararCatalogo();
        [, , $tokenTablet] = $this->crearOperador();
        [$camara, $posicionUno, $posicionDos] = $this->crearCamaraMateriales();
        $recepcion = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/recepciones', $this->payloadRecepcion(
                $cliente,
                $proveedor,
                $entradaPrincipal,
                $entradaAuxiliar,
            ))
            ->assertCreated()
            ->json('data');
        $confirmada = $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/confirmar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->json('data');
        $folioPrincipal = $confirmada['detalles'][0]['bultos'][0]['folio']['numero_folio'];
        $folioAuxiliar = $confirmada['detalles'][1]['bultos'][0]['folio']['numero_folio'];
        $sesion = $this->conToken($tokenTablet)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');

        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $folioPrincipal,
                $posicionUno,
                $sesion,
                $entradaPrincipal,
                0,
            ))
            ->assertOk();
        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $folioAuxiliar,
                $posicionDos,
                $sesion,
                $entradaAuxiliar,
                1,
            ))
            ->assertOk();

        $receta = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/recetas', [
                'cliente_id' => $cliente->id,
                'item_salida_id' => $salida->id,
                'nombre' => 'Caja preparada 10 kg',
                'cantidad_base_salida' => 100,
                'componentes' => [
                    [
                        'item_entrada_id' => $entradaPrincipal->id,
                        'cantidad_estandar' => 100,
                        'es_componente_principal' => true,
                        'factor_conversion' => 1,
                        'merma_estandar_porcentaje' => 1,
                        'tolerancia_porcentaje' => 0.5,
                    ],
                    [
                        'item_entrada_id' => $entradaAuxiliar->id,
                        'cantidad_estandar' => 10,
                        'es_componente_principal' => false,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.versiones.0.numero_version', 1)
            ->assertJsonPath('data.versiones.0.estado', 'activa')
            ->assertJsonCount(2, 'data.versiones.0.componentes')
            ->json('data');

        $operacionOrden = (string) Str::uuid();
        $payloadOrden = [
            'operacion_id' => $operacionOrden,
            'version_receta_material_id' => $receta['versiones'][0]['id'],
            'cantidad_planificada_salida' => 50,
            'linea' => 'Armado 1',
            'turno' => 'Día',
            'fecha_operacional' => '2026-07-24',
        ];
        $orden = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/ordenes', $payloadOrden)
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.version', 1)
            ->json('data');
        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/ordenes', $payloadOrden)
            ->assertCreated()
            ->assertJsonPath('data.id', $orden['id']);

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/recetas/{$receta['id']}/versiones", [
                'cantidad_base_salida' => 100,
                'componentes' => [
                    [
                        'item_entrada_id' => $entradaPrincipal->id,
                        'cantidad_estandar' => 120,
                        'es_componente_principal' => true,
                    ],
                    [
                        'item_entrada_id' => $entradaAuxiliar->id,
                        'cantidad_estandar' => 20,
                        'es_componente_principal' => false,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.versiones.0.numero_version', 2)
            ->assertJsonPath('data.versiones.1.estado', 'retirada');

        $operacionPlanificacion = (string) Str::uuid();
        $planificada = $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/planificar", [
                'operacion_id' => $operacionPlanificacion,
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'planificada')
            ->assertJsonPath('data.version', 2)
            ->assertJsonCount(2, 'data.reservas')
            ->assertJsonPath('data.eventos.1.tipo', 'planificada')
            ->json('data');

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/planificar", [
                'operacion_id' => $operacionPlanificacion,
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $orden['id']);
        $this->assertSame(2, ReservaTransformacionMaterial::query()
            ->where('orden_transformacion_material_id', $orden['id'])
            ->count());
        $this->assertDatabaseHas('reservas_transformacion_materiales', [
            'orden_transformacion_material_id' => $orden['id'],
            'item_material_id' => $entradaPrincipal->id,
            'cantidad' => 50,
            'estado' => 'activa',
            'orden_fifo' => 1,
        ]);
        $this->assertDatabaseHas('reservas_transformacion_materiales', [
            'orden_transformacion_material_id' => $orden['id'],
            'item_material_id' => $entradaAuxiliar->id,
            'cantidad' => 5,
            'estado' => 'activa',
            'orden_fifo' => 1,
        ]);
        $this->assertSame('50.000', FolioMaterial::query()
            ->findOrFail(Folio::query()->where('numero_folio', $folioPrincipal)->value('id'))
            ->cantidad_reservada);
        $this->assertSame('5.000', FolioMaterial::query()
            ->findOrFail(Folio::query()->where('numero_folio', $folioAuxiliar)->value('id'))
            ->cantidad_reservada);
        $this->assertSame($administrador->id, $planificada['creado_por']['id']);

        $operacionCancelacion = (string) Str::uuid();
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/cancelar", [
                'operacion_id' => $operacionCancelacion,
                'motivo' => 'Cambio de programación de la línea.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada')
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.eventos.2.tipo', 'cancelada');
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/cancelar", [
                'operacion_id' => $operacionCancelacion,
                'motivo' => 'Cambio de programación de la línea.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada');
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/cancelar", [
                'operacion_id' => $operacionCancelacion,
                'motivo' => 'Motivo diferente con el mismo UUID.',
            ])
            ->assertConflict();

        $this->assertSame('0.000', FolioMaterial::query()
            ->findOrFail(Folio::query()->where('numero_folio', $folioPrincipal)->value('id'))
            ->cantidad_reservada);
        $this->assertSame('0.000', FolioMaterial::query()
            ->findOrFail(Folio::query()->where('numero_folio', $folioAuxiliar)->value('id'))
            ->cantidad_reservada);
        $this->assertSame(2, ReservaTransformacionMaterial::query()
            ->where('orden_transformacion_material_id', $orden['id'])
            ->where('estado', 'liberada')
            ->count());

        $recetaSinSaldo = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/recetas', [
                'cliente_id' => $cliente->id,
                'item_salida_id' => $salida->id,
                'nombre' => 'Receta con auxiliar insuficiente',
                'cantidad_base_salida' => 100,
                'componentes' => [
                    [
                        'item_entrada_id' => $entradaPrincipal->id,
                        'cantidad_estandar' => 100,
                        'es_componente_principal' => true,
                    ],
                    [
                        'item_entrada_id' => $entradaAuxiliar->id,
                        'cantidad_estandar' => 40,
                        'es_componente_principal' => false,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');
        $ordenSinSaldo = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/ordenes', [
                'operacion_id' => (string) Str::uuid(),
                'version_receta_material_id' => $recetaSinSaldo['versiones'][0]['id'],
                'cantidad_planificada_salida' => 50,
                'fecha_operacional' => '2026-07-24',
            ])
            ->assertCreated()
            ->json('data');
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$ordenSinSaldo['id']}/planificar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->assertSame(0, ReservaTransformacionMaterial::query()
            ->where('orden_transformacion_material_id', $ordenSinSaldo['id'])
            ->count());
        $this->assertSame('0.000', FolioMaterial::query()
            ->findOrFail(Folio::query()->where('numero_folio', $folioPrincipal)->value('id'))
            ->cantidad_reservada);
        $this->assertSame('0.000', FolioMaterial::query()
            ->findOrFail(Folio::query()->where('numero_folio', $folioAuxiliar)->value('id'))
            ->cantidad_reservada);
    }

    public function test_rechaza_receta_sin_unico_componente_principal_y_restringe_permisos(): void
    {
        [$administrador, $tokenOficina, $cliente, , $entradaPrincipal, $entradaAuxiliar, $salida] =
            $this->prepararCatalogo();
        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $tokenCamarero = $camarero->createToken('oficina-consulta', ['oficina'])->plainTextToken;
        $alcance = app(AlcanceOperacionalUsuario::class);
        $capacidadesAdministrador = $alcance->capacidadesApi($administrador);
        $capacidadesCamarero = $alcance->capacidadesApi($camarero);
        $this->assertTrue($capacidadesAdministrador['puede_consultar_transformaciones_materiales']);
        $this->assertTrue($capacidadesAdministrador['puede_gestionar_transformaciones_materiales']);
        $this->assertTrue($capacidadesAdministrador['puede_administrar_recetas_materiales']);
        $this->assertTrue($capacidadesCamarero['puede_consultar_transformaciones_materiales']);
        $this->assertFalse($capacidadesCamarero['puede_gestionar_transformaciones_materiales']);
        $this->assertTrue($capacidadesCamarero['puede_operar_transformaciones_materiales']);
        $this->assertFalse($capacidadesCamarero['puede_administrar_recetas_materiales']);
        $payload = [
            'cliente_id' => $cliente->id,
            'item_salida_id' => $salida->id,
            'nombre' => 'Receta inválida',
            'cantidad_base_salida' => 100,
            'componentes' => [
                [
                    'item_entrada_id' => $entradaPrincipal->id,
                    'cantidad_estandar' => 100,
                    'es_componente_principal' => true,
                ],
                [
                    'item_entrada_id' => $entradaAuxiliar->id,
                    'cantidad_estandar' => 10,
                    'es_componente_principal' => true,
                ],
            ],
        ];

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/recetas', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->assertDatabaseCount('recetas_materiales', 0);

        $payload['componentes'][1]['es_componente_principal'] = false;
        $this->conToken($tokenCamarero)
            ->postJson('/api/materiales/transformaciones/recetas', $payload)
            ->assertForbidden();
        $this->conToken($tokenCamarero)
            ->getJson('/api/materiales/transformaciones/recetas')
            ->assertOk();
    }

    public function test_ejecuta_lotes_parciales_genera_fag_y_cierra_liberando_saldos(): void
    {
        [, $tokenOficina, $cliente, $proveedor, $entradaPrincipal, $entradaAuxiliar, $salida] =
            $this->prepararCatalogo();
        [$operador, $dispositivo, $tokenTablet] = $this->crearOperador();
        [$camara, $posicionUno, $posicionDos] = $this->crearCamaraMateriales();
        $recepcion = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/recepciones', $this->payloadRecepcion(
                $cliente,
                $proveedor,
                $entradaPrincipal,
                $entradaAuxiliar,
            ))
            ->assertCreated()
            ->json('data');
        $confirmada = $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/confirmar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->json('data');
        $folioPrincipal = $confirmada['detalles'][0]['bultos'][0]['folio'];
        $folioAuxiliar = $confirmada['detalles'][1]['bultos'][0]['folio'];
        $sesion = $this->conToken($tokenTablet)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');
        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $folioPrincipal['numero_folio'],
                $posicionUno,
                $sesion,
                $entradaPrincipal,
                0,
            ))
            ->assertOk();
        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $folioAuxiliar['numero_folio'],
                $posicionDos,
                $sesion,
                $entradaAuxiliar,
                1,
            ))
            ->assertOk();

        $receta = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/recetas', [
                'cliente_id' => $cliente->id,
                'item_salida_id' => $salida->id,
                'nombre' => 'Caja preparada operativa',
                'cantidad_base_salida' => 100,
                'componentes' => [
                    [
                        'item_entrada_id' => $entradaPrincipal->id,
                        'cantidad_estandar' => 100,
                        'es_componente_principal' => true,
                        'factor_conversion' => 1,
                        'merma_estandar_porcentaje' => 1,
                    ],
                    [
                        'item_entrada_id' => $entradaAuxiliar->id,
                        'cantidad_estandar' => 10,
                        'es_componente_principal' => false,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');
        $orden = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/ordenes', [
                'operacion_id' => (string) Str::uuid(),
                'version_receta_material_id' => $receta['versiones'][0]['id'],
                'cantidad_planificada_salida' => 50,
                'fecha_operacional' => '2026-07-25',
            ])
            ->assertCreated()
            ->json('data');
        $orden = $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/planificar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'planificada')
            ->json('data');

        $operacionInicio = (string) Str::uuid();
        $inicio = [
            'operacion_id' => $operacionInicio,
            'version_conocida' => 2,
        ];
        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/iniciar", $inicio)
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso')
            ->assertJsonPath('data.version', 3)
            ->json('data');
        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/iniciar", $inicio)
            ->assertOk()
            ->assertJsonPath('data.version', 3);

        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 3,
                'cantidad_planificada_salida' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.lotes.0.estado', 'abierto')
            ->json('data');
        $loteUno = $orden['lotes'][0];
        $payloadCierreUno = [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 4,
            'cantidad_real_salida' => 29,
            'consumos' => [
                ['folio_id' => $folioPrincipal['id'], 'cantidad' => 30],
                ['folio_id' => $folioAuxiliar['id'], 'cantidad' => 3],
            ],
        ];
        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$loteUno['id']}/cerrar", $payloadCierreUno)
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso')
            ->assertJsonPath('data.version', 5)
            ->assertJsonPath('data.cantidad_real_salida', '29.000')
            ->assertJsonPath('data.lotes.0.salida_teorica', '30.000')
            ->assertJsonPath('data.lotes.0.merma_estandar', '0.300')
            ->assertJsonPath('data.lotes.0.merma_real', '1.000')
            ->assertJsonPath('data.lotes.0.desviacion_merma', '0.700')
            ->assertJsonPath('data.lotes.0.salidas.0.numero_folio', 'FGE0000003')
            ->json('data');
        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$loteUno['id']}/cerrar", $payloadCierreUno)
            ->assertOk()
            ->assertJsonCount(1, 'data.lotes.0.salidas');

        $perfil = PerfilImpresionEtiqueta::query()->where('predeterminado', true)->firstOrFail();
        $folioSalidaUno = $orden['lotes'][0]['salidas'][0];
        $descarga = $this->conToken($tokenOficina)
            ->post(
                "/api/materiales/transformaciones/ordenes/{$orden['id']}/etiquetas",
                [
                    'operacion_id' => (string) Str::uuid(),
                    'perfil_id' => $perfil->id,
                    'formato' => 'pdf',
                    'canal' => 'oficina_descarga',
                    'folio_ids' => [$folioSalidaUno['folio_id']],
                    'copias' => 1,
                ],
                ['Accept' => 'application/pdf'],
            )
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $descarga->getContent());
        $this->assertDatabaseHas('trabajos_impresion_materiales', [
            'id' => $descarga->headers->get('X-Estiba-Print-Job'),
            'origen' => 'transformacion',
            'recepcion_material_id' => null,
            'orden_transformacion_material_id' => $orden['id'],
            'lote_transformacion_material_id' => $loteUno['id'],
        ]);
        $this->conToken($tokenOficina)
            ->getJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/impresiones")
            ->assertOk()
            ->assertJsonPath('data.0.origen', 'transformacion')
            ->assertJsonPath('data.0.folios.0.numero_folio', 'FGE0000003');
        $this->conToken($tokenTablet)
            ->post(
                "/api/materiales/transformaciones/ordenes/{$orden['id']}/etiquetas",
                [
                    'operacion_id' => (string) Str::uuid(),
                    'perfil_id' => $perfil->id,
                    'formato' => 'zpl',
                    'canal' => 'pda_directa',
                    'folio_ids' => [$folioSalidaUno['folio_id']],
                    'copias' => 1,
                    'motivo_reimpresion' => 'Etiqueta dañada durante la manipulación.',
                ],
                ['Accept' => 'application/zpl'],
            )
            ->assertOk()
            ->assertSee('FGE0000003', escape: false)
            ->assertSee('Transformaci', escape: false);

        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 5,
                'cantidad_planificada_salida' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 6)
            ->json('data');
        $loteDos = $orden['lotes'][1];
        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$loteDos['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 6,
                'cantidad_real_salida' => 20,
                'consumos' => [
                    ['folio_id' => $folioPrincipal['id'], 'cantidad' => 20],
                    ['folio_id' => $folioAuxiliar['id'], 'cantidad' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso')
            ->assertJsonPath('data.version', 7)
            ->assertJsonPath('data.cantidad_real_salida', '49.000')
            ->assertJsonPath('data.lotes.1.salidas.0.numero_folio', 'FGE0000004')
            ->json('data');

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 7,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $cerrada = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 7,
                'motivo_desviacion' => 'Producción real inferior por ajuste de línea.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cerrada')
            ->assertJsonPath('data.version', 8)
            ->assertJsonPath('data.cantidad_real_salida', '49.000')
            ->json('data');

        $this->assertCount(2, $cerrada['lotes']);
        $this->assertSame('0.000', FolioMaterial::query()
            ->findOrFail($folioPrincipal['id'])
            ->cantidad_reservada);
        $this->assertSame('30.000', FolioMaterial::query()
            ->findOrFail($folioPrincipal['id'])
            ->cantidad_actual);
        $this->assertSame('0.000', FolioMaterial::query()
            ->findOrFail($folioAuxiliar['id'])
            ->cantidad_reservada);
        $this->assertSame('5.000', FolioMaterial::query()
            ->findOrFail($folioAuxiliar['id'])
            ->cantidad_actual);
        $this->assertSame(2, Folio::query()
            ->where('origen_sistema', 'transformacion_materiales')
            ->where('estado_operacional', 'pendiente_ubicacion')
            ->where('activo', true)
            ->count());
        $this->assertSame(4, MovimientoInventarioMaterial::query()
            ->where('orden_transformacion_material_id', $orden['id'])
            ->where('tipo', 'consumo_transformacion')
            ->count());
        $this->assertSame(2, MovimientoInventarioMaterial::query()
            ->where('orden_transformacion_material_id', $orden['id'])
            ->where('tipo', 'produccion_transformacion')
            ->count());
        $this->assertDatabaseHas('eventos_transformacion_materiales', [
            'orden_transformacion_material_id' => $orden['id'],
            'tipo' => 'iniciada',
            'user_id' => $operador->id,
            'dispositivo_id' => $dispositivo->id,
        ]);
    }

    public function test_exige_motivo_fuera_de_fifo_y_revierte_el_cierre_incompleto(): void
    {
        [, $tokenOficina, $cliente, $proveedor, $entradaPrincipal, $entradaAuxiliar, $salida] =
            $this->prepararCatalogo();
        [, , $tokenTablet] = $this->crearOperador();
        [$camara, $posicionUno, $posicionDos, $posicionTres] = $this->crearCamaraMateriales();
        $payloadRecepcion = $this->payloadRecepcion(
            $cliente,
            $proveedor,
            $entradaPrincipal,
            $entradaAuxiliar,
        );
        $payloadRecepcion['numero_guia_despacho'] = 'GD-TRA-FIFO';
        $payloadRecepcion['detalles'][0]['bultos'] = [
            ['cantidad' => 40, 'lote_proveedor' => 'MP-FIFO-01'],
            ['cantidad' => 40, 'lote_proveedor' => 'MP-FIFO-02'],
        ];
        $recepcion = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/recepciones', $payloadRecepcion)
            ->assertCreated()
            ->json('data');
        $confirmada = $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/confirmar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->json('data');
        $folioPrincipalUno = $confirmada['detalles'][0]['bultos'][0]['folio'];
        $folioPrincipalDos = $confirmada['detalles'][0]['bultos'][1]['folio'];
        $folioAuxiliar = $confirmada['detalles'][1]['bultos'][0]['folio'];
        $sesion = $this->conToken($tokenTablet)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');

        foreach ([
            [$folioPrincipalUno['numero_folio'], $posicionUno, $entradaPrincipal, 0],
            [$folioPrincipalDos['numero_folio'], $posicionDos, $entradaPrincipal, 1],
            [$folioAuxiliar['numero_folio'], $posicionTres, $entradaAuxiliar, 2],
        ] as [$folio, $posicion, $item, $version]) {
            $this->conToken($tokenTablet)
                ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                    $folio,
                    $posicion,
                    $sesion,
                    $item,
                    $version,
                ))
                ->assertOk();
        }

        $receta = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/recetas', [
                'cliente_id' => $cliente->id,
                'item_salida_id' => $salida->id,
                'nombre' => 'Control FIFO operacional',
                'cantidad_base_salida' => 100,
                'componentes' => [
                    [
                        'item_entrada_id' => $entradaPrincipal->id,
                        'cantidad_estandar' => 100,
                        'es_componente_principal' => true,
                    ],
                    [
                        'item_entrada_id' => $entradaAuxiliar->id,
                        'cantidad_estandar' => 10,
                        'es_componente_principal' => false,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');
        $orden = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/transformaciones/ordenes', [
                'operacion_id' => (string) Str::uuid(),
                'version_receta_material_id' => $receta['versiones'][0]['id'],
                'cantidad_planificada_salida' => 50,
                'fecha_operacional' => '2026-07-25',
            ])
            ->assertCreated()
            ->json('data');
        $orden = $this->conToken($tokenOficina)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/planificar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->json('data');
        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/iniciar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 2,
            ])
            ->assertOk()
            ->json('data');
        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 3,
                'cantidad_planificada_salida' => 10,
            ])
            ->assertOk()
            ->json('data');
        $lote = $orden['lotes'][0];

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$lote['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 4,
                'cantidad_real_salida' => 10,
                'consumos' => [
                    ['folio_id' => $folioPrincipalDos['id'], 'cantidad' => 10],
                    ['folio_id' => $folioAuxiliar['id'], 'cantidad' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->assertDatabaseCount('consumos_transformacion_materiales', 0);
        $this->assertSame(0, MovimientoInventarioMaterial::query()
            ->where('orden_transformacion_material_id', $orden['id'])
            ->count());
        $this->assertSame('40.000', FolioMaterial::query()
            ->findOrFail($folioPrincipalDos['id'])
            ->cantidad_actual);

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$lote['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 4,
                'cantidad_real_salida' => 10,
                'consumos' => [
                    [
                        'folio_id' => $folioPrincipalDos['id'],
                        'cantidad' => 10,
                        'motivo_desviacion_fifo' => 'Bulto delantero inaccesible durante el turno.',
                    ],
                    ['folio_id' => $folioAuxiliar['id'], 'cantidad' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.lotes.0.consumos.0.siguio_fifo', false)
            ->assertJsonPath(
                'data.lotes.0.consumos.0.motivo_desviacion_fifo',
                'Bulto delantero inaccesible durante el turno.',
            )
            ->assertJsonPath('data.lotes.0.salidas.0.numero_folio', 'FGE0000004');
    }

    private function prepararCatalogo(): array
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $token = $administrador->createToken('oficina-transformacion', ['oficina'])->plainTextToken;
        $catalogo = ClienteMaterial::query()
            ->with(['cliente', 'temporada'])
            ->where('codigo', 'GENERAL')
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->firstOrFail();
        $cliente = $catalogo->cliente;
        $cliente->update(['codigo_folio_materiales' => 'GE']);
        $entradaPrincipal = $this->crearItem(
            $catalogo,
            $administrador,
            'CAJA-DES-10',
            'Caja desarmada 10 kg',
            CategoriaOperacionalMaterial::MaterialMp,
        );
        $entradaAuxiliar = $this->crearItem(
            $catalogo,
            $administrador,
            'ABS-10',
            'Absorbente para caja 10 kg',
            CategoriaOperacionalMaterial::Insumo,
        );
        $salida = $this->crearItem(
            $catalogo,
            $administrador,
            'CAJA-PREP-10',
            'Caja preparada 10 kg',
            CategoriaOperacionalMaterial::MaterialPt,
        );
        $proveedor = ProveedorMaterial::create([
            'codigo' => 'PROV-TRA',
            'nombre' => 'Proveedor transformación',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        DB::table('clientes_proveedores_materiales')->insert([
            'id' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'proveedor_material_id' => $proveedor->id,
            'activo' => true,
            'categorias' => json_encode(['Embalaje'], JSON_UNESCAPED_UNICODE),
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            $administrador,
            $token,
            $cliente,
            $proveedor,
            $entradaPrincipal,
            $entradaAuxiliar,
            $salida,
        ];
    }

    private function crearItem(
        ClienteMaterial $catalogo,
        User $usuario,
        string $codigo,
        string $nombre,
        CategoriaOperacionalMaterial $categoria,
    ): ItemMaterial {
        return ItemMaterial::create([
            'cliente_material_id' => $catalogo->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'categoria' => 'Embalaje',
            'categoria_operacional' => $categoria,
            'unidad_medida' => 'unidades',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }

    private function crearOperador(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-TRA-'.Str::upper(Str::random(6)),
            'nombre' => 'Tablet transformación',
            'activo' => true,
        ]);
        $token = $usuario
            ->crearTokenParaDispositivo($dispositivo, 'tablet-transformacion')
            ->plainTextToken;

        return [$usuario, $dispositivo, $token];
    }

    private function crearCamaraMateriales(): array
    {
        $camara = Camara::create([
            'codigo' => 'MAT-TRA-01',
            'nombre' => 'Cámara transformación',
            'contenido' => ContenidoCamara::Materiales,
        ]);
        $posiciones = collect([1, 2, 3])->map(fn (int $numero): Posicion => Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => $numero,
            'nivel' => 1,
            'etiqueta' => sprintf('B01-P%02d-N1', $numero),
        ]));

        return [$camara, ...$posiciones->all()];
    }

    private function payloadRecepcion(
        Cliente $cliente,
        ProveedorMaterial $proveedor,
        ItemMaterial $entradaPrincipal,
        ItemMaterial $entradaAuxiliar,
    ): array {
        return [
            'operacion_id' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'proveedor_material_id' => $proveedor->id,
            'numero_guia_despacho' => 'GD-TRA-001',
            'fecha_documento' => '2026-07-24',
            'detalles' => [
                [
                    'item_material_id' => $entradaPrincipal->id,
                    'cantidad_documental' => 80,
                    'cantidad_recibida' => 80,
                    'cantidad_rechazada' => 0,
                    'bultos' => [['cantidad' => 80, 'lote_proveedor' => 'MP-001']],
                ],
                [
                    'item_material_id' => $entradaAuxiliar->id,
                    'cantidad_documental' => 10,
                    'cantidad_recibida' => 10,
                    'cantidad_rechazada' => 0,
                    'bultos' => [['cantidad' => 10, 'lote_proveedor' => 'INS-001']],
                ],
            ],
        ];
    }

    private function payloadUbicacion(
        string $numeroFolio,
        Posicion $posicion,
        string $sesion,
        ItemMaterial $item,
        int $version,
    ): array {
        return [
            'operacion_id' => (string) Str::uuid(),
            'numero_folio' => $numeroFolio,
            'tipo_bulto' => 'material',
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion,
            'version_destino_conocida' => $version,
            'generado_dispositivo_at' => now()->toAtomString(),
            'datos_material' => [
                'item_material_id' => $item->id,
                'cantidad' => 1,
                'lote' => 'IGNORADO-FOLIO-EXISTENTE',
                'proveedor' => 'Proveedor transformación',
            ],
        ];
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
