<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Package;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily billing escalation ladder for client expiry, run per tenant via
 * `tenants:run billing:process-expirations` (see routes/console.php):
 *
 *   D-7 / D-3: SMS reminder (D-3 substitutes for the spec's "push + WhatsApp" — neither
 *              channel exists yet; that's the separate Multi-channel notification hub task).
 *   D-0:       throttle to 512k/512k via a simple queue, same static-IP-only limitation as the
 *              provisioning wizard's queue step (see ClientProvisioningController) — PPPoE
 *              clients on a dynamic IP pool are skipped/logged instead, not silently ignored.
 *   D+1:       auto-suspend (disable the PPPoE secret), mirroring ClientController::suspend()'s
 *              hard-fail-on-router-unreachable philosophy: if the router can't be reached, the
 *              client is NOT marked Suspended, so tomorrow's run retries it.
 *   D+7:       escalate — logged only. There's no reseller model/commission ledger yet (that's
 *              the separate Reseller system task), so this can't credit/notify a reseller.
 *
 * Also restores any previously-throttled client back to full speed once they've paid/renewed
 * (expiry_date pushed into the future) — checked first, before the ladder above, so a client who
 * pays never gets caught by the same run's D-0/D+1 checks.
 */
class ProcessBillingExpirations extends Command
{
    protected $signature = 'billing:process-expirations';

    protected $description = 'Run the daily expiry reminder / throttle / auto-suspend / escalation ladder for clients.';

    public function handle(MikroTikServiceFactory $factory, SmsService $smsService): int
    {
        $today = now()->toDateString();
        $connection = tenancy()->initialized ? 'tenant' : null;
        $clients = fn () => $connection ? Client::on($connection) : Client::query();
        $packages = fn () => $connection ? Package::on($connection) : Package::query();

        $restored = 0;
        foreach ($clients()->whereNotNull('throttled_at')->where('expiry_date', '>=', $today)->get() as $client) {
            $this->restoreBandwidth($client, $factory, $packages());
            $restored++;
        }

        $reminded7 = $this->remind($clients()->where('status', 'Active')->where('expiry_date', now()->addDays(7)->toDateString())->get(), 7, $smsService);
        $reminded3 = $this->remind($clients()->where('status', 'Active')->where('expiry_date', now()->addDays(3)->toDateString())->get(), 3, $smsService);

        $throttled = 0;
        foreach ($clients()->where('status', 'Active')->where('expiry_date', $today)->whereNull('throttled_at')->get() as $client) {
            if ($this->throttle($client, $factory, $packages())) {
                $throttled++;
            }
        }

        $suspended = 0;
        $dueYesterday = now()->subDay()->toDateString();
        foreach ($clients()->where('status', '!=', 'Suspended')->where('expiry_date', $dueYesterday)->get() as $client) {
            if ($this->suspend($client, $factory)) {
                $suspended++;
            }
        }

        $escalated = 0;
        $dueLastWeek = now()->subDays(7)->toDateString();
        foreach ($clients()->where('status', 'Suspended')->where('expiry_date', $dueLastWeek)->get() as $client) {
            Log::warning('BILLING_ESCALATION: client 7 days overdue and still suspended — no reseller system to escalate to yet.', [
                'client_id' => $client->id,
                'full_name' => $client->full_name,
                'expiry_date' => $client->expiry_date,
            ]);
            $escalated++;
        }

        $this->info("Restored: {$restored}, D-7 reminders: {$reminded7}, D-3 reminders: {$reminded3}, throttled: {$throttled}, suspended: {$suspended}, escalated: {$escalated}.");

        return self::SUCCESS;
    }

    private function remind(iterable $dueClients, int $daysRemaining, SmsService $smsService): int
    {
        $count = 0;
        foreach ($dueClients as $client) {
            $result = $smsService->sendExpiryReminder($client, $daysRemaining);
            if (! $result['ok']) {
                Log::warning("D-{$daysRemaining} expiry reminder failed: ".$result['message'], ['client_id' => $client->id]);
            }
            $count++;
        }

        return $count;
    }

    private function throttle(Client $client, MikroTikServiceFactory $factory, Builder $packageQuery): bool
    {
        $package = (clone $packageQuery)->where('mikrotik_id', $client->mikrotik_id)->where('name', $client->package_name)->first();
        $isStaticIp = $package && preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', (string) $package->remote_address);

        if (! $isStaticIp) {
            Log::info('BILLING_THROTTLE_SKIPPED: client has no static IP to throttle via queue.', ['client_id' => $client->id]);

            return false;
        }

        try {
            $factory->make($client->mikrotik)->addSimpleQueue($client->pppoe_username, $package->remote_address, '512k/512k');
            $client->update(['throttled_at' => now()]);

            return true;
        } catch (Throwable $e) {
            Log::error('BILLING_THROTTLE_FAILED: '.$e->getMessage(), ['client_id' => $client->id]);

            return false;
        }
    }

    private function restoreBandwidth(Client $client, MikroTikServiceFactory $factory, Builder $packageQuery): void
    {
        $package = (clone $packageQuery)->where('mikrotik_id', $client->mikrotik_id)->where('name', $client->package_name)->first();

        try {
            if ($package && preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', (string) $package->remote_address)) {
                $factory->make($client->mikrotik)->addSimpleQueue($client->pppoe_username, $package->remote_address, $package->rate_limit);
            }
        } catch (Throwable $e) {
            Log::error('BILLING_RESTORE_QUEUE_FAILED: '.$e->getMessage(), ['client_id' => $client->id]);
        }

        $client->update(['throttled_at' => null]);
    }

    private function suspend(Client $client, MikroTikServiceFactory $factory): bool
    {
        try {
            $factory->make($client->mikrotik)->disableUser($client->pppoe_username);
        } catch (Throwable $e) {
            Log::error('BILLING_AUTO_SUSPEND_FAILED: router unreachable, will retry next run: '.$e->getMessage(), ['client_id' => $client->id]);

            return false;
        }

        $client->update(['status' => 'Suspended']);

        return true;
    }
}
