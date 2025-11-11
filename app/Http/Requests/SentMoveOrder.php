<?php

namespace App\Http\Requests;

use App\Utils\MapsArrayToInstance;

readonly class SentMoveOrder {
    use MapsArrayToInstance;
    public function __construct(
        public int $division_id,
        public string $destination_territory_id,
    )
    {
        
    }
}