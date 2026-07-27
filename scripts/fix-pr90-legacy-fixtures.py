from pathlib import Path


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    file = Path(path)
    text = file.read_text()
    found = text.count(old)
    if found < count:
        raise RuntimeError(f'{path}: expected {count}, found {found}: {old[:140]!r}')
    file.write_text(text.replace(old, new, count))


# Cargas: el folio material de prueba debe existir antes de su ubicación.
replace(
    'tests/Feature/Api/CargaApiTest.php',
    'use App\\Models\\Folio;\nuse App\\Models\\ItemMaterial;',
    'use App\\Models\\Folio;\nuse App\\Models\\FolioMaterial;\nuse App\\Models\\ItemMaterial;',
)
replace(
    'tests/Feature/Api/CargaApiTest.php',
    "        $sesion = app(ServicioSesionEstiba::class)\n            ->abrir($camara, $operador, $dispositivo);\n        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(\n            operacionId: (string) Str::uuid(),\n            numeroFolio: $numeroFolio,\n            tipoBulto: TipoBulto::Material,",
    "        $sesion = app(ServicioSesionEstiba::class)\n            ->abrir($camara, $operador, $dispositivo);\n        $folio = Folio::create([\n            'numero_folio' => $numeroFolio,\n            'tipo_bulto' => TipoBulto::Material,\n            'estado_operacional' => EstadoOperacionalFolio::PendienteUbicacion,\n            'fecha_ingreso' => now(),\n            'activo' => true,\n            'origen_sistema' => 'recepcion_materiales',\n        ]);\n        FolioMaterial::create([\n            'folio_id' => $folio->id,\n            'item_material_id' => $item->id,\n            'cantidad_inicial' => 10,\n            'cantidad_actual' => 10,\n            'cantidad_reservada' => 0,\n            'unidad_medida' => $item->unidad_medida,\n        ]);\n        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(\n            operacionId: (string) Str::uuid(),\n            numeroFolio: $folio->numero_folio,\n            tipoBulto: TipoBulto::Material,",
)
replace(
    'tests/Feature/Api/CargaApiTest.php',
    "            versionDestinoConocida: 0,\n            generadoDispositivoAt: now(),\n            datosMaterial: [\n                'item_material_id' => $item->id,\n                'cantidad' => 10,\n            ],\n        );",
    "            versionDestinoConocida: 0,\n            generadoDispositivoAt: now(),\n        );",
)

# Panel gerencial: el saldo material también nace antes de ser estibado.
replace(
    'tests/Feature/Api/PanelGerencialApiTest.php',
    "        $sesion = app(ServicioSesionEstiba::class)->abrir(\n            $camara,\n            $operador,\n            $dispositivo,\n        );\n        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(\n            operacionId: (string) Str::uuid(),\n            numeroFolio: 'MAT-FOLIO-001',\n            tipoBulto: TipoBulto::Material,",
    "        $sesion = app(ServicioSesionEstiba::class)->abrir(\n            $camara,\n            $operador,\n            $dispositivo,\n        );\n        $folioDisponible = Folio::create([\n            'numero_folio' => 'MAT-FOLIO-001',\n            'tipo_bulto' => TipoBulto::Material,\n            'estado_operacional' => EstadoOperacionalFolio::PendienteUbicacion,\n            'fecha_ingreso' => now(),\n            'activo' => true,\n            'origen_sistema' => 'recepcion_materiales',\n        ]);\n        FolioMaterial::create([\n            'folio_id' => $folioDisponible->id,\n            'item_material_id' => $item->id,\n            'cantidad_inicial' => 125,\n            'cantidad_actual' => 125,\n            'cantidad_reservada' => 0,\n            'unidad_medida' => $item->unidad_medida,\n        ]);\n        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(\n            operacionId: (string) Str::uuid(),\n            numeroFolio: $folioDisponible->numero_folio,\n            tipoBulto: TipoBulto::Material,",
)
replace(
    'tests/Feature/Api/PanelGerencialApiTest.php',
    "            versionDestinoConocida: 0,\n            generadoDispositivoAt: now(),\n            datosMaterial: [\n                'item_material_id' => $item->id,\n                'cantidad' => 125,\n            ],\n        );",
    "            versionDestinoConocida: 0,\n            generadoDispositivoAt: now(),\n        );",
)
