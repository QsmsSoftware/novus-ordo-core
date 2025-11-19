<?php
namespace App\Domain;

readonly class ResourceTypeMeta {
    public function __construct(
        public string $description,
        public bool $canBeStocked,
        public int $startingStock,
    )
    {
        
    }
}