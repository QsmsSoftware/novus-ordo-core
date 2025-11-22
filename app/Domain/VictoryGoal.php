<?php

namespace App\Domain;

use App\Facades\Metacache;
use App\Models\Game;
use App\Models\NationDetail;
use App\Models\Turn;
use Closure;
use InvalidArgumentException;

readonly class VictoryGoal {
    public ?Closure $valuePostProcessing;
    private Closure $isGoalFulfilled;
    private Closure $calculateGoalAdvancement;

    private function __construct(
        public string $title,
        private Closure $valueGetter,
        private int $sortOrder,
        public int|float $goal,
        public StatUnit $unit,
        ?Closure $calculateGoalAdvancement = null,
        ?Closure $isGoalFulfilled = null,
        ?Closure $valuePostProcessing = null,
    )
    {
        if ($sortOrder != SORT_ASC && $sortOrder != SORT_DESC) {
            throw new InvalidArgumentException("sortOrder: must be either SORT_ASC or SORT_DESC");
        }

        $this->valuePostProcessing = is_null($valuePostProcessing) ? fn (mixed $v) => $v : $valuePostProcessing;
        $this->isGoalFulfilled = is_null($isGoalFulfilled) ? fn (int|float $v, int|float $goal) => $v >= $goal : $isGoalFulfilled;
        $this->calculateGoalAdvancement = is_null($calculateGoalAdvancement) ? fn (int|float $v, int|float $goal) => min(1.00, $v / $goal) : $calculateGoalAdvancement;
    }

    public static function getNationProgressions(NationDetail ...$nationDetails): array {
        if (count($nationDetails) < 1) {
            return [];
        }

        $nationDetails = collect($nationDetails);
        $goals = VictoryGoal::getGoals($nationDetails->first()->getGame(), $nationDetails->first()->getTurn());
        $progressions = [];

        foreach ($goals as $goal) {
            assert($goal instanceof VictoryGoal);

            $rank = 1;

            $progressions[$goal->title] = $nationDetails->mapWithKeys(function (NationDetail $d) use ($goal) {
                    $value = ($goal->valueGetter)($d);

                    return [ $d->getNationId() => $value ];
                })
                ->sortBy(fn (int|float $v) => $v, descending: $goal->sortOrder == SORT_DESC)
                ->map(function ($v, int $nationId)  use ($goal, &$rank) {
                    return new VictoryProgress(
                        nationId: $nationId,
                        goal: $goal->title,
                        rank: $rank++,
                        value: $v,
                        progress: ($goal->calculateGoalAdvancement)($v, $goal->goal),
                        isFulfilled: ($goal->isGoalFulfilled)($v, $goal->goal),
                        valuePostProcessing: $goal->valuePostProcessing,
                    );
                });
        }

        return $progressions;
    }

    private static function approximate(int $value): int {
        $scale = pow(10, max(1, floor(log($value, 10))));

        return round($value / $scale) * $scale;
    }

    public static function getGoals(Game $game, Turn $turn): array {
        return [
            new VictoryGoal(
                title: 'Majority of usable land area',
                valueGetter: fn (NationDetail $d) => Metacache::remember($d->getUsableLandKm2(...)),
                sortOrder: SORT_DESC,
                goal: floor(Metacache::remember($game->getUsableLandKm2(...)) / 2) + 1,
                unit: StatUnit::Km2,
            ),
            new VictoryGoal(
                title: 'Majority of population',
                valueGetter: fn (NationDetail $d) => Metacache::remember($d->getPopulationSize(...)),
                sortOrder: SORT_DESC,
                goal: floor(Metacache::remember($turn->getPopulationSize(...)) / 2) + 1,
                unit: StatUnit::ApproximateNumber,
                valuePostProcessing: fn (int $v) => VictoryGoal::approximate($v),
            ),
        ];
    }
}