<?php

use App\Enums\ContenidoCamara;
use App\Enums\ModoBandaOperacional;
use App\Enums\UsoBandaOperacional;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandas_operacionales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('camara_id')->constrained('camaras')->restrictOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->json('usos_permitidos');
            $table->string('modo', 30)->default(ModoBandaOperacional::Operativa->value);
            $table->text('motivo_estado')->nullable();
            $table->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['camara_id', 'numero'], 'bandas_operacionales_camara_numero_unique');
            $table->index(['camara_id', 'modo'], 'bandas_operacionales_camara_modo_idx');
        });

        $ahora = now();
        $usos = json_encode(UsoBandaOperacional::valores(), JSON_THROW_ON_ERROR);

        DB::table('camaras')
            ->where('contenido', ContenidoCamara::Productos->value)
            ->select(['id', 'cantidad_bandas'])
            ->orderBy('id')
            ->each(function (object $camara) use ($ahora, $usos): void {
                $bandas = [];

                for ($numero = 1; $numero <= (int) $camara->cantidad_bandas; $numero++) {
                    $bandas[] = [
                        'id' => (string) Str::uuid(),
                        'camara_id' => $camara->id,
                        'numero' => $numero,
                        'usos_permitidos' => $usos,
                        'modo' => ModoBandaOperacional::Operativa->value,
                        'version' => 1,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                foreach (array_chunk($bandas, 250) as $lote) {
                    DB::table('bandas_operacionales')->insert($lote);
                }

                DB::table('camaras')
                    ->where('id', $camara->id)
                    ->increment('version_plano');
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('bandas_operacionales');
    }
};
