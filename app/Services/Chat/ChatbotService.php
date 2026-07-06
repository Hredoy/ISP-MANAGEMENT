<?php

namespace App\Services\Chat;

use App\Models\Client;
use App\Models\KbArticle;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Three-tier chatbot escalation ladder (Sprint 1 · Day 5 · AI + Realtime spec):
 *
 *   Tier 1 - Local KB: instant, free, keyword-matched (see KbArticle::findMatch()).
 *   Tier 2 - Groq (llama3-8b-8192): free tier, dynamic system prompt built from live context.
 *   Tier 3 - Gemini Flash: fallback when Groq is unconfigured/unavailable/erroring.
 *
 * Only successful AI-generated answers (tier 2/3) are cached, and permanently - caching a
 * temporary "sorry, I can't answer right now" failure would keep serving that failure forever
 * even after Groq/Gemini recover, so failures are never written to cache.
 *
 * The spec's dynamic system prompt also calls for "active faults in zone" and "last 3 tickets" -
 * neither exists in this codebase yet (fault detection is the SNMP polling engine task; tickets
 * are the Smart ticket system task, both still TODO), so the prompt only includes what's real
 * today: the client's status, package, and expiry date.
 */
class ChatbotService
{
    public function __construct(
        private readonly GroqChatDriver $groq,
        private readonly GeminiChatDriver $gemini,
    ) {}

    /**
     * @return array{answer: string, tier: string}
     */
    public function answer(string $message, ?Client $client = null): array
    {
        $kbMatch = KbArticle::findMatch($message);

        if ($kbMatch) {
            return ['answer' => $kbMatch->answer, 'tier' => 'local_kb'];
        }

        $cacheKey = TenantCache::aiAnswerKey($this->hash($message));
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $systemPrompt = $this->buildSystemPrompt($client);

        $groqResult = $this->groq->ask($systemPrompt, $message);
        if ($groqResult['ok']) {
            return $this->cacheAndReturn($cacheKey, $groqResult['answer'], 'groq');
        }

        $geminiResult = $this->gemini->ask($systemPrompt, $message);
        if ($geminiResult['ok']) {
            return $this->cacheAndReturn($cacheKey, $geminiResult['answer'], 'gemini');
        }

        return [
            'answer' => "Sorry, I couldn't find an answer to that right now. Our support team will follow up with you shortly.",
            'tier' => 'fallback',
        ];
    }

    private function hash(string $message): string
    {
        return hash('sha256', Str::lower(trim($message)));
    }

    /**
     * @return array{answer: string, tier: string}
     */
    private function cacheAndReturn(string $cacheKey, string $answer, string $tier): array
    {
        $result = ['answer' => $answer, 'tier' => $tier];
        Cache::forever($cacheKey, $result);

        return $result;
    }

    private function buildSystemPrompt(?Client $client): string
    {
        $prompt = 'You are a helpful, concise customer support assistant for an ISP (internet '.
            'service provider) in Bangladesh. Reply in the same language the customer used '.
            '(Bangla or English). Keep replies short and practical.';

        if ($client) {
            $prompt .= "\n\nCustomer context: name={$client->full_name}, package={$client->package_name}, ".
                "status={$client->effective_status}, expiry_date={$client->expiry_date}.";
        }

        return $prompt;
    }
}
