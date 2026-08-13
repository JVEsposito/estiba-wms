<?php

use App\Services\Autorizacion\CatalogoModulosAcceso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias_integridad', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('origen', 30)->index();
            $table->string('estado', 30)->index();
            $table->foreignId('iniciada_por_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->dateTime('iniciada_at')->index();
            $table->dateTime('finalizada_at')->nullable();
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->unsignedInteger('hallazgos_activos')->default(0);
            $table->unsignedInteger('hallazgos_criticos')->default(0);
            $table->unsignedInteger('hallazgos_advertencia')->default(0);
            $table->unsignedInteger('hallazgos_informativos')->default(0);
            $table->unsignedInteger('hallazgos_nuevos')->default(0);
            $table->unsignedInteger('hallazgos_resueltos')->default(0);
            $table->json('reglas_ejecutadas')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('hallazgos_integridad', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('huella', 64)->unique();
            $table->string('regla_codigo', 100)->index();
            $table->string('severidad', 30)->index();
            $table->string('modulo', 60)->index();
            $table->string('entidad_tipo', 60)->index();
            $table->string('entidad_id', 100)->nullable();
            $table->string('referencia', 150)->nullable()->index();
            $table->string('titulo', 180);
            $table->text('detalle');
            $table->json('contexto')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->unsignedInteger('ocurrencias')->default(1);
            $table->foreignUuid('primera_auditoria_id')
                ->constrained('auditorias_integridad')
                ->restrictOnDelete();
            $table->foreignUuid('ultima_auditoria_id')
                ->constrained('auditorias_integridad')
                ->restrictOnDelete();
            $table->dateTime('detectado_primera_vez_at');
            $table->dateTime('detectado_ultima_vez_at')->index();
            $table->dateTime('resuelto_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['activo', 'severidad', 'modulo'],
                'hallazgos_integridad_estado_idx',
            );
            $table->index(
                ['entidad_tipo', 'entidad_id'],
                'hallazgos_integridad_entidad_idx',
            );
        });

        $this->habilitarModuloAdministrador();
    }

    public function down(): void
    {
        $this->retirarModuloPerfiles();
        Schema::dropIfExists('hallazgos_integridad');
        Schema::dropIfExists('auditorias_integridad');
    }

    private function habilitarModuloAdministrador(): void
    {
        if (! Schema::hasTable('perfiles_acceso')) {
            return;
        }

        DB::table('perfiles_acceso')
            ->where('rol_base', 'administrador')
            ->where('protegido', true)
            ->orderBy('id')
            ->each(function (object $perfil): void {
                $modulos = json_decode($perfil->modulos, true, flags: JSON_THROW_ON_ERROR);

                if (in_array(CatalogoModulosAcceso::OFICINA_INTEGRIDAD_OPERACIONAL, $modulos, true)) {
                    return;
                }

                $modulos[] = CatalogoModulosAcceso::OFICINA_INTEGRIDAD_OPERACIONAL;
                DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                    'modulos' => json_encode(array_values($modulos), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
    }

    private function retirarModuloPerfiles(): void
    {
        if (! Schema::hasTable('perfiles_acceso')) {
            return;
        }

        DB::table('perfiles_acceso')->orderBy('id')->each(function (object $perfil): void {
            $modulos = json_decode($perfil->modulos, true, flags: JSON_THROW_ON_ERROR);
            $filtrados = array_values(array_filter(
                $modulos,
                fn (string $modulo): bool => $modulo !== CatalogoModulosAcceso::OFICINA_INTEGRIDAD_OPERACIONAL,
            ));

            if ($filtrados === $modulos) {
                return;
            }

            DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                'modulos' => json_encode($filtrados, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }
};
