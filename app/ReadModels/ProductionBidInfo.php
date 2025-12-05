<?php

namespace App\ReadModels;

use App\Utils\MapsObjectToInstance;

readonly class ProductionBidInfo {
    use MapsObjectToInstance;

    public function __construct(
        public string $resource_type,
        public int $max_quantity,
        public int $max_labor_allocation_per_unit,
        public int $priority,
    ) {}
}