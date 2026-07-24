<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRoutesConventionTest extends TestCase
{
    public function test_las_rutas_api_estan_centralizadas_y_sin_duplicados(): void
    {
        $rutas = [
            ['GET', 'api/materiales/recepciones/catalogos', 'can:consultar-recepciones-materiales'],
            ['GET', 'api/materiales/recepciones/folios-pendientes', 'can:consultar-recepciones-materiales'],
            ['GET', 'api/materiales/recepciones', 'can:consultar-recepciones-materiales'],
            ['GET', 'api/materiales/recepciones/{recepcionMaterial}', 'can:consultar-recepciones-materiales'],
            ['POST', 'api/materiales/recepciones', 'can:gestionar-recepciones-materiales'],
            ['POST', 'api/materiales/recepciones/{recepcionMaterial}/confirmar', 'can:gestionar-recepciones-materiales'],
            ['POST', 'api/materiales/recepciones/{recepcionMaterial}/anular', 'can:anular-recepciones-materiales'],
            ['GET', 'api/materiales/transformaciones/recetas', 'can:consultar-transformaciones-materiales'],
            ['GET', 'api/materiales/transformaciones/ordenes', 'can:consultar-transformaciones-materiales'],
            ['GET', 'api/materiales/transformaciones/ordenes/{ordenTransformacionMaterial}', 'can:consultar-transformaciones-materiales'],
            ['POST', 'api/materiales/transformaciones/recetas', 'can:administrar-recetas-materiales'],
            ['POST', 'api/materiales/transformaciones/recetas/{recetaMaterial}/versiones', 'can:administrar-recetas-materiales'],
            ['POST', 'api/materiales/transformaciones/ordenes', 'can:gestionar-transformaciones-materiales'],
            ['POST', 'api/materiales/transformaciones/ordenes/{ordenTransformacionMaterial}/planificar', 'can:gestionar-transformaciones-materiales'],
            ['POST', 'api/materiales/transformaciones/ordenes/{ordenTransformacionMaterial}/cancelar', 'can:gestionar-transformaciones-materiales'],
            ['PUT', 'api/administracion/usuarios/{usuario}', 'can:administrar-accesos'],
        ];

        $registradas = collect(Route::getRoutes()->getRoutes());

        foreach ($rutas as [$metodo, $uri, $permiso]) {
            $coincidencias = $registradas->filter(
                fn ($ruta): bool => $ruta->uri() === $uri
                    && in_array($metodo, $ruta->methods(), true),
            );

            $this->assertCount(1, $coincidencias, "La ruta {$metodo} {$uri} debe registrarse exactamente una vez.");
            $middleware = $coincidencias->first()->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, "La ruta {$metodo} {$uri} debe requerir autenticación.");
            $this->assertContains($permiso, $middleware, "La ruta {$metodo} {$uri} debe conservar su autorización.");
        }
    }

    public function test_bootstrap_no_registra_proveedores_dedicados_a_rutas_api(): void
    {
        $proveedores = require base_path('bootstrap/providers.php');

        $this->assertNotContains('App\\Providers\\RecepcionMaterialServiceProvider', $proveedores);
        $this->assertNotContains('App\\Providers\\TransformacionMaterialServiceProvider', $proveedores);
        $this->assertFileDoesNotExist(app_path('Providers/RecepcionMaterialServiceProvider.php'));
        $this->assertFileDoesNotExist(app_path('Providers/TransformacionMaterialServiceProvider.php'));

        $proveedorUsuarios = file_get_contents(app_path('Providers/AdministracionUsuarioServiceProvider.php'));
        $this->assertIsString($proveedorUsuarios);
        $this->assertStringNotContainsString('Route::', $proveedorUsuarios);
    }
}
