<?php

namespace App\Models;

use App\ModelTraits\ReplicatesForTurns;
use App\ReadModels\TerritoryTurnPublicInfo;
use App\Services\StaticJavascriptResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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

    public function isOwnedByNation(): bool {
        return !is_null($this->owner);
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

    public static function createRuleOwnedByNation(Nation $nation, Turn $turn):Exists {
        return Rule::exists(TerritoryDetail::class, 'territory_id')
            ->where('turn_id', $turn->getId())
            ->where('owner_nation_id', $nation->getId());
    }

    public function assignOwner(Nation|NeutralOwnership $newOwner): void {
        $this->owner_nation_id = $newOwner instanceof NeutralOwnership ? null : $newOwner->getId();
        $this->save();
    }

    public function export(): TerritoryTurnPublicInfo {
        $ownerOrNull = $this->getOwnerOrNull();
        $territory = $this->getTerritory();

        return new TerritoryTurnPublicInfo(
            territory_id: $territory->getId(),
            turn_number: $this->getTurn()->getNumber(),
            owner_nation_id: $ownerOrNull?->getId(),
            stats: [],
        );
    }

    public static function exportAllTurnPublicInfo(Turn $turn): array {
        $territories = DB::table('territory_details')
            ->where('game_id', $turn->getGame()->getId())
            ->where('turn_id', $turn->getId())
            ->get()->all();

        return array_map(fn ($t) => TerritoryTurnPublicInfo::fromObject($t, [
            'turn_number' => $turn->getNumber(),
            'stats' => []
        ]), $territories);
    }

    public static function getAllTerritoriesTurnInfoClientResource(Turn $turn): StaticJavascriptResource {
        return StaticJavascriptResource::forTurn(
            'territories-turn-js',
            fn() => "let allTerritoriesTurnInfo = " . json_encode(TerritoryDetail::exportAllTurnPublicInfo($turn)) . ";",
            $turn
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
