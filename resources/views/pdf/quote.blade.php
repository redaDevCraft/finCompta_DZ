<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis {{ $quote->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; padding: 8px; border: 1px solid #d1d5db; }
        td { padding: 8px; border: 1px solid #d1d5db; vertical-align: top; }
        .header td { border: none; padding: 0; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 8px; }
        .box { border: 1px solid #d1d5db; padding: 12px; border-radius: 6px; }
        .text-right { text-align: right; }
        .totals { margin-top: 18px; width: 320px; margin-left: auto; }
        .grand-total td { font-size: 15px; font-weight: bold; background: #eef2ff; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 65%;">
                <div class="title">Devis</div>
                <div><strong>{{ $quote->company->raison_sociale }}</strong></div>
                <div>{{ $quote->company->address_line1 }}</div>
                <div>NIF : {{ $quote->company->nif }}</div>
                <div>NIS : {{ $quote->company->nis }}</div>
                <div>RC : {{ $quote->company->rc }}</div>
            </td>
            <td style="width: 35%;">
                <div class="box">
                    <div><strong>N° devis :</strong> {{ $quote->number }}</div>
                    <div><strong>Date :</strong> {{ optional($quote->issue_date)->format('d/m/Y') }}</div>
                    <div><strong>Valable jusqu'au :</strong> {{ optional($quote->expiry_date)->format('d/m/Y') ?: '—' }}</div>
                    <div><strong>Référence :</strong> {{ $quote->reference ?: '—' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table style="margin: 16px 0;">
        <tr>
            <td style="width: 50%;">
                <strong>Émetteur</strong><br>
                {{ $quote->company->raison_sociale }}
            </td>
            <td style="width: 50%;">
                <strong>Client</strong><br>
                {{ $quote->contact?->display_name ?? '—' }}<br>
                NIF : {{ $quote->contact?->nif ?? '—' }}<br>
                NIS : {{ $quote->contact?->nis ?? '—' }}<br>
                RC : {{ $quote->contact?->rc ?? '—' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qté</th>
                <th>PU HT</th>
                <th>TVA</th>
                <th>Total ligne</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->lines as $line)
                @php
                    $lineHt = (float) $line->quantity * (float) $line->unit_price;
                    $lineVat = $lineHt * ((float) $line->vat_rate / 100);
                @endphp
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="text-right">{{ number_format((float) $line->quantity, 4, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format((float) $line->unit_price, 2, ',', ' ') }} {{ $quote->currency?->code ?? 'DZD' }}</td>
                    <td class="text-right">{{ number_format((float) $line->vat_rate, 2, ',', ' ') }} %</td>
                    <td class="text-right">{{ number_format($lineHt + $lineVat, 2, ',', ' ') }} {{ $quote->currency?->code ?? 'DZD' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td><strong>Total HT</strong></td>
            <td class="text-right">{{ number_format((float) $quote->subtotal, 2, ',', ' ') }} {{ $quote->currency?->code ?? 'DZD' }}</td>
        </tr>
        <tr>
            <td><strong>Total TVA</strong></td>
            <td class="text-right">{{ number_format((float) $quote->tax_total, 2, ',', ' ') }} {{ $quote->currency?->code ?? 'DZD' }}</td>
        </tr>
        <tr class="grand-total">
            <td><strong>Total TTC</strong></td>
            <td class="text-right">{{ number_format((float) $quote->total, 2, ',', ' ') }} {{ $quote->currency?->code ?? 'DZD' }}</td>
        </tr>
    </table>
</body>
</html>
