<?php

namespace App\Models\Concerns;

use DomainException;
use Illuminate\Database\Eloquent\Model;

trait ImpideEliminacionFisica
{
    protected static function bootImpideEliminacionFisica(): void
    {
        static::deleting(function (Model $model): never {
            throw new DomainException(sprintf(
                '%s no admite eliminación física; debe cambiarse su estado operacional.',
                class_basename($model),
            ));
        });
    }
}
