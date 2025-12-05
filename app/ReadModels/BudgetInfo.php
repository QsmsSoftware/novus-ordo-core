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
        public array $balances,
        public int $max_recruitement_pool_expansion,
        public array $labor_pools,
        public array $labor_facility_allocations,
        public int $free_labor,
    ) {}
}