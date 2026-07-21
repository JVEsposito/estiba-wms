<?php

namespace App\Services\Folios;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\TipoBulto;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\RegistroHabilitacionAlmacenamiento;
use App\Models\User;
use DomainException;

class ServicioHabilitacionAlmacenamiento
{
    public function prepararFolioManual(
        Folio $folio,
        ?User $usuario = null,
        ?Dispositivo $dispositivo = null,
        ?string $observacion = null,
    ): Folio {
        if ($folio->tipo_bulto === TipoBulto::Material) {
            return $folio;
        }

        if ($folio->condicion_termica !== null || $folio->habilitacion_almacenamiento !== null) {
            return $folio;
        }

        $fuente = $folio->origen_sistema === 'repaletizaje'
            ? FuenteHabilitacionAlmacenamiento::CondicionHeredadaRepaletizaje
            : FuenteHabilitacionAlmacenamiento::RegularizacionManual;

        $folio->update([
            'condicion_termica' => CondicionTermicaFolio::CondicionHeredada,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fuente_habilitacion_almacenamiento' => $fuente,
            'habilitado_almacenamiento_at' => now(),
            'habilitado_almacenamiento_por_user_id' => $usuario?->id,
            'retencion_termica_motivo' => null,
        ]);

        $this->registrar(
            $folio,
            HabilitacionAlmacenamientoFolio::Habilitado,
            $usuario,
            $dispositivo,
            $fuente === FuenteHabilitacionAlmacenamiento::CondicionHeredadaRepaletizaje
                ? 'repaletizaje'
                : 'regularizacion_manual',
            null,
            'Folio habilitado al ingresar excepcionalmente desde cámaras.',
            $observacion,
        );

        return $folio->refresh();
    }

    public function habilitar(
        Folio $folio,
        CondicionTermicaFolio $condicion,
        FuenteHabilitacionAlmacenamiento $fuente,
        User $usuario,
        ?Dispositivo $dispositivo = null,
        ?string $procesoOrigen = null,
        ?string $referenciaOrigen = null,
        ?string $observacion = null,
    ): Folio {
        $this->validarProducto($folio);

        $folio->update([
            'condicion_termica' => $condicion,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fuente_habilitacion_almacenamiento' => $fuente,
            'habilitado_almacenamiento_at' => now(),
            'habilitado_almacenamiento_por_user_id' => $usuario->id,
            'retencion_termica_motivo' => null,
        ]);

        $this->registrar(
            $folio,
            HabilitacionAlmacenamientoFolio::Habilitado,
            $usuario,
            $dispositivo,
            $procesoOrigen,
            $referenciaOrigen,
            'Habilitación de almacenamiento otorgada.',
            $observacion,
        );

        return $folio->refresh();
    }

    public function retener(
        Folio $folio,
        CondicionTermicaFolio $condicion,
        string $motivo,
        ?User $usuario = null,
        ?Dispositivo $dispositivo = null,
        ?string $procesoOrigen = null,
        ?string $referenciaOrigen = null,
    ): Folio {
        $this->validarProducto($folio);

        $folio->update([
            'condicion_termica' => $condicion,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Retenido,
            'fuente_habilitacion_almacenamiento' => null,
            'habilitado_almacenamiento_at' => null,
            'habilitado_almacenamiento_por_user_id' => null,
            'retencion_termica_motivo' => trim($motivo),
            'estado_operacional' => EstadoOperacionalFolio::Bloqueado,
        ]);

        $this->registrar(
            $folio,
            HabilitacionAlmacenamientoFolio::Retenido,
            $usuario,
            $dispositivo,
            $procesoOrigen,
            $referenciaOrigen,
            trim($motivo),
        );

        return $folio->refresh();
    }

