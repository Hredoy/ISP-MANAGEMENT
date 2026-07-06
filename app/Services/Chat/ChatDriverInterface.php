<?php

namespace App\Services\Chat;

interface ChatDriverInterface
{
    /**
     * @return array{ok: bool, answer: string, message: string}
     */
    public function ask(string $systemPrompt, string $message): array;
}
