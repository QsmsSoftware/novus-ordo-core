<?php

namespace App\Models;

use App\Domain\DivisionType;
use App\Domain\OrderType;
use App\Models\Division;
use App\Models\Territory;
use App\Models\Turn;
use App\ReadModels\AttackOrderInfo;
use App\ReadModels\DisbandOrderInfo;
use App\ReadModels\MoveOrderInfo;
use App\ReadModels\RaidOrderInfo;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use SoftDeletes;
    use GuardsForAssertions;

    public function getId(): int {
        return $this->id;
    }

    public function getType(): OrderType {
        return OrderType::from($this->type);
    }

    public function destinationTerritory(): BelongsTo {
        return $this->belongsTo(Territory::class, 'destination_territory_id');
    }

    public function targetTerritory(): BelongsTo {
        return $this->belongsTo(Territory::class, 'target_territory_id');
    }

    public function getDestinationTerritory(): Territory {
        return $this->destinationTerritory;
    }

    public function getTargetTerritory(): Territory {
        return $this->targetTerritory;
    }

    public function onExecution(): void {
        $this->save();
    }

    public static function getTotalCostsByResourceType(Nation $nation, Turn $turn): array {
        $attackingTypes = DB::table('orders')
            ->where('orders.nation_id', $nation->getId())
            ->where('orders.turn_id', $turn->getId())
            ->where('orders.type', OrderType::Attack->value)
            ->whereNull('orders.deleted_at')
            ->join('divisions', 'orders.division_id', '=', 'divisions.id')
            ->pluck('divisions.division_type')
            ->map(fn (int $type) => DivisionType::from($type));

        return DivisionType::calculateTotalAttackCostsByResourceType(...$attackingTypes);
    }

    public function exportForOwner(): object {
        return match($this->getType()) {
            OrderType::Move => new MoveOrderInfo(
                $this->division_id,
                order_type: OrderType::Move->name,
                destination_territory_id: $this->getDestinationTerritory()->getId(),
                is_operating: false,
            ),
            OrderType::Attack => new AttackOrderInfo(
                $this->division_id,
                order_type: OrderType::Attack->name,
                rebase_territory_id: $this->getDestinationTerritory()->getId(),
                target_territory_id: $this->getTargetTerritory()->getId(),
                is_operating: true,
            ),
            OrderType::Raid => new RaidOrderInfo(
                $this->division_id,
                order_type: OrderType::Raid->name,
                target_territory_id: $this->getTargetTerritory()->getId(),
                is_operating: true,
            ),
            OrderType::Disband => new DisbandOrderInfo(
                $this->division_id,
                order_type: OrderType::Disband->name,
                is_operating: false,
            ),
        };
    }

    private static function prepareBaseOrder(Division $division, OrderType $type): Order {
        $order = new Order();
        $order->game_id = $division->getGame()->getId();
        $order->nation_id = $division->getNation()->getId();
        $order->division_id = $division->getId();
        $order->turn_id = $division->getGame()->getCurrentTurn()->getId();
        $order->type = $type->value;

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

    public static function createAttackOrder(Division $division, Territory $targetTerritory, Territory $rebaseTerritory): Order {
        $order = Order::prepareBaseOrder($division, OrderType::Attack);
        $order->destination_territory_id = $rebaseTerritory->getId();
        $order->target_territory_id = $targetTerritory->getId();
        $order->save();

        return $order;
    }

    public static function createRaidOrder(Division $division, Territory $targetTerritory): Order {
        $order = Order::prepareBaseOrder($division, OrderType::Raid);
        $order->destination_territory_id = null;
        $order->target_territory_id = $targetTerritory->getId();
        $order->save();

        return $order;
    }
}
