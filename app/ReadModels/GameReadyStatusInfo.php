<?php

namespace App\ReadModels;

use Carbon\CarbonImmutable;

readonly class GameReadyStatusInfo {
    public function __construct(
        public int $turn_number,
        public array $ready_for_next_turn_nation_ids,
        public int $nation_count,
        public ?CarbonImmutable $turn_expiration,
        public bool $is_game_ready,
    )
    {
    
    }
}