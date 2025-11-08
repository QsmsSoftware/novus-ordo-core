<?php

namespace App\Models;

use App\Domain\GenerationData;
use App\Domain\TerritoryConnectionData;
use App\Utils\GuardsForAssertions;
use App\Facades\RuntimeInfo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use LogicException;
use PhpOption\Option;

enum VictoryStatus :int {
    case HasNotBeenWon = 0;
    case HasBeenWon = 1;
}

readonly class NotEnoughFreeTerritories {
    public function __construct(
        public int $required,
        public int $remaining
    ) {}
}

readonly class EnoughFreeTerritories {}

readonly class VictoryProgress {
    public float $progress;
    public bool $isVictorious;

    public function __construct(
        public int $nationId,
        public int $numberOfTerritories,
        public int $numberOfTerritoriesRequired
    ) {
        $this->progress = min($this->numberOfTerritories / $this->numberOfTerritoriesRequired, 1);
        $this->isVictorious = $this->numberOfTerritories >= $this->numberOfTerritoriesRequired;
    }
}

readonly class GameReadyStatusInfo {
    public function __construct(
        public int $turn_number,
        public array $ready_for_next_turn_nation_ids,
        public int $nation_count,
        public ?CarbonImmutable $turn_expiration,
        public bool $is_game_ready,
    )
    {
    
    }
}

readonly class GameInfo {
    public function __construct(
        public int $game_id,
        public int $turn_number,
    ) {}
}

class Game extends Model
{
    use GuardsForAssertions;

    const int NUMBER_OF_STARTING_TERRITORIES = 5;
    const float REQUIRED_OWNERSHIP_RATIO_FOR_VICTORY = 0.5;

    public function nations(): HasMany {
        return $this->hasMany(Nation::class);
    }

    public function nationsStillNotReadyForNextTurn(): HasMany {
        return $this->hasMany(Nation::class)
            ->whereNot(Nation::whereReadyForNextTurn());
    }

    public function nationsReadyForNextTurn(): HasMany {
        return $this->hasMany(Nation::class)
            ->where(Nation::whereReadyForNextTurn());
    }

    public function territories(): HasMany {
        return $this->hasMany(Territory::class);
    }

    public function turns(): HasMany {
        return $this->hasMany(Turn::class);
    }

    public function currentTurn(): HasOne {
        return $this->hasOne(Turn::class)
            ->whereNotNull(Turn::FIELD_TURN_ACTIVATED_AT)
            ->latestOfMany();
    }

    public function getCurrentTurn(): Turn {
        return $this->currentTurn;
    }

    private function lastTurn(): HasOne {
        return $this->hasOne(Turn::class)
            ->latestOfMany();
    }

