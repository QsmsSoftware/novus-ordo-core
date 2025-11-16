<?php

namespace App\Http\Requests;

use App\Utils\MapsArrayToInstance;

readonly class SentDeploymentOrder {
    use MapsArrayToInstance;
    public function __construct(
        public string $division_type,
        public int $territory_id,
    )
    {
        
    }
}