<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hallazgos_integridad', function (Blueprint $table): void {
            $table->index(
                ['activo', 'ultima_auditoria_id'],
                'hallazgos_integridad_revision_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('hallazgos_integridad', function (Blueprint $table): void {
            $table->dropIndex('hallazgos_integridad_revision_idx');
        });
    }
};
