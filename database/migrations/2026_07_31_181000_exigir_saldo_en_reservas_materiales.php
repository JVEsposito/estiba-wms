<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE reservas_materiales '
            .'MODIFY saldo_material_almacen_id CHAR(36) NOT NULL',
        );
        DB::statement(
            'ALTER TABLE reservas_transformacion_materiales '
            .'MODIFY saldo_material_almacen_id CHAR(36) NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE reservas_transformacion_materiales '
            .'MODIFY saldo_material_almacen_id CHAR(36) NULL',
        );
        DB::statement(
            'ALTER TABLE reservas_materiales '
            .'MODIFY saldo_material_almacen_id CHAR(36) NULL',
        );
    }
};
