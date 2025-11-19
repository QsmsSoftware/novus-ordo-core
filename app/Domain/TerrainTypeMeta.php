<?php
namespace App\Domain;

readonly class TerrainTypeMeta {
    public array $baseResources;

    public function __construct(
        public string $description,
        public float $maxPopulationDensity,
        ResourceProduction ...$baseResources,
    )
    {
        $this->baseResources = $baseResources;
    }
}