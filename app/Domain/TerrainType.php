<?php
namespace App\Domain;

use App\ReadModels\TerrainTypeInfo;
use Illuminate\Support\Collection;

enum TerrainType :int {    
    case Water = 0;
    case Plain = 1;
    // #[Description('Plain (with major river)')]
    case River = 2;
    case Desert = 3;
    case Tundra = 4;
    case Mountain = 5;
    case Forest = 6;

    // public static function getDescription(TerrainType $value): string {
    //     $enumInfo = new ReflectionEnum(OrderType::class);

    //     $caseInfo = $enumInfo->getCase(OrderType::Move->name);

    //     $attributes = $caseInfo->getAttributes(Description::class);

    //     if (count($attributes) < 1) {
    //         return $value->name;
    //     }

    //     return reset($attributes)->newInstance()->value;
    // }

    public static function getMeta(TerrainType $terrainType): TerrainTypeMeta {
        return match($terrainType) {
            TerrainType::Water => new TerrainTypeMeta("Water", 0),
            TerrainType::Plain => new TerrainTypeMeta("Plain", 1.00,
                new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 4), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 1)),
            TerrainType::River => new TerrainTypeMeta("Description('Plain (with major river)", 1.00,
                new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 4), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 1)),
            TerrainType::Desert => new TerrainTypeMeta("Desert", 0.25,
                new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 2), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 4)),
            TerrainType::Tundra => new TerrainTypeMeta("Tundra", 0.25,
                new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 2), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 4)),
            TerrainType::Mountain => new TerrainTypeMeta("Mountain", 0.25,
                new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 2), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 1), new ResourceProduction(ResourceType::Oil, 1)),
            TerrainType::Forest => new TerrainTypeMeta("Forest", 0.25,
                new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 2), new ResourceProduction(ResourceType::Material, 1), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 1)),
        };
    }

    public static function getMetas(): Collection {
        return collect(TerrainType::cases())->mapWithKeys(fn (TerrainType $terrainType) => [$terrainType->value => TerrainType::getMeta($terrainType)]);
    }

    public static function getResourceProductionByResource(TerrainType $terrainType): array {
        $values = [];
        $meta = TerrainType::getMeta($terrainType);
        foreach(ResourceType::cases() as $resourceType) {
            $resourceProductionOrNull = array_find($meta->baseResources, fn (ResourceProduction $rp) => $rp->type == $resourceType);
            $values[$resourceType->value] = is_null($resourceProductionOrNull) ? 0 : $resourceProductionOrNull->amountProducted;
        }

        return $values;
    }

    public static function getResourceProductionByTerrainResource(): array {
        $values = [];
        foreach(ResourceType::cases() as $resourceType) {
            foreach(TerrainType::cases() as $terrainType) {
                $meta = TerrainType::getMeta($terrainType);
                $resourceProductionOrNull = array_find($meta->baseResources, fn (ResourceProduction $rp) => $rp->type == $resourceType);
                $values[$terrainType->value][$resourceType->value] = is_null($resourceProductionOrNull) ? 0 : $resourceProductionOrNull->amountProducted;
            }
        }

        return $values;
    }

    public static function exportMetas(): array {
        $types = [];
        foreach(TerrainType::cases() as $terrainType) {
            $meta = TerrainType::getMeta($terrainType);
            $types[] = new TerrainTypeInfo($terrainType->name, $meta->description);
        }

        return $types;
    }
}