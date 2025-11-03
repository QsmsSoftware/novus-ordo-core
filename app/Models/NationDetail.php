<?php

namespace App\Models;

use App\ModelTraits\ReplicatesForTurns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

readonly class NationInfo 
{
    public function __construct(
        public int $nation_id,
        public int $turn_number,
        public string $usual_name
    ) {}
}

readonly class OwnedNationInfo {
    public function __construct(
        public int $nation_id,
        public int $turn_number,
        public string $usual_name,
        public bool $is_ready_for_next_turn,
    ) {}
}

readonly class BudgetInfo 
{
    public function __construct(
        public int $nation_id,
        public int $turn_number,
        public int $production,
        public int $reserves,
        public int $upkeep,
        public int $expenses,
        public int $available_production,
        public int $max_remaining_deployments,
    ) {}
}

class NationDetail extends Model
{
    use ReplicatesForTurns;

    public function game() :BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame() :Game {
        return $this->game;
    }

    public function nation() :BelongsTo {
        return $this->belongsTo(Nation::class);
    }

    public function getNation() :Nation {
        return $this->nation;
    }

    public function territories() :HasMany {
        $nation = $this->getNation();

        return $nation->getGame()->territories()
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $this->turn_id)
                ->where(TerritoryDetail::FIELD_OWNER_NATION_ID, $nation->getId())
            );
    }

    public function getTerritoryById(int $territoryId) :Territory {
        return $this->territories()->find($territoryId);
    }

    public function activeDivisions() :HasMany {
        return $this->getNation()->divisions()
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $this->turn_id)
                ->where(DivisionDetail::FIELD_IS_ACTIVE, true)
            );
    }

    public function getActiveDivisionWithId(int $divisionId) :Division {
        return $this->activeDivisions()->find($divisionId);
    }

    public function battlesWhereAttacker() :HasMany {
        return $this->getNation()
            ->battlesWhereAttacker()
            ->where('turn_id', $this->turn_id);
    }

    public function battlesWhereDefender() :HasMany {
        return $this->getNation()
            ->battlesWhereDefender()
            ->where('turn_id', $this->turn_id);
    }

    public function getAllBattlesWhereParticipant() :Collection {
        return $this->battlesWhereAttacker()->get()->concat($this->battlesWhereDefender()->get());
    }

    public function export() :NationInfo {
        return new NationInfo($this->getNation()->getId(), $this->getTurn()->getId(), $this->getNation()->getUsualName());
    }

    public function exportForOwner() :OwnedNationInfo {
        return new OwnedNationInfo(
            nation_id: $this->getNation()->getId(),
            turn_number: $this->getTurn()->getNumber(),
            usual_name: $this->getNation()->getUsualName(),
            is_ready_for_next_turn: $this->getNation()->isReadyForNextTurn(),
        );
    }

    public function deployments() :HasMany {
        $turn = $this->getTurn();

        return $this->getNation()->deployments()
            ->where('turn_id', $turn->getId());
    }

    public function deploymentsInTerritory(Territory $territory) :HasMany {
        $turn = $this->getTurn();

        return $this->getNation()->deployments()
            ->where('turn_id', $turn->getId())
            ->where('territory_id', $territory->getId());
    }

    public function getProduction() :int {
        return $this->territories()->count();
    }

    public function getReserves() :int {
        return $this->reserves;
    }

    public function getUpkeep() :int {
        return $this->activeDivisions()
            ->get()
            ->sum(fn (Division $d) => $d->getDetail($this->getTurn())->getUpkeep());
    }

    public function getExpenses() :int {
        return $this->deployments()->count() * Deployment::DIVISION_COST;
    }

    public function getAvailableProduction() :int {
        return max(0, $this->getProduction() + $this->getReserves() - $this->getUpkeep() - $this->getExpenses());
    }

    public function getMaxSustainableUpkeepRemaining() {
        return max(0, $this->getProduction() - $this->getUpkeep());
    }

    public function getMaxRemainingDeployments() :int {
        return min(
            floor($this->getAvailableProduction() / Deployment::DIVISION_COST),
            floor($this->getMaxSustainableUpkeepRemaining() / DivisionDetail::UPKEEP_PER_DIVISION) - $this->deployments()->count()
        );
    }

    public function exportBudget() :BudgetInfo {
        return new BudgetInfo(
            nation_id: $this->getNation()->getId(),
            turn_number: $this->getTurn()->getId(),
            production: $this->getProduction(),
            reserves: $this->getReserves(),
            upkeep: $this->getUpkeep(),
            expenses: $this->getExpenses(),
            available_production: $this->getAvailableProduction(),
            max_remaining_deployments: $this->getMaxRemainingDeployments(),
        );
    }

    public function onNextTurn(NationDetail $current) :void {
        $this->reserves = $current->getAvailableProduction();
        $this->save();
    }

    public static function create(
        Nation $nation
    ) :NationDetail {
        $nation_details = new NationDetail();

        $nation_details->game_id = $nation->getGame()->getId();
        $nation_details->nation_id = $nation->getId();
        $nation_details->turn_id = Turn::getCurrentForGame($nation->getGame())->getId();
        $nation_details->reserves = 0;

    	$nation_details->save();

        return $nation_details;
    }
}