    public function freeSuitableTerritoriesInTurn(?Turn $turnOrNull = null): HasMany {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this));
        return $this->territories()
            ->where(Territory::whereIsSuitableAsHome())
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $turn->getId())
                ->whereNull(TerritoryDetail::FIELD_OWNER_NATION_ID)
            );
    }

    public function alreadyTakenTerritoriesInTurn(?Turn $turnOrNull = null): HasMany {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this));
        return $this->territories()
            ->where(Territory::whereIsSuitableAsHome())
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $turn->getId())
                ->whereNotNull(TerritoryDetail::FIELD_OWNER_NATION_ID)
            );
    }

    public function getTerritoryWithId(int $territoryId): Territory {
        return $this->territories()->find($territoryId);
    }

    public function divisions(): HasMany {
        return $this->hasMany(Division::class);
    }

    public function activeDivisionsInTurn(?Turn $turnOrNull = null): HasMany {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this));
        return $this->divisions()
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $turn->getId())
                ->where(DivisionDetail::FIELD_IS_ACTIVE, true)
            );
    }

    public function deployments(): HasMany {
        return $this->hasMany(Deployment::class);
    }

    public function getDeploymentWithIdOrNull(int $deploymentId): ?Deployment {
        return $this->deployments()->find($deploymentId);
    }

    public function isActive(): bool {
        return $this->is_active;
    }

    public function exportForTurn(?Turn $turnOrNull = null): GameInfo {
        $turn = $turnOrNull ?? Turn::getCurrentForGame($this);
        return new GameInfo(
            game_id: $this->getId(),
            turn_number: $turn->getNumber(),
            
        );
    }

    public function exportReadyStatus(): GameReadyStatusInfo {
        Cache::lock($this->getCacheLockKeyForChangeTurn(), RuntimeInfo::maxExectutionTimeSeconds() * 0.8)
            ->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});
        $turn = $this->getCurrentTurn();
        return new GameReadyStatusInfo(
            turn_number: $turn->getNumber(),
            ready_for_next_turn_nation_ids: $this->nationsReadyForNextTurn()->pluck('id')->all(),
            nation_count: $this->nations()->count(),
            turn_expiration: $turn->getExpirationOrNull(),
            is_game_ready: !$this->getCurrentTurn()->hasEnded(),
        );
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

    public function getId(): int {
        return $this->getKey();
    }
    
    public function getVictoryStatus(): VictoryStatus {
        return VictoryStatus::from($this->victory_status);
    }

    public function tryNextTurnIfNationsReady(Turn $turnToEnd): Turn {
        if (!$this->nationsStillNotReadyForNextTurn()->exists()) {
            return $this->tryNextTurn($turnToEnd);
        }

        return $this->getCurrentTurn();
    }

    public function isUpkeeping(): bool {
        $creatingGame = !Cache::lock(Game::CacheLockKeyCritalSectionCreateGame, 1)
            ->get(fn () => true);

        if ($creatingGame) {
            return true;
        }

        return !Cache::lock($this->getCacheLockKeyForChangeTurn(), 1)
            ->get(fn () => true);
    }

    private function getCacheLockKeyForChangeTurn(): string {
        return "critical_section:change_turn_game_{$this->getId()}";
    }

    public function tryNextTurn(Turn $turnToEnd): Turn {
        $lock = Cache::lock($this->getCacheLockKeyForChangeTurn(), RuntimeInfo::maxExectutionTimeSeconds() * 0.8);

        $gotLock = $lock->get(function () use ($turnToEnd) {
                $currentTurn = Turn::getCurrentForGame($this);

                if ($turnToEnd->getId() != $currentTurn->getId()) {
                    return;
                }

                $currentTurn->end();

                $nextTurn = $currentTurn->createNext();

                // Upkeep.
                $this->nations()->get()->each(fn (Nation $n) => $n->onNextTurn($currentTurn, $nextTurn));
                $this->territories()->get()->each(fn (Territory $t) => $t->onNextTurn($currentTurn, $nextTurn));
                $this->activeDivisionsInTurn($currentTurn)->get()->each(fn (Division $d) => $d->onNextTurn($currentTurn, $nextTurn));

                // Move divisions.
                $this->activeDivisionsInTurn($currentTurn)->get()->each(fn (Division $d) => $d->onMovePhase($currentTurn, $nextTurn));

                // Attacks.
                $divisionsByOwnerAndDestinationTerritory = $this->activeDivisionsInTurn($currentTurn)->get()
                    ->filter(fn (Division $d) => $d->getDetail($currentTurn)->isAttacking())
                    ->groupBy([
                        fn (Division $d) => $d->getNation()->getId() . '-' . $d->getDetail($currentTurn)->getOrder()->getDestinationTerritory()->getId()
                    ])
                    ->shuffle();
                foreach ($divisionsByOwnerAndDestinationTerritory as $attackingDivisions) {
                    $destinationTerritoryId = Division::notNull($attackingDivisions->first())
                        ->getDetail($currentTurn)
                        ->getOrder()
                        ->getDestinationTerritory()
                        ->getId();
                    $destinationTerritory = $this->getTerritoryWithId($destinationTerritoryId);
                    Battle::resolveBattle($destinationTerritory, $attackingDivisions);
                    $attackingDivisions->each(fn (Division $d) => $d->getDetail($currentTurn)->getOrder()->onExecution());
                }

                $this->activeDivisionsInTurn($currentTurn)->get()->each(fn (Division $d) => $d->afterBattlePhase($currentTurn, $nextTurn));

                $this->updateVictoryStatus();

                $this->save();

                $nextTurn->activate();

                Nation::resetAllReadyForNextTurnStatuses($this);
            });
        
        if (!$gotLock) {
            // Assuming that another next turn or rollback command is executing, waiting for the execution to finish.
            $lock->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});
        }

        return Turn::getCurrentForGame($this);
    }

    public function rollbackLastTurn(): void {
        $lock = Cache::lock($this->getCacheLockKeyForChangeTurn(), RuntimeInfo::maxExectutionTimeSeconds() * 0.8);

        $gotLock = $lock->get(function () {
            $lastTurn = Turn::getCurrentForGame($this);

            if ($lastTurn->getNumber() == 1) {
                throw new LogicException("Can't roll back the first turn!");
            }

            $lastTurn->delete(); // Will cascade.

            $currentTurn = Turn::getCurrentForGame($this);

            $currentTurn->reset();
            $currentTurn->deployments()->rawUpdate([ Deployment::FIELD_HAS_BEEN_DEPLOYED => false]);
            $currentTurn->orders()->rawUpdate([ Order::FIELD_HAS_BEEN_EXECUTED => false]);

            $this->updateVictoryStatus();
        });

        if (!$gotLock) {
            // Assuming that another next turn or rollback command is executing, waiting for the execution to finish.
            $lock->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});
        }
    }
    
    private function updateVictoryStatus(): void {
        $winnerOrNull = $this->getWinnerOrNull();

        $this->victory_status = is_null($winnerOrNull) ? VictoryStatus::HasNotBeenWon : VictoryStatus::HasBeenWon->value;

        $this->save();
    }

    public function disable(): void {
        $this->is_active = false;
    }

    public function getRequiredTerritoriesForVictory(): int {
        return floor(Game::REQUIRED_OWNERSHIP_RATIO_FOR_VICTORY * $this->territories()->count()) + 1;
    }

    public function getVictoryProgression(): Collection {
        $requiredTerritories = $this->getRequiredTerritoriesForVictory();
        return $this->nations()->get()
            ->map(fn (Nation $nation) => new VictoryProgress($nation->getId(), $nation->getDetail()->territories()->count(), $requiredTerritories))
            ->sortByDesc(fn (VictoryProgress $p) => $p->progress);
    }

    public function getWinnerOrNull(): ?Nation {
        $progressions = $this->getVictoryProgression();

        $victoryOrNull = $progressions->first(fn (VictoryProgress $p, int $nationId) => $p->isVictorious);

        return match(true) {
            $victoryOrNull instanceof VictoryProgress => Nation::notNull($this->nations()->find($victoryOrNull->nationId)),
            $victoryOrNull === null => null,
        };
    }

    public function getNationWithIdOrNull(int $nationId): ?Nation {
        return $this->nations()->find($nationId);
    }

    public function getDivisionWithIdOrNull(int $divisionId): ?Division {
        return $this->divisions()->find($divisionId);
    }

    public function hasEnoughTerritoriesForNewNation(): NotEnoughFreeTerritories|EnoughFreeTerritories {
        $freeTerritories = $this->freeSuitableTerritoriesInTurn()->take(Game::NUMBER_OF_STARTING_TERRITORIES)->get();

        if ($freeTerritories->count() < Game::NUMBER_OF_STARTING_TERRITORIES) {
            return new NotEnoughFreeTerritories(Game::NUMBER_OF_STARTING_TERRITORIES, $freeTerritories->count());
        }

        return new EnoughFreeTerritories();
    }

    public static function getCurrentOrNull(): ?Game {
        return Game::where('is_active', 1)
            ->first();
    }

    public static function getCurrent(): Game {
        return Game::where('is_active', 1)
            ->first();
    }
    private const CacheLockKeyCritalSectionCreateGame = "critical_section:create_game";

    public static function createNew(): Game {
        $lock = Cache::lock(Game::CacheLockKeyCritalSectionCreateGame, RuntimeInfo::maxExectutionTimeSeconds() * 0.8);

        $gameOrFalsy = $lock->get(function () {
            $currentGameOrNull = Game::getCurrentOrNull();

            Option::fromValue($currentGameOrNull)->forAll(function (Game $currentGame) {
                $currentGame->disable();
                $currentGame->save();
            });

            return Game::create();
        });

        if (!$gameOrFalsy) {
            // Assuming that another create game command is executing, waiting for the execution to finish.
            $lock->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});

            return Game::getCurrent();
        }
        else {
            return $gameOrFalsy;
        }
    }

    private static function create() {
        $game = new Game();
        $game->is_active = true;
        $game->victory_status = VictoryStatus::HasNotBeenWon;
        $game->save();

        $turn = Turn::createFirst($game);

        $mapData = GenerationData::getMapData();

        $territoriesByCoords = [];

        foreach($mapData->territories as $territoryData) {
            $territory = Territory::create($game, $territoryData);
            $territoriesByCoords[$territory->getX()][$territory->getY()] = $territory;
        }

        foreach($mapData->territories as $territoryData) {
            $territory = Territory::notNull($territoriesByCoords[$territoryData->x][$territoryData->y]);
            collect($territoryData->connections)->map(fn (TerritoryConnectionData $c) => $territory->connectedTerritories()->attach($territoriesByCoords[$c->x][$c->y], ['game_id' => $game->getId(), 'is_connected_by_land' => $c->isConnectedByLand]));
        }

        $turn->activate();

        return $game;
    }
}