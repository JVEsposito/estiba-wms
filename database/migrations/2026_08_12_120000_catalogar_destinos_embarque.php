<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puertos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pais_id')->constrained('paises')->restrictOnDelete();
            $table->string('codigo', 12)->unique();
            $table->string('nombre', 180);
            $table->string('tipo', 20)->default('maritimo');
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->unique(['pais_id', 'nombre', 'tipo'], 'puerto_pais_nombre_tipo_unique');
            $table->index(['pais_id', 'activo', 'nombre'], 'puerto_pais_activo_nombre_idx');
        });

        $this->cargarPuertos();

        Schema::table('embarques', function (Blueprint $table): void {
            $table->foreignUuid('puerto_embarque_id')->nullable()->after('puerto_embarque')
                ->constrained('puertos')->restrictOnDelete();
        });

        Schema::table('instructivos_embarque', function (Blueprint $table): void {
            $table->foreignUuid('pais_destino_id')->nullable()->after('destino_pais')
                ->constrained('paises')->restrictOnDelete();
            $table->foreignUuid('puerto_destino_id')->nullable()->after('destino_ciudad')
                ->constrained('puertos')->restrictOnDelete();
        });

        $this->vincularDatosExistentes();
        $this->corregirReferenciasInternasEnCargas();
    }

    public function down(): void
    {
        Schema::table('instructivos_embarque', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('puerto_destino_id');
            $table->dropConstrainedForeignId('pais_destino_id');
        });

        Schema::table('embarques', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('puerto_embarque_id');
        });

        Schema::dropIfExists('puertos');
    }

    private function cargarPuertos(): void
    {
        $catalogo = json_decode(
            file_get_contents(database_path('data/puertos_embarque.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $paises = DB::table('paises')->pluck('id', 'iso_alpha2');
        $ahora = now();

        $filas = collect($catalogo)
            ->map(function (array $puerto) use ($paises, $ahora): array {
                $paisId = $paises[$puerto['pais_iso2']] ?? null;

                if ($paisId === null) {
                    throw new RuntimeException(
                        "El puerto {$puerto['codigo']} referencia un país inexistente.",
                    );
                }

                return [
                    'id' => (string) Str::uuid(),
                    'pais_id' => $paisId,
                    'codigo' => $puerto['codigo'],
                    'nombre' => $puerto['nombre'],
                    'tipo' => $puerto['tipo'] ?? 'maritimo',
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            });

        foreach ($filas->chunk(100) as $grupo) {
            DB::table('puertos')->insert($grupo->all());
        }
    }

    private function vincularDatosExistentes(): void
    {
        $paises = DB::table('paises')->get(['id', 'nombre_es'])
            ->keyBy(fn (object $pais): string => $this->normalizar($pais->nombre_es));
        $puertos = DB::table('puertos')->get(['id', 'pais_id', 'nombre']);
        $puertosPorPais = $puertos->keyBy(
            fn (object $puerto): string => $puerto->pais_id.'|'.$this->normalizar($puerto->nombre),
        );
        $puertosPorNombre = $puertos->groupBy(
            fn (object $puerto): string => $this->normalizar($puerto->nombre),
        );

        DB::table('instructivos_embarque')
            ->get(['id', 'destino_pais', 'destino_ciudad'])
            ->each(function (object $instructivo) use ($paises, $puertosPorPais): void {
                $pais = $paises->get($this->normalizar($instructivo->destino_pais));
                $puerto = $pais
                    ? $puertosPorPais->get(
                        $pais->id.'|'.$this->normalizar($instructivo->destino_ciudad),
                    )
                    : null;

                DB::table('instructivos_embarque')->where('id', $instructivo->id)->update([
                    'pais_destino_id' => $pais?->id,
                    'puerto_destino_id' => $puerto?->id,
                ]);
            });

        DB::table('embarques')->whereNotNull('puerto_embarque')
            ->get(['id', 'puerto_embarque'])
            ->each(function (object $embarque) use ($puertosPorNombre): void {
                $coincidencias = $puertosPorNombre->get(
                    $this->normalizar($embarque->puerto_embarque),
                    collect(),
                );

                if ($coincidencias->count() !== 1) {
                    return;
                }

                DB::table('embarques')->where('id', $embarque->id)->update([
                    'puerto_embarque_id' => $coincidencias->first()->id,
                ]);
            });
    }

    private function corregirReferenciasInternasEnCargas(): void
    {
        DB::table('embarques')->whereNotNull('carga_id')->get(['carga_id', 'codigo'])
            ->each(function (object $embarque): void {
                DB::table('cargas')
                    ->where('id', $embarque->carga_id)
                    ->where('numero_orden_externa', $embarque->codigo)
                    ->update(['numero_orden_externa' => null]);
            });
    }

    private function normalizar(?string $valor): string
    {
        return mb_strtoupper(trim(Str::ascii((string) $valor)));
    }
};
