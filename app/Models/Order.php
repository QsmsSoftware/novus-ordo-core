<?php

namespace App\Models;

use App\Domain\DivisionType;
use App\Domain\OrderType;
use App\Domain\ResourceType;
use App\Models\Division;
use App\Models\Territory;
use App\Models\Turn;
use App\ReadModels\DisbandOrderInfo;
use App\ReadModels\MoveAttackOrderInfo;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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

    public static function getTotalCostsByResourceType(Nation $nation, Turn $turn): array {
        $deployedTypes = DB::table('orders')
            ->where('orders.nation_id', $nation->getId())
            ->where('orders.turn_id', $turn->getId())
            ->where('orders.type', OrderType::Attack->value)
            ->whereNull('orders.deleted_at')
            ->join('divisions', 'orders.division_id', '=', 'divisions.id')
            ->pluck('divisions.division_type')
            ->map(fn (int $type) => DivisionType::from($type));

        return Order::calculateTotalAttackCostsByResourceType(...$deployedTypes);
    }

    public static function calculateTotalAttackCostsByResourceType(DivisionType ...$deployedTypes): array {
        $costsByType = DivisionType::getAttackCostsByType();

        $costs = [];

        foreach(ResourceType::cases() as $resourceType) {
            $costs[$resourceType->value] = 0;
            foreach ($deployedTypes as $divisionType) {
                $costs[$resourceType->value] += $costsByType[$divisionType->value][$resourceType->value];
            }
        }

        return $costs;
    }

    public function exportForOwner(): object {
        return match($this->getType()) {
            OrderType::Move => new MoveAttackOrderInfo(
                $this->division_id,
                order_type: OrderType::Move->name,
                destination_territory_id: $this->getDestinationTerritory()->getId(),
            ),
            OrderType::Attack => new MoveAttackOrderInfo(
                $this->division_id,
                order_type: OrderType::Attack->name,
                destination_territory_id: $this->getDestinationTerritory()->getId(),
            ),
            OrderType::Disband => new DisbandOrderInfo(
                $this->division_id,
                order_type: OrderType::Disband->name,
            ),
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

    private static function prepareBaseOrder(Division $division, OrderType $type): Order {
        $order = new Order();
        $order->game_id = $division->getGame()->getId();
        $order->nation_id = $division->getNation()->getId();
        $order->division_id = $division->getId();
        $order->turn_id = $division->getGame()->getCurrentTurn()->getId();
        $order->type = $type->value;
        $order->has_been_executed = false;

        return $order;
    }

    public static function createDisbandOrder(Division $division): Order {
        $order = Order::prepareBaseOrder($division, OrderType::Disband);
        $order->save();

        return $order;
    }

    public static function createMoveOrder(Division $division, Territory $destinationTerritory): Order {
        $order = Order::prepareBaseOrder($division, OrderType::Move);
        $order->destination_territory_id = $destinationTerritory->getId();
        $order->save();

        return $order;
    }

    public static function createAttackOrder(Division $division, Territory $destinationTerritory): Order {
        $order = Order::prepareBaseOrder($division, OrderType::Attack);
        $order->destination_territory_id = $destinationTerritory->getId();
        $order->save();

        return $order;
    }
}
