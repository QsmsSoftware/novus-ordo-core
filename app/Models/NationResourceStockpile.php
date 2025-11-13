<?php

namespace App\Models;

use App\Domain\ResourceType;
use App\ModelTraits\ReplicatesForTurns;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class NationResourceStockpile extends Model
{
    use ReplicatesForTurns;
    use GuardsForAssertions;

    public function getAvailableQuantity(): float {
        return $this->available_quantity;
    }

    public function getResourceType(): ResourceType {
        return ResourceType::from($this->resource_type);
    }

    public function onNextTurn(float $balance): void {
        if ($this->available_quantity < -$balance) {
            throw new InvalidArgumentException("balance: must be less than the current available quantity ({$this->available_quantity}) if it's a debit.");
        }

        $this->available_quantity += $balance;
        $this->save();
    }

    public static function create(Nation $nation, Turn $turn, ResourceType $resourceType, float $initialQuantity): NationResourceStockpile {
        if ($initialQuantity < 0) {
            throw new InvalidArgumentException("initialQuantity: must be greater than or equal to 0.");
        }

        $stockpile = new NationResourceStockpile;
        $stockpile->game_id = $nation->getGame()->getId();
        $stockpile->nation_id = $nation->getId();
        $stockpile->turn_id = $turn->getId();
        $stockpile->resource_type = $resourceType->value;
        $stockpile->available_quantity = $initialQuantity;
        $stockpile->save();

        return $stockpile;
    }
}
