<?php

namespace App\ReadModels;

readonly class LeaderTurnPublicInfo {
    public function __construct(
        public int $leader_id,
        public int $nation_id,
        public string $name,
        public string $title,
        public ?string $picture_src,
    ) {}
}