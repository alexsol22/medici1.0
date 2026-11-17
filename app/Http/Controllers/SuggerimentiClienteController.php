<?php

namespace App\Http\Controllers;

use App\Models\Legacy\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Suggerimenti della ragione sociale per la ricerca nell'elenco clienti. */
class SuggerimentiClienteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isStaff(), 403);

        $termine = trim($request->string('q')->toString());

        if ($termine === '') {
            return response()->json(['results' => []]);
        }

        $termine = mb_substr($termine, 0, 80);
        $like = '%'.addcslashes($termine, '%_\\').'%';

        $results = Cliente::visibiliA($request->user())
            ->where('ragione_sociale', 'like', $like)
            ->orderBy('ragione_sociale')
            ->limit(20)
            ->get(['id', 'ragione_sociale'])
            ->map(fn (Cliente $cliente): array => [
                'id' => $cliente->id,
                'text' => $cliente->ragione_sociale,
            ])
            ->values();

        return response()->json(['results' => $results]);
    }
}
