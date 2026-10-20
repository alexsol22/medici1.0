<?php

namespace App\Livewire\Concerns;

use App\Models\Cliente;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Builder;

trait ConAccesso
{
    protected function clientiVisibili(): Builder
    {
        return Cliente::visibiliA(auth()->user());
    }

    protected function sediVisibili(): Builder
    {
        return Sede::whereIn('cliente_id', $this->clientiVisibili()->select('clienti.id'));
    }

    protected function soloStaff(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }
}
