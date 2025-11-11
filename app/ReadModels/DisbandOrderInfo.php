<?php

namespace App\ReadModels;

readonly class DisbandOrderInfo {
    public function __construct(
        public int $division_id,
        public string $order_type,
    )
    {
        
    }
}