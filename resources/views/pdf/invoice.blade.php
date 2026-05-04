<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    body { 
         font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            line-height: 1.45;
     }

    /* Mixed direction: general layout is LTR (French labels),
       Arabic content blocks are explicitly RTL */
    .ar {
      direction: rtl;
      text-align: right;
      unicode-bidi: embed;
    }
    .ltr {
      direction: ltr;
      text-align: left;
    }
  </style>
</head>
<body>

  {{-- Customer block — Arabic name needs the .ar wrapper --}}
  <div class="ar">
    {{ $invoice->contact->display_name }}     {{-- مؤسسة النور للتجارة --}}
    <br>
    {{ $invoice->contact->raison_sociale }}
    <br>
    {{ $invoice->contact->address_line1 }}
    <br>
    {{ $invoice->contact->address_wilaya }}
  </div>

  {{-- Invoice metadata — French/numbers stay LTR --}}
  <div class="ltr">
    Facture N° : {{ $invoice->invoice_number }}<br>
    Date : {{ $invoice->issue_date }}<br>
    Échéance : {{ $invoice->due_date }}
  </div>

  ...rest of invoice...

</body>
</html>