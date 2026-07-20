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
        Schema::create('clientes_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['temporada_id', 'nombre'], 'cliente_validacion_temporada_nombre_unique');
        });

        Schema::create('marcas_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_validacion_id')->constrained('clientes_validacion')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['cliente_validacion_id', 'nombre'], 'marca_validacion_cliente_nombre_unique');
        });

        Schema::create('especies_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['temporada_id', 'nombre'], 'especie_validacion_temporada_nombre_unique');
        });

        Schema::create('variedades_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('especie_validacion_id')->constrained('especies_validacion')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['especie_validacion_id', 'nombre'], 'variedad_validacion_especie_nombre_unique');
        });

        Schema::create('calibres_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('especie_validacion_id')->constrained('especies_validacion')->restrictOnDelete();
            $table->string('nombre', 50);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['especie_validacion_id', 'nombre'], 'calibre_validacion_especie_nombre_unique');
        });

        Schema::create('envases_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('especie_validacion_id')->constrained('especies_validacion')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['especie_validacion_id', 'nombre'], 'envase_validacion_especie_nombre_unique');
        });

        Schema::create('csg_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('codigo', 50);
            $table->string('predio', 150)->nullable();
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['temporada_id', 'codigo'], 'csg_validacion_temporada_codigo_unique');
        });

        Schema::create('csg_variedades_validacion', function (Blueprint $table) {
            $table->foreignUuid('csg_validacion_id')->constrained('csg_validacion')->restrictOnDelete();
            $table->foreignUuid('variedad_validacion_id')->constrained('variedades_validacion')->restrictOnDelete();
            $table->timestamps();
            $table->primary(
                ['csg_validacion_id', 'variedad_validacion_id'],
                'csg_variedad_validacion_primary',
            );
        });

        Schema::table('articulos_validacion', function (Blueprint $table) {
            $table->foreignUuid('especie_validacion_id')->nullable()->after('temporada_id')
                ->constrained('especies_validacion')->restrictOnDelete();
            $table->foreignUuid('variedad_validacion_id')->nullable()->after('especie_validacion_id')
                ->constrained('variedades_validacion')->restrictOnDelete();
            $table->foreignUuid('calibre_validacion_id')->nullable()->after('variedad_validacion_id')
                ->constrained('calibres_validacion')->restrictOnDelete();
            $table->foreignUuid('envase_validacion_id')->nullable()->after('calibre_validacion_id')
                ->constrained('envases_validacion')->restrictOnDelete();
        });

        Schema::table('origenes_validacion', function (Blueprint $table) {
            $table->foreignUuid('cliente_validacion_id')->nullable()->after('temporada_id')
                ->constrained('clientes_validacion')->restrictOnDelete();
            $table->foreignUuid('marca_validacion_id')->nullable()->after('cliente_validacion_id')
                ->constrained('marcas_validacion')->restrictOnDelete();
            $table->foreignUuid('csg_validacion_id')->nullable()->after('marca_validacion_id')
                ->constrained('csg_validacion')->restrictOnDelete();
        });

        $this->migrarCatalogosExistentes();
    }

    public function down(): void
    {
        Schema::table('origenes_validacion', function (Blueprint $table) {
            $table->dropForeign(['cliente_validacion_id']);
            $table->dropForeign(['marca_validacion_id']);
            $table->dropForeign(['csg_validacion_id']);
            $table->dropColumn([
                'cliente_validacion_id',
                'marca_validacion_id',
                'csg_validacion_id',
            ]);
        });

        Schema::table('articulos_validacion', function (Blueprint $table) {
            $table->dropForeign(['especie_validacion_id']);
            $table->dropForeign(['variedad_validacion_id']);
            $table->dropForeign(['calibre_validacion_id']);
            $table->dropForeign(['envase_validacion_id']);
            $table->dropColumn([
                'especie_validacion_id',
                'variedad_validacion_id',
                'calibre_validacion_id',
                'envase_validacion_id',
            ]);
        });

        Schema::dropIfExists('csg_variedades_validacion');
        Schema::dropIfExists('csg_validacion');
        Schema::dropIfExists('envases_validacion');
        Schema::dropIfExists('calibres_validacion');
        Schema::dropIfExists('variedades_validacion');
        Schema::dropIfExists('especies_validacion');
        Schema::dropIfExists('marcas_validacion');
        Schema::dropIfExists('clientes_validacion');
    }

    private function migrarCatalogosExistentes(): void
    {
        $ahora = now();

        foreach (DB::table('temporadas')->pluck('id') as $temporadaId) {
            $especies = [];
            $variedades = [];
            $calibres = [];
            $envases = [];

            foreach (DB::table('articulos_validacion')->where('temporada_id', $temporadaId)->get() as $articulo) {
                $claveEspecie = $this->clave($articulo->especie);
                if (! isset($especies[$claveEspecie])) {
                    $especies[$claveEspecie] = (string) Str::uuid();
                    DB::table('especies_validacion')->insert([
                        'id' => $especies[$claveEspecie],
                        'temporada_id' => $temporadaId,
                        'nombre' => $articulo->especie,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }

                $especieId = $especies[$claveEspecie];
                $claveVariedad = $especieId.'|'.$this->clave($articulo->variedad);
                $claveCalibre = $especieId.'|'.$this->clave($articulo->calibre);
                $claveEnvase = $especieId.'|'.$this->clave($articulo->envase);

                if (! isset($variedades[$claveVariedad])) {
                    $variedades[$claveVariedad] = (string) Str::uuid();
                    DB::table('variedades_validacion')->insert([
                        'id' => $variedades[$claveVariedad],
                        'especie_validacion_id' => $especieId,
                        'nombre' => $articulo->variedad,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }

                if (! isset($calibres[$claveCalibre])) {
                    $calibres[$claveCalibre] = (string) Str::uuid();
                    DB::table('calibres_validacion')->insert([
                        'id' => $calibres[$claveCalibre],
                        'especie_validacion_id' => $especieId,
                        'nombre' => $articulo->calibre,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }

                if (! isset($envases[$claveEnvase])) {
                    $envases[$claveEnvase] = (string) Str::uuid();
                    DB::table('envases_validacion')->insert([
                        'id' => $envases[$claveEnvase],
                        'especie_validacion_id' => $especieId,
                        'nombre' => $articulo->envase,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }

                DB::table('articulos_validacion')->where('id', $articulo->id)->update([
                    'especie_validacion_id' => $especieId,
                    'variedad_validacion_id' => $variedades[$claveVariedad],
                    'calibre_validacion_id' => $calibres[$claveCalibre],
                    'envase_validacion_id' => $envases[$claveEnvase],
                ]);
            }

            $clientes = [];
            $marcas = [];
            $csg = [];

            foreach (DB::table('origenes_validacion')->where('temporada_id', $temporadaId)->get() as $origen) {
                $claveCliente = $this->clave($origen->cliente);
                if (! isset($clientes[$claveCliente])) {
                    $clientes[$claveCliente] = (string) Str::uuid();
                    DB::table('clientes_validacion')->insert([
                        'id' => $clientes[$claveCliente],
                        'temporada_id' => $temporadaId,
                        'nombre' => $origen->cliente,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }

                $clienteId = $clientes[$claveCliente];
                $claveMarca = $clienteId.'|'.$this->clave($origen->marca);
                if (! isset($marcas[$claveMarca])) {
                    $marcas[$claveMarca] = (string) Str::uuid();
                    DB::table('marcas_validacion')->insert([
                        'id' => $marcas[$claveMarca],
                        'cliente_validacion_id' => $clienteId,
                        'nombre' => $origen->marca,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }

                $claveCsg = $this->clave($origen->csg);
                if (! isset($csg[$claveCsg])) {
                    $csg[$claveCsg] = (string) Str::uuid();
                    DB::table('csg_validacion')->insert([
                        'id' => $csg[$claveCsg],
                        'temporada_id' => $temporadaId,
                        'codigo' => $origen->csg,
                        'predio' => $origen->predio,
                        'activo' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                } elseif ($origen->predio) {
                    DB::table('csg_validacion')
                        ->where('id', $csg[$claveCsg])
                        ->whereNull('predio')
                        ->update(['predio' => $origen->predio, 'updated_at' => $ahora]);
                }

                DB::table('origenes_validacion')->where('id', $origen->id)->update([
                    'cliente_validacion_id' => $clienteId,
                    'marca_validacion_id' => $marcas[$claveMarca],
                    'csg_validacion_id' => $csg[$claveCsg],
                ]);
            }
        }

        $autorizaciones = DB::table('combinaciones_validacion as combinacion')
            ->join('articulos_validacion as articulo', 'articulo.id', '=', 'combinacion.articulo_validacion_id')
            ->join('origenes_validacion as origen', 'origen.id', '=', 'combinacion.origen_validacion_id')
            ->where('combinacion.activo', true)
            ->whereNotNull('articulo.variedad_validacion_id')
            ->whereNotNull('origen.csg_validacion_id')
            ->distinct()
            ->get([
                'origen.csg_validacion_id',
                'articulo.variedad_validacion_id',
            ]);

        foreach ($autorizaciones as $autorizacion) {
            DB::table('csg_variedades_validacion')->insertOrIgnore([
                'csg_validacion_id' => $autorizacion->csg_validacion_id,
                'variedad_validacion_id' => $autorizacion->variedad_validacion_id,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function clave(string $valor): string
    {
        return mb_strtolower(trim($valor));
    }
};
