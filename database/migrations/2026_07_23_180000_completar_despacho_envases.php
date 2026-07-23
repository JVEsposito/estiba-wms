<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias_despacho_envases', function (Blueprint $table): void {
            $table->string('temporada_codigo_snapshot', 50)->nullable()->after('temporada_id');
            $table->string('temporada_nombre_snapshot', 150)->nullable()->after('temporada_codigo_snapshot');
            $table->string('cliente_codigo_snapshot', 50)->nullable()->after('cliente_id');
            $table->string('cliente_nombre_snapshot', 150)->nullable()->after('cliente_codigo_snapshot');
            $table->foreignId('cancelado_por_user_id')->nullable()->after('anulado_por_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelado_at')->nullable()->after('confirmado_at');
            $table->text('motivo_cancelacion')->nullable()->after('motivo_anulacion');
            $table->json('documento_snapshot')->nullable()->after('motivo_cancelacion');
            $table->char('documento_hash', 64)->nullable()->after('documento_snapshot');
            $table->timestamp('documento_generado_at')->nullable()->after('documento_hash');
        });

        Schema::create('eventos_guias_despacho_envases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('guia_despacho_envase_id');
            $table->foreign('guia_despacho_envase_id', 'evento_guia_envases_guia_fk')
                ->references('id')
                ->on('guias_despacho_envases')
                ->restrictOnDelete();
            $table->string('tipo', 30);
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('ocurrido_at');
            $table->json('datos')->nullable();
            $table->timestamps();
            $table->index(
                ['guia_despacho_envase_id', 'ocurrido_at'],
                'evento_guia_envases_ocurrido_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_guias_despacho_envases');

        Schema::table('guias_despacho_envases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelado_por_user_id');
            $table->dropColumn([
                'temporada_codigo_snapshot',
                'temporada_nombre_snapshot',
                'cliente_codigo_snapshot',
                'cliente_nombre_snapshot',
                'cancelado_at',
                'motivo_cancelacion',
                'documento_snapshot',
                'documento_hash',
                'documento_generado_at',
            ]);
        });
    }
};
