<?php

namespace App\ReadModels;

use App\Utils\MapsObjectToInstance;

readonly class TerritoryBasePublicInfo {
    use MapsObjectToInstance;

    public function __construct(
        public int $territory_id,
        public int $x,
        public int $y,
        public string $terrain_type,
        public float $usable_land_ratio,
        public string $name,
        public bool $has_sea_access,
        public array $connected_land_territory_ids,
        public array $connected_territory_ids,
        public array $stats,
    ) {}
}