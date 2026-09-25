<?php

namespace App\Services\Temporadas\Cierre;

use App\Enums\AudienciaNotificacionOperacional;
use App\Enums\CategoriaPendienteCierre;
use App\Enums\SeveridadNotificacionOperacional;
use App\Enums\TipoNotificacionOperacional;
use App\Models\NotificacionOperacional;
use App\Models\Temporada;
use App\Models\User;

/**
 * Avisa a cada responsable qué registros de la temporada tiene sin cerrar.
 *
 * El aviso es una notificación operacional dirigida al usuario. Se repite
 * solo si su lista de pendientes cambió desde el aviso anterior.
 */
final class ServicioAvisoCierreTemporada
{
    public function __construct(
        private readonly ServicioDiagnosticoCierreTemporada $diagnostico,
    ) {}

    /**
     * @return array{
     *     avisados: list<array{usuario: array{id: int, nombre: string}, cantidad: int, nuevo: bool}>,
     *     sin_responsable: int
     * }
     */
    public function avisar(Temporada $temporada, User $administrador): array
    {
        $porUsuario = [];
        $sinResponsable = 0;

        foreach (CategoriaPendienteCierre::cases() as $categoria) {
            foreach ($this->diagnostico->pendientes($temporada, $categoria) as $pendiente) {
                $usuarioId = $pendiente['responsable']['id'] ?? null;
                if ($usuarioId === null) {
                    $sinResponsable++;

                    continue;
                }
                $porUsuario[$usuarioId][$categoria->value][] = $pendiente;
            }
        }

        $usuarios = User::query()
            ->whereKey(array_keys($porUsuario))
            ->where('activo', true)
            ->get(['id', 'name'])
            ->keyBy('id');
        $avisados = [];

        foreach ($porUsuario as $usuarioId => $categorias) {
            $usuario = $usuarios->get($usuarioId);
            if ($usuario === null) {
                $sinResponsable += array_sum(array_map('count', $categorias));

                continue;
            }

            $resumen = [];
            $huella = [];
            foreach ($categorias as $valor => $items) {
                $categoria = CategoriaPendienteCierre::from($valor);
                $resumen[] = [
                    'categoria' => $valor,
                    'etiqueta' => $categoria->etiqueta(),
                    'como_cerrar' => $categoria->comoCerrar(),
                    'cantidad' => count($items),
                    'referencias' => array_slice(array_column($items, 'referencia'), 0, 10),
                ];
                array_push($huella, ...array_map(fn (array $item): string => "{$valor}:{$item['id']}", $items));
            }
            sort($huella);
            $cantidad = count($huella);
            $clave = sprintf(
                'cierre-temporada:%s:usuario:%d:%s',
                $temporada->id,
                $usuario->id,
                substr(hash('sha256', implode('|', $huella)), 0, 32),
            );

            $notificacion = NotificacionOperacional::query()->firstOrCreate(
                ['clave' => $clave],
                [
                    'tipo' => TipoNotificacionOperacional::CierreTemporadaPendiente,
                    'audiencia_tipo' => AudienciaNotificacionOperacional::Usuario,
                    'audiencia_valor' => (string) $usuario->id,
                    'severidad' => SeveridadNotificacionOperacional::Advertencia,
                    'titulo' => "Cierre de temporada {$temporada->codigo}",
                    'mensaje' => sprintf(
                        'Tienes %d %s sin cerrar. %s. Ciérralos en su módulo o avisa a administración si deben regularizarse.',
                        $cantidad,
                        $cantidad === 1 ? 'registro' : 'registros',
                        implode('; ', array_map(
                            fn (array $fila): string => "{$fila['etiqueta']}: {$fila['cantidad']}",
                            $resumen,
                        )),
                    ),
                    'datos' => [
                        'temporada' => ['id' => $temporada->id, 'codigo' => $temporada->codigo],
                        'pendientes' => $resumen,
                        'avisado_por' => ['id' => $administrador->id, 'nombre' => $administrador->name],
                    ],
                ],
            );

            $avisados[] = [
                'usuario' => ['id' => (int) $usuario->id, 'nombre' => (string) $usuario->name],
                'cantidad' => $cantidad,
                'nuevo' => $notificacion->wasRecentlyCreated,
            ];
        }

        usort($avisados, fn (array $a, array $b): int => $b['cantidad'] <=> $a['cantidad']);

        return ['avisados' => $avisados, 'sin_responsable' => $sinResponsable];
    }
}
