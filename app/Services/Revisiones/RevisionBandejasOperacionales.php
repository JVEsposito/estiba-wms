<?php

namespace App\Services\Revisiones;

use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class RevisionBandejasOperacionales
{
    public const PREFRIO = 'prefrio';

    public const CARGAS = 'cargas';

    private const PREFIJO = 'bandejas-operacionales:revision:';

    public function obtener(string $bandeja): string
    {
        $this->asegurarBandeja($bandeja);

        return (string) Cache::rememberForever(
            self::PREFIJO.$bandeja,
            fn (): string => (string) Str::uuid(),
        );
    }

    public function invalidar(string ...$bandejas): void
    {
        foreach (array_unique($bandejas) as $bandeja) {
            $this->asegurarBandeja($bandeja);
            Cache::forget(self::PREFIJO.$bandeja);
        }
    }

    private function asegurarBandeja(string $bandeja): void
    {
        if (! in_array($bandeja, [self::PREFRIO, self::CARGAS], true)) {
            throw new InvalidArgumentException("La bandeja operacional {$bandeja} no existe.");
        }
    }
}
