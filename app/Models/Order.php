<?php

namespace App\Models;

use App\Domain\OrderType;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

readonly class MoveOrAttackOrderInfo {
    public function __construct(
        public string $order_type,
        public int $destination_territory_id,
    )
    {
        
    }
}

class Order extends Model
{
    use SoftDeletes;
    use GuardsForAssertions;

    public const string FIELD_HAS_BEEN_EXECUTED = 'has_been_executed';

    public function getId(): int {
        return $this->id;
    }

    public function getType(): OrderType {
        return OrderType::from($this->type);
    }

    public function destinationTerritory(): BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    public function getDestinationTerritory(): Territory {
        return $this->destinationTerritory;
    }

    public function onExecution(): void {
        $this->has_been_executed = true;
        $this->save();
    }

    public function exportForOwner(): MoveOrAttackOrderInfo {
        return match($this->getType()) {
            OrderType::Move => new MoveOrAttackOrderInfo(
                    order_type: OrderType::Move->name,
                    destination_territory_id: $this->getDestinationTerritory()->getId(),
                )
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_been_executed' => 'boolean',
        ];
    }


    public static function createMoveOrder(Division $division, Territory $destinationTerritory): Order {
        $order = new Order();
        $order->game_id = $destinationTerritory->getGame()->getId();
        $order->nation_id = $division->getNation()->getId();
        $order->division_id = $division->getId();
        $order->turn_id = Turn::getCurrentForGame($destinationTerritory->getGame())->getId();
        $order->type = OrderType::Move->value;
        $order->has_been_executed = false;
        $order->destination_territory_id = $destinationTerritory->getId();
        $order->save();

        return $order;
    }
}
