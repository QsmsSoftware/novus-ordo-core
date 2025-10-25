<?php

namespace App\Domain;

use LogicException;

readonly class TerritoryConnectionData {
    public function __construct(
        public int $x,
        public int $y,
        public bool $isConnectedByLand,
    )
    {
        
    }
}

readonly class TerritoryData {
    public function __construct(
        public int $x,
        public int $y,
        public TerrainType $terrainType,
        public float $usableLandRatio,
        public bool $hasSeaAccess,
        public array $connections,
    )
    {
        if ($x < 0 || $x > MapData::WIDTH) {
            throw new LogicException("x coordinate is invalid: $x");
        }
        if ($y < 0 || $y > MapData::HEIGHT) {
            throw new LogicException("y coordinate is invalid: $y");
        }
        if ($usableLandRatio < 0 || $usableLandRatio > 1) {
            throw new LogicException("Usable land ration not between 0.00 - 1.00: $usableLandRatio");
        }
    }
}

readonly class MapData {
    public const WIDTH = 30;
    public const HEIGHT = 20;
    
    public function __construct(
        public array $territories,
    )
    {
        
    }
}