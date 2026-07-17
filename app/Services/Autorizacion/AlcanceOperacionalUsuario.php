<?php

namespace App\Services\Autorizacion;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\User;

class AlcanceOperacionalUsuario
{
    /**
     * @return array<int, ContenidoCamara>
     */
    public function contenidosVisibles(User $usuario): array
    {
        if (! $usuario->activo) {
            return [];
        }

        return match ($usuario->rol) {
            RolUsuario::SupervisorFrio,
            RolUsuario::CamareroFrio => [ContenidoCamara::Productos],
            RolUsuario::SupervisorMateriales,
            RolUsuario::CamareroMateriales => [ContenidoCamara::Materiales],
            RolUsuario::Administrador,
            RolUsuario::Despachador,
            RolUsuario::Consulta => ContenidoCamara::cases(),
            RolUsuario::Validador => [],
        };
    }

    public function ambitoCamaras(User $usuario): string
    {
        $contenidos = $this->contenidosVisibles($usuario);

        if (count($contenidos) === 2) {
            return 'ambos';
        }

        return $contenidos[0]->value ?? 'ninguno';
    }

    public function puedeVerCamara(User $usuario, Camara|ContenidoCamara $camara): bool
    {
        $contenido = $camara instanceof Camara ? $camara->contenido : $camara;

        return in_array($contenido, $this->contenidosVisibles($usuario), true);
    }

    public function puedeOperarCamara(User $usuario, Camara|ContenidoCamara $camara): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        $contenido = $camara instanceof Camara ? $camara->contenido : $camara;

