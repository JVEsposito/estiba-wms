<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Todas las temporadas existentes quedan como productivas: la migración
        // no reclasifica ni modifica ningún dato operacional.
        Schema::table('temporadas', function (Blueprint $table): void {
            $table->string('tipo', 20)->default('productiva')->after('activa')->index();
            $table->string('prefijo_documental', 6)->nullable()->unique()->after('tipo');
        });

        // Historial inmutable de cada cambio de tipo, con su motivo.
        Schema::create('clasificaciones_temporada', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('tipo_anterior', 20);
            $table->string('tipo_nuevo', 20);
            $table->text('motivo');
            $table->foreignId('clasificado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('clasificado_at');
            $table->timestamps();
            $table->index(['temporada_id', 'clasificado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clasificaciones_temporada');
        Schema::table('temporadas', function (Blueprint $table): void {
            $table->dropUnique(['prefijo_documental']);
            $table->dropIndex(['tipo']);
            $table->dropColumn(['tipo', 'prefijo_documental']);
        });
    }
};
