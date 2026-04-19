<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bilan au {{ $bilan['as_of_date'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .page { padding: 24px 32px; }
        h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
        }
        .sub {
            color: #6b7280;
            font-size: 10px;
            margin-bottom: 18px;
        }
        .grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        .col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 0 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 3px 6px;
            text-align: left;
            vertical-align: top;
        }
        .section-title {
            background: #111827;
            color: #fff;
            padding: 5px 6px;
            font-weight: bold;
            font-size: 10.5px;
            margin-top: 10px;
        }
        .rubrique-header td {
            font-weight: bold;
            background: #f3f4f6;
            padding: 3px 6px;
            border-top: 1px solid #d1d5db;
        }
        .line td {
            padding-left: 18px;
            color: #374151;
            border-bottom: 1px dotted #e5e7eb;
        }
        .line td.amount { text-align: right; }
        .rubrique-total td {
            font-weight: bold;
            text-align: right;
            border-top: 1px solid #9ca3af;
            padding: 3px 6px;
        }
        .section-total {
            font-weight: bold;
            border-top: 2px solid #111827;
            padding: 6px;
            text-align: right;
            font-size: 10.5px;
        }
        .total-row {
            background: #111827;
            color: #fff;
            font-weight: bold;
            padding: 6px;
            font-size: 11px;
            text-align: right;
            margin-top: 8px;
        }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .code { color: #6b7280; font-size: 9px; margin-right: 4px; }
    </style>
</head>
<body>
<div class="page">
    <h1>Bilan comptable</h1>
    <div class="sub">
        @if(!empty($bilan['company']['raison_sociale']))
            <strong>{{ $bilan['company']['raison_sociale'] }}</strong><br>
        @endif
        Arrêté au <strong>{{ \Carbon\Carbon::parse($bilan['as_of_date'])->format('d/m/Y') }}</strong>
        — devise : {{ $bilan['company']['currency'] ?? 'DZD' }}
    </div>

    <div class="grid">
        <div class="col">
            <div class="section-title">ACTIF</div>
            @foreach($bilan['actif'] as $section)
                <table>
                    <tr class="rubrique-header">
                        <td colspan="2">{{ $section['label'] }}</td>
                    </tr>
                    @foreach($section['rubriques'] as $r)
                        <tr class="rubrique-header">
                            <td colspan="2" style="font-weight: normal; font-style: italic; background: #fafafa;">
                                {{ $r['label'] }}
                            </td>
                        </tr>
                        @foreach($r['lines'] as $line)
                            <tr class="line">
                                <td><span class="code">{{ $line['code'] }}</span>{{ $line['label'] }}</td>
                                <td class="amount">{{ number_format($line['amount'], 2, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                        <tr class="rubrique-total">
                            <td colspan="2">
                                Sous-total — {{ number_format($r['total'], 2, ',', ' ') }}
                            </td>
                        </tr>
                    @endforeach
                </table>
                <div class="section-total">
                    Total {{ $section['label'] }} : {{ number_format($section['total'], 2, ',', ' ') }}
                </div>
            @endforeach
            <div class="total-row">
                TOTAL ACTIF : {{ number_format($bilan['totals']['actif'], 2, ',', ' ') }}
            </div>
        </div>

        <div class="col">
            <div class="section-title">PASSIF</div>
            @foreach($bilan['passif'] as $section)
                <table>
                    <tr class="rubrique-header">
                        <td colspan="2">{{ $section['label'] }}</td>
                    </tr>
                    @foreach($section['rubriques'] as $r)
                        <tr class="rubrique-header">
                            <td colspan="2" style="font-weight: normal; font-style: italic; background: #fafafa;">
                                {{ $r['label'] }}
                            </td>
                        </tr>
                        @foreach($r['lines'] as $line)
                            <tr class="line">
                                <td><span class="code">{{ $line['code'] }}</span>{{ $line['label'] }}</td>
                                <td class="amount">{{ number_format($line['amount'], 2, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                        <tr class="rubrique-total">
                            <td colspan="2">
                                Sous-total — {{ number_format($r['total'], 2, ',', ' ') }}
                            </td>
                        </tr>
                    @endforeach
                </table>
                <div class="section-total">
                    Total {{ $section['label'] }} : {{ number_format($section['total'], 2, ',', ' ') }}
                </div>
            @endforeach
            <div class="total-row">
                TOTAL PASSIF : {{ number_format($bilan['totals']['passif'], 2, ',', ' ') }}
            </div>
        </div>
    </div>

    @if(abs($bilan['totals']['difference']) > 0.01)
        <div style="margin-top: 10px; padding: 6px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;">
            <strong>Écart actif / passif :</strong>
            {{ number_format($bilan['totals']['difference'], 2, ',', ' ') }}
            — des écritures non soldées ou non validées peuvent expliquer cette différence.
        </div>
    @endif

    <div style="margin-top: 12px; color: #9ca3af; font-size: 8.5px;">
        Document généré le {{ now()->format('d/m/Y H:i') }} — logiciel fincompta-dz.
    </div>
</div>
</body>
</html>
