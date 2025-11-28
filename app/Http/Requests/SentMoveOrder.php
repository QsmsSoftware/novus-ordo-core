<?php

namespace App\Http\Requests;

use App\Utils\MapsArrayToInstance;

readonly class SentMoveOrder {
    use MapsArrayToInstance;
    public function __construct(
        public int $division_id,
        public int $destination_territory_id,
        public array $path_territory_ids,
    )
    {
        
    }
}