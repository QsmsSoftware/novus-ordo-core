<?php

namespace App\Models;

use App\ModelTraits\ReplicatesForTurns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

readonly class TerritoryInfo {
    public function __construct(
        public int $territory_id,
        public int $turn_number,
        public string $name,
        public ?int $owner_nation_id,
    ) {}
}

readonly class OwnedTerritoryInfo {
    public function __construct(
        public int $territory_id,
        public int $turn_number,
        public string $name,
        public ?int $owner_nation_id,
    ) {}
}

class NeutralOwnership {}

class TerritoryDetail extends Model
{
    use ReplicatesForTurns;

    public const string FIELD_OWNER_NATION_ID = 'owner_nation_id';

    public function territory(): BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    public function getTerritory(): Territory {
        return $this->territory;
    }

    public function turn(): BelongsTo {
        return $this->belongsTo(Turn::class);
    }

    public function getTurn(): Turn {
        return $this->turn;
    }

    public function owner(): BelongsTo {
        return $this->belongsTo(Nation::class, TerritoryDetail::FIELD_OWNER_NATION_ID);
    }

    public function getOwnerOrNull(): ?Nation {
        return $this->owner;
    }

    public function getOwnerDivisions(): Collection {
        $ownerOrNull = $this->getOwnerOrNull();
        if ($ownerOrNull === null) {
            return collect();
        }
        $owner = Nation::notNull($ownerOrNull);

        return $owner->getDetail($this->getTurn())->activeDivisions()
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $this->turn_id)
                ->where('territory_id', $this->territory_id)
            )
            ->get();
    }

    public function assignOwner(Nation|NeutralOwnership $newOwner): void {
        $this->owner_nation_id = $newOwner instanceof NeutralOwnership ? null : $newOwner->getId();
        $this->save();
    }

    public function export(): TerritoryInfo {
        $ownerOrNull = $this->getOwnerOrNull();

        return new TerritoryInfo(
            territory_id: $this->getTerritory()->getId(),
            turn_number: $this->getTurn()->getNumber(),
            name: $this->getTerritory()->getName(),
            owner_nation_id: $ownerOrNull?->getId()
        );
    }

    public function exportForOwner(): OwnedTerritoryInfo {
        $ownerOrNull = $this->getOwnerOrNull();

        return new OwnedTerritoryInfo(
            territory_id: $this->getTerritory()->getId(),
            turn_number: $this->getTurn()->getNumber(),
            name: $this->getTerritory()->getName(),
            owner_nation_id: $ownerOrNull?->getId()
        );
    }

    public function onNextTurn(TerritoryDetail $current): void {
        $this->save();
    }

    public static function create(Territory $territory): TerritoryDetail {
        $details = new TerritoryDetail();
        $details->game_id = $territory->getGame()->getId();
        $details->territory_id = $territory->getId();
        $details->turn_id = Turn::getCurrentForGame($territory->getGame())->getId();
        $details->save();

        return $details;
    }
}