    public function marcarEnProceso(
        Folio $folio,
        ?User $usuario = null,
        ?Dispositivo $dispositivo = null,
        ?string $procesoOrigen = null,
        ?string $referenciaOrigen = null,
    ): Folio {
        $this->validarProducto($folio);

        $folio->update([
            'condicion_termica' => CondicionTermicaFolio::EnProceso,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::NoHabilitado,
            'fuente_habilitacion_almacenamiento' => null,
            'habilitado_almacenamiento_at' => null,
            'habilitado_almacenamiento_por_user_id' => null,
            'retencion_termica_motivo' => null,
            'estado_operacional' => EstadoOperacionalFolio::PendientePrefrio,
        ]);

        $this->registrar(
            $folio,
            HabilitacionAlmacenamientoFolio::NoHabilitado,
            $usuario,
            $dispositivo,
            $procesoOrigen,
            $referenciaOrigen,
            'Folio incorporado a un proceso térmico.',
        );

        return $folio->refresh();
    }

    public function validarIngresoCamara(Folio $folio): void
    {
        if ($folio->tipo_bulto === TipoBulto::Material) {
            return;
        }

        if (! $folio->activo) {
            throw new DomainException('El folio se encuentra inactivo y no puede ingresar a cámara.');
        }

        if (in_array($folio->estado_operacional, [
            EstadoOperacionalFolio::Bloqueado,
            EstadoOperacionalFolio::Anulado,
            EstadoOperacionalFolio::RetiradoDefinitivo,
            EstadoOperacionalFolio::Despachado,
        ], true)) {
            throw new DomainException('El estado operacional del folio no permite su ingreso a cámara.');
        }

        if ($folio->condicion_termica === null && $folio->habilitacion_almacenamiento === null) {
            return;
        }

        if ($folio->habilitacion_almacenamiento !== HabilitacionAlmacenamientoFolio::Habilitado) {
            throw new DomainException('El folio no se encuentra habilitado para almacenamiento.');
        }

        if (in_array($folio->condicion_termica, [
            CondicionTermicaFolio::PendientePrefrio,
            CondicionTermicaFolio::EnProceso,
            CondicionTermicaFolio::RequiereReproceso,
            CondicionTermicaFolio::Retenido,
        ], true)) {
            throw new DomainException('La condición térmica del folio no permite su ingreso a cámara.');
        }
    }

    public function validarUbicacionInicial(Folio $folio): void
    {
        if (! $folio->activo) {
            throw new DomainException('El folio se encuentra inactivo y no puede ingresar a cámara.');
        }

        if ($folio->estado_operacional === EstadoOperacionalFolio::Disponible) {
            $this->validarIngresoCamara($folio);

            return;
        }

        $esProductoAprobadoEnPrefrio = $folio->tipo_bulto !== TipoBulto::Material
            && $folio->estado_operacional === EstadoOperacionalFolio::PendientePrefrio
            && $folio->condicion_termica === CondicionTermicaFolio::PrefrioAprobado
            && $folio->habilitacion_almacenamiento === HabilitacionAlmacenamientoFolio::Habilitado;

        if ($esProductoAprobadoEnPrefrio) {
            $this->validarIngresoCamara($folio);

            return;
        }

        if ($folio->estado_operacional === EstadoOperacionalFolio::PendientePrefrio) {
            throw new DomainException('El folio aún no ha sido aprobado en Prefrío.');
        }

        throw new DomainException('El folio no se encuentra disponible para ingresar a cámara.');
    }

    private function validarProducto(Folio $folio): void
    {
        if ($folio->tipo_bulto === TipoBulto::Material) {
            throw new DomainException('La habilitación térmica solo aplica a pallets y saldos de producto.');
        }
    }

    private function registrar(
        Folio $folio,
        HabilitacionAlmacenamientoFolio $estado,
        ?User $usuario,
        ?Dispositivo $dispositivo,
        ?string $procesoOrigen,
        ?string $referenciaOrigen,
        ?string $motivo,
        ?string $observacion = null,
    ): void {
        RegistroHabilitacionAlmacenamiento::create([
            'folio_id' => $folio->id,
            'estado_resultante' => $estado,
            'condicion_termica' => $folio->condicion_termica,
            'fuente' => $folio->fuente_habilitacion_almacenamiento,
            'proceso_origen' => $procesoOrigen,
            'referencia_origen' => $referenciaOrigen,
            'user_id' => $usuario?->id,
            'dispositivo_id' => $dispositivo?->id,
            'ocurrido_at' => now(),
            'motivo' => $motivo,
            'observacion' => $observacion,
        ]);
    }
}
