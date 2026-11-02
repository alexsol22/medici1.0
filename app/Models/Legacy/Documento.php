<?php

namespace App\Models\Legacy;

use App\Casts\YesNo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Documento extends LegacyModel
{
    protected $table = 'tc_documenti';

    protected function casts(): array
    {
        return [
            'data_documento' => 'datetime',
            'inizio' => 'date',
            'fine' => 'date',
            'scadenza' => 'date',
            'admin' => YesNo::class,
            'formazione' => YesNo::class,
        ];
    }

    public function visualizzazioni(): HasMany
    {
        return $this->hasMany(DocumentoView::class, 'id_documento');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_tipo');
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_tipo');
    }

    public function certificato(): BelongsTo
    {
        return $this->belongsTo(Certificato::class, 'tipologia');
    }

    public function visualizzazione(): HasOne
    {
        return $this->hasOne(DocumentoView::class, 'id_documento');
    }

    /** Cartella dei file come in Yii: protected/documenti/{Cliente_|Operatore_}{id_tipo}. */
    public function cartella(): string
    {
        return ($this->tipo === 'C' ? 'Cliente_' : 'Operatore_').$this->id_tipo;
    }

    public function percorso(): string
    {
        return rtrim(config('filesystems.documenti_path'), '/').'/'.$this->cartella().'/'.$this->file;
    }

    public function inScadenza(): bool
    {
        return $this->scadenza !== null && $this->scadenza->lte(today()->addDays(30));
    }
}
