<?php

namespace App\Console\Commands;

use App\Models\SmsDeviceToken;
use Illuminate\Console\Command;

/**
 * Issues a device token for the SMS reader companion app. Intended to run inside a tenant
 * context via `php artisan tenants:run "sms:device-token --label=..." --tenants=<id>` (see
 * stancl/tenancy's tenants:run), so the token is created against that tenant's own database.
 * The plaintext token is only ever shown here, at creation time — only its sha256 hash is
 * stored (see SmsDeviceToken::generate()).
 */
class GenerateSmsDeviceToken extends Command
{
    protected $signature = 'sms:device-token {--label= : Optional label to identify the device, e.g. a phone owner\'s name}';

    protected $description = 'Generate a device token for the SMS reader companion app (payment SMS auto-match).';

    public function handle(): int
    {
        [, $plainToken] = SmsDeviceToken::generate($this->option('label'));

        $this->info('Device token generated. Copy it now — it will not be shown again:');
        $this->line($plainToken);

        return self::SUCCESS;
    }
}
