<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Turni selezionati</title>
    <style>
        @page { margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .intestazione td { vertical-align: middle; }
        .marca { font-size: 16px; font-weight: bold; color: {{ config('brand.colors.navy') }}; }
        .medio { margin: 2px 0; }
        .titolo { margin: 14px 0 6px; font-size: 14px; font-weight: bold; }
        .registro th { background: #e6e6e6; text-align: left; padding: 5px; border: 1px solid #bbb; }
        .registro td { padding: 4px 5px; border: 1px solid #ddd; }
        .totale td { background: #f4f4f4; font-weight: bold; }
        .piede { margin-top: 12px; color: #555; }
    </style>
</head>
<body>
    <table class="intestazione">
        <tr>
            <td style="width: 56px">@if ($logo)<img src="{{ $logo }}" alt="" width="48" height="48">@endif</td>
            <td>
                <p class="marca">{{ $azienda?->app }}</p>
                <p class="medio">{{ $azienda?->societa_indirizzo }}</p>
                <p class="medio">{{ $azienda?->societa_cap }} {{ $azienda?->societa_comune }}@if ($azienda?->societa_provincia) ({{ $azienda->societa_provincia }})@endif</p>
                @if ($azienda?->societa_iva)
                    <p class="medio">P.Iva: {{ $azienda->societa_iva }}</p>
                @endif
            </td>
        </tr>
    </table>

    <div class="titolo">Turni selezionati ({{ $turni->count() }})</div>

    <table class="registro">
        <thead>
            <tr>
                <th>Ragione sociale</th>
                <th>Sede</th>
                <th>Operatore</th>
                <th>Inizio</th>
                <th>Fine</th>
                <th>Ore</th>
                <th>Stato</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($turni as $turno)
                <tr>
                    <td>{{ $turno->sede?->cliente?->ragione_sociale }}</td>
                    <td>{{ $turno->sede?->descrizione }}</td>
                    <td>{{ $turno->operatore?->nome_completo }}</td>
                    <td>{{ $turno->inizio?->format('d/m/Y H:i') }}</td>
                    <td>{{ $turno->fine?->format('d/m/Y H:i') }}</td>
                    <td>{{ $turno->ore }}</td>
                    <td>{{ \App\Models\Legacy\Turno::etichette()[$turno->statoEffettivo()][0] }}</td>
                </tr>
            @endforeach
            <tr class="totale">
                <td colspan="5">Totale ore</td>
                <td>{{ number_format((float) $turni->sum('ore'), 2, '.', '') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="piede">Email: {{ $azienda?->societa_email }} · Tel: {{ $azienda?->societa_telefono }}</div>
</body>
</html>
