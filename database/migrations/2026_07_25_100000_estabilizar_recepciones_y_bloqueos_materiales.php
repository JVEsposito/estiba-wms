<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_recepciones_materiales', function (Blueprint $table): void {
            $table->decimal('cantidad_contada', 14, 3)
                ->nullable()
                ->after('cantidad_documental');
        });

        DB::table('detalles_recepciones_materiales')->update([
            'cantidad_contada' => DB::raw('cantidad_recibida + cantidad_rechazada'),
        ]);

        Schema::table('detalles_recepciones_materiales', function (Blueprint $table): void {
            $table->decimal('cantidad_contada', 14, 3)
                ->nullable(false)
                ->change();
        });

        Schema::create('eventos_bloqueos_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->string('tipo', 20)->index();
            $table->string('estado_anterior', 32);
            $table->string('estado_resultante', 32);
            $table->text('motivo');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();

            $table->index(
                ['folio_id', 'ocurrido_at'],
                'eventos_bloqueos_materiales_folio_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_bloqueos_materiales');

        Schema::table('detalles_recepciones_materiales', function (Blueprint $table): void {
            $table->dropColumn('cantidad_contada');
        });
    }
};
