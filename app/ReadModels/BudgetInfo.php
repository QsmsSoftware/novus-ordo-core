<?php

namespace App\ReadModels;

readonly class BudgetInfo 
{
    public function __construct(
        public int $nation_id,
        public int $turn_number,
        public array $production,
        public array $stockpiles,
        public array $upkeep,
        public array $expenses,
        public array $available_production,
        public int $max_remaining_deployments,
    ) {}
}