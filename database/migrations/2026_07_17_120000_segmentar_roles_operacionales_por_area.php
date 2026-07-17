<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->migrarRoles([
            'operador' => 'camarero_frio',
            'supervisor' => 'supervisor_frio',
        ]);
    }

    public function down(): void
    {
        $this->migrarRoles([
            'camarero_frio' => 'operador',
            'supervisor_frio' => 'supervisor',
        ]);
    }

    /**
     * @param  array<string, string>  $transformaciones
     */
    private function migrarRoles(array $transformaciones): void
    {
        DB::transaction(function () use ($transformaciones): void {
            $usuariosMigrados = DB::table('users')
                ->whereIn('rol', array_keys($transformaciones))
                ->pluck('id');

            foreach ($transformaciones as $anterior => $nuevo) {
                DB::table('users')
                    ->where('rol', $anterior)
                    ->update(['rol' => $nuevo, 'updated_at' => now()]);
            }

            if ($usuariosMigrados->isNotEmpty() && Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->whereIn('tokenable_id', $usuariosMigrados)
                    ->delete();
            }
        });
    }
};
