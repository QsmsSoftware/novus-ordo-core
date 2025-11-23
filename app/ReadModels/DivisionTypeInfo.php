<?php

namespace App\ReadModels;

readonly class DivisionTypeInfo 
{
    public function __construct(
        public string $division_type,
        public string $description,
        public array $deployment_costs,
        public array $upkeep_costs,
        public array $attack_costs,
        public int $attack_power,
        public int $defense_power,
        public int $moves,
        public bool $can_take_territory,
        public bool $can_fly,
    ) {}
}