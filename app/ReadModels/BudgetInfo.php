<?php

namespace App\ReadModels;

readonly class BudgetInfo 
{
    public function __construct(
        public int $nation_id,
        public int $turn_number,
        public int $production,
        public int $reserves,
        public int $upkeep,
        public int $expenses,
        public int $available_production,
        public int $max_remaining_deployments,
    ) {}
}