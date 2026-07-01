<?php

namespace App\Services\Olt\Support;

use RuntimeException;

/**
 * Minimal raw-socket telnet client for automating OLT CLIs (Huawei/ZTE)
 * that don't need full IAC option negotiation to reach a login prompt.
 * No maintained telnet Composer package exists, so this fills that gap.
 */
class TelnetClient
{
    private $socket;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 23,
        private readonly int $timeout = 8,
    ) {}

    public function connect(): void
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            throw new RuntimeException("Telnet connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    public function waitFor(string $prompt, ?int $timeout = null): string
    {
        $buffer = '';
        $deadline = time() + ($timeout ?? $this->timeout);

        while (time() < $deadline) {
            $chunk = fread($this->socket, 4096);

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if ($meta['timed_out'] ?? false) {
                    break;
                }

                usleep(50000);

                continue;
            }

            $buffer .= $chunk;

            if (str_contains($buffer, $prompt)) {
                break;
            }
        }

        return $this->stripTelnetControlBytes($buffer);
    }

    public function send(string $line): void
    {
        fwrite($this->socket, $line."\r\n");
    }

    public function sendAndWait(string $line, string $prompt, ?int $timeout = null): string
    {
        $this->send($line);

        return $this->waitFor($prompt, $timeout);
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    private function stripTelnetControlBytes(string $data): string
    {
        // Strip IAC (0xFF) negotiation sequences (IAC + command + option = 3 bytes).
        return preg_replace('/\xFF[\xFB-\xFE]./s', '', $data) ?? $data;
    }
}
