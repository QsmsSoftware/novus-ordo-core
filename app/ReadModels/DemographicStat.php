<?php

namespace App\ReadModels;

readonly class DemographicStat {
    public function __construct(
        public string $title,
        public mixed $value,
        public string $unit,
    ) {}
}