        return match ($contenido) {
            ContenidoCamara::Productos => in_array($usuario->rol, [
                RolUsuario::Administrador,
                RolUsuario::SupervisorFrio,
                RolUsuario::CamareroFrio,
            ], true),
            ContenidoCamara::Materiales => in_array($usuario->rol, [
                RolUsuario::Administrador,
                RolUsuario::SupervisorMateriales,
                RolUsuario::CamareroMateriales,
            ], true),
        };
    }

    public function puedeOperarAlgunaCamara(User $usuario): bool
    {
        return $this->puedeOperarCamara($usuario, ContenidoCamara::Productos)
            || $this->puedeOperarCamara($usuario, ContenidoCamara::Materiales);
    }

    public function puedeSupervisarCamara(User $usuario, Camara|ContenidoCamara $camara): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        $contenido = $camara instanceof Camara ? $camara->contenido : $camara;

        return $usuario->rol === RolUsuario::Administrador
            || ($contenido === ContenidoCamara::Productos
                && $usuario->rol === RolUsuario::SupervisorFrio)
            || ($contenido === ContenidoCamara::Materiales
                && $usuario->rol === RolUsuario::SupervisorMateriales);
    }

    public function puedeCrearCamara(User $usuario, ContenidoCamara $contenido): bool
    {
        return $this->puedeSupervisarCamara($usuario, $contenido);
    }

    public function puedeAdministrarCamaras(User $usuario): bool
    {
        return $this->rolActivo($usuario, [RolUsuario::Administrador]);
    }

    public function puedeAdministrarAccesos(User $usuario): bool
    {
        return $this->puedeAdministrarCamaras($usuario);
    }

    public function contenidoForzadoCreacion(User $usuario): ?ContenidoCamara
    {
        return match ($usuario->rol) {
            RolUsuario::SupervisorFrio => ContenidoCamara::Productos,
            RolUsuario::SupervisorMateriales => ContenidoCamara::Materiales,
            default => null,
        };
    }

    public function puedeCerrarSesionForzosamente(User $usuario, Camara $camara): bool
    {
        return $this->puedeSupervisarCamara($usuario, $camara);
    }

    public function puedeGestionarCargas(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorFrio,
            RolUsuario::Despachador,
        ]);
    }

    public function puedeConsultarCargas(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorFrio,
            RolUsuario::CamareroFrio,
            RolUsuario::Despachador,
            RolUsuario::Consulta,
        ]);
    }

    public function puedeConsultarCatalogoCargas(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorFrio,
            RolUsuario::Despachador,
            RolUsuario::Consulta,
        ]);
    }

    public function puedeReportarIncidenciasCarga(User $usuario): bool
    {
        return $this->puedeOperarCamara($usuario, ContenidoCamara::Productos);
    }

    public function puedeResolverComercialmenteCarga(User $usuario): bool
    {
        return $this->rolActivo($usuario, [RolUsuario::Administrador, RolUsuario::Despachador]);
    }

    public function puedeResolverReparacionCarga(User $usuario): bool
    {
        return $this->puedeResolverComercialmenteCarga($usuario)
            || $this->rolActivo($usuario, [RolUsuario::SupervisorFrio]);
    }

    public function puedeEnviarFoliosAnden(User $usuario): bool
    {
        return $this->puedeOperarCamara($usuario, ContenidoCamara::Productos);
    }

    public function puedeCerrarDespachoFrigorifico(User $usuario): bool
    {
        return $this->puedeResolverComercialmenteCarga($usuario);
    }

    public function puedeGestionarAndenes(User $usuario): bool
    {
        return $this->rolActivo($usuario, [RolUsuario::Administrador]);
    }

    public function puedeGestionarDespachosMateriales(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorMateriales,
            RolUsuario::Despachador,
        ]);
    }

    public function puedeConsultarDespachosMateriales(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorMateriales,
            RolUsuario::CamareroMateriales,
            RolUsuario::Despachador,
            RolUsuario::Consulta,
        ]);
    }

    public function puedeRetirarMateriales(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorMateriales,
            RolUsuario::CamareroMateriales,
        ]);
    }

    public function puedeCancelarDespachosMateriales(User $usuario): bool
    {
        return $this->puedeGestionarDespachosMateriales($usuario);
    }

    public function puedeConsultarKardexMateriales(User $usuario): bool
    {
        return $this->rolActivo($usuario, [RolUsuario::Administrador, RolUsuario::SupervisorMateriales]);
    }

    public function puedeValidarPallets(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorFrio,
            RolUsuario::Validador,
        ]);
    }

    public function puedeRechazarPallets(User $usuario): bool
    {
        return $this->rolActivo($usuario, [RolUsuario::Administrador, RolUsuario::SupervisorFrio]);
    }

    public function puedeConsultarValidacionesPallet(User $usuario): bool
    {
        return $this->puedeValidarPallets($usuario);
    }

    public function puedeAdministrarCatalogosValidacion(User $usuario): bool
    {
        return $this->rolActivo($usuario, [RolUsuario::Administrador]);
    }

    public function puedeAccederOficina(User $usuario): bool
    {
        return $this->rolActivo($usuario, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorFrio,
            RolUsuario::SupervisorMateriales,
            RolUsuario::Despachador,
            RolUsuario::Consulta,
        ]);
    }

    /**
     * @return array<string, bool|string>
     */
    public function capacidadesApi(User $usuario): array
    {
        return [
            'ambito_camaras' => $this->ambitoCamaras($usuario),
            'puede_supervisar' => $this->puedeSupervisarCamara($usuario, ContenidoCamara::Productos)
                || $this->puedeSupervisarCamara($usuario, ContenidoCamara::Materiales),
            'puede_operar_productos' => $this->puedeOperarCamara($usuario, ContenidoCamara::Productos),
            'puede_operar_materiales' => $this->puedeOperarCamara($usuario, ContenidoCamara::Materiales),
            'puede_consultar_cargas' => $this->puedeConsultarCargas($usuario),
            'puede_consultar_catalogo_cargas' => $this->puedeConsultarCatalogoCargas($usuario),
            'puede_gestionar_cargas' => $this->puedeGestionarCargas($usuario),
            'puede_reportar_incidencias_carga' => $this->puedeReportarIncidenciasCarga($usuario),
            'puede_resolver_comercialmente_carga' => $this->puedeResolverComercialmenteCarga($usuario),
            'puede_resolver_reparacion_carga' => $this->puedeResolverReparacionCarga($usuario),
            'puede_enviar_folios_anden' => $this->puedeEnviarFoliosAnden($usuario),
            'puede_cerrar_despacho_frigorifico' => $this->puedeCerrarDespachoFrigorifico($usuario),
            'puede_gestionar_andenes' => $this->puedeGestionarAndenes($usuario),
            'puede_consultar_despachos_materiales' => $this->puedeConsultarDespachosMateriales($usuario),
            'puede_gestionar_despachos_materiales' => $this->puedeGestionarDespachosMateriales($usuario),
            'puede_retirar_materiales' => $this->puedeRetirarMateriales($usuario),
            'puede_cancelar_despachos_materiales' => $this->puedeCancelarDespachosMateriales($usuario),
            'puede_consultar_kardex_materiales' => $this->puedeConsultarKardexMateriales($usuario),
            'puede_validar_pallets' => $this->puedeValidarPallets($usuario),
            'puede_rechazar_pallets' => $this->puedeRechazarPallets($usuario),
            'puede_consultar_validaciones_pallet' => $this->puedeConsultarValidacionesPallet($usuario),
            'puede_administrar_catalogos_validacion' => $this->puedeAdministrarCatalogosValidacion($usuario),
        ];
    }

    /**
     * @param  array<int, RolUsuario>  $roles
     */
    private function rolActivo(User $usuario, array $roles): bool
    {
        return $usuario->activo && in_array($usuario->rol, $roles, true);
    }
}
