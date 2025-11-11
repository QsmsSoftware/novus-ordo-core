<?php

namespace App\ReadModels;

readonly class DeploymentInfo {
    public function __construct(
        public int $deployment_id,
        public int $nation_id,
        public int $territory_id,
    ) {}
}