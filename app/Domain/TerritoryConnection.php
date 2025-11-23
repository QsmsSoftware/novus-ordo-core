<?php

namespace App\Domain;

readonly class TerritoryConnection {
    public function __construct(
        public int $territoryId,
        public int $connectedTerritoryId,
        public bool $isConnectedByLand,
    )
    {
        
    }
}