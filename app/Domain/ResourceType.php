<?php
namespace App\Domain;

use App\ReadModels\ResourceTypeInfo;

enum ResourceType :int {    
    case Capital = 0;
    case RecruitmentPool = 1;
    case Food = 2;
    case Material = 3;
    case Ore = 4;
    case Oil = 5;

    public static function getMeta(ResourceType $resourceType): ResourceTypeMeta {
        return match($resourceType) {
            ResourceType::Capital => new ResourceTypeMeta(description: "Capital (money)", canBeStocked: true),
            ResourceType::RecruitmentPool => new ResourceTypeMeta(description: "Recruitement pool", canBeStocked: false),
            ResourceType::Food => new ResourceTypeMeta(description: "Food", canBeStocked: true),
            ResourceType::Material => new ResourceTypeMeta(description: "Raw materials", canBeStocked: true),
            ResourceType::Ore => new ResourceTypeMeta(description: "Ores", canBeStocked: true),
            ResourceType::Oil => new ResourceTypeMeta(description: "Oil", canBeStocked: true),
        };
    }

    public static function exportMetas(): array {
        $resourceTypes = [];
        foreach (ResourceType::cases() as $resourceType) {
            $meta = ResourceType::getMeta($resourceType);
            $resourceTypes[] = new ResourceTypeInfo($resourceType->name, $meta->description, $meta->canBeStocked);
        }

        return $resourceTypes;
    }
}