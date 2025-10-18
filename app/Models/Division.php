<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Division extends Model
{
    use GuardsForAssertions;

    public function game(): BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame(): Game {
        return $this->game;
    }

    public function nation(): BelongsTo {
        return $this->belongsTo(Nation::class);
    }

    public function getNation(): Nation {
        return $this->nation;
    }

    public function details(): HasMany {
        return $this->hasMany(DivisionDetail::class);
    }

    public function getMostRecentDetail(?Turn $turnOrNull = null): DivisionDetail {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this->getGame()));
        return $this->details()
            ->where('turn_id', '<=', $turn->getId())
            ->orderByDesc('turn_id')->first();
    }

    public function getDetail(?Turn $turnOrNull = null): DivisionDetail {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this->getGame()));
        return $this->details()->where('turn_id', $turn->getId())->first();
    }

    public function orders(): HasMany {
        return $this->hasMany(Order::class);
    }

    public function sendMoveOrder(Territory $destination): Order {
        $detail = $this->getMostRecentDetail();

        if (!$detail->isActive()) {
            throw new LogicException("Can't give a move order to inactive division {$this->getId()}");
        }

        $previousOrderOrNull = $detail->getOrderOrNull();

        if ($previousOrderOrNull !== null) {
            $previousOrderOrNull->delete();
        }

        return Order::createMoveOrder($this, $destination);
    }

    public function cancelOrder(): void {
        $orderOrNull = $this->getDetail()->getOrderOrNull();

        if ($order = $orderOrNull??false) {
            $order->delete();
        }
    }

    public function getId(): int {
        return $this->id;
    }

    public function onNextTurn(Turn $currentTurn, Turn $nextTurn): void {
        $currentDetail = $this->getDetail($currentTurn);
        $newDetail = $currentDetail->replicateForTurn($nextTurn);
        $newDetail->onNextTurn($currentDetail);
    }

    public function onMovePhase(Turn $currentTurn, Turn $nextTurn): void {
        if ($this->getDetail($currentTurn)->isMoving()) {
            $this->getDetail($nextTurn)->moveTo($this->getDetail($currentTurn)->getOrder()->getDestinationTerritory());
            $this->getDetail($nextTurn)->getOrder()->onExecution();
        }
    }

    public static function create(Deployment $deployment): Division {
        $division = new Division();
        $division->game_id = $deployment->getGame()->getId();
        $division->nation_id = $deployment->getNation()->getId();
        $division->save();

        DivisionDetail::create($division, $deployment->getTerritory());
        
        return $division;
    }
}
