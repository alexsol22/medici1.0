<?php

namespace App\Livewire;

use App\Models\Impostazione as ModelloImpostazione;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Impostazioni')]
class Impostazioni extends Component
{
    private const ETICHETTA_API_KEY = 'API key';

    private const GRUPPI = [
        'google_maps' => [
            'nome' => 'Google Maps',
            'descrizione' => 'Credenziali per mappe e servizi di geolocalizzazione.',
            'campi' => [
                ['chiave' => 'maps_api_key', 'etichetta' => self::ETICHETTA_API_KEY, 'tipo' => 'password', 'placeholder' => 'AIza…'],
            ],
        ],
        'sms' => [
            'nome' => 'SMS',
            'descrizione' => 'Impostazioni del provider SMS utilizzato per gli invii.',
            'campi' => [
                ['chiave' => 'sms_provider', 'etichetta' => 'Provider', 'tipo' => 'text', 'placeholder' => 'Nome del servizio SMS'],
                ['chiave' => 'sms_api_key', 'etichetta' => self::ETICHETTA_API_KEY, 'tipo' => 'password', 'placeholder' => 'Chiave API'],
                ['chiave' => 'sms_sender_id', 'etichetta' => 'Mittente (Sender ID)', 'tipo' => 'text', 'placeholder' => 'Security'],
                ['chiave' => 'sms_endpoint', 'etichetta' => 'Endpoint API', 'tipo' => 'url', 'placeholder' => 'https://api.example.com'],
            ],
        ],
        'whatsapp' => [
            'nome' => 'WhatsApp',
            'descrizione' => 'Credenziali WhatsApp Business Platform.',
            'campi' => [
                ['chiave' => 'whatsapp_access_token', 'etichetta' => 'Access token', 'tipo' => 'password', 'placeholder' => 'Token di accesso'],
                ['chiave' => 'whatsapp_phone_number_id', 'etichetta' => 'Phone number ID', 'tipo' => 'text', 'placeholder' => 'ID del numero WhatsApp'],
                ['chiave' => 'whatsapp_business_account_id', 'etichetta' => 'Business account ID', 'tipo' => 'text', 'placeholder' => 'ID dell’account business'],
            ],
        ],
        'telegram' => [
            'nome' => 'Telegram',
            'descrizione' => 'Bot Telegram e chat di destinazione.',
            'campi' => [
                ['chiave' => 'telegram_bot_token', 'etichetta' => 'Token del bot', 'tipo' => 'password', 'placeholder' => 'Token fornito da BotFather'],
                ['chiave' => 'telegram_chat_id', 'etichetta' => 'Chat ID', 'tipo' => 'text', 'placeholder' => 'ID chat o canale'],
            ],
        ],
        'openai' => [
            'nome' => 'OpenAI',
            'descrizione' => 'Chiave API e modello da usare per i servizi OpenAI.',
            'campi' => [
                ['chiave' => 'openai_api_key', 'etichetta' => self::ETICHETTA_API_KEY, 'tipo' => 'password', 'placeholder' => 'sk-…'],
                ['chiave' => 'openai_model', 'etichetta' => 'Modello', 'tipo' => 'text', 'placeholder' => 'gpt-4o-mini'],
            ],
        ],
        'altro' => [
            'nome' => 'Altra integrazione',
            'descrizione' => 'Aggiungi i dati di un servizio non elencato.',
            'campi' => [
                ['chiave' => 'custom_name', 'etichetta' => 'Nome servizio', 'tipo' => 'text', 'placeholder' => 'Nome dell’integrazione'],
                ['chiave' => 'custom_api_key', 'etichetta' => self::ETICHETTA_API_KEY, 'tipo' => 'password', 'placeholder' => 'Chiave API'],
                ['chiave' => 'custom_endpoint', 'etichetta' => 'Endpoint API', 'tipo' => 'url', 'placeholder' => 'https://api.example.com'],
            ],
        ],
    ];

    /** @var array<string, string> */
    public array $dati = [];

    public function mount(): void
    {
        $this->autorizza();

        foreach (self::GRUPPI as $gruppo) {
            foreach ($gruppo['campi'] as $campo) {
                $this->dati[$campo['chiave']] = '';
            }
        }

        $this->caricaValoriNonSegreti();
    }

    public function salva(string $id): void
    {
        $this->autorizza();
        $gruppo = $this->gruppo($id);
        $rules = [];

        foreach ($gruppo['campi'] as $campo) {
            $rules['dati.'.$campo['chiave']] = $campo['tipo'] === 'url'
                ? ['nullable', 'url', 'max:2048']
                : ['nullable', 'string', 'max:5000'];
        }

        $validati = $this->validate($rules)['dati'] ?? [];

        DB::transaction(function () use ($gruppo, $validati): void {
            foreach ($gruppo['campi'] as $campo) {
                $valore = trim($validati[$campo['chiave']] ?? '');

                if ($valore !== '') {
                    ModelloImpostazione::updateOrCreate(
                        ['chiave' => $campo['chiave']],
                        ['valore' => $valore],
                    );
                }
            }
        });

        foreach ($gruppo['campi'] as $campo) {
            $this->dati[$campo['chiave']] = '';
        }
        $this->caricaValoriNonSegreti();

        session()->flash('impostazioni', 'Configurazione '.$gruppo['nome'].' salvata.');
    }

    public function rimuovi(string $id): void
    {
        $this->autorizza();
        $gruppo = $this->gruppo($id);
        $chiavi = array_column($gruppo['campi'], 'chiave');

        ModelloImpostazione::query()->whereIn('chiave', $chiavi)->delete();

        foreach ($gruppo['campi'] as $campo) {
            $this->dati[$campo['chiave']] = '';
        }

        session()->flash('impostazioni', 'Configurazione '.$gruppo['nome'].' rimossa.');
    }

    private function autorizza(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->isStaff(), 403);
    }

    private function caricaValoriNonSegreti(): void
    {
        $campi = collect(self::GRUPPI)
            ->flatMap(fn (array $gruppo): array => $gruppo['campi'])
            ->reject(fn (array $campo): bool => $campo['tipo'] === 'password');
        $valori = ModelloImpostazione::query()
            ->whereIn('chiave', $campi->pluck('chiave'))
            ->get()
            ->keyBy('chiave');

        foreach ($campi as $campo) {
            $this->dati[$campo['chiave']] = $valori->get($campo['chiave'])?->valore ?? '';
        }
    }

    /**
     * @return array{nome: string, descrizione: string, campi: list<array{chiave: string, etichetta: string, tipo: string, placeholder: string}>}
     */
    private function gruppo(string $id): array
    {
        abort_unless(isset(self::GRUPPI[$id]), 404);

        return self::GRUPPI[$id];
    }

    public function render()
    {
        $chiaviSalvate = ModelloImpostazione::query()
            ->whereIn('chiave', collect(self::GRUPPI)->flatMap(fn (array $gruppo): array => array_column($gruppo['campi'], 'chiave')))
            ->pluck('chiave')
            ->all();

        $gruppi = collect(self::GRUPPI)
            ->map(fn (array $gruppo): array => $gruppo + [
                'configurata' => collect(array_column($gruppo['campi'], 'chiave'))
                    ->intersect($chiaviSalvate)
                    ->isNotEmpty(),
            ])
            ->all();

        return view('livewire.impostazioni', ['gruppi' => $gruppi]);
    }
}
