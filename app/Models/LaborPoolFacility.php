<?php

namespace App\Models;

use App\Domain\ResourceType;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LaborPoolFacility extends Model
{
    use GuardsForAssertions;

    public static function getFacilities(NationDetail $detail): Collection {
        return LaborPoolFacility::where('nation_id', $detail->getNationId())
            ->where('turn_id', $detail->getTurnId())
            ->get();
    }

    public static function getFacility(LaborPool $pool, ResourceType $resourceType): LaborPoolFacility {
        return LaborPoolFacility::where('labor_pool_id', $pool->getId())
            ->where('resource_type', $resourceType->value)
            ->first();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getCapacity(): int {
        return $this->capacity;
    }

    public function getProductivityPercent(): int {
        return $this->productivity_pct;
    }

    public function getProductivity(): float {
        return $this->productivity_pct / 100;
    }

    public function getLaborPoolId(): int {
        return $this->labor_pool_id;
    }

    public function getTerritoryId(): int {
        return $this->territory_id;
    }

    public function getGameId(): int {
        return $this->game_id;
    }

    public function getNationId(): int {
        return $this->nation_id;
    }
    
    public function getTurnId(): int {
        return $this->turn_id;
    }

    public function setAllocation(int $allocation): void {
        $laborPoolAllocationOrNull = LaborPoolAllocation::getAllocationOrNull($this);
        if (is_null($laborPoolAllocationOrNull)) {
            LaborPoolAllocation::create($this, $allocation);
        }
        else {
            $laborPoolAllocationOrNull->setAllocation($allocation);
        }
    }

    public function addToAllocation(int $extraAllocation): void {
        $laborPoolAllocationOrNull = LaborPoolAllocation::getAllocationOrNull($this);
        if (is_null($laborPoolAllocationOrNull)) {
            LaborPoolAllocation::create($this, $extraAllocation);
        }
        else {
            $laborPoolAllocationOrNull->addToAllocation($extraAllocation);
        }
    }

    public function getResourceType(): ResourceType {
        return ResourceType::from($this->resource_type);
    }

    public static function create(LaborPool $pool, ResourceType $resourceType, int $capacity, int $productivityPercent): LaborPoolFacility {
        $poolProductivity = new LaborPoolFacility();
        $poolProductivity->game_id = $pool->getGameId();
        $poolProductivity->nation_id = $pool->getNationId();
        $poolProductivity->territory_id = $pool->getTerritoryId();
        $poolProductivity->turn_id = $pool->getTurnId();
        $poolProductivity->labor_pool_id = $pool->getId();
        $poolProductivity->capacity = $capacity;
        $poolProductivity->resource_type = $resourceType->value;
        $poolProductivity->productivity_pct = $productivityPercent;

        $poolProductivity->save();

        return $poolProductivity;
    }
}
