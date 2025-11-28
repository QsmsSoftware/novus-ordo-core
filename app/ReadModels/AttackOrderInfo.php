<?php

namespace App\ReadModels;

readonly class AttackOrderInfo {
    public function __construct(
        public int $division_id,
        public string $order_type,
        public int $rebase_territory_id,
        public int $target_territory_id,
        public bool $is_operating,
    )
    {
        
    }
}