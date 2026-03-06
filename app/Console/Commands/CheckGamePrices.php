<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PriceCheckerService;

class CheckGamePrices extends Command
{

    protected $signature = "prices:check";

    protected $description = "Check game prices";

    public function handle(PriceCheckerService $service)
    {

        $service->checkPrices();

        $this->info("Price check finished");

    }

}