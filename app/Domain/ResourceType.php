<?php
namespace App\Domain;

use App\ReadModels\ResourceTypeInfo;
use App\Utils\ParsableFromCaseName;
use Illuminate\Support\Collection;

enum ResourceType :int {
    use ParsableFromCaseName;

    case Capital = 0;
    case RecruitmentPool = 1;
    case Food = 2;
    case Material = 3;
    case Ore = 4;
    case Oil = 5;

    public static function getMeta(ResourceType $resourceType): ResourceTypeMeta {
        return match($resourceType) {
            ResourceType::Capital => new ResourceTypeMeta(description: "Capital (money)", startingStock: 20, upkeepBidPriority: UpkeepBidPriority::AfterCommandBids, canPlaceCommand: false, reserveLaborForUpkeep: true),
            ResourceType::RecruitmentPool => new ResourceTypeMeta(description: "Recruitement pool", startingStock: 0, canBeStocked: false, canPlaceCommand: false, producedByLabor: false),
            ResourceType::Food => new ResourceTypeMeta(description: "Food", startingStock: 0, upkeepBidPriority: UpkeepBidPriority::Highest),
            ResourceType::Material => new ResourceTypeMeta(description: "Raw materials", startingStock: 0),
            ResourceType::Ore => new ResourceTypeMeta(description: "Ores", startingStock: 10),
            ResourceType::Oil => new ResourceTypeMeta(description: "Oil", startingStock: 10),
        };
    }

    public static function getMetas(): Collection {
        return collect(ResourceType::cases())->mapWithKeys(fn (ResourceType $resourceType) => [$resourceType->value => ResourceType::getMeta($resourceType)]);
    }

    public static function exportMetas(): array {
        $productionByResourceTerrain = [];
        foreach(TerrainType::getResourceProductionByTerrainResource() as $terrain => $productionByResource) {
            foreach($productionByResource as $resource => $production) {
                $productionByResourceTerrain[$resource][$terrain] = $production;
            }
        }

        $resourceTypes = [];
        foreach (ResourceType::cases() as $resourceType) {
            $meta = ResourceType::getMeta($resourceType);
            
            $resourceTypes[] = new ResourceTypeInfo(
                $resourceType->name,
                $meta->description,
                $meta->canBeStocked,
                base_production_by_terrain_type: collect($productionByResourceTerrain[$resourceType->value])->mapWithKeys(fn (float $production, int $terrain) => [TerrainType::from($terrain)->name => $production])->all(),
            );
        }

        return $resourceTypes;
    }
}