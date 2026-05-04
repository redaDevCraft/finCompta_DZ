<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiExtractionService
{
    protected string $baseUrl;
    protected string $model;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.local_llm.base_url'), '/');
        $this->model = (string) config('services.local_llm.model');
        $this->apiKey = (string) config('services.local_llm.api_key', 'ollama');
    }

    
    public function extractExpenseFields(string $ocrText, string $companyId): array
    {
        $prompt = $this->buildExtractionPrompt($ocrText);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0,
                    'max_tokens' => 1024,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You extract structured accounting document fields. Return only valid JSON.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'reason' => 'api_error',
                    'suggestions' => [],
                ];
            }

            return $this->parseExtractionResponse($response->json(), $companyId);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'reason' => 'api_error',
                'suggestions' => [],
            ];
        }
    }

    public function classifyExpenseAccount(string $vendorName, string $description, float $amountHt): array
    {
        $prompt = <<<PROMPT
You are classifying an Algerian SME expense into an SCF class 6 account.

Choose ONLY one of these account codes:
- 601 = Achats de marchandises
- 604 = Achats d'études et prestations de services
- 606 = Achats non stockés de matières et fournitures
- 611 = Sous-traitance
- 613 = Locations
- 616 = Primes d'assurances
- 622 = Rémunérations d'intermédiaires et honoraires
- 625 = Déplacements, missions et réceptions
- 626 = Frais postaux et de télécommunications
- 631 = Impôts, taxes et versements assimilés

Vendor: {$vendorName}
Description: {$description}
Amount HT: {$amountHt}

Return STRICTLY valid JSON in this shape:
{
  "account_code": "6xx|null",
  "account_label": "...|null",
  "confidence": 0.0
}

Return ONLY JSON. No explanation.
PROMPT;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0,
                    'max_tokens' => 300,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You classify expenses into Algerian SCF accounts and return only JSON.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                return [
                    'account_code' => null,
                    'account_label' => null,
                    'confidence' => 0.0,
                ];
            }

            $raw = data_get($response->json(), 'choices.0.message.content', '{}');
            $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return [
                'account_code' => $parsed['account_code'] ?? null,
                'account_label' => $parsed['account_label'] ?? null,
                'confidence' => (float) ($parsed['confidence'] ?? 0.0),
            ];
        } catch (\Throwable $e) {
            return [
                'account_code' => null,
                'account_label' => null,
                'confidence' => 0.0,
            ];
        }
    }

    private function buildExtractionPrompt(string $text): string
    {
        return <<<PROMPT
Extract ONLY the following fields from this document text and return STRICTLY valid JSON:

{
  "vendor_name": { "value": "...|null", "confidence": 0.0 },
  "vendor_nif": { "value": "...|null", "confidence": 0.0 },
  "vendor_nis": { "value": "...|null", "confidence": 0.0 },
  "document_date": { "value": "YYYY-MM-DD|null", "confidence": 0.0 },
  "reference": { "value": "...|null", "confidence": 0.0 },
  "total_ht": { "value": 0.00, "confidence": 0.0 },
  "total_vat": { "value": 0.00, "confidence": 0.0 },
  "total_ttc": { "value": 0.00, "confidence": 0.0 },
  "document_type": { "value": "invoice|receipt|other|null", "confidence": 0.0 }
}

Rules:
- Do NOT calculate any taxes.
- Do NOT validate compliance.
- Do NOT infer missing values unless strongly supported by the text.
- Return ONLY valid JSON.

Document text:
{$text}
PROMPT;
    }
    public function mapBankCsvColumns(array $headers, array $sampleRow): array
{
    $headersJson = json_encode(array_values($headers), JSON_UNESCAPED_UNICODE);
    $sampleJson = json_encode($sampleRow, JSON_UNESCAPED_UNICODE);

    $prompt = <<<PROMPT
Tu es un assistant d'analyse de relevés bancaires.

À partir des en-têtes CSV/Excel et d'un exemple de ligne, identifie les colonnes suivantes :
- date
- label
- debit
- credit
- balance

Contraintes :
- Réponds uniquement en JSON valide
- Utilise exactement les clés: date, label, debit, credit, balance
- La valeur de balance peut être null
- Ne jamais inventer une colonne absente

Headers:
{$headersJson}

Sample row:
{$sampleJson}
PROMPT;

    $raw = $this->ask($prompt);
    $decoded = json_decode($raw, true);

    return [
        'date' => $decoded['date'] ?? null,
        'label' => $decoded['label'] ?? null,
        'debit' => $decoded['debit'] ?? null,
        'credit' => $decoded['credit'] ?? null,
        'balance' => $decoded['balance'] ?? null,
    ];
}




    private function parseExtractionResponse(array $resp, string $companyId): array
    {
        try {
            $raw = data_get($resp, 'choices.0.message.content', '{}');

            if (is_array($raw)) {
                $raw = implode("\n", array_map(function ($item) {
                    return is_array($item) ? ($item['text'] ?? json_encode($item)) : (string) $item;
                }, $raw));
            }

            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'reason' => 'ai_parse_error',
                'suggestions' => [],
            ];
        }

        $thresholds = [
            'total_ht' => 0.85,
            'total_vat' => 0.85,
            'total_ttc' => 0.85,
            'vendor_nif' => 0.80,
            'vendor_nis' => 0.80,
            'document_date' => 0.75,
            'vendor_name' => 0.65,
            'reference' => 0.60,
            'document_type' => 0.60,
        ];

        $suggestions = [];

        foreach ($data as $field => $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $value = $payload['value'] ?? null;
            $confidence = (float) ($payload['confidence'] ?? 0.0);

            if ($value === null || $value === '') {
                continue;
            }

            $suggestions[] = [
                'field_name' => $field,
                'suggested_value' => is_scalar($value) ? (string) $value : json_encode($value),
                'confidence' => $confidence,
                'needs_review' => $confidence < ($thresholds[$field] ?? 0.75),
            ];
        }

        return [
            'success' => true,
            'suggestions' => $suggestions,
        ];
    }
    /**
 * Classify what type of document this is before extracting fields.
 * Returns one of: purchase_invoice, sales_invoice, bank_statement,
 * receipt, credit_note, unknown.
 */
