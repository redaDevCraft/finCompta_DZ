<?php

namespace App\Actions\Ai;

use App\Enums\AiIntent;
use App\Models\AiConversation;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiContextExtractor;
use App\Services\Ai\AiIntentClassifier;
use App\Services\Ai\AiPromptBuilder;
use App\Services\Ai\AiResponseSanitizer;
use App\Services\Ai\GroqService;

class AskAiAction
{
    public function __construct(
        private AiIntentClassifier  $classifier,
        private AiContextExtractor  $extractor,
        private AiPromptBuilder     $promptBuilder,
        private GroqService         $groq,
        private AiResponseSanitizer $sanitizer,
    ) {
    }

    public function __invoke(string $message, Company $company, User $user, ?AiConversation $conversation = null): array
    {
        $conversation ??= AiConversation::create([
            'company_id' => $company->id,
            'user_id'    => $user->id,
        ]);

        $history = $conversation->messages()
            ->latest()
            ->limit(6)
            ->get()
            ->reverse()
            ->values();

        $intent  = $this->classifier->classify($message); // AiIntent[cite:171]
        $context = $this->extractor->extract($company);

        $messages = $this->promptBuilder->build(
            userMessage: $message,
            company:     $company,
            context:     $context,
            history:     $history,
            intent:      $intent,
        );

        $raw  = $this->groq->chat($messages);
        $text = $this->sanitizer->clean($raw['text']);

        $conversation->messages()->createMany([
            [
                'role'   => 'user',
                'content'=> $message,
                'intent' => $intent->value,
            ],
            [
                'role'        => 'assistant',
                'content'     => $text,
                'tokens_used' => $raw['tokens'],
            ],
        ]);

        $conversation->update(['last_active_at' => now()]);

        return [
            'reply'           => $text,
            'conversation_id' => $conversation->id,
        ];
    }
}