<?php

namespace App\ReadModels;

readonly class ResourceTypeInfo 
{
    public function __construct(
        public string $resource_type,
        public string $description,
        public bool $can_be_stocked,
        public array $base_production,
    ) {}
}