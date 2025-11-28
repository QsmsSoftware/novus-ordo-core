<?php

namespace App\ReadModels;

readonly class RaidOrderInfo {
    public function __construct(
        public int $division_id,
        public string $order_type,
        public int $target_territory_id,
        public bool $is_operating,
    )
    {
        
    }
}