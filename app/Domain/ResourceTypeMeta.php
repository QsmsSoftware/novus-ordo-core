<?php
namespace App\Domain;

use App\Models\ProductionBid;

readonly class ResourceTypeMeta {
    public function __construct(
        public string $description,
        public bool $canBeStocked,
        public int $startingStock,
        public UpkeepBidPriority $upkeepBidPriority = UpkeepBidPriority::Default,
        public bool $canPlaceCommand = true,
    )
    {
        
    }
}