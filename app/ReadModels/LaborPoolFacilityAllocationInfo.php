<?php

namespace App\ReadModels;

use App\Utils\MapsObjectToInstance;

readonly class LaborPoolFacilityAllocationInfo {
    use MapsObjectToInstance;

    public function __construct(
        public int $nation_id,
        public int $territory_id,
        public int $labor_pool_id,
        public string $resource_type,
        public int $capacity,
        public float $productivity,
        public int $allocation,
        public int $production,
    ) {}
}