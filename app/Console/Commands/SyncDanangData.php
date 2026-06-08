<?php

namespace App\Console\Commands;

use App\Services\DanangSyncService;
use Illuminate\Console\Command;

class SyncDanangData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:danang-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync flood reports and stations from muangap.danang.gov.vn';

    /**
     * Execute the console command.
     */
    public function handle(DanangSyncService $syncService)
    {
        $this->info('Starting sync from muangap.danang.gov.vn...');

        $this->info('Syncing stations...');
        $stationsCount = $syncService->syncStations();
        $this->info("Successfully synced {$stationsCount} stations.");

        $this->info('Syncing flood reports...');
        $reportsCount = $syncService->syncFloodReports();
        $this->info("Successfully synced {$reportsCount} flood reports.");

        $this->info('Sync complete!');
        return self::SUCCESS;
    }
}
