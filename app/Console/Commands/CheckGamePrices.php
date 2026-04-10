<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PriceCheckerService;

class CheckGamePrices extends Command
{

    protected $signature = "prices:check";

    protected $description = "Check game prices";

    // Executes periodic price-check workflow and prints completion status.
    public function handle(PriceCheckerService $service)
    {

        // Runs sale/target checks and dispatches notifications as needed.
        $service->checkPrices();

        $this->info("Price check finished");

    }

}