<?php

namespace App\Http\Requests;

use App\Utils\MapsArrayToInstance;

readonly class SentDisbandOrder {
    use MapsArrayToInstance;
    public function __construct(
        public int $division_id,
    )
    {
        
    }
}