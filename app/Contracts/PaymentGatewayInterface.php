<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface 
{
    public function baseURL(): string;

    public function isConfigured(): bool;

     /**
     * @return array{ok: true, id: string, url: string, raw: mixed}|array{ok: false, error: string, detail?: string}
     */

     public function createCheckout(
        Payment $payment,
        string $successUrl,
        string $failureUrl,
        string $webhookUrl

     ): array;

     public function verifyWebhookSignature(
        string $rawBody, ?string $signatureHeader
     ): bool;

}