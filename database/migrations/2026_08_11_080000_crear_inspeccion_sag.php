<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MODULO = 'frigorifico.inspeccion-sag';

    public function up(): void
    {
        DB::table('secuencias_documentos')->updateOrInsert(
            ['clave' => 'lotes_inspeccion_sag'],
            ['ultimo_numero' => 0],
        );

        Schema::create('paises', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('iso_alpha2', 2)->unique();
            $table->char('iso_alpha3', 3)->unique();
            $table->char('iso_numerico', 3)->unique();
            $table->string('nombre_es', 150)->index();
            $table->boolean('es_iso_oficial')->default(true);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('bloques_mercado', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 150);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('bloque_mercado_pais', function (Blueprint $table): void {
            $table->foreignUuid('bloque_mercado_id')->constrained('bloques_mercado')->restrictOnDelete();
            $table->foreignUuid('pais_id')->constrained('paises')->restrictOnDelete();
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->primary(['bloque_mercado_id', 'pais_id']);
        });

        Schema::create('lotes_inspeccion_sag', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->string('tipo', 30)->index();
            $table->string('estado', 40)->index();
            $table->unsignedInteger('cantidad_solicitada')->nullable();
            $table->string('referencia_correo', 250)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('iniciado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('finalizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('iniciado_at')->nullable();
            $table->dateTime('finalizado_at')->nullable();
            $table->dateTime('cancelado_at')->nullable();
            $table->timestamps();

            $table->index(['temporada_id', 'estado', 'created_at'], 'lotes_sag_temporada_estado_idx');
        });

        Schema::create('destinos_lote_inspeccion_sag', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('lote_inspeccion_sag_id')->constrained('lotes_inspeccion_sag')->restrictOnDelete();
            $table->string('tipo_destino', 20);
            $table->foreignUuid('pais_id')->nullable()->constrained('paises')->restrictOnDelete();
            $table->foreignUuid('bloque_mercado_id')->nullable()->constrained('bloques_mercado')->restrictOnDelete();
            $table->json('destino_snapshot');
            $table->json('miembros_snapshot')->nullable();
            $table->timestamps();

            $table->index(['lote_inspeccion_sag_id', 'tipo_destino'], 'destinos_lote_sag_tipo_idx');
        });

        Schema::create('lotes_inspeccion_sag_folios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('lote_inspeccion_sag_id')->constrained('lotes_inspeccion_sag')->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->json('estado_sag_anterior');
            $table->text('observacion')->nullable();
            $table->foreignId('resuelto_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('resuelto_at')->nullable();
            $table->timestamps();

            $table->unique(['lote_inspeccion_sag_id', 'folio_id'], 'lote_sag_folio_unique');
            $table->index(['folio_id', 'estado'], 'lote_sag_folio_estado_idx');
        });

        Schema::create('resultados_destino_inspeccion_sag', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('lote_inspeccion_sag_folio_id');
            $table->foreign(
                'lote_inspeccion_sag_folio_id',
                'resultado_sag_folio_fk',
            )->references('id')->on('lotes_inspeccion_sag_folios')->restrictOnDelete();
            $table->uuid('destino_lote_inspeccion_sag_id');
            $table->foreign(
                'destino_lote_inspeccion_sag_id',
                'resultado_sag_destino_fk',
            )->references('id')->on('destinos_lote_inspeccion_sag')->restrictOnDelete();
            $table->string('resultado', 30)->default('pendiente')->index();
            $table->string('tipo_aprobacion', 10)->nullable()->index();
            $table->text('observacion')->nullable();
            $table->foreignId('resuelto_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('resuelto_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['lote_inspeccion_sag_folio_id', 'destino_lote_inspeccion_sag_id'],
                'resultado_destino_sag_unique',
            );
        });

        Schema::create('autorizaciones_sag_folio', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->string('tipo_aprobacion', 10)->index();
            $table->string('tipo_destino', 20)->index();
            $table->foreignUuid('pais_id')->nullable()->constrained('paises')->restrictOnDelete();
            $table->foreignUuid('bloque_mercado_id')->nullable()->constrained('bloques_mercado')->restrictOnDelete();
            $table->foreignUuid('resultado_origen_id')->constrained('resultados_destino_inspeccion_sag')->restrictOnDelete();
            $table->json('destino_snapshot');
            $table->json('miembros_snapshot')->nullable();
            $table->boolean('activa')->default(true)->index();
            $table->foreignId('aprobado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('aprobado_at');
            $table->foreignId('revocado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('revocado_at')->nullable();
            $table->string('motivo_revocacion', 500)->nullable();
            $table->timestamps();

            $table->index(['folio_id', 'activa'], 'autorizacion_sag_folio_activa_idx');
        });

        $this->cargarPaisesYUnionEuropea();
        $this->habilitarModuloEnPerfilesExistentes();
    }

    public function down(): void
    {
        $this->retirarModuloDePerfilesExistentes();

        DB::table('secuencias_documentos')->where('clave', 'lotes_inspeccion_sag')->delete();

        Schema::dropIfExists('autorizaciones_sag_folio');
        Schema::dropIfExists('resultados_destino_inspeccion_sag');
        Schema::dropIfExists('lotes_inspeccion_sag_folios');
        Schema::dropIfExists('destinos_lote_inspeccion_sag');
        Schema::dropIfExists('lotes_inspeccion_sag');
        Schema::dropIfExists('bloque_mercado_pais');
        Schema::dropIfExists('bloques_mercado');
        Schema::dropIfExists('paises');
    }

    private function cargarPaisesYUnionEuropea(): void
    {
        $paises = json_decode(
            file_get_contents(database_path('data/paises_iso_3166_1.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $ahora = now();
        $ids = [];
        $filas = [];

        foreach ($paises as $pais) {
            $id = (string) Str::uuid();
            $ids[$pais['iso_alpha2']] = $id;
            $filas[] = [
                'id' => $id,
                'iso_alpha2' => $pais['iso_alpha2'],
                'iso_alpha3' => $pais['iso_alpha3'],
                'iso_numerico' => $pais['iso_numerico'],
                'nombre_es' => $pais['nombre_es'],
                'es_iso_oficial' => $pais['iso_alpha2'] !== 'XK',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($filas, 100) as $grupo) {
            DB::table('paises')->insert($grupo);
        }

        $unionEuropeaId = (string) Str::uuid();
        DB::table('bloques_mercado')->insert([
            'id' => $unionEuropeaId,
            'codigo' => 'UE',
            'nombre' => 'Unión Europea',
            'descripcion' => 'Bloque transversal para inspecciones con destino a la Unión Europea.',
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $miembrosUnionEuropea = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU',
            'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];

        DB::table('bloque_mercado_pais')->insert(array_map(
            fn (string $codigo): array => [
                'bloque_mercado_id' => $unionEuropeaId,
                'pais_id' => $ids[$codigo],
                'vigente_desde' => null,
                'vigente_hasta' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            $miembrosUnionEuropea,
        ));
    }

    private function habilitarModuloEnPerfilesExistentes(): void
    {
        DB::table('perfiles_acceso')
            ->whereIn('rol_base', ['administrador', 'supervisor_frio', 'consulta'])
            ->orderBy('id')
            ->each(function (object $perfil): void {
                $modulos = json_decode($perfil->modulos, true, flags: JSON_THROW_ON_ERROR);

                if (! in_array(self::MODULO, $modulos, true)) {
                    $modulos[] = self::MODULO;
                    DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                        'modulos' => json_encode(array_values($modulos), JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function retirarModuloDePerfilesExistentes(): void
    {
        if (! Schema::hasTable('perfiles_acceso')) {
            return;
        }

        DB::table('perfiles_acceso')->orderBy('id')->each(function (object $perfil): void {
            $modulos = json_decode($perfil->modulos, true, flags: JSON_THROW_ON_ERROR);
            $filtrados = array_values(array_filter(
                $modulos,
                fn (string $modulo): bool => $modulo !== self::MODULO,
            ));

            if ($filtrados !== $modulos) {
                DB::table('perfiles_acceso')->where('id', $perfil->id)->update([
                    'modulos' => json_encode($filtrados, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
