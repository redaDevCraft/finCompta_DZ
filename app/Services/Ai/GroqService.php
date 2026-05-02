<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class GroqService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'llama-3.3-70b-versatile',
    ) {
    }

    public function chat(array $messages): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => $this->model,
                'messages'    => $messages,
                'max_tokens'  => 600,
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Groq API error: ' . $response->status());
        }

        return [
            'text'   => $response->json('choices.0.message.content') ?? '',
            'tokens' => $response->json('usage.total_tokens') ?? null,
        ];
    }
}