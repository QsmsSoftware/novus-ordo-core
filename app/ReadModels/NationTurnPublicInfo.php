<?php

namespace App\ReadModels;

readonly class NationTurnPublicInfo {
    public function __construct(
        public int $nation_id,
        public int $turn_number,
        public string $usual_name,
        public array $stats,
    ) {}
}