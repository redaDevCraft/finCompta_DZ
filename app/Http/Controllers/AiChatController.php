<?php

namespace App\Http\Controllers;

use App\Actions\Ai\AskAiAction;
use App\Exceptions\AiPromptInjectionException;
use App\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    public function ask(Request $request, AskAiAction $ask): JsonResponse
    {
        $validated = $request->validate([
            'message'         => ['required', 'string', 'max:500'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $company = app('currentCompany');
        $user    = $request->user();

        $conversation = null;
        if (!empty($validated['conversation_id'])) {
            $conversation = AiConversation::where('id', $validated['conversation_id'])
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->firstOrFail();
        }

        try {
            $result = $ask(
                message:      $validated['message'],
                company:      $company,
                user:         $user,
                conversation: $conversation,
            );

            return response()->json([
                'reply'           => $result['reply'],
                'conversation_id' => $result['conversation_id'],
            ]);
        } catch (AiPromptInjectionException) {
            return response()->json([
                'reply' => 'Je ne peux pas traiter cette demande.',
            ], 422);
        } catch (\Throwable $e) {
            Log::channel('ai')->error('ai_chat_error', [
                'user_id'    => $user->id,
                'company_id' => $company->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'reply' => 'Une erreur est survenue. Veuillez réessayer plus tard.',
            ], 500);
        }
    }
}