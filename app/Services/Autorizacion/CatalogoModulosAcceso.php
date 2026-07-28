<?php

namespace App\Services\Autorizacion;

use App\Enums\RolUsuario;
use App\Models\User;

class CatalogoModulosAcceso
{
    public const TABLET_OPERACION_FRIGORIFICO = 'operacion';

    public const TABLET_RECEPCION_MATERIALES = 'recepcion_materiales';

    public const TABLET_VALIDACION_PT = 'validacion';

    public const TABLET_VALIDACION_MP = 'validacion_mp';

    public const TABLET_PREFRIO = 'prefrio';

    /**
     * Reservada para una futura implementación de las operaciones de inventario,
     * despacho y transformación de Materiales en PDA/tablet.
     */
    public const TABLET_OPERACION_MATERIALES = 'operacion_materiales';

    /**
     * @return array<int, array{
     *     clave: string,
     *     nombre: string,
     *     descripcion: string,
     *     modulos: array<int, array{clave: string, nombre: string, descripcion: string}>
     * }>
     */
    public function macromodulos(): array
    {
        return [
            [
                'clave' => 'materia-prima',
                'nombre' => 'Materia Prima',
                'descripcion' => 'Recepción, lotización, envases y validación de materia prima.',
                'modulos' => [
                    $this->modulo('materia-prima.romana', 'Romana', 'Ingreso, pesaje y seguimiento de recepciones.'),
                    $this->modulo('materia-prima.digitacion', 'Digitación de lotes', 'Creación y gestión de lotes posteriores a validación.'),
                    $this->modulo('materia-prima.validacion-mp', 'Validación MP', 'Flujo PDA/tablet para validar y segregar recepciones.'),
                    $this->modulo('materia-prima.cuenta-envases', 'Cuenta de envases', 'Saldos y movimientos de envases por cliente.'),
                    $this->modulo('materia-prima.despacho-envases', 'Despacho de envases', 'Guías, reservas y confirmación de salidas.'),
                ],
            ],
            [
                'clave' => 'frigorifico',
                'nombre' => 'Frigorífico (PT)',
                'descripcion' => 'Validación, prefrío, cámaras y despacho de producto terminado.',
                'modulos' => [
                    $this->modulo('frigorifico.validacion', 'Validación PT', 'Validación y observación de pallets.'),
                    $this->modulo('frigorifico.catalogos', 'Catálogos PT', 'Maestros utilizados por Validación PT.'),
                    $this->modulo('frigorifico.prefrio', 'Prefrío', 'Túneles, procesos y verificaciones de prefrío.'),
                    $this->modulo('frigorifico.camaras', 'Cámaras PT', 'Plano, movimientos y sesiones de producto terminado.'),
                    $this->modulo('frigorifico.cargas', 'Cargas y despachos', 'Órdenes, separación, andenes e incidencias.'),
                ],
            ],
            [
                'clave' => 'materiales',
                'nombre' => 'Materiales',
                'descripcion' => 'Catálogos, existencias, recepciones, despachos y transformación.',
                'modulos' => [
                    $this->modulo('materiales.resumen', 'Resumen', 'Estado general del inventario de materiales.'),
                    $this->modulo('materiales.catalogos', 'Catálogos', 'Clientes, proveedores, ítems y destinos.'),
                    $this->modulo('materiales.etiquetas', 'Recepción y etiquetas', 'Recepciones, folios e impresión de etiquetas.'),
                    $this->modulo('materiales.inventario', 'Inventario', 'Existencias, posiciones, kardex y correcciones.'),
                    $this->modulo('materiales.despachos', 'Despachos', 'Reservas, retiros y despachos internos.'),
                    $this->modulo('materiales.recetas', 'Recetas', 'Configuración de transformaciones internas.'),
                    $this->modulo('materiales.ordenes', 'Órdenes', 'Planificación y ejecución de transformaciones.'),
                    $this->modulo('materiales.exportaciones', 'Exportaciones', 'Descarga de inventarios autorizados.'),
                ],
            ],
            [
                'clave' => 'consultas',
                'nombre' => 'Consultas',
                'descripcion' => 'Búsqueda transversal y productores SAG/CSG.',
                'modulos' => [
                    $this->modulo('consultas.busqueda', 'Búsqueda operacional', 'Consulta transversal de folios, lotes y recepciones.'),
                    $this->modulo('consultas.sag', 'Consulta SAG / CSG', 'Consulta del registro externo del SAG.'),
                    $this->modulo('consultas.productores', 'Productores verificados', 'Expedientes y asociaciones de productores.'),
                ],
            ],
            [
                'clave' => 'administracion',
                'nombre' => 'Gerencia y Administración',
                'descripcion' => 'Indicadores transversales y configuración reservada.',
                'modulos' => [
                    $this->modulo('gerencia.panel', 'Panel gerencial', 'Indicadores, ocupación y disponibilidad operacional.'),
                    $this->modulo('administracion.accesos', 'Accesos y temporadas', 'Usuarios, perfiles, tablets y maestros globales.'),
                    $this->modulo('administracion.camaras', 'Configuración de cámaras', 'Creación y estructura física de cámaras.'),
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function claves(): array
    {
        return collect($this->macromodulos())
            ->flatMap(fn (array $macromodulo): array => array_column($macromodulo['modulos'], 'clave'))
            ->values()
            ->all();
    }

    /**
     * Los módulos de oficina y tablet se publican por separado. Esto evita que
     * una capacidad web habilite accidentalmente una pantalla móvil todavía no
     * implementada.
     *
     * @return array<string, array<int, string>>
     */
    public function modulosTablet(): array
    {
        return [
            self::TABLET_OPERACION_FRIGORIFICO => [
                'frigorifico.camaras',
                'frigorifico.cargas',
            ],
            self::TABLET_RECEPCION_MATERIALES => [
                'materiales.etiquetas',
            ],
            self::TABLET_VALIDACION_PT => [
                'frigorifico.validacion',
            ],
            self::TABLET_VALIDACION_MP => [
                'materia-prima.validacion-mp',
            ],
            self::TABLET_PREFRIO => [
                'frigorifico.prefrio',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function modulosTabletUsuario(User $usuario): array
    {
        $modulosOficina = $this->modulosUsuario($usuario);

        return collect($this->modulosTablet())
            ->filter(
                fn (array $requeridos): bool => array_intersect($requeridos, $modulosOficina) !== [],
            )
            ->keys()
            ->values()
            ->all();
    }

    public static function habilidadTablet(string $modulo): string
    {
        return "tablet:{$modulo}";
    }

    /**
     * @return array<int, string>
     */
    public function modulosPredeterminados(RolUsuario $rol): array
    {
        if ($rol === RolUsuario::Administrador) {
            return $this->claves();
        }

        return match ($rol) {
            RolUsuario::SupervisorFrio => [
                'gerencia.panel',
                'materia-prima.romana',
                'materia-prima.digitacion',
                'frigorifico.validacion',
                'frigorifico.prefrio',
                'frigorifico.camaras',
                'frigorifico.cargas',
                'consultas.busqueda',
                'consultas.sag',
                'consultas.productores',
            ],
            RolUsuario::SupervisorMateriales => [
                'gerencia.panel',
                'materia-prima.cuenta-envases',
                'materia-prima.despacho-envases',
                'materiales.resumen',
                'materiales.etiquetas',
                'materiales.inventario',
                'materiales.despachos',
                'materiales.recetas',
                'materiales.ordenes',
                'materiales.exportaciones',
            ],
            RolUsuario::Despachador => [
                'materia-prima.romana',
                'materia-prima.cuenta-envases',
                'materia-prima.despacho-envases',
                'frigorifico.camaras',
                'frigorifico.cargas',
                'materiales.resumen',
                'materiales.inventario',
                'materiales.despachos',
                'materiales.recetas',
                'materiales.ordenes',
                'materiales.exportaciones',
            ],
            RolUsuario::OperadorPrefrio => [
                'frigorifico.prefrio',
            ],
            RolUsuario::OperadorRomana => [
                'materia-prima.romana',
                'materia-prima.digitacion',
                'materia-prima.cuenta-envases',
                'materia-prima.despacho-envases',
            ],
            RolUsuario::DigitadorMateriaPrima => [
                'materia-prima.romana',
                'materia-prima.digitacion',
                'materia-prima.cuenta-envases',
                'consultas.busqueda',
                'consultas.sag',
                'consultas.productores',
            ],
            RolUsuario::CamareroFrio => [
                'frigorifico.camaras',
                'frigorifico.cargas',
            ],
            RolUsuario::CamareroMateriales => [
                'materiales.resumen',
                'materiales.etiquetas',
                'materiales.inventario',
                'materiales.despachos',
                'materiales.recetas',
                'materiales.ordenes',
                'materiales.exportaciones',
            ],
            RolUsuario::Validador => [
                'frigorifico.validacion',
            ],
            RolUsuario::ValidadorMp => [
                'materia-prima.validacion-mp',
            ],
            RolUsuario::Consulta => [
                'gerencia.panel',
                'materia-prima.romana',
                'materia-prima.digitacion',
                'materia-prima.cuenta-envases',
                'frigorifico.prefrio',
                'frigorifico.camaras',
                'frigorifico.cargas',
                'materiales.resumen',
                'materiales.inventario',
                'materiales.despachos',
                'materiales.recetas',
                'materiales.ordenes',
                'materiales.exportaciones',
            ],
            RolUsuario::Administrador => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public function modulosUsuario(User $usuario): array
    {
        if (! $usuario->activo) {
            return [];
        }

        if (! $usuario->perfil_acceso_id) {
            return $this->modulosPredeterminados($usuario->rol);
        }

        $usuario->loadMissing('perfilAcceso');
        $perfil = $usuario->perfilAcceso;

        if (! $perfil || ! $perfil->activo) {
            return [];
        }

        $permitidos = $this->modulosPredeterminados($usuario->rol);

        return array_values(array_intersect($perfil->modulos, $permitidos));
    }

    public function usuarioTieneModulo(User $usuario, string|array $modulos): bool
    {
        $requeridos = is_array($modulos) ? $modulos : [$modulos];

        return array_intersect($requeridos, $this->modulosUsuario($usuario)) !== [];
    }

    /**
     * @return array{clave: string, nombre: string, descripcion: string}
     */
    private function modulo(string $clave, string $nombre, string $descripcion): array
    {
        return compact('clave', 'nombre', 'descripcion');
    }
}
