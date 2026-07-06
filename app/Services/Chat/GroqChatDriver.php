<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use Throwable;

class GroqChatDriver implements ChatDriverInterface
{
    public function ask(string $systemPrompt, string $message): array
    {
        $apiKey = config('services.groq.key');
        $model = config('services.groq.model', 'llama-3.1-8b-instant');

        if (! $apiKey) {
            return ['ok' => false, 'answer' => '', 'message' => 'GROQ_API_KEY_NOT_CONFIGURED'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $message],
                    ],
                ]);

            if (! $response->successful()) {
                return ['ok' => false, 'answer' => '', 'message' => "GROQ_HTTP_{$response->status()}"];
            }

            $answer = $response->json('choices.0.message.content');

            if (! $answer) {
                return ['ok' => false, 'answer' => '', 'message' => 'GROQ_EMPTY_RESPONSE'];
            }

            return ['ok' => true, 'answer' => trim($answer), 'message' => 'GROQ_OK'];
        } catch (Throwable $e) {
            return ['ok' => false, 'answer' => '', 'message' => $e->getMessage()];
        }
    }
}
