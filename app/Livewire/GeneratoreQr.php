<?php

namespace App\Livewire;

use App\Models\Legacy\Codice;
use App\Models\User;
use App\Services\QrAziendale;
use App\Services\QrSede;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToListContents;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;
use Throwable;

#[Layout('layouts::app')]
#[Title('Generatore QR')]
class GeneratoreQr extends Component
{
    use WithFileUploads, WithPagination;

    private const LIMITE_GIORNO = 200;

    public string $colore = '#000000';

    public string $titolo = '';

    public int $quantita = 1;

    public bool $personalizza = false;

    public $logo;

    /** @var array<int, array{id: int, codice: string}> */
    public array $appenaCreati = [];

    #[Url(except: '')]
    public string $cerca = '';

    #[Url(except: 'tutti')]
    public string $statoFile = 'tutti';

    #[Url(except: 'tutti')]
    public string $assegnazione = 'tutti';

    /** @var array<int, string>|null */
    private ?array $filePresenti = null;

    public function mount(): void
    {
        $this->autorizzaStaff();
    }

    public function generaLiberi(QrSede $qr): void
    {
        $this->autorizzaStaff();
        set_time_limit(120);
        $rimasti = $this->rimastiOggi();
        $dati = $this->validate(
            [
                'quantita' => ['required', 'integer', 'min:1', 'max:'.$rimasti],
                'personalizza' => ['boolean'],
                'colore' => ['exclude_unless:personalizza,true', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'titolo' => ['exclude_unless:personalizza,true', 'nullable', 'string', 'max:40'],
                'logo' => ['exclude_unless:personalizza,true', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            ],
            [
                'quantita.min' => 'Indica da 1 a 200 QR da generare.',
                'quantita.max' => $this->messaggioQuantita($rimasti),
                'titolo.max' => 'Il titolo può avere al massimo 40 caratteri.',
            ],
            [
                'quantita' => 'quantità',
                'titolo' => 'titolo',
                'colore' => 'colore',
                'logo' => 'logo',
            ],
        );

        $personalizza = (bool) $dati['personalizza'];
        $colore = $personalizza ? $dati['colore'] : '#000000';
        $titolo = $personalizza ? trim(preg_replace('/\s+/u', ' ', $dati['titolo'] ?? '') ?? '') : '';
        $contenutoLogo = $personalizza ? $this->logo?->get() : null;
        $this->riserva($dati['quantita']);
        $creati = [];
        $riservati = [];
        $dopo = null;

        try {
            for ($i = 0; $i < $dati['quantita']; $i++) {
                $creati[] = $this->creaCodiceLibero($qr, $riservati, $colore, $contenutoLogo, $titolo, $dopo);
                $ultimo = $creati[array_key_last($creati)]->codice;
                $riservati[] = $ultimo;
                $dopo = $this->numeroCodice($ultimo) ?? $dopo;
            }
        } catch (Throwable $e) {
            $this->annullaCodici($creati, $qr);
            $this->rilascia($dati['quantita']);

            throw $e;
        }

        $this->appenaCreati = array_map(fn (Codice $codice): array => [
            'id' => $codice->id,
            'codice' => $codice->codice,
        ], $creati);

        $this->resetPage();
        $this->filePresenti = null;
        $primo = $creati[0]->codice;
        $ultimo = $creati[array_key_last($creati)]->codice;
        session()->flash('qr', count($creati) === 1
            ? "Creato {$primo}."
            : "Creati da {$primo} a {$ultimo}.");
    }

    public function updatingCerca(): void
    {
        $this->resetPage();
    }

    public function updatingStatoFile(): void
    {
        $this->resetPage();
    }

    public function updatingAssegnazione(): void
    {
        $this->resetPage();
    }

    public function ristampa(int $id, QrSede $qr): void
    {
        $this->autorizzaStaff();
        $dati = $this->validate(
            [
                'personalizza' => ['boolean'],
                'colore' => ['exclude_unless:personalizza,true', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'titolo' => ['exclude_unless:personalizza,true', 'nullable', 'string', 'max:40'],
                'logo' => ['exclude_unless:personalizza,true', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            ],
            [
                'titolo.max' => 'Il titolo può avere al massimo 40 caratteri.',
            ],
            [
                'titolo' => 'titolo',
                'colore' => 'colore',
                'logo' => 'logo',
            ],
        );

        $codice = Codice::query()->findOrFail($id);
        abort_if(blank($codice->codice), 404);

        $personalizza = (bool) $dati['personalizza'];
        $percorso = $qr->percorso($codice->codice);
        $giaStampato = Storage::disk(config('filesystems.files_disk'))->exists($percorso);
        $qr->generaPersonalizzato(
            $codice->codice,
            $personalizza ? $dati['colore'] : '#000000',
            $personalizza ? $this->logo?->get() : null,
            $personalizza ? trim(preg_replace('/\s+/u', ' ', $dati['titolo'] ?? '') ?? '') : '',
        );
        $this->filePresenti = null;

        session()->flash('qr', $giaStampato
            ? "Sostituito il file di {$codice->codice}."
            : "Creato il file di {$codice->codice}.");
    }

    public function render(QrAziendale $generatore)
    {
        $this->autorizzaStaff();
        $prossimo = Codice::prossimoCodice();
        $titolo = $this->personalizza ? trim($this->titolo) : '';
        $colore = $this->personalizza ? $this->colore : '#000000';
        $logoDataUri = $this->personalizza && $this->logo
            ? 'data:'.$this->logo->getMimeType().';base64,'.base64_encode($this->logo->get())
            : null;

        $conFile = $this->codiciConFile();
        $totale = Codice::query()->count();
        $stampati = $conFile === [] ? 0 : Codice::query()->whereIn('codice', $conFile)->count();

        return view('livewire.generatore-qr', [
            'prossimo' => $prossimo,
            'rimasti' => $this->rimastiOggi(),
            'svg' => $prossimo ? $generatore->svg($prossimo, $titolo, $logoDataUri, $colore) : null,
            'codici' => $this->elenco($conFile),
            'filePresenti' => array_fill_keys($conFile, true),
            'totaleCodici' => $totale,
            'codiciStampati' => $stampati,
            'codiciSenzaFile' => $totale - $stampati,
        ]);
    }

    /** @param  array<int, string>  $conFile */
    private function elenco(array $conFile): LengthAwarePaginator
    {
        $cerca = trim($this->cerca);
        $like = '%'.$cerca.'%';
        $statoFile = in_array($this->statoFile, ['stampati', 'assenti'], true) ? $this->statoFile : 'tutti';
        $assegnazione = in_array($this->assegnazione, ['liberi', 'assegnati'], true) ? $this->assegnazione : 'tutti';

        return Codice::query()
            ->with([
                'sede:id,descrizione,id_cliente',
                'sede.cliente:id,ragione_sociale',
                'postazione:id,titolo,id_sede,id_cliente',
                'postazione.cliente:id,ragione_sociale',
                'postazione.sede:id,descrizione,id_cliente',
                'postazione.sede.cliente:id,ragione_sociale',
            ])
            ->when($statoFile === 'stampati', fn ($query) => $query->whereIn('codice', $conFile))
            ->when($statoFile === 'assenti' && $conFile !== [], fn ($query) => $query->whereNotIn('codice', $conFile))
            ->when($assegnazione === 'liberi', fn ($query) => $query->where('stato', Codice::CREATO))
            ->when($assegnazione === 'assegnati', fn ($query) => $query->where('stato', Codice::ATTIVO))
            ->when($cerca !== '', function ($query) use ($like): void {
                $query->where(function ($query) use ($like): void {
                    $query->where('codice', 'like', $like)
                        ->orWhereHas('sede', function ($query) use ($like): void {
                            $query->where('descrizione', 'like', $like)
                                ->orWhereHas('cliente', fn ($query) => $query->where('ragione_sociale', 'like', $like));
                        })
                        ->orWhereHas('postazione', function ($query) use ($like): void {
                            $query->where('titolo', 'like', $like)
                                ->orWhereHas('sede', fn ($query) => $query->where('descrizione', 'like', $like))
                                ->orWhereHas('cliente', fn ($query) => $query->where('ragione_sociale', 'like', $like));
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(25);
    }

    /** @return array<int, string> */
    private function codiciConFile(): array
    {
        if ($this->filePresenti !== null) {
            return $this->filePresenti;
        }

        $disco = Storage::disk(config('filesystems.files_disk'));
        $cartella = trim(config('filesystems.private_paths.documenti'), '/').'/QrcodeText';

        try {
            $percorsi = $disco->files($cartella);
        } catch (UnableToListContents $errore) {
            if ($disco->directoryExists($cartella)) {
                throw $errore;
            }

            $percorsi = [];
        }

        $this->filePresenti = array_map(
            fn (string $percorso): string => pathinfo($percorso, PATHINFO_FILENAME),
            $percorsi,
        );

        return $this->filePresenti;
    }

    private function chiaveGiorno(): string
    {
        return 'generatore-qr.'.now()->toDateString();
    }

    private function generatiOggi(): int
    {
        return (int) Cache::get($this->chiaveGiorno(), 0);
    }

    private function rimastiOggi(): int
    {
        return max(0, self::LIMITE_GIORNO - $this->generatiOggi());
    }

    private function messaggioQuantita(int $rimasti): string
    {
        if ($rimasti === 0) {
            return 'Hai già generato 200 codici oggi.';
        }

        if ($rimasti < self::LIMITE_GIORNO) {
            return "Oggi puoi generarne ancora {$rimasti}.";
        }

        return 'Indica da 1 a 200 QR da generare.';
    }

    private function riserva(int $quantita): void
    {
        $chiave = $this->chiaveGiorno();
        $totale = (int) Cache::increment($chiave, $quantita);
        Cache::put($chiave, $totale, now()->endOfDay());

        if ($totale <= self::LIMITE_GIORNO) {
            return;
        }

        $this->rilascia($quantita);

        throw ValidationException::withMessages([
            'quantita' => $this->messaggioQuantita($this->rimastiOggi()),
        ]);
    }

    private function rilascia(int $quantita): void
    {
        $chiave = $this->chiaveGiorno();
        $totale = max(0, (int) Cache::decrement($chiave, $quantita));
        Cache::put($chiave, $totale, now()->endOfDay());
    }

    private function numeroCodice(string $codice): ?int
    {
        if (preg_match('/^ITALPOL\*(\d{6})$/', $codice, $corrispondenza) !== 1) {
            return null;
        }

        return (int) $corrispondenza[1];
    }

    /** @param  array<int, string>  $riservati */
    private function creaCodiceLibero(QrSede $qr, array $riservati, string $colore, ?string $logo, string $titolo, ?int $dopo = null): Codice
    {
        $stringa = null;

        for ($tentativo = 1; $tentativo <= 5; $tentativo++) {
            $proposta = Codice::prossimoCodice($riservati, $dopo);

            try {
                $qr->genera($proposta, $colore, $logo, $titolo);
                $stringa = $proposta;
                break;
            } catch (RuntimeException $e) {
                if ($tentativo === 5 || ! str_contains($e->getMessage(), 'esiste già')) {
                    throw $e;
                }

                $riservati[] = $proposta;
            }
        }

        if ($stringa === null) {
            throw new RuntimeException('Impossibile generare un codice QR libero.');
        }

        try {
            return Codice::query()->create([
                'codice' => $stringa,
                'stato' => Codice::CREATO,
            ]);
        } catch (Throwable $e) {
            Storage::disk(config('filesystems.files_disk'))->delete($qr->percorso($stringa));

            throw $e;
        }
    }

    /** @param array<int, Codice> $codici */
    private function annullaCodici(array $codici, QrSede $qr): void
    {
        foreach ($codici as $codice) {
            try {
                Storage::disk(config('filesystems.files_disk'))->delete($qr->percorso($codice->codice));
                $codice->delete();
            } catch (Throwable $errorePulizia) {
                report($errorePulizia);
            }
        }
    }

    private function autorizzaStaff(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->isStaff(), 403);
    }
}
