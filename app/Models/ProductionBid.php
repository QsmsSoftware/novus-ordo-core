<?php

namespace App\Models;

use App\Domain\BidType;
use App\Domain\ProductionBidConstants;
use App\Domain\ResourceType;
use App\ModelTraits\ReplicatesForTurns;
use App\ReadModels\ProductionBidInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProductionBid extends Model
{
    use ReplicatesForTurns;

    public function getResourceType(): ResourceType {
        return ResourceType::from($this->resource_type);
    }

    public function getBidType(): BidType {
        return BidType::from($this->bid_type);
    }

    public function getPriority(): int {
        return $this->priority;
    }

    public function getMaxQuantity(): int {
        return $this->max_quantity;
    }

    public function getMaxLaborPerUnit(): int {
        return $this->max_labor_allocation_per_unit;
    }

    private function setMaxQuantity(int $quantity): void {
        ProductionBid::ensureValidMaxQuantity($quantity);

        $this->max_quantity = $quantity;
        $this->max_labor_allocation_per_unit = ProductionBidConstants::MAX_LABOR_PER_UNIT_LIMIT;
        $this->save();
    }

    private function setMaxQuantityAndLabor(int $maxQuantity, int $maxLaborPerUnit): void {
        ProductionBid::ensureValidMaxQuantity($maxQuantity);
        ProductionBid::ensureValidMaxLaborPerUnit($maxLaborPerUnit);

        $this->max_quantity = $maxQuantity;
        $this->max_labor_allocation_per_unit = $maxLaborPerUnit;
        $this->save();
    }

    public function exportForOwner(): ProductionBidInfo {
        return new ProductionBidInfo(
            resource_type: $this->getResourceType()->name,
            max_quantity: $this->getMaxQuantity(),
            max_labor_allocation_per_unit: $this->getMaxLaborPerUnit(),
            priority: $this->getPriority(),
        );
    }

    public static function getAllCommandBids(NationDetail $nationDetail): Collection {
        return ProductionBid::where('nation_id', $nationDetail->getNationId())
            ->where('turn_id', $nationDetail->getTurnId())
            ->where('bid_type', BidType::Command)
            ->get();
    }

    public static function getCommandBidOrNull(NationDetail $nationDetail, ResourceType $resourceType): ?ProductionBid {
        return ProductionBid::where('nation_id', $nationDetail->getNationId())
            ->where('turn_id', $nationDetail->getTurnId())
            ->where('bid_type', BidType::Command)
            ->where('resource_type', $resourceType->value)
            ->first();
    }

    public static function getCommandBid(NationDetail $nationDetail, ResourceType $resourceType): ProductionBid {
        return ProductionBid::getCommandBidOrNull($nationDetail, $resourceType);
    }

    private static function getBidOrNull(BidType $bidType, NationDetail $nationDetail, ResourceType $resourceType): ?ProductionBid {
        return ProductionBid::where('nation_id', $nationDetail->getNationId())
            ->where('turn_id', $nationDetail->getTurnId())
            ->where('bid_type', $bidType->value)
            ->where('resource_type', $resourceType->value)
            ->first();
    }

    public static function getAll(NationDetail $nationDetail): Collection {
        return ProductionBid::where('nation_id', $nationDetail->getNationId())
            ->where('turn_id', $nationDetail->getTurnId())
            ->get();
    }
    
    public static function setUpkeepBid(NationDetail $nationDetail, ResourceType $resourceType, int $quantity): void {
        ProductionBid::ensureValidMaxQuantity($quantity);

        $bidOrNull = ProductionBid::getBidOrNull(BidType::Upkeep, $nationDetail, $resourceType);

        if (is_null($bidOrNull)) {
            if ($quantity > 0) {
                ProductionBid::createUpkeepBid($nationDetail, $resourceType, $quantity);
            }
        }
        else {
            $bidOrNull->setMaxQuantity($quantity);
        }
    }

    private static function createUpkeepBid(NationDetail $nationDetail, ResourceType $resourceType, int $quantity): ProductionBid {
        ProductionBid::ensureValidMaxQuantity($quantity);

        $bid = new ProductionBid();
        $bid->game_id = $nationDetail->getGameId();
        $bid->nation_id = $nationDetail->getNationId();
        $bid->turn_id = $nationDetail->getTurnId();
        $bid->bid_type = BidType::Upkeep;
        $bid->resource_type = $resourceType->value;
        $bid->max_quantity = $quantity;
        $bid->max_labor_allocation_per_unit = ProductionBidConstants::MAX_LABOR_PER_UNIT_LIMIT;
        $bid->priority = ResourceType::getMeta($resourceType)->upkeepBidPriority->value;
        $bid->save();

        return $bid;
    }

    public static function setCommandBid(NationDetail $nationDetail, ResourceType $resourceType, int $maxQuantity, int $maxLaborPerUnit, int $priority = ProductionBidConstants::HIGHEST_COMMAND_BID_PRIORITY): void {
        ProductionBid::guardAgainstInvalidValues($maxQuantity, $maxLaborPerUnit, $priority);

        $bidOrNull = ProductionBid::getBidOrNull(BidType::Command, $nationDetail, $resourceType);

        if (is_null($bidOrNull)) {
            ProductionBid::createCommandBid($nationDetail, $resourceType, $maxQuantity, $maxLaborPerUnit, $priority);
        }
        else {
            $bidOrNull->setMaxQuantityAndLabor($maxQuantity, $maxLaborPerUnit);
        }
    }

    private static function ensureValidMaxQuantity(int $maxQuantity): void {
        if ($maxQuantity < 0) {
            throw new InvalidArgumentException("maxQuantity: value must be greater or equal to 0");
        }

        if ($maxQuantity > ProductionBidConstants::MAX_QUANTITY_LIMIT) {
            throw new InvalidArgumentException("maxQuantity: value is greater than ProductionBid::MAX_QUANTITY_LIMIT (" . ProductionBidConstants::MAX_QUANTITY_LIMIT . ")");
        }
    }

    private static function ensureValidMaxLaborPerUnit(int $maxLaborPerUnit) {
        if ($maxLaborPerUnit < 0) {
            throw new InvalidArgumentException("maxQuantity: value must be greater or equal to 0");
        }

        if ($maxLaborPerUnit > ProductionBidConstants::MAX_LABOR_PER_UNIT_LIMIT) {
            throw new InvalidArgumentException("maxLaborPerUnit: value is greater than ProductionBid::MAX_LABOR_PER_UNIT_LIMIT (" . ProductionBidConstants::MAX_LABOR_PER_UNIT_LIMIT . ")");
        }
    }

    private static function ensureValidPriority(int $priority) {
        if ($priority < ProductionBidConstants::HIGHEST_COMMAND_BID_PRIORITY) {
            throw new InvalidArgumentException("maxQuantity: value is lower than ProductionBid::HIGHEST_COMMAND_BID_PRIORITY (" . ProductionBidConstants::HIGHEST_COMMAND_BID_PRIORITY . ")");
        }

        if ($priority > ProductionBidConstants::LOWEST_COMMAND_BID_PRIORITY) {
            throw new InvalidArgumentException("priority: value is greater than ProductionBid::LOWEST_COMMAND_BID_PRIORITY (" . ProductionBidConstants::LOWEST_COMMAND_BID_PRIORITY . ")");
        }
    }

    private static function guardAgainstInvalidValues(int $maxQuantity, int $maxLaborPerUnit, int $priority): void {
        ProductionBid::ensureValidMaxQuantity($maxQuantity);
        ProductionBid::ensureValidMaxLaborPerUnit($maxLaborPerUnit);
        ProductionBid::ensureValidPriority($priority);
    }

    private static function createCommandBid(NationDetail $nationDetail, ResourceType $resourceType, int $maxQuantity, int $maxLaborPerUnit, int $priority = ProductionBidConstants::HIGHEST_COMMAND_BID_PRIORITY): ProductionBid {
        ProductionBid::guardAgainstInvalidValues($maxQuantity, $maxLaborPerUnit, $priority);

        $bid = new ProductionBid();
        $bid->game_id = $nationDetail->getGameId();
        $bid->nation_id = $nationDetail->getNationId();
        $bid->turn_id = $nationDetail->getTurnId();
        $bid->bid_type = BidType::Command;
        $bid->resource_type = $resourceType->value;
        $bid->max_quantity = $maxQuantity;
        $bid->max_labor_allocation_per_unit = $maxLaborPerUnit;
        $bid->priority = $priority;
        $bid->save();

        return $bid;
    }
}
