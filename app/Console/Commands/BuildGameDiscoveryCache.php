<?php

namespace App\Console\Commands;

use App\Services\GameDiscoveryService;
use Illuminate\Console\Command;

class BuildGameDiscoveryCache extends Command
{
    protected $signature = 'games:build-cache';
    protected $description = 'Build daily game discovery cache (recommend, genres, AAA)';

    public function handle(GameDiscoveryService $service): int
    {
        $this->info('Building game discovery cache...');

        $service->buildDailyCache();

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
