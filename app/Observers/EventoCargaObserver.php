<?php

namespace App\Observers;

use App\Events\EventoCargaRegistrado;
use App\Models\EventoCarga;

class EventoCargaObserver
{
    public function created(EventoCarga $evento): void
    {
        EventoCargaRegistrado::dispatch($evento);
    }
}
