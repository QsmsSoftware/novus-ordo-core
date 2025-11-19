<?php

namespace App\ReadModels;

readonly class TerrainTypeInfo 
{
    public function __construct(
        public string $terrain_type,
        public string $description,
    ) {}
}