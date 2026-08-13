<?php

namespace App\Services\Transiciones;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonException;
use Stringable;

class NormalizadorTransicionOperacional
{
    public function normalizar(mixed $valor): mixed
    {
        if ($valor instanceof BackedEnum) {
            return $valor->value;
        }

        if ($valor instanceof DateTimeInterface) {
            return $valor->format(DATE_ATOM);
        }

        if ($valor instanceof Model) {
            return [
                'modelo' => $valor::class,
                'id' => (string) $valor->getKey(),
            ];
        }

        if ($valor instanceof Collection) {
            return $this->normalizar($valor->all());
        }

        if ($valor instanceof Arrayable) {
            return $this->normalizar($valor->toArray());
        }

        if ($valor instanceof Stringable) {
            return (string) $valor;
        }

        if (! is_array($valor)) {
            return $valor;
        }

        if (array_is_list($valor)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalizar($item),
                $valor,
            );
        }

        ksort($valor, SORT_STRING);

        return array_map(
            fn (mixed $item): mixed => $this->normalizar($item),
            $valor,
        );
    }

    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        try {
            return hash('sha256', json_encode(
                $this->normalizar($payload),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } catch (JsonException $exception) {
            throw new \DomainException(
                'El contenido de la transición operacional no es serializable.',
                previous: $exception,
            );
        }
    }
}
