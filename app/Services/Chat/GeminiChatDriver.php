<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Gemini Flash - free tier fallback (1,500 req/day at time of writing) for when Groq is
 * unavailable or its quota is exhausted. Requires GEMINI_API_KEY (config('services.gemini.key'));
 * ask() fails gracefully with ok=false when unset.
 */
class GeminiChatDriver implements ChatDriverInterface
{
    private const MODEL = 'gemini-1.5-flash';

    public function ask(string $systemPrompt, string $message): array
    {
        $apiKey = config('services.gemini.key');

        if (! $apiKey) {
            return ['ok' => false, 'answer' => '', 'message' => 'GEMINI_API_KEY_NOT_CONFIGURED'];
        }

        try {
            $response = Http::timeout(15)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.":generateContent?key={$apiKey}", [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['parts' => [['text' => $message]]]],
                ]);

            if (! $response->successful()) {
                return ['ok' => false, 'answer' => '', 'message' => "GEMINI_HTTP_{$response->status()}"];
            }

            $answer = $response->json('candidates.0.content.parts.0.text');

            if (! $answer) {
                return ['ok' => false, 'answer' => '', 'message' => 'GEMINI_EMPTY_RESPONSE'];
            }

            return ['ok' => true, 'answer' => trim($answer), 'message' => 'GEMINI_OK'];
        } catch (Throwable $e) {
            return ['ok' => false, 'answer' => '', 'message' => $e->getMessage()];
        }
    }
}
