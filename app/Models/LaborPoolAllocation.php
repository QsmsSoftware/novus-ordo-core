<?php

namespace App\Models;

use App\Domain\ResourceType;
use App\ReadModels\LaborPoolFacilityAllocationInfo;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaborPoolAllocation extends Model
{
    use GuardsForAssertions;

    public static function getFreeLabor(NationDetail $detail): int {
        return DB::table('labor_pool_facilities')
            ->where('labor_pool_facilities.nation_id', $detail->getNationId())
            ->where('labor_pool_facilities.turn_id', $detail->getTurnId())
            ->where('labor_pool_facilities.resource_type', ResourceType::Capital->value)
            ->join('labor_pool_allocations', 'labor_pool_facilities.id', '=', 'labor_pool_allocations.labor_pool_facility_id')
            ->selectRaw('SUM(allocation) as free_labor')
            ->value('free_labor') ?? 0;
    }

    public static function getProduction(NationDetail $detail, ResourceType $resourceType): int {
        return DB::table('labor_pool_facilities')
            ->where('labor_pool_facilities.nation_id', $detail->getNationId())
            ->where('labor_pool_facilities.turn_id', $detail->getTurnId())
            ->where('labor_pool_facilities.resource_type', $resourceType->value)
            ->join('labor_pool_allocations', 'labor_pool_facilities.id', '=', 'labor_pool_allocations.labor_pool_facility_id')
            ->selectRaw('SUM(productivity_pct / 100 * allocation) as total_production')
            ->value('total_production') ?? 0;
    }

    public static function resetAllocations(NationDetail $detail): void {
        DB::table('labor_pools')
            ->where('labor_pools.nation_id', $detail->getNationId())
            ->where('labor_pools.turn_id', $detail->getTurnId())
            ->join('labor_pool_facilities', 'labor_pools.id', '=', 'labor_pool_facilities.labor_pool_id')
            ->join('labor_pool_allocations', 'labor_pool_facilities.id', '=', 'labor_pool_allocations.labor_pool_facility_id')
            ->update([ 'labor_pool_allocations.allocation' => 0 ]);
    }

    public static function exportAllForOwner(NationDetail $detail): array {
        $turn = $detail->getTurn();
        return DB::table('labor_pools')
            ->where('labor_pools.nation_id', $detail->getNationId())
            ->where('labor_pools.turn_id', $turn->getId())
            ->join('labor_pool_facilities', 'labor_pools.id', '=', 'labor_pool_facilities.labor_pool_id')
            ->join('labor_pool_allocations', 'labor_pool_facilities.id', '=', 'labor_pool_allocations.labor_pool_facility_id')
            ->select(
                'labor_pools.nation_id',
                'labor_pools.turn_id',
                'labor_pools.territory_id',
                'labor_pool_facilities.labor_pool_id',
                'labor_pool_facilities.capacity',
                'labor_pool_facilities.resource_type',
                'labor_pool_facilities.productivity_pct',
                'labor_pool_allocations.allocation',
            )
            ->get()
            ->map(fn (object $row) => LaborPoolFacilityAllocationInfo::fromObject($row, [
                'resource_type' => ResourceType::from($row->resource_type)->name,
                'productivity' => $row->productivity_pct / 100,
                'allocation' => $row->allocation ?? 0,
                'production' => $row->allocation * $row->productivity_pct / 100,
            ]))
            ->all();
    }

    public function getAllocation(): int {
        return $this->allocation;
    }

    public function setAllocation(int $allocation): void {
        $this->allocation = $allocation;

        $this->save();
    }

    public function addToAllocation(int $value): void {
        $this->setAllocation($this->allocation + $value);
    }

    public static function getAllocationOrNull(LaborPoolFacility $facility): ?LaborPoolAllocation {
        return LaborPoolAllocation::where('labor_pool_facility_id', $facility->getId())
            ->first();
    }

    public static function create(LaborPoolFacility $facility, int $allocation): LaborPoolAllocation {
        $poolAllocation = new LaborPoolAllocation();
        $poolAllocation->game_id = $facility->getGameId();
        $poolAllocation->nation_id = $facility->getNationId();
        $poolAllocation->territory_id = $facility->getTerritoryId();
        $poolAllocation->turn_id = $facility->getTurnId();
        $poolAllocation->labor_pool_id = $facility->getLaborPoolId();
        $poolAllocation->labor_pool_facility_id = $facility->getId();
        $poolAllocation->resource_type = $facility->getResourceType()->value;
        $poolAllocation->allocation = $allocation;

        $poolAllocation->save();

        return $poolAllocation;
    }
}
