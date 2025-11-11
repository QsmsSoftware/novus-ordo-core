<?php

namespace App\ReadModels;

readonly class UserOwnerInfo {
    public bool $is_logged_in_game;
    public function __construct(
        public string $user_name,
    ) {}
}