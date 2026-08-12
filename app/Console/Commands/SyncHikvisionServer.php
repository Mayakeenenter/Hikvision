<?php

namespace App\Console\Commands;

use App\Services\ServerSyncService;
use Illuminate\Console\Command;

class SyncHikvisionServer extends Command
{
    protected $signature = 'attendance:sync-server';

    protected $description = 'Sync pending local Hikvision events to the Bluehost server.';

    public function handle(ServerSyncService $serverSync): int
    {
        $this->info('Starting server sync for pending Hikvision events...');

        $result = $serverSync->syncPendingEvents();

        if ($result['total'] === 0) {
            $this->info('No pending events found to sync.');
            return self::SUCCESS;
        }

        $this->info(
            "Processed {$result['total']} pending event(s): {$result['sent']} sent, {$result['failed']} failed."
        );

        if ($result['failed'] > 0) {
            $this->warn('Some events could not be synced. They will be retried on the next run.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
