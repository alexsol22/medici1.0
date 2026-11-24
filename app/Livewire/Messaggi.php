<?php

namespace App\Livewire;

use App\Models\Legacy\Admin;
use App\Models\Legacy\Email;
use App\Models\Legacy\SendSms;
use App\Models\Legacy\SendWhatsapp;
use App\Models\Legacy\Tracking;
use App\Services\Sms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Messaggi')]
class Messaggi extends Component
{
    use WithPagination;

    public string $canale = 'sms';

    public string $filtroOperatore = '';

    public string $filtroDal = '';

    public string $filtroAl = '';

    public bool $modalEmail = false;

    public ?string $emailVisualizzazione = null;

    public function mount(?string $canale = null): void
    {
        abort_unless(auth()->user()->isStaff(), 403);

        if ($canale !== null) {
            abort_unless(in_array($canale, ['sms', 'email', 'whatsapp'], true), 404);

            $this->canale = $canale;
        }
    }

    public function updatedFiltroOperatore(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroDal(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroAl(): void
    {
        $this->resetPage();
    }

    public function azzeraFiltri(): void
    {
        $this->filtroOperatore = '';
        $this->filtroDal = '';
        $this->filtroAl = '';
        $this->resetValidation();
        $this->resetPage();
    }

    public function visualizzaEmail(int $id): void
    {
        abort_unless(auth()->user()->isStaff(), 403);

        $email = Email::query()->findOrFail($id);
        Tracking::registra('Visualizzazione dettaglio  email  '.$email->oggetto);
        $policy = $this->policyArchivioEmail();

        $this->emailVisualizzazione = '<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="'.$policy.'"></head><body>'.$email->email.'</body></html>';
        $this->modalEmail = true;
    }

    /**
     * Le mail già archiviate usano data URI oppure l'URL pubblico del logo.
     * L'origine dell'app è l'unica immagine remota ammessa.
     */
    private function policyArchivioEmail(): string
    {
        $immagini = 'data: cid:';
        $origine = $this->origineApplicazione();

        if ($origine !== null) {
            $immagini .= ' '.$origine;
        }

        return "default-src 'none'; img-src {$immagini}; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none'";
    }

    private function origineApplicazione(): ?string
    {
        $parti = parse_url((string) config('app.url'));
        $schema = $parti['scheme'] ?? '';
        $host = $parti['host'] ?? '';

        if ($schema === '' || $host === '') {
            return null;
        }

        $porta = isset($parti['port']) ? ':'.$parti['port'] : '';

        return $schema.'://'.$host.$porta;
    }

    public function chiudiEmail(): void
    {
        $this->modalEmail = false;
        $this->emailVisualizzazione = null;
    }

    public function render()
    {
        $this->validate([
            'filtroOperatore' => ['nullable', 'integer'],
            'filtroDal' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'filtroAl' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:filtroDal'],
        ]);

        $operatori = Admin::query()
            ->where('user_type', 4)
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get(['id', 'nome', 'cognome']);

        $dati = match (true) {
            $this->canale === 'sms' => [
                'smsInviati' => $this->applicaFiltriInvii(SendSms::query())
                    ->with([
                        'operatore:id,nome,cognome',
                        'risultati:id,send_id,adl_id,costo,response',
                        'risultati.consegne:id,refer_id,delivery_status,delivery_data',
                    ])
                    ->orderByDesc('data_invio')
                    ->paginate(25),
                'crediti' => app(Sms::class)->crediti(),
            ],
            $this->canale === 'email' => [
                'emailInviate' => $this->applicaFiltriInvii(Email::query())
                    ->with('operatore:id,nome,cognome')
                    ->orderByDesc('data_invio')
                    ->paginate(25),
            ],
            $this->canale === 'whatsapp' => [
                'whatsappInviati' => $this->applicaFiltriInvii(SendWhatsapp::query())
                    ->with('operatore:id,nome,cognome')
                    ->orderByDesc('data_invio')
                    ->orderByDesc('id')
                    ->paginate(25),
            ],
            default => [],
        };

        return view('livewire.messaggi', [
            ...$dati,
            'operatori' => $operatori,
        ]);
    }

    private function applicaFiltriInvii(Builder $query): Builder
    {
        return $query
            ->when($this->filtroOperatore !== '', fn (Builder $builder) => $builder->where('id_operatore', $this->filtroOperatore))
            ->when($this->filtroDal !== '', fn (Builder $builder) => $builder->where('data_invio', '>=', $this->dataOraSql($this->filtroDal)))
            ->when($this->filtroAl !== '', fn (Builder $builder) => $builder->where('data_invio', '<', $this->dataOraSql($this->filtroAl, true)));
    }

    private function dataOraSql(string $dataOra, bool $esclusiva = false): string
    {
        $data = CarbonImmutable::parse($dataOra);

        if ($esclusiva) {
            $data = $data->addMinute();
        }

        return $data->format('Y-m-d H:i:s');
    }
}
