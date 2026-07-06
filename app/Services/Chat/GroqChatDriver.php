<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Groq's OpenAI-compatible chat completions API - free tier, 14,400 req/day at time of
 * writing. Requires GROQ_API_KEY (config('services.groq.key')); ask() fails gracefully with
 * ok=false when unset, rather than throwing, so ChatbotService can fall through to Gemini.
 */
class GroqChatDriver implements ChatDriverInterface
{
    private const MODEL = 'llama3-8b-8192';

    public function ask(string $systemPrompt, string $message): array
    {
        $apiKey = config('services.groq.key');

        if (! $apiKey) {
            return ['ok' => false, 'answer' => '', 'message' => 'GROQ_API_KEY_NOT_CONFIGURED'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => self::MODEL,
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
