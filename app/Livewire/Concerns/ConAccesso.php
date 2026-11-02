<?php

namespace App\Livewire\Concerns;

use App\Models\Legacy\Cliente;
use App\Models\Legacy\Sede;
use Illuminate\Database\Eloquent\Builder;

trait ConAccesso
{
    protected function clientiVisibili(): Builder
    {
        return Cliente::visibiliA(auth()->user());
    }

    protected function sediVisibili(): Builder
    {
        return Sede::whereIn('id_cliente', $this->clientiVisibili()->select('tc_clienti.id'));
    }

    protected function soloStaff(): void
    {
        abort_unless(auth()->user()->isStaff(), 403);
    }
}
