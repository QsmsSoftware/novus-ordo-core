<?php
namespace App\Domain;

use App\ReadModels\DivisionTypeInfo;
use App\Utils\ParsableFromCaseName;

enum DivisionType :int {
    use ParsableFromCaseName;
    
    case Infantry = 0;
    case Armored = 1;

    public static function getMeta(DivisionType $type): DivisionTypeMeta {
        return match($type) {
            DivisionType::Infantry => new DivisionTypeMeta(
                description: "Infantry division",
                deploymentCosts: [ResourceType::Capital->value => 3, ResourceType::RecruitmentPool->value => 1],
                upkeepCosts: [ResourceType::Capital->value => 1, ResourceType::RecruitmentPool->value => 1],
                attackCosts: [],
                attackPower: 20,
                defensePower: 30
            ),
            DivisionType::Armored => new DivisionTypeMeta(
                description: "Armored division",
                deploymentCosts: [ResourceType::Capital->value => 5, ResourceType::RecruitmentPool->value => 1, ResourceType::Ore->value => 1],
                upkeepCosts: [ResourceType::Capital->value => 1, ResourceType::RecruitmentPool->value => 1],
                attackCosts: [ResourceType::Oil->value => 1],
                attackPower: 50,
                defensePower: 30
            ),
        };
    }

    public static function getDeploymentCostsByType(): array {
        $costs = [];

        foreach (DivisionType::cases() as $divisionType) {
            $meta = DivisionType::getMeta($divisionType);
            foreach (ResourceType::cases() as $resourceType) {
                $costs[$divisionType->value][$resourceType->value] = isset($meta->deploymentCosts[$resourceType->value])
                    ? $meta->deploymentCosts[$resourceType->value]
                    : 0;
            }
        }

        return $costs;
    }

    private static function exportCosts(array $costs): array {
        $exported = [];

        foreach ($costs as $type => $cost) {
            $resourceType = ResourceType::from($type);

            $exported[$resourceType->name] = $cost;
        }

        return $exported;
    }

    public static function exportMetas(): array {
        $types = [];
        foreach (DivisionType::cases() as $type) {
            $meta = DivisionType::getMeta($type);
            $types[] = new DivisionTypeInfo(
                division_type: $type->name,
                description: $meta->description,
                deployment_costs: DivisionType::exportCosts($meta->deploymentCosts),
                upkeep_costs: DivisionType::exportCosts($meta->upkeepCosts),
                attack_costs: DivisionType::exportCosts($meta->attackCosts),
                attack_power: $meta->attackPower,
                defense_power: $meta->defensePower,
            );
        }

        return $types;
    }
}