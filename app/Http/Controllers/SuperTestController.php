<?php

namespace App\Http\Controllers;

use App\SuperTest\Catalogo;
use App\SuperTest\Copiatore;
use App\SuperTest\Esecutore;
use App\SuperTest\SessioneReale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SuperTestController extends Controller
{
    public function index(): View
    {
        return view('supertest.index', [
            'sezioni' => Catalogo::sezioni(),
        ]);
    }

    public function sessione(Request $request): JsonResponse
    {
        return response()->json(SessioneReale::stato((int) $request->query('da', 0)));
    }

    public function avvia(Copiatore $copiatore): JsonResponse
    {
        $id = SessioneReale::nuova();

        if (app()->runningUnitTests() || defined('PHPUNIT_COMPOSER_INSTALL')) {
            try {
                $copiatore->copia(SessioneReale::database($id), $id);
                SessioneReale::segnaPronta($id);
            } catch (Throwable $errore) {
                SessioneReale::segnaErrore($id, Copiatore::messaggio($errore));
            }
        } else {
            SessioneReale::lanciaCopia($id);
        }

        return response()->json(SessioneReale::stato());
    }

    public function elimina(): JsonResponse
    {
        SessioneReale::elimina();

        return response()->json(SessioneReale::stato());
    }

    public function esegui(Request $request, Esecutore $esecutore): JsonResponse
    {
        $dati = $request->validate([
            'id' => ['required', 'string', Rule::in(Catalogo::ids())],
            'sessione' => ['nullable', 'string', 'regex:/^[0-9a-f]{32}$/'],
        ]);

        set_time_limit(180);

        return response()->json($esecutore->esegui($dati['id'], $dati['sessione'] ?? null));
    }
}
