<?php

namespace App\ReadModels;

readonly class UserNationSetupStatusOwnerInfo {
    public bool $is_logged_in_game;
    public function __construct(
        public ?int $game_id,
        public ?int $nation_id,
        public string $nation_setup_status,
    ) {}
}