<?php

namespace App\Console\Commands;

use App\Models\Legacy\Codice;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pulizia anagrafiche di produzione. Le tabelle legacy sono MyISAM: niente transazione.
 * I turni restano se il cliente oppure l'operatore è tra quelli da tenere.
 */
class PulisciAnagrafiche extends Command
{
    /** @var list<string> */
    private const CLIENTI = [
        'Ditta di Prova  Campori',
        'Prova',
        'Prova 2',
    ];

    /** @var list<string> */
    private const OPERATORI = [
        'alesolax55@gmail.com',
        'sandrabolt69@gmail.com',
    ];

    protected $signature = 'app:pulisci-anagrafiche {--esegui : Elimina i record. Senza questa opzione mostra solo il riepilogo}';

    protected $description = 'Elimina clienti e operatori, con i record collegati, tenendo le anagrafiche indicate e i turni di chi resta';

    public function handle(): int
    {
        $clienti = $this->risolviClienti();
        $operatori = $this->risolviOperatori();

        if ($clienti === null || $operatori === null) {
            $this->warn('Nessuna eliminazione eseguita.');

            return self::FAILURE;
        }

        $clientiTenuti = array_column($clienti, 'id');
        $operatoriTenuti = array_column($operatori, 'id');

        if (count($clientiTenuti) !== count(self::CLIENTI) || count($operatoriTenuti) !== count(self::OPERATORI)) {
            $this->error('Anagrafiche da tenere incomplete.');
            $this->warn('Nessuna eliminazione eseguita.');

            return self::FAILURE;
        }

        $this->info('Clienti che restano:');
        foreach ($clienti as $cliente) {
            $this->line('  '.$cliente['id'].'  '.$cliente['etichetta']);
        }

        $this->info('Operatori che restano:');
        foreach ($operatori as $operatore) {
            $this->line('  '.$operatore['id'].'  '.$operatore['etichetta']);
        }

        $this->warn('I turni restano se il cliente o l\'operatore è tra quelli sopra. Gli altri turni, e i record collegati a chi viene eliminato, no.');

        $esegui = (bool) $this->option('esegui');
        $staff = (int) $this->db()->table('tc_admin')->whereIn('user_type', [1, 2])->count();
        $turniTenuti = $this->contaTurniTenuti($clientiTenuti, $operatoriTenuti);
        $righe = [];
        $fileEliminati = false;

        foreach ($this->passi($clientiTenuti, $operatoriTenuti) as $passo) {
            if ($esegui && ! $fileEliminati && $passo['anagrafica']) {
                $this->eliminaFile($clientiTenuti, $operatoriTenuti);
                $fileEliminati = true;
            }

            $righe[] = [$passo['etichetta'], (string) $this->applica($passo['tabella'], $passo['vincolo'], $esegui, $passo['valori'] ?? null)];
        }

        $this->table(['Record', $esegui ? 'Elaborati' : 'Da elaborare'], $righe);

        if (! $esegui) {
            $this->newLine();
            $this->warn('Nessun record eliminato. Controlla il riepilogo, fai un backup, poi esegui:');
            $this->line('php artisan app:pulisci-anagrafiche --esegui');

            return self::SUCCESS;
        }

        if (! $this->verifica($clientiTenuti, $operatoriTenuti, $turniTenuti, $staff)) {
            $this->error('Verifica fallita. Le tabelle legacy non sono transazionali: controlla il database e, se serve, ripristina il backup.');

            return self::FAILURE;
        }

        $this->info('Eliminazione completata.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{id: int, etichetta: string}>|null
     */
    private function risolviClienti(): ?array
    {
        $clienti = [];

        foreach (self::CLIENTI as $nome) {
            $trovati = $this->db()->table('tc_clienti')
                ->whereRaw('TRIM(ragione_sociale) = ?', [$nome])
                ->get(['id', 'ragione_sociale']);

            if ($trovati->count() !== 1) {
                $this->error($trovati->isEmpty()
                    ? "Nessun cliente con ragione sociale «{$nome}»."
                    : "Trovati {$trovati->count()} clienti con ragione sociale «{$nome}».");

                $simili = $this->db()->table('tc_clienti')
                    ->where('ragione_sociale', 'like', '%'.addcslashes($nome, '%_\\').'%')
                    ->orderBy('ragione_sociale')
                    ->limit(15)
                    ->pluck('ragione_sociale');

                if ($simili->isNotEmpty()) {
                    $this->line('Nomi presenti: '.$simili->implode(' | '));
                }

                return null;
            }

            $clienti[] = [
                'id' => (int) $trovati->first()->id,
                'etichetta' => (string) $trovati->first()->ragione_sociale,
            ];
        }

        return $clienti;
    }

    /**
     * @return list<array{id: int, etichetta: string}>|null
     */
    private function risolviOperatori(): ?array
    {
        $operatori = [];

        foreach (self::OPERATORI as $email) {
            $normalizzata = mb_strtolower(trim($email));
            $trovati = $this->db()->table('tc_admin')
                ->where('user_type', 4)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizzata])
                ->get(['id', 'email', 'nome', 'cognome']);

            if ($trovati->count() !== 1) {
                $this->error($trovati->isEmpty()
                    ? "Nessun operatore con email «{$email}»."
                    : "Trovati {$trovati->count()} operatori con email «{$email}».");

                $altri = $this->db()->table('tc_admin')
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizzata])
                    ->get(['id', 'user_type', 'email']);

                if ($altri->isNotEmpty()) {
                    $this->line('La email è presente, ma non su un solo operatore (user_type 4): '.$altri->map(fn ($riga) => '#'.$riga->id.' tipo '.$riga->user_type)->implode(' | '));
                }

                return null;
            }

