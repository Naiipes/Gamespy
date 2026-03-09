<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GameDiscoveryService
{

    public function popular()
    {

        return Http::get(
            "https://www.cheapshark.com/api/1.0/deals",
            [
                "sortBy"=>"DealRating",
                "pageSize"=>20
            ]
        )->json();

    }

    public function discounts()
    {

        return Http::get(
            "https://www.cheapshark.com/api/1.0/deals",
            [
                "sortBy"=>"Savings",
                "pageSize"=>20
            ]
        )->json();

    }

    public function free()
    {

        return Http::get(
            "https://www.cheapshark.com/api/1.0/deals",
            [
                "price"=>0,
                "pageSize"=>20
            ]
        )->json();

    }

}