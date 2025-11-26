<?php

namespace App\ReadModels;

use App\Utils\MapsObjectToInstance;

readonly class TerritoryTurnOwnerInfo {
    use MapsObjectToInstance;

    public function __construct(
        public int $territory_id,
        public bool $can_deploy,
        public array $stats
    ) {}
}