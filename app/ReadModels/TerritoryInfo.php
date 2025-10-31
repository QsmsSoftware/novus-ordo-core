<?php

namespace App\ReadModels;

use App\Utils\MapsObjectToInstance;

readonly class TerritoryInfo {
    use MapsObjectToInstance;

    public function __construct(
        public int $territory_id,
        public int $turn_number,
        public int $x,
        public int $y,
        public string $terrain_type,
        public float $usable_land_ratio,
        public string $name,
        public ?int $owner_nation_id,
        public bool $has_sea_access,
        public array $connected_territory_ids,
        public array $stats,
    ) {}
}