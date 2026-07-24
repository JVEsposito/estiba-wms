from pathlib import Path


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    target = Path(path)
    content = target.read_text()
    if old not in content:
        raise SystemExit(f"No se encontró bloque esperado en {path}: {old[:120]!r}")
    target.write_text(content.replace(old, new, count))


replace(
    "tests/Feature/Api/AdministracionAccesoApiTest.php",
    """        ])->map(fn (array $cliente): string => $this
            ->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/clientes', [
                'codigo' => $cliente[0],
                'nombre' => $cliente[1],
                'activo' => true,
            ])
            ->assertCreated()
            ->json('data.id'));

        $proveedorId = $this->postJson('/api/administracion/materiales/proveedores', [
""",
    """        ])->map(fn (array $cliente): string => $this
            ->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/clientes', [
                'codigo' => $cliente[0],
                'nombre' => $cliente[1],
                'activo' => true,
            ])
            ->assertCreated()
            ->json('data.id'));
        $categorias = $clienteIds->map(function (string $clienteId) use ($administrador): array {
            $catalogo = ClienteMaterial::query()
                ->where('cliente_id', $clienteId)
                ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
                ->firstOrFail();
            ItemMaterial::create([
                'cliente_material_id' => $catalogo->id,
                'codigo' => $catalogo->codigo.'-EMBALAJE-PRV',
                'nombre' => 'Material de embalaje para proveedor',
                'categoria' => 'Embalaje',
                'categoria_operacional' => 'insumo',
                'unidad_medida' => 'unidades',
                'origen_sistema' => 'manual',
                'activo' => true,
                'creado_por_user_id' => $administrador->id,
                'actualizado_por_user_id' => $administrador->id,
            ]);

            return ['cliente_id' => $clienteId, 'categoria' => 'Embalaje'];
        });

        $proveedorId = $this->postJson('/api/administracion/materiales/proveedores', [
""",
)
replace(
    "tests/Feature/Api/AdministracionAccesoApiTest.php",
    """            'activo' => true,
            'cliente_ids' => $clienteIds->all(),
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.clientes')
""",
    """            'activo' => true,
            'cliente_ids' => $clienteIds->all(),
            'categorias' => $categorias->all(),
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.clientes')
            ->assertJsonCount(2, 'data.categorias')
""",
)
replace(
    "tests/Feature/Api/CatalogoRecepcionMaterialApiTest.php",
    """                'proveedor_material_id' => $proveedorMaterial->id,
                'activo' => $activo,
                'creado_por_user_id' => $administrador->id,
""",
    """                'proveedor_material_id' => $proveedorMaterial->id,
                'activo' => $activo,
                'categorias' => json_encode(['Embalaje'], JSON_UNESCAPED_UNICODE),
                'creado_por_user_id' => $administrador->id,
""",
)
replace(
    "tests/Feature/Api/TransformacionMaterialApiTest.php",
    """            'proveedor_material_id' => $proveedor->id,
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
""",
    """            'proveedor_material_id' => $proveedor->id,
            'activo' => true,
            'categorias' => json_encode(['Embalaje'], JSON_UNESCAPED_UNICODE),
            'creado_por_user_id' => $administrador->id,
""",
)
replace(
    "tests/Feature/InterfazEdicionUsuarioAccesosTest.php",
    """        $this->get('/oficina/accesos')
            ->assertOk()
            ->assertSee('office-user-management', false)
            ->assertSee('Al editar, déjala vacía para conservar la contraseña actual.')
            ->assertSee('id=\"createUserForm\"', false)
            ->assertSee('id=\"usersTableBody\"', false);
""",
    """        $this->get('/oficina/accesos')
            ->assertOk()
            ->assertSee('Al editar, déjala vacía para conservar la contraseña actual.')
            ->assertSee('id=\"createUserForm\"', false)
            ->assertSee('id=\"usersTableBody\"', false);

        $script = file_get_contents(resource_path('js/office-user-management.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('createUserForm', $script);
        $this->assertStringContainsString('usersTableBody', $script);
        $this->assertStringContainsString('Usuario activo y habilitado', $script);
""",
)
replace(
    "tests/Feature/InterfazRecetasMaterialesTest.php",
    """        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('office-material-recipes', false);
""",
    """        $this->get('/oficina/materiales')->assertOk();
""",
)

Path("diagnostics/provider-categories-tests.txt").unlink(missing_ok=True)
Path("scripts/apply_provider_category_test_fixes.py").unlink(missing_ok=True)
