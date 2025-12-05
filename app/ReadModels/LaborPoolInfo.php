<?php

namespace App\ReadModels;

use App\Utils\MapsObjectToInstance;

readonly class LaborPoolInfo {
    use MapsObjectToInstance;

    public function __construct(
        public int $labor_pool_id,
        public int $nation_id,
        public int $territory_id,
        public int $size,
        public int $free_labor,
    ) {}
}