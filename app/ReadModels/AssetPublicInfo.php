<?php

namespace App\ReadModels;

readonly class AssetPublicInfo {
    public function __construct(
        public string $uri,
        public ?string $title,
        public ?string $description,
        public ?string $attribution,
    )
    {
        
    }
}