<?php

namespace App\Domain;

use Closure;

readonly class VictoryProgress {
    public function __construct(
        public int $nationId,
        public string $goal,
        public int $rank,
        public int|float $value,
        public float $progress,
        public bool $isFulfilled,
        public Closure $valuePostProcessing,
    ) {
    }
}