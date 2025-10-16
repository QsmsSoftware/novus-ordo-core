<?php

namespace App\Models;

use App\Domain\OrderType;
use App\ModelTraits\ReplicatesForTurns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

readonly class OwnedDivisionInfo {
    public function __construct(
        public int $division_id,
        public int $nation_id,
        public int $territory_id,
        public ?MoveOrAttackOrderInfo $order,
    )
    {
        
    }
}

class DivisionDetail extends Model
{
    public const UPKEEP_PER_DIVISION = 1;
    public const string FIELD_IS_ACTIVE = "is_active";

    use ReplicatesForTurns;

    public function division() :BelongsTo {
        return $this->belongsTo(Division::class);
    }

    public function getDivision() :Division {
        return $this->division;
    }

    public function nation() :BelongsTo {
        return $this->belongsTo(Nation::class);
    }

    public function getNation() :Nation {
        return $this->nation;
    }

    public function territory() :BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    public function getTerritory() :Territory {
        return $this->territory;
    }

    public function getOrderOrNull() :?Order {
        return $this->getDivision()->orders()
            ->where('turn_id', $this->turn_id)
            ->first();
    }

    public function getOrder() :Order {
        return $this->getOrderOrNull();
    }

    public function getUpkeep() :int {
        return DivisionDetail::UPKEEP_PER_DIVISION;
    }

    public function isActive() :bool {
        return $this->is_active;
    }

    public function isMoving() :bool {
        $orderOrNull = $this->getOrderOrNull();

        return ($order = $orderOrNull??false)
            && $order->getType() == OrderType::Move
            && $this->getNation()->equals($order->getDestinationTerritory()->getDetail()->getOwnerOrNull());
    }

    public function isAttacking() :bool {
        $orderOrNull = $this->getOrderOrNull();

        return ($order = $orderOrNull??false)
            && $order->getType() == OrderType::Move
            && !$this->getNation()->equals($order->getDestinationTerritory()->getDetail()->getOwnerOrNull());
    }

    public function exportForOwner() :OwnedDivisionInfo {
        $division = $this->getDivision();
        return new OwnedDivisionInfo(
            division_id: $division->getId(),
            nation_id: $this->getNation()->getId(),
            territory_id: $this->getTerritory()->getId(),
            order: $this->getOrderOrNull()?->exportForOwner(),
        );
    }

    public function onNextTurn(DivisionDetail $current) :void {
        $this->save();
    }

    public function moveTo(Territory $territory) :void {
        $this->territory_id = $territory->getId();
        $this->save();
    }

    public function disband() :void {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function create(
        Division $division,
        Territory $territory
    ) :DivisionDetail {
        $details = new DivisionDetail();

        $details->game_id = $division->getGame()->getId();
        $details->nation_id = $division->getNation()->getId();
        $details->division_id = $division->getId();
        $details->territory_id = $territory->getId();
        $details->is_active = true;
        $details->turn_id = Turn::getCurrentForGame($division->getNation()->getGame())->getId();
        $details->save();

        return $details;
    }
}
