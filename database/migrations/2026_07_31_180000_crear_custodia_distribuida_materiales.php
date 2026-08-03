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
        Schema::table('destinos_materiales', function (Blueprint $table): void {
            $table->string('codigo', 50)->nullable()->after('id');
            $table->string('tipo', 20)->default('virtual')->after('nombre')->index();
            $table->boolean('requiere_ubicacion_fisica')->default(false)->after('centro_costo');
            $table->unique('codigo', 'destinos_materiales_codigo_unique');
        });

        DB::table('destinos_materiales')
            ->whereNull('codigo')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $destino, int $indice): void {
                DB::table('destinos_materiales')
                    ->where('id', $destino->id)
                    ->update([
                        'codigo' => sprintf('ALM-%05d', $indice + 1),
                        'tipo' => 'virtual',
                        'requiere_ubicacion_fisica' => false,
                    ]);
            });

        Schema::create('saldos_materiales_almacenes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('folio_id')
                ->constrained('folios_materiales', 'folio_id')
                ->cascadeOnDelete();
            $table->foreignUuid('almacen_material_id')
                ->constrained('destinos_materiales')
                ->restrictOnDelete();
            $table->decimal('cantidad_actual', 14, 3)->default(0);
            $table->decimal('cantidad_reservada', 14, 3)->default(0);
            $table->foreignUuid('camara_id')->nullable()->constrained('camaras')->restrictOnDelete();
            $table->foreignUuid('posicion_id')->nullable()->constrained('posiciones')->restrictOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(
                ['folio_id', 'almacen_material_id'],
                'saldo_material_folio_almacen_unique',
            );
            $table->index(
                ['almacen_material_id', 'folio_id'],
                'saldo_material_almacen_folio_idx',
            );
            $table->index(
                ['almacen_material_id', 'cantidad_actual', 'cantidad_reservada'],
                'saldo_material_disponible_idx',
            );
        });

        DB::statement(
            'ALTER TABLE saldos_materiales_almacenes '
            .'ADD CONSTRAINT saldo_material_actual_no_negativo CHECK (cantidad_actual >= 0)',
        );
        DB::statement(
            'ALTER TABLE saldos_materiales_almacenes '
            .'ADD CONSTRAINT saldo_material_reservada_no_negativa CHECK (cantidad_reservada >= 0)',
        );
        DB::statement(
            'ALTER TABLE saldos_materiales_almacenes '
            .'ADD CONSTRAINT saldo_material_reserva_valida '
            .'CHECK (cantidad_reservada <= cantidad_actual)',
        );
        DB::statement(
            'ALTER TABLE saldos_materiales_almacenes '
            .'ADD CONSTRAINT saldo_material_posicion_con_camara '
            .'CHECK (posicion_id IS NULL OR camara_id IS NOT NULL)',
        );

        Schema::table('reservas_materiales', function (Blueprint $table): void {
            $table->uuid('saldo_material_almacen_id')->nullable()->after('folio_id');
            $table->foreign(
                'saldo_material_almacen_id',
                'reservas_materiales_saldo_almacen_fk',
            )
                ->references('id')
                ->on('saldos_materiales_almacenes')
                ->restrictOnDelete();
            $table->index(
                ['saldo_material_almacen_id', 'estado'],
                'reservas_materiales_saldo_estado_idx',
            );
        });

        Schema::table('reservas_transformacion_materiales', function (Blueprint $table): void {
            $table->uuid('saldo_material_almacen_id')->nullable()->after('folio_id');
            $table->foreign(
                'saldo_material_almacen_id',
                'reservas_transformacion_saldo_almacen_fk',
            )
                ->references('id')
                ->on('saldos_materiales_almacenes')
                ->restrictOnDelete();
            $table->index(
                ['saldo_material_almacen_id', 'estado'],
                'reservas_transformacion_saldo_estado_idx',
            );
        });

        Schema::create('movimientos_almacenes_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->index();
            $table->unsignedSmallInteger('secuencia')->default(1);
            $table->char('payload_hash', 64);
            $table->string('tipo', 30)->index();
            $table->foreignUuid('folio_id')
                ->constrained('folios_materiales', 'folio_id')
                ->restrictOnDelete();
            $table->foreignUuid('item_material_id')
                ->constrained('items_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('almacen_origen_id')
                ->nullable()
                ->constrained('destinos_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('almacen_destino_id')
                ->nullable()
                ->constrained('destinos_materiales')
                ->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('saldo_origen_anterior', 14, 3)->nullable();
            $table->decimal('saldo_origen_resultante', 14, 3)->nullable();
            $table->decimal('saldo_destino_anterior', 14, 3)->nullable();
            $table->decimal('saldo_destino_resultante', 14, 3)->nullable();
            $table->string('centro_costo', 100)->nullable()->index();
            $table->text('motivo')->nullable();
            $table->string('documento_relacionado', 150)->nullable();
            $table->foreignUuid('despacho_material_id')
                ->nullable()
                ->constrained('despachos_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('retiro_material_id')
                ->nullable()
                ->constrained('retiros_materiales')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')
                ->nullable()
                ->constrained('dispositivos')
                ->restrictOnDelete();
            $table->json('metadatos')->nullable();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();

            $table->unique(
                ['operacion_id', 'secuencia'],
                'mov_almacen_operacion_secuencia_unique',
            );
            $table->index(
                ['folio_id', 'ocurrido_at'],
                'mov_almacen_folio_fecha_idx',
            );
            $table->index(
                ['almacen_origen_id', 'ocurrido_at'],
                'mov_almacen_origen_fecha_idx',
            );
            $table->index(
                ['almacen_destino_id', 'ocurrido_at'],
                'mov_almacen_destino_fecha_idx',
            );
        });

        $this->migrarSaldosExistentes();
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_almacenes_materiales');

        Schema::table('reservas_transformacion_materiales', function (Blueprint $table): void {
            $table->dropForeign('reservas_transformacion_saldo_almacen_fk');
            $table->dropIndex('reservas_transformacion_saldo_estado_idx');
            $table->dropColumn('saldo_material_almacen_id');
        });

        Schema::table('reservas_materiales', function (Blueprint $table): void {
            $table->dropForeign('reservas_materiales_saldo_almacen_fk');
            $table->dropIndex('reservas_materiales_saldo_estado_idx');
            $table->dropColumn('saldo_material_almacen_id');
        });

        Schema::dropIfExists('saldos_materiales_almacenes');

        Schema::table('destinos_materiales', function (Blueprint $table): void {
            $table->dropUnique('destinos_materiales_codigo_unique');
            $table->dropColumn(['codigo', 'tipo', 'requiere_ubicacion_fisica']);
        });
    }

    private function migrarSaldosExistentes(): void
    {
        $usuarioId = DB::table('users')->orderBy('id')->value('id');

        if ($usuarioId === null) {
            return;
        }

        DB::table('destinos_materiales')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'codigo' => 'BOD-CENTRAL',
            'nombre' => 'Bodega Central de Materiales',
            'tipo' => 'fisica',
            'centro_costo' => 'BODEGA',
            'requiere_ubicacion_fisica' => true,
            'descripcion' => 'Almacén físico principal creado al habilitar custodia distribuida.',
            'origen_sistema' => 'estiba_wms',
            'activo' => true,
            'creado_por_user_id' => $usuarioId,
            'actualizado_por_user_id' => $usuarioId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $almacenId = DB::table('destinos_materiales')
            ->where('codigo', 'BOD-CENTRAL')
            ->value('id');

        if ($almacenId === null) {
            return;
        }

        DB::table('folios_materiales as fm')
            ->leftJoin('ubicaciones_actuales as ua', 'ua.folio_id', '=', 'fm.folio_id')
            ->leftJoin('posiciones as p', 'p.id', '=', 'ua.posicion_id')
            ->select([
                'fm.folio_id',
                'fm.cantidad_actual',
                'fm.cantidad_reservada',
                DB::raw('COALESCE(ua.camara_id, p.camara_id) as camara_id'),
                'ua.posicion_id',
            ])
            ->orderBy('fm.folio_id')
            ->chunk(250, function ($folios) use ($almacenId): void {
                $ahora = now();
                $filas = collect($folios)->map(fn (object $folio): array => [
                    'id' => (string) Str::uuid(),
                    'folio_id' => $folio->folio_id,
                    'almacen_material_id' => $almacenId,
                    'cantidad_actual' => $folio->cantidad_actual,
                    'cantidad_reservada' => $folio->cantidad_reservada,
                    'camara_id' => (float) $folio->cantidad_actual > 0
                        ? $folio->camara_id
                        : null,
                    'posicion_id' => (float) $folio->cantidad_actual > 0
                        ? $folio->posicion_id
                        : null,
                    'version' => 0,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all();

                DB::table('saldos_materiales_almacenes')->insertOrIgnore($filas);
            });

        DB::statement(
            "UPDATE reservas_materiales AS r\n"
            ."JOIN saldos_materiales_almacenes AS s ON s.folio_id = r.folio_id\n"
            ."JOIN destinos_materiales AS a ON a.id = s.almacen_material_id\n"
            ."SET r.saldo_material_almacen_id = s.id\n"
            ."WHERE r.saldo_material_almacen_id IS NULL\n"
            ."AND a.codigo = 'BOD-CENTRAL'",
        );
        DB::statement(
            "UPDATE reservas_transformacion_materiales AS r\n"
            ."JOIN saldos_materiales_almacenes AS s ON s.folio_id = r.folio_id\n"
            ."JOIN destinos_materiales AS a ON a.id = s.almacen_material_id\n"
            ."SET r.saldo_material_almacen_id = s.id\n"
            ."WHERE r.saldo_material_almacen_id IS NULL\n"
            ."AND a.codigo = 'BOD-CENTRAL'",
        );
    }
};
