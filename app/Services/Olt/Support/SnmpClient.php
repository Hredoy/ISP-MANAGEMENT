<?php

namespace App\Services\Olt\Support;

use RuntimeException;

class SnmpClient
{
    public const SYS_DESCR = '1.3.6.1.2.1.1.1.0';

    public function __construct(
        private readonly string $host,
        private readonly string $community = 'public',
        private readonly int $timeoutMicroseconds = 3000000,
        private readonly int $retries = 1,
    ) {}

    public function get(string $oid): ?string
    {
        $this->assertExtensionLoaded();

        $value = @snmpget($this->host, $this->community, $oid, $this->timeoutMicroseconds, $this->retries);

        return $value === false ? null : $this->normalize((string) $value);
    }

    public function walk(string $oid): array
    {
        $this->assertExtensionLoaded();

        $values = @snmprealwalk($this->host, $this->community, $oid, $this->timeoutMicroseconds, $this->retries);

        if ($values === false) {
            return [];
        }

        return collect($values)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->normalize((string) $value)])
            ->all();
    }

    public function sysDescr(): ?string
    {
        return $this->get(self::SYS_DESCR);
    }

    public static function assertExtensionLoaded(): void
    {
        if (! extension_loaded('snmp')) {
            throw new RuntimeException('PHP SNMP extension is not installed. Install php-snmp/php8.x-snmp on the server.');
        }
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/^(STRING|INTEGER|Gauge32|Counter32|Counter64|OID|Hex-STRING):\s*/i', '', $value) ?? $value, " \t\n\r\0\x0B\"");
    }
}
