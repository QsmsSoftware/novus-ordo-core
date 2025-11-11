<?php

namespace App\ReadModels;

readonly class OwnedDivisionInfo {
    public function __construct(
        public int $division_id,
        public int $nation_id,
        public int $territory_id,
        public string $division_type,
        public ?object $order,
    )
    {
        
    }
}