<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULO = 'materia-prima.hidrocooler';

    public function up(): void
    {
        Schema::table('procesos_hidrocooler_materia_prima', function (Blueprint $table): void {
            $table->string('codigo', 50)->nullable()->unique('hidro_mp_codigo_unique')->after('id');
            $table->char('payload_inicio_hash', 64)->nullable()->after('operacion_inicio_id');
            $table->char('payload_termino_hash', 64)->nullable()->after('operacion_termino_id');
            $table->string('operador_snapshot', 150)->nullable()->after('equipo');
            $table->char('equipo_activo_clave', 64)->nullable()->unique('hidro_mp_equipo_activo_unique')->after('equipo');
            $table->unsignedInteger('cantidad_envases_snapshot')->nullable()->after('operador_snapshot');
            $table->decimal('kilos_netos_snapshot', 12, 3)->nullable()->after('cantidad_envases_snapshot');
            $table->decimal('temperatura_inicial_c', 6, 2)->nullable()->after('duracion_minutos');
            $table->decimal('temperatura_objetivo_c', 6, 2)->nullable()->after('temperatura_inicial_c');
            $table->decimal('temperatura_agua_inicial_c', 6, 2)->nullable()->after('temperatura_objetivo_c');
            $table->decimal('temperatura_agua_final_c', 6, 2)->nullable()->after('temperatura_c');
            $table->string('destino_salida', 20)->nullable()->index('hidro_mp_destino_index')->after('temperatura_agua_final_c');
            $table->text('observacion_inicio')->nullable()->after('destino_salida');
        });

        DB::table('procesos_hidrocooler_materia_prima')
            ->orderBy('created_at')
            ->each(function (object $proceso): void {
                $lote = DB::table('lotes_materia_prima')
                    ->where('id', $proceso->lote_materia_prima_id)
                    ->first(['cantidad_envases_primarios', 'kilos_netos_confirmados']);
                $operador = DB::table('users')
                    ->where('id', $proceso->iniciado_por_user_id)
                    ->value('name');

                DB::table('procesos_hidrocooler_materia_prima')
                    ->where('id', $proceso->id)
                    ->update([
                        'codigo' => 'HC-LEG-'.strtoupper(str_replace('-', '', $proceso->id)),
                        'operador_snapshot' => $operador,
                        'cantidad_envases_snapshot' => $lote?->cantidad_envases_primarios,
                        'kilos_netos_snapshot' => $lote?->kilos_netos_confirmados,
                        'destino_salida' => $proceso->termino_at ? 'camara' : null,
                    ]);
            });

        Schema::table('entregas_fruta_proceso', function (Blueprint $table): void {
            $table->uuid('asignacion_camara_lote_id')->nullable()->change();
            $table->uuid('camara_id')->nullable()->change();
        });

        DB::table('perfiles_acceso')
            ->select(['id', 'modulos'])
            ->orderBy('id')
            ->each(function (object $perfil): void {
                $modulos = json_decode((string) $perfil->modulos, true);
                if (! is_array($modulos)
                    || ! in_array('materia-prima.digitacion', $modulos, true)
                    || in_array(self::MODULO, $modulos, true)) {
                    return;
                }

                $modulos[] = self::MODULO;
                DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                    'modulos' => json_encode(array_values($modulos), JSON_THROW_ON_ERROR),
                ]);
            });
    }

    public function down(): void
    {
        if (DB::table('entregas_fruta_proceso')
            ->whereNull('asignacion_camara_lote_id')
            ->orWhereNull('camara_id')
            ->exists()) {
            throw new RuntimeException(
                'No se puede revertir el módulo mientras existan entregas directas desde Hidrocooler.',
            );
        }

        DB::table('perfiles_acceso')
            ->select(['id', 'modulos'])
            ->orderBy('id')
            ->each(function (object $perfil): void {
                $modulos = json_decode((string) $perfil->modulos, true);
                if (! is_array($modulos)) {
                    return;
                }

                DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                    'modulos' => json_encode(array_values(array_filter(
                        $modulos,
                        fn ($modulo): bool => $modulo !== self::MODULO,
                    )), JSON_THROW_ON_ERROR),
                ]);
            });

        Schema::table('entregas_fruta_proceso', function (Blueprint $table): void {
            $table->uuid('asignacion_camara_lote_id')->nullable(false)->change();
            $table->uuid('camara_id')->nullable(false)->change();
        });

        Schema::table('procesos_hidrocooler_materia_prima', function (Blueprint $table): void {
            $table->dropUnique('hidro_mp_codigo_unique');
            $table->dropUnique('hidro_mp_equipo_activo_unique');
            $table->dropIndex('hidro_mp_destino_index');
            $table->dropColumn([
                'codigo',
                'payload_inicio_hash',
                'payload_termino_hash',
                'operador_snapshot',
                'equipo_activo_clave',
                'cantidad_envases_snapshot',
                'kilos_netos_snapshot',
                'temperatura_inicial_c',
                'temperatura_objetivo_c',
                'temperatura_agua_inicial_c',
                'temperatura_agua_final_c',
                'destino_salida',
                'observacion_inicio',
            ]);
        });
    }
};