public function classifyDocument(string $ocrText): array
{
    $truncated = mb_substr($ocrText, 0, 1500);

    $prompt = <<<PROMPT
You are classifying an Algerian accounting document.

Analyze this document text and identify what type it is.

Choose EXACTLY one of these types:
- purchase_invoice  = Facture d'achat / fournisseur (we are the buyer)
- sales_invoice     = Facture de vente / client (we are the seller)
- credit_note       = Avoir / Note de crédit
- bank_statement    = Relevé bancaire / Relevé de compte
- receipt           = Reçu / ticket de caisse / justificatif simple
- unknown           = Cannot determine

Signals to look for:
- purchase_invoice: vendor name + our company as buyer, "Doit", "Facture N°", NIF/NIS of seller
- sales_invoice: our company as seller, client name, "Facture client"
- credit_note: "Avoir", "Note de crédit", "Remboursement"
- bank_statement: "Solde", "Débit", "Crédit", multiple transaction rows, "RIB", "IBAN", bank name
- receipt: small amount, no NIF/NIS, "Ticket", "Reçu"

Return STRICTLY valid JSON:
{
  "document_type": "purchase_invoice|sales_invoice|credit_note|bank_statement|receipt|unknown",
  "confidence": 0.0,
  "signals": ["signal1", "signal2"]
}

Document text:
{$truncated}
PROMPT;

    try {
        $raw    = $this->ask($prompt);
        $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return [
            'document_type' => $parsed['document_type'] ?? 'unknown',
            'confidence'    => (float) ($parsed['confidence'] ?? 0.0),
            'signals'       => $parsed['signals'] ?? [],
        ];
    } catch (\Throwable) {
        return ['document_type' => 'unknown', 'confidence' => 0.0, 'signals' => []];
    }
}
private function ask(string $prompt): string
{
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->post($this->baseUrl . '/chat/completions', [
            'model'       => $this->model,
            'temperature' => 0,
            'max_tokens'  => 512,
            'messages'    => [
                ['role' => 'system', 'content' => 'You are a helpful assistant. Return only valid JSON.'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ]);

    return data_get($response->json(), 'choices.0.message.content', '{}');
}
    
}