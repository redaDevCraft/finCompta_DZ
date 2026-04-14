<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            line-height: 1.45;
        }

        .container {
            width: 100%;
        }

        .header,
        .meta,
        .blocks,
        .totals-grid {
            width: 100%;
            margin-bottom: 18px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }

        .muted {
            color: #6b7280;
        }

        .box {
            border: 1px solid #d1d5db;
            padding: 12px;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f3f4f6;
            text-align: left;
            font-size: 11px;
            padding: 8px;
            border: 1px solid #d1d5db;
        }

        td {
            padding: 8px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }

        .two-col > div {
            width: 48%;
            display: inline-block;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .totals {
            margin-top: 18px;
            width: 320px;
            margin-left: auto;
        }

        .totals td {
            font-size: 12px;
        }

        .grand-total td {
            font-size: 15px;
            font-weight: bold;
            background: #eef2ff;
        }

        .footer {
            margin-top: 28px;
            font-size: 11px;
            color: #374151;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <table class="header">
            <tr>
                <td style="width: 65%; border: none; padding: 0;">
                    <div class="title">Facture</div>
                    <div><strong>{{ $invoice->company->raison_sociale }}</strong></div>
                    <div>{{ $invoice->company->address_line1 }}</div>
                    <div>NIF : {{ $invoice->company->nif }}</div>
                    <div>NIS : {{ $invoice->company->nis }}</div>
                    <div>RC : {{ $invoice->company->rc }}</div>
                    <div>AI : {{ $invoice->company->ai ?? '—' }}</div>
                </td>
                <td style="width: 35%; border: none; padding: 0;">
                    <div class="box">
                        <div><strong>N° facture :</strong> {{ $invoice->invoice_number }}</div>
                        <div><strong>Date d'émission :</strong> {{ optional($invoice->issue_date)->format('d/m/Y') }}</div>
                        <div><strong>Date d'échéance :</strong> {{ optional($invoice->due_date)->format('d/m/Y') ?: '—' }}</div>
                        <div><strong>Mode de paiement :</strong> {{ $invoice->payment_mode ?: '—' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="blocks">
            <tr>
                <td style="width: 50%;">
                    <strong>Fournisseur</strong><br>
                    {{ $invoice->company->raison_sociale }}<br>
                    NIF : {{ $invoice->company->nif }}<br>
                    NIS : {{ $invoice->company->nis }}<br>
                    RC : {{ $invoice->company->rc }}<br>
                    AI : {{ $invoice->company->ai ?? '—' }}
                </td>
                <td style="width: 50%;">
                    <strong>Client</strong><br>
                    {{ data_get($invoice->client_snapshot, 'display_name', $invoice->contact?->display_name ?? '—') }}<br>
                    @if(data_get($invoice->client_snapshot, 'raison_sociale'))
                        {{ data_get($invoice->client_snapshot, 'raison_sociale') }}<br>
                    @endif
                    NIF : {{ data_get($invoice->client_snapshot, 'nif', '—') }}<br>
                    NIS : {{ data_get($invoice->client_snapshot, 'nis', '—') }}<br>
                    RC : {{ data_get($invoice->client_snapshot, 'rc', '—') }}
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th>Qté</th>
                    <th>Unité</th>
                    <th>Prix unitaire HT</th>
                    <th>Remise</th>
                    <th>Total HT</th>
                    <th>TVA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lines as $line)
                    <tr>
                        <td>{{ $line->designation }}</td>
                        <td class="text-right">{{ number_format((float) $line->quantity, 4, ',', ' ') }}</td>
                        <td>{{ $line->unit ?: '—' }}</td>
                        <td class="text-right">{{ number_format((float) $line->unit_price_ht, 2, ',', ' ') }} DZD</td>
                        <td class="text-right">{{ number_format((float) $line->discount_pct, 2, ',', ' ') }} %</td>
                        <td class="text-right">{{ number_format((float) $line->line_ht, 2, ',', ' ') }} DZD</td>
                        <td class="text-right">{{ number_format((float) $line->line_vat, 2, ',', ' ') }} DZD</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 18px;">
            <table>
                <thead>
                    <tr>
                        <th>Base HT</th>
                        <th>Taux</th>
                        <th>Montant TVA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->vatBuckets as $bucket)
                        <tr>
                            <td class="text-right">{{ number_format((float) $bucket->base_ht, 2, ',', ' ') }} DZD</td>
                            <td class="text-right">{{ number_format((float) $bucket->rate_pct, 2, ',', ' ') }} %</td>
                            <td class="text-right">{{ number_format((float) $bucket->vat_amount, 2, ',', ' ') }} DZD</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <table class="totals">
            <tr>
                <td><strong>Total HT</strong></td>
                <td class="text-right">{{ number_format((float) $invoice->subtotal_ht, 2, ',', ' ') }} DZD</td>
            </tr>
            <tr>
                <td><strong>Total TVA</strong></td>
                <td class="text-right">{{ number_format((float) $invoice->total_vat, 2, ',', ' ') }} DZD</td>
            </tr>
            <tr class="grand-total">
                <td><strong>Total TTC</strong></td>
                <td class="text-right">{{ number_format((float) $invoice->total_ttc, 2, ',', ' ') }} DZD</td>
            </tr>
        </table>

        <div class="footer">
            Facture conforme aux dispositions fiscales algériennes en vigueur
        </div>
    </div>
</body>
</html>