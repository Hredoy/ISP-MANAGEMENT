<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiChatDriver implements ChatDriverInterface
{
    public function ask(string $systemPrompt, string $message): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-2.5-flash-lite');

        if (! $apiKey) {
            return ['ok' => false, 'answer' => '', 'message' => 'GEMINI_API_KEY_NOT_CONFIGURED'];
        }

        try {
            $response = Http::timeout(15)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
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
