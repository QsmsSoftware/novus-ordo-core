<?php

namespace App\ReadModels;

use App\Utils\MapsObjectToInstance;

readonly class TerritoryTurnPublicInfo {
    use MapsObjectToInstance;

    public function __construct(
        public int $territory_id,
        public int $turn_number,
        public ?int $owner_nation_id,
        public array $stats,
        public ?array $owner_production,
        public array $loyalties,
    ) {}
}