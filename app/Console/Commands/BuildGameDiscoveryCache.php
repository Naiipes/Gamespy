<?php

namespace App\Console\Commands;

use App\Services\GameDiscoveryService;
use Illuminate\Console\Command;

class BuildGameDiscoveryCache extends Command
{
    protected $signature = 'games:build-cache';
    protected $description = 'Build daily game discovery cache (recommend, genres, AAA)';

    // Runs daily discovery cache build and returns non-zero when refresh is skipped.
    public function handle(GameDiscoveryService $service): int
    {
        $this->info('Building game discovery cache...');

        // Keep existing cache if upstream returned no usable deal payload.
        if (!$service->buildDailyCache()) {
            $this->warn('Skipped cache refresh because CheapShark returned no valid deals. Existing cache was kept.');

            return Command::FAILURE;
        }

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
