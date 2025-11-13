<?php
namespace App\Domain;

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
            TerrainType::Water => new TerrainTypeMeta("Water"),
            TerrainType::Plain => new TerrainTypeMeta("Plain", new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 2), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 0.20)),
            TerrainType::River => new TerrainTypeMeta("Description('Plain (with major river)", new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 2), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 0.20)),
            TerrainType::Desert => new TerrainTypeMeta("Desert", new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 1.20), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 1)),
            TerrainType::Tundra => new TerrainTypeMeta("Tundra", new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 1.20), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 1)),
            TerrainType::Mountain => new TerrainTypeMeta("Mountain", new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 1.20), new ResourceProduction(ResourceType::Material, 0.20), new ResourceProduction(ResourceType::Ore, 1), new ResourceProduction(ResourceType::Oil, 0.20)),
            TerrainType::Forest => new TerrainTypeMeta("Forest", new ResourceProduction(ResourceType::Capital, 1), new ResourceProduction(ResourceType::RecruitmentPool, 1),  new ResourceProduction(ResourceType::Food, 1.20), new ResourceProduction(ResourceType::Material, 1), new ResourceProduction(ResourceType::Ore, 0.20), new ResourceProduction(ResourceType::Oil, 0.20)),
        };
    }

    public static function getResourceProduction(): array {
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
}