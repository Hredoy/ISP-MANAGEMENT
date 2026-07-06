<?php

namespace App\Console\Commands;

use App\Services\DevicePollingService;
use Illuminate\Console\Command;

class PollDeviceStatus extends Command
{
    protected $signature = 'devices:poll';

    protected $description = 'Poll every MikroTik router for status/CPU (Redis-cached, DB written only on change) and raise a fault if CPU stays over 90% for 5+ minutes.';

    public function handle(DevicePollingService $poller): int
    {
        $result = $poller->pollAll();

        $this->info(
            "Polled {$result['polled']} router(s): {$result['changed']} status change(s), ".
            "{$result['faults_opened']} fault(s) opened, {$result['faults_resolved']} fault(s) resolved."
        );

        return self::SUCCESS;
    }
}
