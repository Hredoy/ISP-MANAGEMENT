<?php

namespace App\Services\Dns;

class DnsResolver
{
    /**
     * True once `$domain` has a CNAME (or A record, for apex domains that can't use CNAME)
     * pointing at `$target`. Wraps the global dns_get_record() so it can be swapped for a
     * fake in tests - this app has no control over when a customer's DNS actually propagates.
     */
    public function pointsTo(string $domain, string $target): bool
    {
        try {
            $records = @dns_get_record($domain, DNS_CNAME) ?: [];

            foreach ($records as $record) {
                if (isset($record['target']) && rtrim($record['target'], '.') === rtrim($target, '.')) {
                    return true;
                }
            }

            $aRecords = @dns_get_record($domain, DNS_A) ?: [];
            $targetIps = @dns_get_record($target, DNS_A) ?: [];
            $targetIps = array_column($targetIps, 'ip');

            foreach ($aRecords as $record) {
                if (isset($record['ip']) && in_array($record['ip'], $targetIps, true)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }
}