            $riga = $trovati->first();
            $operatori[] = [
                'id' => (int) $riga->id,
                'etichetta' => trim($riga->nome.' '.$riga->cognome).'  '.$riga->email,
            ];
        }

        return $operatori;
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     * @return list<array{etichetta: string, tabella: string, anagrafica: bool, vincolo: callable, valori?: array<string, mixed>}>
     */
    private function passi(array $clientiTenuti, array $operatoriTenuti): array
    {
        return [
            $this->passo('Presenze', 'tc_gda', fn (Builder $query) => $this->vincoloPresenzeDaEliminare($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Ritardi', 'tc_ritardi', fn (Builder $query) => $this->vincoloRitardiDaEliminare($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Viste documenti', 'tc_documenti_view', fn (Builder $query) => $this->vincoloVisteDocumenti($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Documenti', 'tc_documenti', fn (Builder $query) => $this->vincoloDocumentiDaEliminare($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Esiti SMS', 'tc_send_sms_result', fn (Builder $query) => $this->vincoloInviiSms($query, $operatoriTenuti, 'tc_send_sms')),
            $this->passo('Consegne SMS', 'tc_send_sms_delivery', fn (Builder $query) => $this->vincoloInviiSms($query, $operatoriTenuti, 'tc_send_sms')),
            $this->passo('SMS', 'tc_send_sms', fn (Builder $query) => $this->vincoloPerOperatoreEliminato($query, $operatoriTenuti)),
            $this->passo('WhatsApp', 'tc_send_whatsapp', fn (Builder $query) => $this->vincoloPerOperatoreEliminato($query, $operatoriTenuti)),
            $this->passo('Coda email', 'tc_send_email', fn (Builder $query) => $this->vincoloPerOperatoreEliminato($query, $operatoriTenuti)),
            $this->passo('Email inviate', 'tc_email', fn (Builder $query) => $this->vincoloPerOperatoreEliminato($query, $operatoriTenuti)),
            $this->passo('Viste bacheca', 'tc_bacheca_view', fn (Builder $query) => $this->vincoloVisteBacheca($query, $operatoriTenuti)),
            $this->passo('Bacheca', 'tc_bacheca', fn (Builder $query) => $this->vincoloPerOperatoreEliminato($query, $operatoriTenuti)),
            $this->passo('Materiali', 'tc_materiali', fn (Builder $query) => $this->vincoloPerOperatoreEliminato($query, $operatoriTenuti)),
            $this->passo('Operatori preferiti', 'tc_operatori_preferiti', fn (Builder $query) => $this->vincoloLegami($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Operatori esclusi', 'tc_operatori_escludi', fn (Builder $query) => $this->vincoloLegami($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Step turni', 'tc_turni_step', fn (Builder $query) => $this->vincoloPerCliente($query, $clientiTenuti)),
            $this->passo('Codici QR da sganciare', 'tc_codici', fn (Builder $query) => $this->vincoloCodici($query, $clientiTenuti), [
                'stato' => Codice::CREATO,
                'tipo' => null,
                'id_sede' => null,
                'id_postazione' => null,
            ]),
            $this->passo('Postazioni', 'tc_postazioni', fn (Builder $query) => $this->vincoloPerCliente($query, $clientiTenuti)),
            $this->passo('Sedi', 'tc_sedi', fn (Builder $query) => $this->vincoloPerCliente($query, $clientiTenuti)),
            $this->passo('Turni', 'tc_turni', fn (Builder $query) => $this->vincoloTurniDaEliminare($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Tracking', '_tracking', fn (Builder $query) => $this->vincoloTracking($query, $clientiTenuti, $operatoriTenuti)),
            $this->passo('Account clienti', 'tc_admin', fn (Builder $query) => $this->vincoloAccountClientiDaEliminare($query, $clientiTenuti), anagrafica: true),
            $this->passo('Operatori', 'tc_admin', fn (Builder $query) => $this->vincoloOperatoriDaEliminare($query, $operatoriTenuti), anagrafica: true),
            $this->passo('Clienti', 'tc_clienti', fn (Builder $query) => $query->whereNotIn('id', $clientiTenuti), anagrafica: true),
        ];
    }

    /**
     * @param  callable(Builder): void  $vincolo
     * @param  array<string, mixed>|null  $valori
     * @return array{etichetta: string, tabella: string, anagrafica: bool, vincolo: callable, valori?: array<string, mixed>}
     */
    private function passo(string $etichetta, string $tabella, callable $vincolo, ?array $valori = null, bool $anagrafica = false): array
    {
        $passo = [
            'etichetta' => $etichetta,
            'tabella' => $tabella,
            'anagrafica' => $anagrafica,
            'vincolo' => $vincolo,
        ];

        if ($valori !== null) {
            $passo['valori'] = $valori;
        }

        return $passo;
    }

    /**
     * @param  callable(Builder): void  $vincolo
     * @param  array<string, mixed>|null  $valori
     */
    private function applica(string $tabella, callable $vincolo, bool $esegui, ?array $valori = null): int
    {
        if (! $esegui) {
            $query = $this->db()->table($tabella);
            $vincolo($query);

            return (int) $query->count();
        }

        $elaborati = 0;

        do {
            $query = $this->db()->table($tabella);
            $vincolo($query);
            $ids = $query->orderBy('id')->limit(400)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $azione = $this->db()->table($tabella)->whereIn('id', $ids->all());
            $quantita = $valori === null ? $azione->delete() : $azione->update($valori);

            if ($quantita === 0) {
                break;
            }

            $elaborati += $quantita;
        } while ($elaborati < 10000000);

        return $elaborati;
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function eliminaFile(array $clientiTenuti, array $operatoriTenuti): void
    {
        $disco = Storage::disk(config('filesystems.files_disk'));
        $documenti = trim((string) config('filesystems.private_paths.documenti'), '/');
        $avatars = trim((string) config('filesystems.private_paths.avatars'), '/');

        $clienti = $this->db()->table('tc_clienti')->whereNotIn('id', $clientiTenuti);
        $clienti->chunkById(200, function ($righe) use ($disco, $documenti): void {
            foreach ($righe as $riga) {
                $disco->deleteDirectory($documenti.'/Cliente_'.$riga->id);
            }
        });

        $operatori = $this->db()->table('tc_admin');
        $this->vincoloOperatoriDaEliminare($operatori, $operatoriTenuti);
        $operatori->chunkById(200, function ($righe) use ($disco, $documenti, $avatars): void {
            foreach ($righe as $riga) {
                $disco->deleteDirectory($documenti.'/Operatore_'.$riga->id);
                $avatar = basename((string) ($riga->avatar ?? ''));

                if ($avatar !== '' && $avatar !== '.' && $avatar !== '..') {
                    $disco->delete($avatars.'/'.$avatar);
                }
            }
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function verifica(array $clientiTenuti, array $operatoriTenuti, int $turniTenuti, int $staff): bool
    {
        $clientiRimasti = $this->db()->table('tc_clienti')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $operatoriRimasti = $this->db()->table('tc_admin')->where('user_type', 4)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $attesiClienti = $clientiTenuti;
        $attesiOperatori = $operatoriTenuti;
        sort($attesiClienti);
        sort($attesiOperatori);

        $turniRimasti = (int) $this->db()->table('tc_turni')->count();
        $staffRimasto = (int) $this->db()->table('tc_admin')->whereIn('user_type', [1, 2])->count();
        $accountFuori = (int) $this->db()->table('tc_admin')->where('user_type', 3)->where(function (Builder $query) use ($clientiTenuti): void {
            $query->whereNull('id_cliente')->orWhereNotIn('id_cliente', $clientiTenuti);
        })->count();

        if ($clientiRimasti !== $attesiClienti) {
            $this->error('I clienti rimasti non coincidono con quelli da tenere.');

            return false;
        }

        if ($operatoriRimasti !== $attesiOperatori) {
            $this->error('Gli operatori rimasti non coincidono con quelli da tenere.');

            return false;
        }

        if ($turniRimasti !== $turniTenuti || $this->contaTurniTenuti($clientiTenuti, $operatoriTenuti) !== $turniTenuti) {
            $this->error('I turni rimasti non coincidono con quelli dei clienti e degli operatori tenuti.');

            return false;
        }

        if ($staffRimasto !== $staff || $accountFuori !== 0) {
            $this->error('Sono stati toccati account che dovevano restare, oppure è rimasto un account cliente da eliminare.');

            return false;
        }

        return true;
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function contaTurniTenuti(array $clientiTenuti, array $operatoriTenuti): int
    {
        return (int) $this->db()->table('tc_turni')->where(function (Builder $query) use ($clientiTenuti, $operatoriTenuti): void {
            $query->whereIn('id_cliente', $clientiTenuti)->orWhereIn('id_operatore', $operatoriTenuti);
        })->count();
    }

    /**
     * @param  list<int>  $clientiTenuti
     */
    private function vincoloPerCliente(Builder $query, array $clientiTenuti, string $colonna = 'id_cliente'): void
    {
        $query->where(function (Builder $filtro) use ($clientiTenuti, $colonna): void {
            $filtro->whereNull($colonna)->orWhereNotIn($colonna, $clientiTenuti);
        });
    }

    /**
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloOperatoriDaEliminare(Builder $query, array $operatoriTenuti): void
    {
        $query->where('user_type', 4)->whereNotIn('id', $operatoriTenuti);
    }

    /**
     * @param  list<int>  $clientiTenuti
     */
    private function vincoloAccountClientiDaEliminare(Builder $query, array $clientiTenuti): void
    {
        $query->where('user_type', 3)->where(function (Builder $filtro) use ($clientiTenuti): void {
            $filtro->whereNull('id_cliente')->orWhereNotIn('id_cliente', $clientiTenuti);
        });
    }

    /**
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloPerOperatoreEliminato(Builder $query, array $operatoriTenuti, string $colonna = 'id_operatore'): void
    {
        $query->whereIn($colonna, function (Builder $sub) use ($operatoriTenuti): void {
            $sub->select('id')->from('tc_admin');
            $this->vincoloOperatoriDaEliminare($sub, $operatoriTenuti);
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloTurniDaEliminare(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $this->vincoloPerCliente($query, $clientiTenuti);
        $query->where(function (Builder $filtro) use ($operatoriTenuti): void {
            $filtro->whereNull('id_operatore')->orWhereNotIn('id_operatore', $operatoriTenuti);
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloPresenzeDaEliminare(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $this->vincoloPerCliente($query, $clientiTenuti);
        $query->where(function (Builder $filtro) use ($operatoriTenuti): void {
            $filtro->whereNull('id_operatore')->orWhereNotIn('id_operatore', $operatoriTenuti);
        })->where(function (Builder $filtro) use ($clientiTenuti, $operatoriTenuti): void {
            $filtro->whereNull('id_turno')->orWhereNotIn('id_turno', function (Builder $sub) use ($clientiTenuti, $operatoriTenuti): void {
                $this->subqueryTurniTenuti($sub, $clientiTenuti, $operatoriTenuti);
            });
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloRitardiDaEliminare(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $query->where(function (Builder $filtro) use ($operatoriTenuti): void {
            $filtro->whereNull('id_operatore')->orWhereNotIn('id_operatore', $operatoriTenuti);
        })->where(function (Builder $filtro) use ($clientiTenuti, $operatoriTenuti): void {
            $filtro->whereNull('id_turno')->orWhereNotIn('id_turno', function (Builder $sub) use ($clientiTenuti, $operatoriTenuti): void {
                $this->subqueryTurniTenuti($sub, $clientiTenuti, $operatoriTenuti);
            });
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function subqueryTurniTenuti(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $query->select('id')->from('tc_turni')->where(function (Builder $turni) use ($clientiTenuti, $operatoriTenuti): void {
            $turni->whereIn('id_cliente', $clientiTenuti)->orWhereIn('id_operatore', $operatoriTenuti);
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloDocumentiDaEliminare(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $query->where(function (Builder $filtro) use ($clientiTenuti, $operatoriTenuti): void {
            $filtro->where(function (Builder $clienti) use ($clientiTenuti): void {
                $clienti->where('tipo', 'C');
                $this->vincoloPerCliente($clienti, $clientiTenuti, 'id_tipo');
            })->orWhere(function (Builder $operatori) use ($operatoriTenuti): void {
                $operatori->where('tipo', 'O');
                $this->vincoloPerOperatoreEliminato($operatori, $operatoriTenuti, 'id_tipo');
            })->orWhere(function (Builder $sedi) use ($clientiTenuti): void {
                $sedi->where('tipo', 'S')->whereIn('id_tipo', function (Builder $sub) use ($clientiTenuti): void {
                    $sub->select('id')->from('tc_sedi');
                    $this->vincoloPerCliente($sub, $clientiTenuti);
                });
            });
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloVisteDocumenti(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $query->where(function (Builder $filtro) use ($clientiTenuti, $operatoriTenuti): void {
            $filtro->whereIn('id_documento', function (Builder $sub) use ($clientiTenuti, $operatoriTenuti): void {
                $sub->select('id')->from('tc_documenti');
                $this->vincoloDocumentiDaEliminare($sub, $clientiTenuti, $operatoriTenuti);
            })->orWhere(function (Builder $operatori) use ($operatoriTenuti): void {
                $this->vincoloPerOperatoreEliminato($operatori, $operatoriTenuti);
            });
        });
    }

    /**
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloInviiSms(Builder $query, array $operatoriTenuti, string $tabella): void
    {
        $query->whereIn('send_id', function (Builder $sub) use ($operatoriTenuti, $tabella): void {
            $sub->select('id')->from($tabella);
            $this->vincoloPerOperatoreEliminato($sub, $operatoriTenuti);
        });
    }

    /**
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloVisteBacheca(Builder $query, array $operatoriTenuti): void
    {
        $query->where(function (Builder $filtro) use ($operatoriTenuti): void {
            $filtro->whereIn('id_bacheca', function (Builder $sub) use ($operatoriTenuti): void {
                $sub->select('id')->from('tc_bacheca');
                $this->vincoloPerOperatoreEliminato($sub, $operatoriTenuti);
            })->orWhere(function (Builder $operatori) use ($operatoriTenuti): void {
                $this->vincoloPerOperatoreEliminato($operatori, $operatoriTenuti);
            });
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloLegami(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $query->where(function (Builder $filtro) use ($clientiTenuti, $operatoriTenuti): void {
            $filtro->whereNull('id_cliente')
                ->orWhereNotIn('id_cliente', $clientiTenuti)
                ->orWhereNull('id_operatore')
                ->orWhereNotIn('id_operatore', $operatoriTenuti);
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     */
    private function vincoloCodici(Builder $query, array $clientiTenuti): void
    {
        $query->where(function (Builder $filtro) use ($clientiTenuti): void {
            $filtro->whereIn('id_sede', function (Builder $sub) use ($clientiTenuti): void {
                $sub->select('id')->from('tc_sedi');
                $this->vincoloPerCliente($sub, $clientiTenuti);
            })->orWhereIn('id_postazione', function (Builder $sub) use ($clientiTenuti): void {
                $sub->select('id')->from('tc_postazioni');
                $this->vincoloPerCliente($sub, $clientiTenuti);
            });
        });
    }

    /**
     * @param  list<int>  $clientiTenuti
     * @param  list<int>  $operatoriTenuti
     */
    private function vincoloTracking(Builder $query, array $clientiTenuti, array $operatoriTenuti): void
    {
        $query->where(function (Builder $filtro) use ($clientiTenuti, $operatoriTenuti): void {
            $filtro->whereIn('id_user', function (Builder $sub) use ($clientiTenuti): void {
                $sub->select('id')->from('tc_admin');
                $this->vincoloAccountClientiDaEliminare($sub, $clientiTenuti);
            })->orWhereIn('id_user', function (Builder $sub) use ($operatoriTenuti): void {
                $sub->select('id')->from('tc_admin');
                $this->vincoloOperatoriDaEliminare($sub, $operatoriTenuti);
            });
        });
    }

    private function db(): Connection
    {
        return DB::connection('mysql');
    }
}
