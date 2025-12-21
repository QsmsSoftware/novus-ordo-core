<?php
namespace App\Domain;

use App\Models\ProductionBid;

readonly class ResourceTypeMeta {
    public function __construct(
        public string $description,
        public int $startingStock,
        public UpkeepBidPriority $upkeepBidPriority = UpkeepBidPriority::Default,
        public bool $canBeStocked = true,
        public bool $canPlaceCommand = true,
        public bool $producedByLabor = true,
        public bool $reserveLaborForUpkeep = false,
    )
    {
        
    }
}