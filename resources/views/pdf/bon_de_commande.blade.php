<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de commande {{ $payment->reference }}</title>
    <style>
        @page { margin: 30mm 20mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 22px; color: #4338ca; margin: 0 0 4px 0; }
        .muted { color: #6b7280; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px; text-align: left; }
        th { background: #eef2ff; color: #312e81; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; }
        tr + tr td { border-top: 1px solid #e5e7eb; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px; margin-top: 12px; }
        .row { display: flex; justify-content: space-between; gap: 24px; }
        .col { width: 48%; }
        .total { font-size: 14px; font-weight: bold; color: #111827; }
        .brand { color: #4338ca; font-weight: bold; }
        .stamp { margin-top: 30px; padding-top: 10px; border-top: 1px dashed #d1d5db; font-size: 10px; }
    </style>
</head>
<body>
    <table style="margin-bottom:10px;">
        <tr>
            <td style="vertical-align:top;">
                <h1>Bon de commande</h1>
                <div class="muted">Référence : <strong>{{ $payment->reference }}</strong></div>
                <div class="muted">Date : {{ $generated_at->format('d/m/Y') }}</div>
            </td>
            <td style="text-align:right; vertical-align:top;">
                <div class="brand" style="font-size:16px;">FinCompta DZ</div>
                <div class="muted">Comptabilité PME Algérie</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <div class="row">
            <div class="col">
                <strong>Émetteur</strong><br>
                <span>{{ $company->raison_sociale }}</span><br>
                <span class="muted">NIF: {{ $company->nif ?? '—' }}</span><br>
                <span class="muted">NIS: {{ $company->nis ?? '—' }}</span><br>
                <span class="muted">RC: {{ $company->rc ?? '—' }}</span><br>
                <span class="muted">{{ $company->address_line1 }} — {{ $company->address_wilaya }}</span>
            </div>
            <div class="col">
                <strong>Destinataire (FinCompta DZ)</strong><br>
                <span>{{ $payee['name'] ?? 'FinCompta DZ' }}</span><br>
                @if(!empty($payee['rc']))<span class="muted">RC: {{ $payee['rc'] }}</span><br>@endif
                @if(!empty($payee['nif']))<span class="muted">NIF: {{ $payee['nif'] }}</span><br>@endif
                @if(!empty($payee['nis']))<span class="muted">NIS: {{ $payee['nis'] }}</span><br>@endif
                @if(!empty($payee['address']))<span class="muted">{{ $payee['address'] }}</span><br>@endif
                @if(!empty($payee['email']))<span class="muted">Email: {{ $payee['email'] }}</span><br>@endif
                @if(!empty($payee['phone']))<span class="muted">Tél: {{ $payee['phone'] }}</span>@endif
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Désignation</th>
                <th style="text-align:center;">Cycle</th>
                <th style="text-align:right;">Montant (DZD)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Abonnement FinCompta DZ — plan {{ $plan->name }}</strong><br>
                    <span class="muted">{{ $plan->tagline }}</span>
                </td>
                <td style="text-align:center;">{{ $cycle === 'yearly' ? 'Annuel' : 'Mensuel' }}</td>
                <td style="text-align:right;">{{ number_format($amount, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align:right; padding-top:10px;" class="total">Total à régler</td>
                <td style="text-align:right; padding-top:10px;" class="total">{{ number_format($amount, 0, ',', ' ') }} DZD</td>
            </tr>
        </tfoot>
    </table>

    <div class="box">
        <strong>Modalités de règlement</strong>
        <table style="margin-top:6px;">
            <tr>
                <td style="width:30%;" class="muted">Banque</td>
                <td>{{ $payee['bank_name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="muted">RIB</td>
                <td><strong>{{ $payee['bank_rib'] ?? '—' }}</strong></td>
            </tr>
            <tr>
                <td class="muted">SWIFT / BIC</td>
                <td>{{ $payee['bank_swift'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="muted">Libellé virement</td>
                <td>{{ $payment->reference }} — {{ $company->raison_sociale }}</td>
            </tr>
            <tr>
                <td class="muted">Envoi justificatif</td>
                <td>{{ $admin_email ?? '—' }} — ou dépôt dans votre espace FinCompta DZ (Facturation → Bon de commande).</td>
            </tr>
        </table>
    </div>

    <div class="stamp">
        Une fois le virement reçu et le justificatif validé, votre abonnement
        <strong>{{ $plan->name }}</strong> ({{ $cycle === 'yearly' ? '12 mois' : '1 mois' }})
        sera activé automatiquement sur <strong>{{ $company->raison_sociale }}</strong>.
        <br>Pour toute question : {{ $payee['email'] ?? $admin_email ?? '—' }}.
    </div>
</body>
</html>
