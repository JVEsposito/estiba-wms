<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secuencias_documentos', function (Blueprint $table) {
            $table->string('clave', 50)->primary();
            $table->unsignedBigInteger('ultimo_numero')->default(0);
        });

        DB::table('secuencias_documentos')->insert([
            'clave' => 'cargas',
            'ultimo_numero' => 0,
        ]);

        Schema::create('cargas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique();
            $table->string('numero_orden_externa', 100)->nullable()->unique();
            $table->string('estado', 40)->index();
            $table->string('prioridad', 20)->default('normal')->index();
            $table->foreignUuid('camara_objetivo_id')
                ->nullable()
                ->constrained('camaras')
                ->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('creada_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('actualizada_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('publicada_por_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('publicada_at')->nullable()->index();
            $table->foreignId('cancelada_por_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('carga_folios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('carga_id')
                ->constrained('cargas')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')
                ->unique()
                ->constrained('folios')
                ->restrictOnDelete();
            $table->foreignId('asignado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('asignado_at');
            $table->timestamps();

        });

        Schema::create('eventos_carga', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('carga_id')
                ->constrained('cargas')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')
                ->nullable()
                ->constrained('folios')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('tipo', 40)->index();
            $table->json('datos')->nullable();
            $table->timestamps();

            $table->index(['carga_id', 'created_at']);
            $table->index(['folio_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_carga');
        Schema::dropIfExists('carga_folios');
        Schema::dropIfExists('cargas');
        Schema::dropIfExists('secuencias_documentos');
    }
};
