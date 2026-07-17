<?php

namespace App\Services\Folios;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\TipoBulto;
use App\Models\Folio;
use App\Models\User;
use DomainException;

class ServicioHabilitacionAlmacenamiento
{
    public function prepararFolioManual(Folio $folio, ?User $usuario = null): Folio
    {
        if ($folio->tipo_bulto === TipoBulto::Material) {
            return $folio;
        }

        if ($folio->condicion_termica !== null || $folio->habilitacion_almacenamiento !== null) {
            return $folio;
        }

        $folio->update([
            'condicion_termica' => CondicionTermicaFolio::CondicionHeredada,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fuente_habilitacion_almacenamiento' => FuenteHabilitacionAlmacenamiento::RegularizacionManual,
            'habilitado_almacenamiento_at' => now(),
            'habilitado_almacenamiento_por_user_id' => $usuario?->id,
            'retencion_termica_motivo' => null,
        ]);

        return $folio->refresh();
    }

    public function habilitar(
        Folio $folio,
        CondicionTermicaFolio $condicion,
        FuenteHabilitacionAlmacenamiento $fuente,
        User $usuario,
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

        return $folio->refresh();
    }

    public function retener(
        Folio $folio,
        CondicionTermicaFolio $condicion,
        string $motivo,
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

        return $folio->refresh();
    }

    public function marcarEnProceso(Folio $folio): Folio
    {
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

    private function validarProducto(Folio $folio): void
    {
        if ($folio->tipo_bulto === TipoBulto::Material) {
            throw new DomainException('La habilitación térmica solo aplica a pallets y saldos de producto.');
        }
    }
}
