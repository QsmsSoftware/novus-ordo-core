<?php

namespace App\ReadModels;

readonly class NationTurnOwnerInfo {
    public function __construct(
        public int $nation_id,
        public int $turn_number,
        public bool $is_ready_for_next_turn,
        public array $stats,
    ) {}
}