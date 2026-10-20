<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Presenza;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function documento(Documento $documento)
    {
        $user = auth()->user();
        $consentito = match (true) {
            $user->isStaff() => true,
            $user->isCliente() => $documento->cliente_id === $user->cliente_id,
            default => $documento->user_id === $user->id,
        };
        abort_unless($consentito && Storage::disk('local')->exists($documento->file), 404);

        if ($user->id === $documento->user_id && ! $documento->visto_at) {
            $documento->update(['visto_at' => now()]);
        }

        return Storage::disk('local')->download($documento->file);
    }

    public function fotoProfilo(User $user)
    {
        $corrente = auth()->user();
        abort_unless($corrente->id === $user->id || $corrente->isStaff(), 403);
        abort_unless($user->foto_path && Storage::disk('local')->exists($user->foto_path), 404);

        return Storage::disk('local')->response($user->foto_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function presenze(Request $request)
    {
        $user = auth()->user();
        $visibili = Cliente::visibiliA($user)->select('clienti.id');

        $righe = Presenza::with(['operatore', 'sede'])
            ->whereIn('cliente_id', $visibili)
            ->when($request->date('da'), fn ($q, $d) => $q->where('rilevata_at', '>=', $d->startOfDay()))
            ->when($request->date('a'), fn ($q, $d) => $q->where('rilevata_at', '<=', $d->endOfDay()))
            ->when($request->integer('sede'), fn ($q, $id) => $q->where('sede_id', $id))
            ->orderBy('rilevata_at')
            ->get();

        return response()->streamDownload(function () use ($righe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Data', 'Ora', 'Operatore', 'Sede', 'Tipo', 'Scarto (min)', 'Distanza (m)'], ';');
            foreach ($righe as $p) {
                fputcsv($out, [
                    $p->rilevata_at->format('d/m/Y'), $p->rilevata_at->format('H:i'),
                    $p->operatore->nome_completo, $p->sede->descrizione,
                    ['I' => 'Inizio', 'F' => 'Fine', 'P' => 'Passaggio'][$p->tipo],
                    $p->ritardo_minuti, $p->distanza_km !== null ? round($p->distanza_km * 1000) : '',
                ], ';');
            }
            fclose($out);
        }, 'presenze-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
