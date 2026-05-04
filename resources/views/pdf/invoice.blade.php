<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 18mm 14mm 18mm 14mm;
        }

        body {
            font-size: 10.5pt;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.45;
            background: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        h1, h2, h3, h4, h5, h6, p {
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .page {
            width: 100%;
        }

        .muted {
            color: #6b7280;
        }

        .small {
            font-size: 8.8pt;
        }

        .tiny {
            font-size: 8pt;
        }

        .right {
            text-align: right;
        }

        .left {
            text-align: left;
        }

        .center {
            text-align: center;
        }

        .rtl {
            direction: rtl;
            text-align: right;
            unicode-bidi: embed;
        }

        .ltr {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
        }

        .strong {
            font-weight: bold;
        }

        .mt-4 { margin-top: 4px; }
        .mt-6 { margin-top: 6px; }
        .mt-8 { margin-top: 8px; }
        .mt-10 { margin-top: 10px; }
        .mt-12 { margin-top: 12px; }
        .mt-16 { margin-top: 16px; }
        .mt-20 { margin-top: 20px; }
        .mt-24 { margin-top: 24px; }

        .mb-4 { margin-bottom: 4px; }
        .mb-6 { margin-bottom: 6px; }
        .mb-8 { margin-bottom: 8px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-12 { margin-bottom: 12px; }

        .header-table td {
            vertical-align: top;
        }

        .brand-block {
            width: 56%;
            padding-right: 12px;
        }

        .meta-block {
            width: 44%;
            padding-left: 12px;
        }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #111827;
        }

        .doc-badge {
            display: inline-block;
            background: #0f766e;
            color: #ffffff;
            padding: 6px 12px;
            font-size: 10pt;
            font-weight: bold;
            border-radius: 4px;
        }

        .panel {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
        }

        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }

        .meta-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .meta-table .label {
            width: 42%;
            color: #6b7280;
        }

        .meta-table .value {
            width: 58%;
            text-align: right;
            font-weight: bold;
            color: #111827;
        }

        .party-table td {
            vertical-align: top;
            width: 50%;
        }

        .party-box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            min-height: 145px;
        }

        .items-table {
            margin-top: 14px;
            border: 1px solid #d1d5db;
        }

        .items-table thead th {
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            padding: 8px 6px;
            font-size: 9pt;
            color: #111827;
        }

        .items-table tbody td {
            border-top: 1px solid #e5e7eb;
            padding: 8px 6px;
            vertical-align: top;
            font-size: 9.3pt;
        }

        .items-table .desc {
            width: 40%;
        }

        .items-table .qty {
            width: 10%;
        }

        .items-table .unit {
            width: 8%;
        }

        .items-table .unit-price {
            width: 14%;
        }

        .items-table .vat {
            width: 10%;
        }

        .items-table .total {
            width: 18%;
        }

        .num {
            text-align: right;
            direction: ltr;
            unicode-bidi: embed;
            white-space: nowrap;
        }

        .totals-wrap {
            margin-top: 14px;
        }

        .totals-table {
            width: 42%;
            margin-left: auto;
            border: 1px solid #d1d5db;
        }

        .totals-table td {
            padding: 7px 10px;
            border-top: 1px solid #e5e7eb;
        }

        .totals-table tr:first-child td {
            border-top: none;
        }

        .totals-table .label {
            background: #f9fafb;
            color: #374151;
            width: 60%;
        }

        .totals-table .value {
            text-align: right;
            font-weight: bold;
            width: 40%;
        }

        .grand-total td {
            background: #ecfdf5;
            font-size: 10.3pt;
            font-weight: bold;
        }

        .vat-table {
            margin-top: 16px;
            border: 1px solid #d1d5db;
        }

        .vat-table th,
        .vat-table td {
            border-top: 1px solid #e5e7eb;
            padding: 7px 8px;
            font-size: 9pt;
        }

        .vat-table thead th {
            border-top: none;
            background: #f3f4f6;
        }

        .footer-note {
            margin-top: 20px;
            font-size: 8.7pt;
            color: #4b5563;
        }

        .signature-area {
            margin-top: 28px;
        }

        .signature-box {
            width: 42%;
            margin-left: auto;
            text-align: center;
        }

        .signature-line {
            margin-top: 38px;
            border-top: 1px solid #9ca3af;
            padding-top: 6px;
            font-size: 8.7pt;
            color: #6b7280;
        }

        .arabic-inline {
            direction: rtl;
            unicode-bidi: embed;
            text-align: right;
            font-weight: bold;
        }

        .mixed-line {
            width: 100%;
        }

        .spacer-6 {
            height: 6px;
        }

        .spacer-10 {
            height: 10px;
        }
    </style>
