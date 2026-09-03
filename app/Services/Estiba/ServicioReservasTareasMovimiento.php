<?php

namespace App\Services\Estiba;

use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoPosicion;
use App\Enums\EstadoReservaTareaMovimiento;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\TipoMovimiento;
use App\Exceptions\ConflictoOperacion;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\Posicion;
use App\Models\ReservaTareaMovimiento;
use App\Models\TareaMovimiento;
use App\Models\UbicacionActual;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ServicioReservasTareasMovimiento
{
    public function validarDestinoManual(?string $posicionDestinoId): void
    {
        if (! $posicionDestinoId) {
            return;
        }

        $reserva = ReservaTareaMovimiento::query()
            ->where('bloqueo_posicion_id', $posicionDestinoId)
            ->lockForUpdate()
            ->first();

        if (! $reserva) {
            return;
        }
        if ($this->estaVencida($reserva)) {
            $this->expirar($reserva);

            return;
        }

        throw new ConflictoOperacion(
            'La posición de destino se encuentra reservada por una tarea operacional.',
        );
    }

    public function asumir(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): ReservaTareaMovimiento {
        $reserva = $this->reservaActivaTarea($tarea->id, true);

        if ($reserva && $this->estaVencida($reserva)) {
            $this->expirar($reserva);
            $tarea->refresh();
            $reserva = null;
        }

        if ($reserva) {
            if ($reserva->user_id !== $usuario->id
                || $reserva->dispositivo_id !== $dispositivo->id) {
                throw new ConflictoOperacion(
                    'La tarea ya fue reservada por otro camarero o dispositivo.',
                );
            }

            return $this->renovarReserva($reserva);
        }

        if ($tarea->responsable_user_id !== null
            || $tarea->estado !== EstadoTareaMovimiento::Pendiente) {
            throw new ConflictoOperacion('La tarea cambió de estado antes de ser reservada.');
        }

        $posicion = $this->bloquearDestinoDisponible($tarea);

        if ($posicion) {
            $reservaDestino = ReservaTareaMovimiento::query()
                ->where('bloqueo_posicion_id', $posicion->id)
                ->lockForUpdate()
                ->first();

            if ($reservaDestino && $this->estaVencida($reservaDestino)) {
                $this->expirar($reservaDestino);
                $reservaDestino = null;
            }

            if ($reservaDestino) {
                throw new ConflictoOperacion(
                    'La posición de destino ya fue reservada por otra tarea.',
                );
            }
        }

        $ahora = now();

        try {
            $reserva = ReservaTareaMovimiento::create([
                'tarea_movimiento_id' => $tarea->id,
                'posicion_destino_id' => $posicion?->id,
                'bloqueo_tarea_id' => $tarea->id,
                'bloqueo_posicion_id' => $posicion?->id,
                'estado' => EstadoReservaTareaMovimiento::Activa,
                'user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo->id,
                'reservada_at' => $ahora,
                'renovada_at' => $ahora,
                'vence_at' => $this->nuevoVencimiento(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictoOperacion(
                'La tarea o su posición de destino acaba de ser reservada por otro camarero.',
                previous: $exception,
            );
        }
        $this->incrementarRevisionCamara($reserva->posicion_destino_id);

        $tarea->update([
            'estado' => EstadoTareaMovimiento::Asumida,
            'responsable_user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'asumida_at' => $ahora,
            'version' => $tarea->version + 1,
        ]);

        return $reserva;
    }

    public function renovar(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): ?ReservaTareaMovimiento {
        $reserva = $this->reservaActivaTarea($tarea->id, true);

        if (! $reserva) {
            throw new ConflictoOperacion('La tarea no posee una reserva activa.');
        }
        if ($this->estaVencida($reserva)) {
            $this->expirar($reserva);

            return null;
        }

        $this->validarPropietario($reserva, $usuario, $dispositivo);

        return $this->renovarReserva($reserva);
    }

    public function liberar(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
        string $motivo = 'Liberada por el camarero.',
    ): void {
        $reserva = $this->reservaActivaTarea($tarea->id, true);

        if (! $reserva) {
            if ($tarea->estado === EstadoTareaMovimiento::Pendiente
                && $tarea->responsable_user_id === null) {
                return;
            }

            throw new ConflictoOperacion('La tarea no posee una reserva activa.');
        }

        if ($this->estaVencida($reserva)) {
            $this->expirar($reserva);

            return;
        }

        $this->validarPropietario($reserva, $usuario, $dispositivo);
        $ahora = now();
        $this->finalizarReserva($reserva, EstadoReservaTareaMovimiento::Liberada, [
            'liberada_at' => $ahora,
            'motivo_liberacion' => trim($motivo),
        ]);
        $this->devolverTareaALaBandeja($reserva->tarea_movimiento_id);
    }

    public function validarParaMovimiento(
        ?TareaMovimiento $tarea,
        Folio $folio,
        TipoMovimiento $tipo,
        ?string $posicionOrigenId,
        ?string $posicionDestinoId,
        User $usuario,
        Dispositivo $dispositivo,
    ): ?TareaMovimiento {
        $this->expirarRelacionadas($folio->id, $posicionDestinoId);

        if (! $tarea) {
            if ($this->reservaActivaFolio($folio->id, true)) {
                throw new ConflictoOperacion(
                    'El pallet se encuentra reservado por una tarea operacional.',
                );
            }
            if ($posicionDestinoId && $this->reservaActivaDestino($posicionDestinoId, true)) {
                throw new ConflictoOperacion(
                    'La posición de destino se encuentra reservada por otra tarea.',
                );
            }

            return null;
        }

        $tareaBloqueada = TareaMovimiento::query()
            ->with('planOperacional')
            ->lockForUpdate()
            ->findOrFail($tarea->id);
        $reserva = $this->reservaActivaTarea($tareaBloqueada->id, true);

        if (! $reserva || $this->estaVencida($reserva)) {
            if ($reserva) {
                $this->expirar($reserva);
            }

            throw new ConflictoOperacion(
                'La reserva de la tarea expiró; vuelva a asumirla antes de mover el pallet.',
            );
        }

        $this->validarPropietario($reserva, $usuario, $dispositivo);
        $this->validarCorrespondencia(
            $tareaBloqueada,
            $folio,
            $tipo,
            $posicionOrigenId,
            $posicionDestinoId,
        );

        $reservaDestino = $posicionDestinoId
            ? $this->reservaActivaDestino($posicionDestinoId, true)
            : null;
        if ($reservaDestino && $reservaDestino->id !== $reserva->id) {
            throw new ConflictoOperacion(
                'La posición de destino se encuentra reservada por otra tarea.',
            );
        }

        return $tareaBloqueada;
    }

    public function validarIntegridadMovimiento(Movimiento $movimiento): void
    {
        $tareaId = $movimiento->tarea_movimiento_id;

        if ($tareaId === null) {
            if ($this->reservaActivaFolio($movimiento->folio_id)) {
                throw new ConflictoOperacion(
                    'El pallet se encuentra reservado por una tarea operacional.',
                );
            }
            if ($movimiento->posicion_destino_id
                && $this->reservaActivaDestino($movimiento->posicion_destino_id)) {
                throw new ConflictoOperacion(
                    'La posición de destino se encuentra reservada por una tarea operacional.',
                );
            }

            return;
        }

        $tarea = TareaMovimiento::query()->with('planOperacional')->find($tareaId);
        $reserva = $this->reservaActivaTarea($tareaId);

        if (! $tarea || ! $reserva || $this->estaVencida($reserva)) {
            throw new ConflictoOperacion('El movimiento no posee una reserva operacional vigente.');
        }
        if ($movimiento->plan_operacional_id !== $tarea->plan_operacional_id
            || $movimiento->user_id !== $reserva->user_id
            || $movimiento->dispositivo_id !== $reserva->dispositivo_id) {
            throw new DomainException('El movimiento no corresponde al actor de la tarea reservada.');
        }

        $folio = Folio::query()->find($movimiento->folio_id);
        if (! $folio) {
            throw new DomainException('El folio de la tarea reservada no existe.');
        }

        $this->validarCorrespondencia(
            $tarea,
            $folio,
            $movimiento->tipo_movimiento,
            $movimiento->posicion_origen_id,
            $movimiento->posicion_destino_id,
        );
    }

    public function completar(TareaMovimiento $tarea, Movimiento $movimiento): void
    {
        $tareaBloqueada = TareaMovimiento::query()
            ->with('planOperacional')
            ->lockForUpdate()
            ->findOrFail($tarea->id);
        $reserva = $this->reservaActivaTarea($tareaBloqueada->id, true);

        if (! $reserva) {
            throw new ConflictoOperacion('La tarea perdió su reserva antes de completar el movimiento.');
        }

        $ahora = now();
        $this->finalizarReserva($reserva, EstadoReservaTareaMovimiento::Completada, [
            'completada_at' => $ahora,
        ]);
        $tareaBloqueada->update([
            'estado' => EstadoTareaMovimiento::Completada,
            'iniciada_at' => $tareaBloqueada->iniciada_at ?? $movimiento->recibido_servidor_at,
            'completada_at' => $ahora,
            'version' => $tareaBloqueada->version + 1,
        ]);

        $plan = $tareaBloqueada->planOperacional;
        $poseePendientes = TareaMovimiento::query()
            ->where('plan_operacional_id', $plan->id)
            ->whereNotIn('estado', [
                EstadoTareaMovimiento::Completada->value,
                EstadoTareaMovimiento::Cancelada->value,
            ])
            ->exists();

        if (! $poseePendientes) {
            $plan->update([
                'estado' => EstadoPlanOperacional::Completado,
                'completado_por_user_id' => $movimiento->user_id,
                'completado_at' => $ahora,
                'version' => $plan->version + 1,
            ]);
        }
    }

    public function expirarVencidas(int $limite = 100): int
    {
        $ids = ReservaTareaMovimiento::query()
            ->where('estado', EstadoReservaTareaMovimiento::Activa->value)
            ->whereNotNull('bloqueo_tarea_id')
            ->where('vence_at', '<=', now())
            ->orderBy('vence_at')
            ->limit($limite)
            ->pluck('id');
        $expiradas = 0;

        foreach ($ids as $id) {
            $expirada = DB::transaction(function () use ($id): bool {
                $reserva = ReservaTareaMovimiento::query()->lockForUpdate()->find($id);

                if (! $reserva
                    || $reserva->estado !== EstadoReservaTareaMovimiento::Activa
                    || ! $this->estaVencida($reserva)) {
                    return false;
                }

                $this->expirar($reserva);

                return true;
            }, attempts: 3);

            $expiradas += $expirada ? 1 : 0;
        }

        return $expiradas;
    }

    private function bloquearDestinoDisponible(TareaMovimiento $tarea): ?Posicion
    {
        if (! $tarea->posicion_destino_id) {
            return null;
        }

        $posicion = Posicion::query()
            ->lockForUpdate()
            ->findOrFail($tarea->posicion_destino_id);

        if ($posicion->camara_id !== $tarea->camara_destino_id
            || $posicion->estado !== EstadoPosicion::Activa) {
            throw new ConflictoOperacion('La posición propuesta ya no está habilitada.');
        }
        if (UbicacionActual::query()
            ->where('posicion_id', $posicion->id)
            ->lockForUpdate()
            ->exists()) {
            throw new ConflictoOperacion('La posición propuesta ya se encuentra ocupada.');
        }

        return $posicion;
    }

    private function validarPropietario(
        ReservaTareaMovimiento $reserva,
        User $usuario,
        Dispositivo $dispositivo,
    ): void {
        if ($reserva->user_id !== $usuario->id
            || $reserva->dispositivo_id !== $dispositivo->id) {
            throw new ConflictoOperacion(
                'La reserva pertenece a otro camarero o dispositivo.',
            );
        }
    }

    private function validarCorrespondencia(
        TareaMovimiento $tarea,
        Folio $folio,
        TipoMovimiento $tipo,
        ?string $posicionOrigenId,
        ?string $posicionDestinoId,
    ): void {
        if (! in_array($tarea->estado, [
            EstadoTareaMovimiento::Asumida,
            EstadoTareaMovimiento::EnProceso,
        ], true)
            || ! $tarea->planOperacional
            || $tarea->planOperacional->estado !== EstadoPlanOperacional::EnEjecucion) {
            throw new ConflictoOperacion('La tarea no se encuentra habilitada para ejecución.');
        }
        if ($tarea->folio_id !== $folio->id
            || $tarea->tipo_movimiento !== $tipo
            || $tarea->posicion_origen_id !== $posicionOrigenId
            || $tarea->posicion_destino_id !== $posicionDestinoId) {
            throw new ConflictoOperacion(
                'El folio, origen o destino no corresponde a la tarea reservada.',
            );
        }
    }

    private function renovarReserva(ReservaTareaMovimiento $reserva): ReservaTareaMovimiento
    {
        $reserva->update([
            'renovada_at' => now(),
            'vence_at' => $this->nuevoVencimiento(),
            'version' => $reserva->version + 1,
        ]);
        $this->incrementarRevisionCamara($reserva->posicion_destino_id);

        return $reserva->refresh();
    }

    private function expirarRelacionadas(string $folioId, ?string $posicionDestinoId): void
    {
        $reservas = ReservaTareaMovimiento::query()
            ->where('estado', EstadoReservaTareaMovimiento::Activa->value)
            ->where(function ($consulta) use ($folioId, $posicionDestinoId): void {
                $consulta->whereHas(
                    'tareaMovimiento',
                    fn ($tareas) => $tareas->where('folio_id', $folioId),
                );
                if ($posicionDestinoId) {
                    $consulta->orWhere('bloqueo_posicion_id', $posicionDestinoId);
                }
            })
            ->lockForUpdate()
            ->get();

        foreach ($reservas as $reserva) {
            if ($this->estaVencida($reserva)) {
                $this->expirar($reserva);
            }
        }
    }

    private function expirar(ReservaTareaMovimiento $reserva): void
    {
        $this->finalizarReserva($reserva, EstadoReservaTareaMovimiento::Expirada, [
            'expirada_at' => now(),
            'motivo_liberacion' => 'Lease vencido por falta de renovación.',
        ]);
        $this->devolverTareaALaBandeja($reserva->tarea_movimiento_id);
    }

    /** @param array<string, mixed> $atributos */
    private function finalizarReserva(
        ReservaTareaMovimiento $reserva,
        EstadoReservaTareaMovimiento $estado,
        array $atributos,
    ): void {
        $posicionDestinoId = $reserva->posicion_destino_id;
        $reserva->update([
            ...$atributos,
            'estado' => $estado,
            'bloqueo_tarea_id' => null,
            'bloqueo_posicion_id' => null,
            'version' => $reserva->version + 1,
        ]);
        $this->incrementarRevisionCamara($posicionDestinoId);
    }

    private function incrementarRevisionCamara(?string $posicionDestinoId): void
    {
        if (! $posicionDestinoId) {
            return;
        }

        $camaraId = Posicion::query()->whereKey($posicionDestinoId)->value('camara_id');
        if ($camaraId) {
            Camara::query()->whereKey($camaraId)->increment('revision_reservas');
        }
    }

    private function devolverTareaALaBandeja(string $tareaId): void
    {
        $tarea = TareaMovimiento::query()->lockForUpdate()->find($tareaId);

        if (! $tarea || $tarea->estado->esFinal()) {
            return;
        }

        $tarea->update([
            'estado' => EstadoTareaMovimiento::Pendiente,
            'responsable_user_id' => null,
            'dispositivo_id' => null,
            'asumida_at' => null,
            'iniciada_at' => null,
            'version' => $tarea->version + 1,
        ]);
    }

    private function reservaActivaTarea(
        string $tareaId,
        bool $bloquear = false,
    ): ?ReservaTareaMovimiento {
        $consulta = ReservaTareaMovimiento::query()
            ->where('bloqueo_tarea_id', $tareaId);

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
    }

    private function reservaActivaDestino(
        string $posicionId,
        bool $bloquear = false,
    ): ?ReservaTareaMovimiento {
        $consulta = ReservaTareaMovimiento::query()
            ->where('bloqueo_posicion_id', $posicionId)
            ->where('vence_at', '>', now());

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
    }

    private function reservaActivaFolio(
        string $folioId,
        bool $bloquear = false,
    ): ?ReservaTareaMovimiento {
        $consulta = ReservaTareaMovimiento::query()
            ->whereNotNull('bloqueo_tarea_id')
            ->where('vence_at', '>', now())
            ->whereHas(
                'tareaMovimiento',
                fn ($tareas) => $tareas->where('folio_id', $folioId),
            );

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
    }

    private function estaVencida(ReservaTareaMovimiento $reserva): bool
    {
        return $reserva->vence_at->lte(now());
    }

    private function nuevoVencimiento(): CarbonInterface
    {
        return now()->addMinutes((int) config('planificador.reserva_tarea_minutos', 10));
    }
}
