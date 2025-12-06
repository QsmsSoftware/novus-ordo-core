<?php
namespace App\Domain;

use App\ReadModels\DivisionTypeInfo;
use App\Utils\ParsableFromCaseName;
use Illuminate\Support\Collection;

enum DivisionType :int {
    use ParsableFromCaseName;
    
    case Infantry = 0;
    case Armored = 1;
    case Artillery = 2;
    case Fighter = 3;
    case Bomber = 4;

    public static function getMeta(DivisionType $type): DivisionTypeMeta {
        return match($type) {
            DivisionType::Infantry => new DivisionTypeMeta(
                description: "Infantry division",
                deploymentCosts: [ResourceType::Capital->value => 3, ResourceType::RecruitmentPool->value => 1],
                upkeepCosts: [ResourceType::RecruitmentPool->value => 1],
                attackCosts: [],
                attackPower: 15,
                defensePower: 30,
            ),
            DivisionType::Armored => new DivisionTypeMeta(
                description: "Armored division",
                deploymentCosts: [ResourceType::Capital->value => 5, ResourceType::RecruitmentPool->value => 1, ResourceType::Ore->value => 5],
                upkeepCosts: [ResourceType::RecruitmentPool->value => 1],
                attackCosts: [ResourceType::Oil->value => 1],
                attackPower: 50,
                defensePower: 30,
                moves: 2,
            ),
            DivisionType::Artillery => new DivisionTypeMeta(
                description: "Artillery brigade",
                deploymentCosts: [ResourceType::Capital->value => 4, ResourceType::RecruitmentPool->value => 1, ResourceType::Ore->value => 1],
                upkeepCosts: [ResourceType::RecruitmentPool->value => 1],
                attackCosts: [],
                attackPower: 30,
                defensePower: 30,
            ),
            DivisionType::Fighter => new DivisionTypeMeta(
                description: "Fighter squadron",
                deploymentCosts: [ResourceType::Capital->value => 10, ResourceType::RecruitmentPool->value => 1, ResourceType::Ore->value => 1],
                upkeepCosts: [ResourceType::RecruitmentPool->value => 1],
                attackCosts: [ResourceType::Oil->value => 1],
                attackPower: 50,
                defensePower: 80,
                moves: 6,
                canTakeTerritory: false,
                canFly: true,
            ),
            DivisionType::Bomber => new DivisionTypeMeta(
                description: "Bomber squadron",
                deploymentCosts: [ResourceType::Capital->value => 15, ResourceType::RecruitmentPool->value => 1, ResourceType::Ore->value => 1],
                upkeepCosts: [ResourceType::RecruitmentPool->value => 1],
                attackCosts: [ResourceType::Oil->value => 1],
                attackPower: 80,
                defensePower: 15,
                moves: 8,
                canTakeTerritory: false,
                canFly: true,
            ),
        };
    }

    public static function getMetas(): Collection {
        return collect(DivisionType::cases())->mapWithKeys(fn (DivisionType $divisionType) => [$divisionType->value => DivisionType::getMeta($divisionType)]);
    }

    public static function getAttackCostsByType(): array {
        $costs = [];

        foreach (DivisionType::cases() as $divisionType) {
            $meta = DivisionType::getMeta($divisionType);
            foreach (ResourceType::cases() as $resourceType) {
                $costs[$divisionType->value][$resourceType->value] = isset($meta->attackCosts[$resourceType->value])
                    ? $meta->attackCosts[$resourceType->value]
                    : 0;
            }
        }

        return $costs;
    }

    public static function calculateTotalAttackCostsByResourceType(DivisionType ...$attackingTypes): array {
        return DivisionType::calculateTotalCostsByResourceType(DivisionType::getAttackCostsByType(), ...$attackingTypes);
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

    public static function calculateTotalDeploymentCostsByResourceType(DivisionType ...$deployedTypes): array {
        $costs = DivisionType::calculateTotalCostsByResourceType(DivisionType::getDeploymentCostsByType(), ...$deployedTypes);

        return $costs;
    }

    public static function getUpkeepCostsByType(): array {
        $costs = [];

        foreach (DivisionType::cases() as $divisionType) {
            $meta = DivisionType::getMeta($divisionType);
            foreach (ResourceType::cases() as $resourceType) {
                $costs[$divisionType->value][$resourceType->value] = isset($meta->upkeepCosts[$resourceType->value])
                    ? $meta->upkeepCosts[$resourceType->value]
                    : 0;
            }
        }

        return $costs;
    }

    public static function calculateTotalUpkeepCostsByResourceType(DivisionType ...$divisionTypes): array {
        return DivisionType::calculateTotalCostsByResourceType(DivisionType::getUpkeepCostsByType(), ...$divisionTypes);
    }

    private static function calculateTotalCostsByResourceType(array $costsByType, DivisionType ...$divisionTypes): array {
        $costs = [];

        foreach(ResourceType::cases() as $resourceType) {
            $costs[$resourceType->value] = 0;
            foreach ($divisionTypes as $divisionType) {
                $costs[$resourceType->value] += $costsByType[$divisionType->value][$resourceType->value];
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
                moves: $meta->moves,
                can_take_territory: $meta->canTakeTerritory,
                can_fly: $meta->canFly,
            );
        }

        return $types;
    }
}