</head>
<body>
@php
    $company = $invoice->company;
    $contact = $invoice->contact;
    $lines = $invoice->lines ?? collect();
    $vatBuckets = $invoice->vatBuckets ?? collect();

    $currency = $invoice->currency ?? 'DZD';

    $formatMoney = function ($value) use ($currency) {
        return number_format((float) $value, 2, '.', ' ') . ' ' . $currency;
    };

    $subtotalHt = $invoice->total_ht
        ?? $lines->sum(fn ($line) => (float) ($line->total_ht ?? ($line->quantity ?? 0) * ($line->unit_price ?? 0)));

    $totalVat = $invoice->total_vat
        ?? $vatBuckets->sum(fn ($bucket) => (float) ($bucket->vat_amount ?? 0));

    $totalTtc = $invoice->total_ttc ?? ($subtotalHt + $totalVat);

    $documentLabel = match($invoice->document_type ?? 'invoice') {
        'credit_note' => 'Avoir',
        'proforma' => 'Facture proforma',
        default => 'Facture',
    };
@endphp

<div class="page">
    <table class="header-table">
        <tr>
            <td class="brand-block">
                <div class="company-name">
                    {{ $company->name ?? 'Votre société' }}
                </div>

                @if(!empty($company->legal_name) && $company->legal_name !== ($company->name ?? null))
                    <div class="muted mt-4">{{ $company->legal_name }}</div>
                @endif

                @if(!empty($company->address_line1))
                    <div class="mt-8">{{ $company->address_line1 }}</div>
                @endif

                @if(!empty($company->address_line2))
                    <div>{{ $company->address_line2 }}</div>
                @endif

                @if(!empty($company->wilaya))
                    <div>{{ $company->wilaya }}</div>
                @endif

                <div class="mt-8 small">
                    @if(!empty($company->nif))
                        <span><span class="muted">NIF:</span> {{ $company->nif }}</span>
                    @endif
                    @if(!empty($company->nis))
                        <span style="margin-left: 12px;"><span class="muted">NIS:</span> {{ $company->nis }}</span>
                    @endif
                    @if(!empty($company->rc))
                        <div class="mt-4"><span class="muted">RC:</span> {{ $company->rc }}</div>
                    @endif
                </div>
            </td>

            <td class="meta-block right">
                <div class="doc-badge">{{ $documentLabel }}</div>

                <div class="panel mt-12">
                    <table class="meta-table">
                        <tr>
                            <td class="label">N° document</td>
                            <td class="value ltr">{{ $invoice->invoice_number ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Date d'émission</td>
                            <td class="value ltr">
                                {{ optional($invoice->issue_date)->format('Y-m-d') ?? $invoice->issue_date ?? '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Date d'échéance</td>
                            <td class="value ltr">
                                {{ optional($invoice->due_date)->format('Y-m-d') ?? $invoice->due_date ?? '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Statut</td>
                            <td class="value">{{ $invoice->status ?? '-' }}</td>
                        </tr>
                        @if(!empty($invoice->reference))
                            <tr>
                                <td class="label">Référence</td>
                                <td class="value ltr">{{ $invoice->reference }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="party-table mt-20">
        <tr>
            <td style="padding-right: 8px;">
                <div class="party-box">
                    <div class="section-title">Facturé à</div>

                    @if($contact)
                        <div class="strong">{{ $contact->display_name }}</div>

                        @if(!empty($contact->raison_sociale))
                            <div class="rtl mt-6">{{ $contact->raison_sociale }}</div>
                        @endif

                        @if(!empty($contact->address_line1))
                            <div class="mt-8 rtl">{{ $contact->address_line1 }}</div>
                        @endif

                        @if(!empty($contact->address_wilaya))
                            <div class="rtl">{{ $contact->address_wilaya }}</div>
                        @endif

                        <div class="mt-8 small">
                            @if(!empty($contact->email))
                                <div class="ltr"><span class="muted">Email:</span> {{ $contact->email }}</div>
                            @endif

                            @if(!empty($contact->phone))
                                <div class="ltr"><span class="muted">Téléphone:</span> {{ $contact->phone }}</div>
                            @endif

                            @if(!empty($contact->nif))
                                <div class="ltr"><span class="muted">NIF:</span> {{ $contact->nif }}</div>
                            @endif

                            @if(!empty($contact->nis))
                                <div class="ltr"><span class="muted">NIS:</span> {{ $contact->nis }}</div>
                            @endif

                            @if(!empty($contact->rc))
                                <div class="ltr"><span class="muted">RC:</span> {{ $contact->rc }}</div>
                            @endif
                        </div>
                    @else
                        <div class="muted">Aucun client lié.</div>
                    @endif
                </div>
            </td>

            <td style="padding-left: 8px;">
                <div class="party-box">
                    <div class="section-title">Informations de règlement</div>

                    <table class="meta-table">
                        <tr>
                            <td class="label">Mode de paiement</td>
                            <td class="value">{{ $invoice->payment_mode ?? $contact->default_payment_mode ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Délai</td>
                            <td class="value ltr">
                                {{ $invoice->payment_terms_days ?? $contact->default_payment_terms_days ?? 0 }} jours
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Devise</td>
                            <td class="value ltr">{{ $currency }}</td>
                        </tr>
                        @if(!empty($company->iban))
                            <tr>
                                <td class="label">IBAN</td>
                                <td class="value ltr">{{ $company->iban }}</td>
                            </tr>
                        @endif
                        @if(!empty($company->bank_account))
                            <tr>
                                <td class="label">Compte bancaire</td>
                                <td class="value ltr">{{ $company->bank_account }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="desc left">Désignation</th>
                <th class="qty center">Qté</th>
                <th class="unit center">Unité</th>
                <th class="unit-price right">PU HT</th>
                <th class="vat center">TVA</th>
                <th class="total right">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                @php
                    $description = $line->description ?? $line->label ?? '-';
                    $qty = (float) ($line->quantity ?? 0);
                    $unitPrice = (float) ($line->unit_price ?? 0);
                    $lineTotalHt = (float) ($line->total_ht ?? ($qty * $unitPrice));
                    $vatRate = $line->vat_rate ?? $line->tax_rate ?? $line->tva_rate ?? 0;
                    $unit = $line->unit ?? 'U';
                @endphp
                <tr>
                    <td class="desc">
                        @if(preg_match('/\p{Arabic}/u', $description))
                            <div class="rtl">{{ $description }}</div>
                        @else
                            <div>{{ $description }}</div>
                        @endif

                        @if(!empty($line->details))
                            <div class="tiny muted mt-4">
                                @if(preg_match('/\p{Arabic}/u', $line->details))
                                    <span class="rtl">{{ $line->details }}</span>
                                @else
                                    {{ $line->details }}
                                @endif
                            </div>
                        @endif
                    </td>
                    <td class="center num">{{ number_format($qty, 2, '.', ' ') }}</td>
                    <td class="center">{{ $unit }}</td>
                    <td class="num">{{ $formatMoney($unitPrice) }}</td>
                    <td class="center num">{{ number_format((float) $vatRate, 2, '.', ' ') }}%</td>
                    <td class="num">{{ $formatMoney($lineTotalHt) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center muted">Aucune ligne de facture.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($vatBuckets->count() > 0)
        <table class="vat-table">
            <thead>
                <tr>
                    <th class="left">Base HT</th>
                    <th class="center">Taux TVA</th>
                    <th class="right">Montant TVA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vatBuckets as $bucket)
                    <tr>
                        <td class="num">{{ $formatMoney($bucket->base_ht ?? 0) }}</td>
                        <td class="center num">{{ number_format((float) ($bucket->rate ?? 0), 2, '.', ' ') }}%</td>
                        <td class="num">{{ $formatMoney($bucket->vat_amount ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="totals-wrap">
        <table class="totals-table">
            <tr>
                <td class="label">Total HT</td>
                <td class="value num">{{ $formatMoney($subtotalHt) }}</td>
            </tr>
            <tr>
                <td class="label">TVA</td>
                <td class="value num">{{ $formatMoney($totalVat) }}</td>
            </tr>
            <tr class="grand-total">
                <td class="label">Total TTC</td>
                <td class="value num">{{ $formatMoney($totalTtc) }}</td>
            </tr>
        </table>
    </div>

    @if(!empty($invoice->notes))
        <div class="panel mt-20">
            <div class="section-title">Notes</div>
            @if(preg_match('/\p{Arabic}/u', $invoice->notes))
                <div class="rtl">{{ $invoice->notes }}</div>
            @else
                <div>{{ $invoice->notes }}</div>
            @endif
        </div>
    @endif

    <div class="footer-note">
        <div>Merci pour votre confiance.</div>

        @if($contact && !empty($contact->display_name) && preg_match('/\p{Arabic}/u', $contact->display_name))
            <div class="rtl mt-6">{{ $contact->display_name }}</div>
        @endif
    </div>

    <div class="signature-area">
        <div class="signature-box">
            <div class="small strong">Cachet et signature</div>
            <div class="signature-line">Signature autorisée</div>
        </div>
    </div>
</div>
</body>
</html>