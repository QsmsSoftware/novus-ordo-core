<?php

namespace App\Models;

use App\Domain\SharedAssetType;
use App\Domain\GenerationData;
use App\Domain\Ranking;
use App\Domain\TerritoryConnectionData;
use App\Domain\VictoryGoal;
use App\Domain\VictoryProgress;
use App\Domain\VictoryStatus;
use App\Utils\GuardsForAssertions;
use App\Facades\RuntimeInfo;
use App\ReadModels\GameInfo;
use App\ReadModels\GameReadyStatusInfo;
use App\ReadModels\RankingInfo;
use App\ReadModels\VictoryGoalInfo;
use App\ReadModels\VictoryStatusInfo;
use App\Services\StaticJavascriptResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use LogicException;
use PhpOption\Option;

readonly class GameHasNotEnoughFreeTerritories {
    public function __construct(
        public int $required,
        public int $remaining
    ) {}
}

readonly class GameHasEnoughFreeTerritories {}

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

    public function getTerritoryWithId(int $territoryId, bool $cache = false): Territory {
        if ($cache) {
            static $cachedTerritoriesByGameIdTerritoryId = [];

            if (!isset($cachedTerritoriesByGameIdTerritoryId[$this->game_id][$territoryId])) {
                $cachedTerritoriesByGameIdTerritoryId[$this->game_id][$territoryId] = $this->territories()->find($territoryId);
            }

            return $cachedTerritoriesByGameIdTerritoryId[$this->game_id][$territoryId];
        }

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

    public function sharedAssetsOfType(SharedAssetType $type): HasMany {
        return $this->hasMany(GameSharedStaticAsset::class)
            ->where('game_id', $this->getId())
            ->where(GameSharedStaticAsset::FIELD_ASSET_TYPE, $type->value);
    }

    public function availableSharedAssetsOfType(SharedAssetType $type): HasMany {
        return $this->sharedAssetsOfType($type)
            ->where(GameSharedStaticAsset::whereAvailable());
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

    public function getUsableLandKm2(): int {
        return $this->territories()->get()->sum(fn (Territory $t) => $t->getUsableLandKm2());
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

                if ($this->getVictoryStatus() == VictoryStatus::HasBeenWon) {
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
                    ->filter(fn (Division $d) => $d->getDetail($currentTurn)->isEngaging())
                    ->groupBy([
                        fn (Division $d) => $d->getNation()->getId() . '-' . $d->getDetail($currentTurn)->getOrder()->getTargetTerritory()->getId()
                    ])
                    ->shuffle();
                foreach ($divisionsByOwnerAndDestinationTerritory as $attackingDivisions) {
                    $destinationTerritoryId = Division::notNull($attackingDivisions->first())
                        ->getDetail($currentTurn)
                        ->getOrder()
                        ->getTargetTerritory()
                        ->getId();
                    $destinationTerritory = $this->getTerritoryWithId($destinationTerritoryId);
                    Battle::resolveBattle($destinationTerritory, $currentTurn, $nextTurn, $attackingDivisions);
                    $attackingDivisions->each(fn (Division $d) => $d->getDetail($currentTurn)->getOrder()->onExecution());
                }

                $this->activeDivisionsInTurn($currentTurn)->get()->each(fn (Division $d) => $d->afterBattlePhase($currentTurn, $nextTurn));

                $this->updateVictoryStatus($nextTurn);

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

            $this->updateVictoryStatus($currentTurn);
        });

        if (!$gotLock) {
            // Assuming that another next turn or rollback command is executing, waiting for the execution to finish.
            $lock->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});
        }
    }
    
    private function updateVictoryStatus(Turn $turn): void {
        $winnerOrNull = $this->getWinnerOrNull($turn);

        $this->victory_status = is_null($winnerOrNull) ? VictoryStatus::HasNotBeenWon : VictoryStatus::HasBeenWon->value;

        $this->save();
    }

    public function disable(): void {
        $this->is_active = false;
    }

    public function exportRankings(Turn $turn): array {
        $nations = $this->nations;
        assert($nations instanceof Collection);
        $details = $nations->map(fn (Nation $n) => $n->getDetail($turn));

        $rankingMetas = Ranking::getRankings();
        $nationRankings = Ranking::rankNations(...$details);

        $exported = [];

        foreach ($rankingMetas as $index => $rankingMeta) {
            assert($rankingMeta instanceof Ranking);
            $rankedNations = $nationRankings[$index];
            assert($rankedNations instanceof Collection);
            $exported[] = new RankingInfo(
                title: $rankingMeta->title,
                ranked_nation_ids: $rankedNations->keys()->map(fn ($nationId) => $nationId)->values()->all(),
                data_unit: $rankingMeta->unit->name,
                data: $rankedNations->map(fn ($v) => $v)->values()->all(),
            );
        }

        return $exported;
    }

    public function exportVictoryStatus(): VictoryStatusInfo {
        $turn = $this->getCurrentTurn();
        return VictoryStatusInfo::from(
            victoryStatus: $this->getVictoryStatus(),
            winnerNationId: $this->getWinnerOrNull($turn)?->getId(),
            goals: collect($this->getGoals($turn)),
            progressions: collect($this->getVictoryProgression($turn)),
        );
    }

    public function getRankingsClientResource(Turn $turn): StaticJavascriptResource {
        return StaticJavascriptResource::forTurn(
            'rankings-turn-js',
            fn() => "let allRankings = " . json_encode($this->exportRankings($turn)) . ";",
            $turn
        );
    }

    public function getRequiredTerritoriesForVictory(): int {
        return floor(Game::REQUIRED_OWNERSHIP_RATIO_FOR_VICTORY * $this->territories()->where(Territory::whereIsControllable())->count()) + 1;
    }

    private function getGoals(Turn $turn): array {
        return VictoryGoal::getGoals($this, $turn);
    }

    private function getVictoryProgression(Turn $turn): array {
        return VictoryGoal::getNationProgressions(...$turn->nationDetails);
    }

    private function getWinnerOrNull(Turn $turn): ?Nation {
        if ($this->nations()->count() < 1) {
            return null;
        }

        $goals = collect($this->getGoals($turn));
        $progressions = $this->getVictoryProgression($turn);

        $tops = $goals->map(fn (VictoryGoal $g) => $progressions[$g->title]->sortByDesc(fn (VictoryProgress $p) => $p->progress)->first());

        if ($tops->some(fn (VictoryProgress $p) => !$p->isFulfilled)) {
            return null;
        }

        $onesOnTop = $tops->unique(fn (VictoryProgress $p) => $p->nationId);

        if ($onesOnTop->count() != 1) {
            return null;
        }
        
        return Nation::notNull($this->nations()->find($onesOnTop->first()->nationId));
    }

    public function getNationWithIdOrNull(int $nationId): ?Nation {
        return $this->nations()->find($nationId);
    }

    public function getDivisionWithIdOrNull(int $divisionId): ?Division {
        return $this->divisions()->find($divisionId);
    }

    public function hasEnoughTerritoriesForNewNation(): GameHasNotEnoughFreeTerritories|GameHasEnoughFreeTerritories {
        $freeTerritories = $this->freeSuitableTerritoriesInTurn()->take(Game::NUMBER_OF_STARTING_TERRITORIES)->get();

        if ($freeTerritories->count() < Game::NUMBER_OF_STARTING_TERRITORIES) {
            return new GameHasNotEnoughFreeTerritories(Game::NUMBER_OF_STARTING_TERRITORIES, $freeTerritories->count());
        }

        return new GameHasEnoughFreeTerritories();
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

        GameSharedStaticAsset::inventory($game);

        $turn->activate();

        return $game;
    }
}