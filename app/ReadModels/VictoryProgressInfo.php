<?php

namespace App\ReadModels;

use App\Domain\VictoryProgress;

readonly class VictoryProgressInfo 
{
    public function __construct(
        public int $nation_id,
        public int $rank,
        public float $progress,
        public int|float $value,
        public bool $is_fulfilled,
    ) {}

    public static function from(VictoryProgress $progress): VictoryProgressInfo {
        return new VictoryProgressInfo($progress->nationId, $progress->rank, $progress->progress, ($progress->valuePostProcessing)($progress->value), $progress->isFulfilled);
    }
}