<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Package;
use App\Models\Zone;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Support\TenantCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared row-validation/creation logic for the client bulk-import feature, used
 * by both the dry-run preview endpoint (commit: false) and the real import
 * endpoint (commit: true) so the two never drift out of sync.
 */
class ClientBulkImportService
{
    public function __construct(private readonly MikroTikServiceFactory $mikroTikFactory)
    {
    }

    /**
     * @param  Collection  $rows  Rows keyed by normalized header (name, phone, address, package, pppoe_username, pppoe_password, onu_mac, zone)
     * @return array{summary: array, rows: array}
     */
    public function process(Collection $rows, bool $commit): array
    {
        $seenUsernames = [];
        $seenPhones = [];
        $results = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header is row 1

            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $packageName = trim((string) ($row['package'] ?? ''));
            $pppoeUsername = trim((string) ($row['pppoe_username'] ?? ''));
            $pppoePassword = trim((string) ($row['pppoe_password'] ?? '')) ?: Str::random(8);
            $onuMac = trim((string) ($row['onu_mac'] ?? '')) ?: null;
            $zoneName = trim((string) ($row['zone'] ?? '')) ?: null;

            if ($name === '' && $phone === '' && $pppoeUsername === '') {
                continue; // blank trailing row, skip silently
            }

            $errors = [];

            if ($name === '') {
                $errors[] = 'Name is required';
            }
            if ($phone === '') {
                $errors[] = 'Phone is required';
            } elseif (! preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
                $errors[] = 'Phone format is invalid';
            }
            if ($address === '') {
                $errors[] = 'Address is required';
            }
            if ($pppoeUsername === '') {
                $errors[] = 'PPPoE Username is required';
            }

            $package = null;
            if ($packageName === '') {
                $errors[] = 'Package is required';
            } else {
                $package = Package::whereRaw('LOWER(name) = ?', [Str::lower($packageName)])->first();
                if (! $package) {
                    $errors[] = "Package not found: {$packageName}";
                }
            }

            if ($pppoeUsername !== '') {
                if (isset($seenUsernames[Str::lower($pppoeUsername)])) {
                    $errors[] = 'Duplicate PPPoE username in file';
                } elseif (Client::where('pppoe_username', $pppoeUsername)->exists()) {
                    $errors[] = 'PPPoE username already exists';
                }
                $seenUsernames[Str::lower($pppoeUsername)] = true;
            }

            if ($phone !== '') {
                if (isset($seenPhones[$phone])) {
                    $errors[] = 'Duplicate phone number in file';
                }
                $seenPhones[$phone] = true;
            }

            if ($errors !== []) {
                $results[] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'phone' => $phone,
                    'status' => 'invalid',
                    'reason' => implode('; ', $errors),
                ];

                continue;
            }

            if (! $commit) {
                $results[] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'phone' => $phone,
                    'status' => 'valid',
                    'reason' => $zoneName && ! $this->findZone($zoneName)
                        ? "Zone \"{$zoneName}\" will be created on import"
                        : null,
                ];

                continue;
            }

            $results[] = $this->createClient($rowNumber, $name, $phone, $address, $package, $pppoeUsername, $pppoePassword, $onuMac, $zoneName);
        }

        if ($commit) {
            TenantCache::forgetDashboardAndClients();
        }

        $summary = [
            'total' => count($results),
            'created' => count(array_filter($results, fn ($r) => $r['status'] === 'created')),
            'valid' => count(array_filter($results, fn ($r) => $r['status'] === 'valid')),
            'invalid' => count(array_filter($results, fn ($r) => $r['status'] === 'invalid')),
            'failed' => count(array_filter($results, fn ($r) => $r['status'] === 'failed')),
        ];

        return ['summary' => $summary, 'rows' => $results];
    }

    private function findZone(string $name): ?Zone
    {
        return Zone::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
    }

    private function createClient(
        int $rowNumber,
        string $name,
        string $phone,
        string $address,
        Package $package,
        string $pppoeUsername,
        string $pppoePassword,
        ?string $onuMac,
        ?string $zoneName,
    ): array {
        return DB::transaction(function () use ($rowNumber, $name, $phone, $address, $package, $pppoeUsername, $pppoePassword, $onuMac, $zoneName) {
            $zone = null;
            if ($zoneName) {
                $zone = $this->findZone($zoneName) ?? Zone::create(['name' => $zoneName]);
            }

            $client = Client::create([
                'mikrotik_id' => $package->mikrotik_id,
                'zone_id' => $zone?->id,
                'onu_mac' => $onuMac,
                'pppoe_username' => $pppoeUsername,
                'pppoe_password' => $pppoePassword,
                'package_name' => $package->name,
                'full_name' => $name,
                'phone_number' => $phone,
                'monthly_bill' => $package->price,
                'full_address' => $address,
                'expiry_date' => now()->addDays(30)->toDateString(),
                'status' => 'Active',
            ]);

            $pppoeNote = null;

            try {
                $this->mikroTikFactory->make($package->mikrotik)->addPPPoEUser([
                    'name' => $pppoeUsername,
                    'password' => $pppoePassword,
                    'profile' => $package->name,
                    'comment' => $name,
                ]);
            } catch (Throwable) {
                $pppoeNote = 'Client saved, but router is unreachable - PPPoE user was not provisioned. Retry from the client edit page.';
            }

            return [
                'row' => $rowNumber,
                'name' => $name,
                'phone' => $phone,
                'status' => 'created',
                'reason' => $pppoeNote,
                'client_id' => $client->id,
            ];
        });
    }
}
