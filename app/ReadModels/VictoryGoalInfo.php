<?php

namespace App\ReadModels;

use App\Domain\VictoryGoal;
use App\Domain\VictoryProgress;

readonly class VictoryGoalInfo 
{
    public function __construct(
        public string $title,
        public float|int $goal,
        public string $unit,
    ) {}

    public static function from(VictoryGoal $goal): VictoryGoalInfo {
        return new VictoryGoalInfo($goal->title, ($goal->valuePostProcessing)($goal->goal), $goal->unit->name);
    }
}