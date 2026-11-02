<?php

namespace App\Http\Controllers;

use App\Models\Legacy\Cliente;
use App\Models\Legacy\Documento;
use App\Models\Legacy\DocumentoView;
use App\Models\Legacy\Gda;
use App\Models\User;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function documento(Request $request, Documento $documento)
    {
        $user = auth()->user();
        $consentito = match (true) {
            $user->isStaff() => true,
            $user->isCliente() => $documento->tipo === 'C' && (int) $documento->id_tipo === (int) $user->cliente_id,
            default => $documento->tipo === 'O' && (int) $documento->id_tipo === (int) $user->id,
        };
        abort_unless($consentito && is_file($documento->percorso()), 404);

        if ($documento->tipo === 'O' && (int) $documento->id_tipo === (int) $user->id) {
            DocumentoView::updateOrCreate(
                ['id_documento' => $documento->id, 'id_operatore' => $user->id],
                ['visto' => 'Y', 'data_vista' => now()],
            );
        }

        if ($request->boolean('visualizza')) {
            return response()->file($documento->percorso());
        }

        return response()->download($documento->percorso());
    }

    public function fotoProfilo(User $user)
    {
        $corrente = auth()->user();
        abort_unless($corrente->id === $user->id || $corrente->isStaff(), 403);
        $percorso = rtrim(config('filesystems.avatar_path'), '/').'/'.basename((string) $user->foto_path);
        abort_unless($user->foto_path && is_file($percorso), 404);

        return response()->file($percorso, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function presenze(Request $request)
    {
        $user = auth()->user();
        $visibili = Cliente::visibiliA($user)->select('tc_clienti.id');

        $righe = Gda::with(['operatore', 'sede'])
            ->whereIn('id_cliente', $visibili)
            ->when($request->date('da'), fn ($q, $d) => $q->where('data', '>=', $d->toDateString()))
            ->when($request->date('a'), fn ($q, $d) => $q->where('data', '<=', $d->toDateString()))
            ->when($request->integer('sede'), fn ($q, $id) => $q->where('id_sede', $id))
            ->orderBy('data')->orderBy('orario')
            ->get();

        return response()->streamDownload(function () use ($righe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Data', 'Ora', 'Operatore', 'Sede', 'Tipo', 'Scarto (min)', 'Distanza (m)'], ';');
            foreach ($righe as $p) {
                fputcsv($out, [
                    $p->data?->format('d/m/Y'), $p->orario,
                    $p->operatore?->nome_completo, $p->sede?->descrizione,
                    ['I' => 'Inizio', 'F' => 'Fine', 'P' => 'Passaggio'][$p->tipo] ?? $p->tipo,
                    $p->ritardo, $p->distanza !== null ? round($p->distanza * 1000) : '',
                ], ';');
            }
            fclose($out);
        }, 'presenze-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
