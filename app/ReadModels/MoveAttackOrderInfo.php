<?php

namespace App\ReadModels;

readonly class MoveAttackOrderInfo {
    public function __construct(
        public int $division_id,
        public string $order_type,
        public int $destination_territory_id,
    )
    {
        
    }
}