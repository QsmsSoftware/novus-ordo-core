<?php

namespace App\ReadModels;

use App\Domain\VictoryGoal;
use App\Domain\VictoryProgress;
use App\Domain\VictoryStatus;
use Illuminate\Support\Collection;

readonly class VictoryStatusInfo
{
    public function __construct(
        public string $victory_status,
        public ?int $winner_nation_id,
        public array $goals,
        public array $progressions
    ) {}

    public static function from(VictoryStatus $victoryStatus, ?int $winnerNationId, Collection $goals, Collection $progressions): VictoryStatusInfo {
        return new VictoryStatusInfo(
            victory_status: $victoryStatus->name,
            winner_nation_id: $winnerNationId,
            goals: $goals
                ->map(fn (VictoryGoal $g) => VictoryGoalInfo::from($g))
                ->all(),
            progressions: $progressions
                ->map(fn (Collection $progression) => $progression
                    ->map(fn (VictoryProgress $vp) => VictoryProgressInfo::from($vp))
                    ->sortBy(fn (VictoryProgressInfo $vp) => $vp->rank)
                    ->values()
                )
                ->values()
                ->toArray(),
        );
    }
}