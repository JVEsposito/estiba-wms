<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_hidrocooler_materia_prima', function (Blueprint $table): void {
            $table->char('turno', 1)->nullable()->index('hidro_mp_turno_index')->after('equipo');
            $table->unsignedSmallInteger('cantidad_bombas_funcionando')->nullable()->after('turno');
            $table->decimal('cloro_libre_ppm', 6, 2)->nullable()->after('temperatura_agua_inicial_c');
            $table->decimal('ph_agua', 4, 2)->nullable()->after('cloro_libre_ppm');
            $table->string('condicion_visual_agua', 20)->nullable()->after('ph_agua');
            $table->boolean('dosificador_operativo')->nullable()->after('condicion_visual_agua');
            $table->string('manejo_agua', 20)->nullable()->after('dosificador_operativo');
            $table->text('accion_correctiva')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('procesos_hidrocooler_materia_prima', function (Blueprint $table): void {
            $table->dropIndex('hidro_mp_turno_index');
            $table->dropColumn([
                'turno',
                'cantidad_bombas_funcionando',
                'cloro_libre_ppm',
                'ph_agua',
                'condicion_visual_agua',
                'dosificador_operativo',
                'manejo_agua',
                'accion_correctiva',
            ]);
        });
    }
};
