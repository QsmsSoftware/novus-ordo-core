<?php

namespace App\Models;

use App\Domain\DivisionType;
use App\Domain\ResourceType;
use App\Domain\SharedAssetType;
use App\Domain\StatUnit;
use App\Domain\TerrainType;
use App\Facades\Metacache;
use App\ModelTraits\ReplicatesForTurns;
use App\ReadModels\BudgetInfo;
use App\ReadModels\DemographicStat;
use App\ReadModels\NationTurnOwnerInfo;
use App\ReadModels\NationTurnPublicInfo;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NationDetail extends Model
{
    use ReplicatesForTurns;

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

    public function territories(): HasMany {
        $nation = $this->getNation();

        return $nation->getGame()->territories()
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $this->turn_id)
                ->where(TerritoryDetail::FIELD_OWNER_NATION_ID, $nation->getId())
            );
    }

    public function getTerritoryById(int $territoryId): Territory {
        return $this->territories()->find($territoryId);
    }

    public function activeDivisions(): HasMany {
        return $this->getNation()->divisions()
            ->whereHas('details', fn (Builder $query) => $query
                ->where('turn_id', $this->turn_id)
                ->where(DivisionDetail::FIELD_IS_ACTIVE, true)
            );
    }

    public function getActiveDivisionWithId(int $divisionId): Division {
        return $this->activeDivisions()->find($divisionId);
    }

    public function battlesWhereAttacker(): HasMany {
        return $this->getNation()
            ->battlesWhereAttacker()
            ->where('turn_id', $this->turn_id);
    }

    public function battlesWhereDefender(): HasMany {
        return $this->getNation()
            ->battlesWhereDefender()
            ->where('turn_id', $this->turn_id);
    }

    public function getAllBattlesWhereParticipant(): Collection {
        return $this
            ->battlesWhereAttacker()
            ->get()
            ->concat($this
                ->battlesWhereDefender()
                ->get()
            );
    }

    public function stockpiles(): HasMany {
        return $this
            ->getNation()
            ->hasMany(NationResourceStockpile::class)
            ->where('turn_id', $this->getTurn()->getId());
    }

    public function getStockpiles(): Collection {
        return $this->stockpiles->mapWithKeys(fn (NationResourceStockpile $stockpile) => [$stockpile->getResourceType()->value => $stockpile]);
    }

    public function exportForOwner(): NationTurnOwnerInfo {
        return new NationTurnOwnerInfo(
            nation_id: $this->getNation()->getId(),
            turn_number: $this->getTurn()->getNumber(),
            is_ready_for_next_turn: $this->getNation()->isReadyForNextTurn(),
            stats: [new DemographicStat('test2', 1 * Territory::TERRITORY_AREA_KM2, StatUnit::Percent->name)],
        );
    }

    public function export(): NationTurnPublicInfo {
        return new NationTurnPublicInfo(
            nation_id: $this->getNation()->getId(),
            turn_number: $this->getTurn()->getNumber(),
            usual_name: $this->getUsualName(),
            formal_name: $this->getFormalName(),
            flag_src: $this->getFlagSrcOrNull(),
            stats: [
                new DemographicStat('Total land area', Metacache::remember($this->getUsableLandKm2(...)), StatUnit::Km2->name),
                new DemographicStat('Population', $this->getPopulationSize(), StatUnit::WholeNumber->name)
            ],
        );
    }

    public function getUsualName(): string {
        return $this->usual_name;
    }

    public function getFormalName(): string {
        return $this->formal_name;
    }

    public function getFlagSrcOrNull(): ?string {
        return $this->flag_src;
    }

    public function getUsableLandKm2(): int {
        return $this->territories()->get()->sum(fn (Territory $t) => $t->getUsableLandKm2());
    }

    public function getPopulationSize(): int {
        return DB::table('territory_details')
            ->where(TerritoryDetail::FIELD_OWNER_NATION_ID, $this->getNation()->getId())
            ->where('turn_id', $this->getTurn()->getId())
            ->sum(TerritoryDetail::FIELD_POPULATION_SIZE);
    }

    public function deployments(): HasMany {
        $turn = $this->getTurn();

        return $this->getNation()->deployments()
            ->where('turn_id', $turn->getId());
    }

    public function deploymentsInTerritory(Territory $territory): HasMany {
        $turn = $this->getTurn();

        return $this->getNation()->deployments()
            ->where('turn_id', $turn->getId())
            ->where('territory_id', $territory->getId());
    }

    public function getProduction(ResourceType $resourceType): float {
        $territoryProductionsByTerrainResource = TerrainType::getResourceProductionByTerrainResource();
        $countByTerrain = $this->territories()
            ->pluck(Territory::FIELD_TERRAIN_TYPE)
            ->countBy();

        assert($countByTerrain instanceof Collection);
        
        return $countByTerrain->reduceWithKeys(fn (float $sum, $count, $terrain) => $sum + $territoryProductionsByTerrainResource[$terrain][$resourceType->value] * $count, 0);
    }

    public function getStockpiledQuantity(ResourceType $resourceType): float {
        $stockpileOrNull = $this->stockpiles()->where('resource_type', $resourceType->value)->first();

        if (is_null($stockpileOrNull)) {
            // No reserve, first turn for this nation or newly introduced resource type.

            return 0;
        }

        return NationResourceStockpile::notNull($stockpileOrNull)->getAvailableQuantity();
    }

    public function getUpkeep(ResourceType $resourceType): float {
        $divisionUpkeepCosts = DivisionDetail::getTotalUpkeepCostsByResourceType($this->getNation(), $this->getTurn());

        return $divisionUpkeepCosts[$resourceType->value]
            + match($resourceType) {
                ResourceType::Food => $this->getPopulationSize() / Territory::UNIT_OF_POPULATION_SIZE,
                default => 0,
            };
    }

    public function getExpenses(ResourceType $resourceType): float {
        $deploymentExpenses = Deployment::getTotalCostsByResourceType($this->getNation(), $this->getTurn());
        $orderExpenses = Order::getTotalCostsByResourceType($this->getNation(), $this->getTurn());

        return $deploymentExpenses[$resourceType->value] + $orderExpenses[$resourceType->value];
    }

    public function getAvailableProduction(ResourceType $resourceType): float {
        return max(0, $this->getStockpiledQuantity($resourceType) + $this->getBalance($resourceType));
    }

    public function getBalance(ResourceType $resourceType): float {
        return $this->getProduction($resourceType) - $this->getUpkeep($resourceType) - $this->getExpenses($resourceType);
    }

    public function canAffordCosts(array $costs): bool {
        foreach(ResourceType::cases() as $resourceType) {
            if ($costs[$resourceType->value] > $this->getAvailableProduction($resourceType)) {
                return false;
            }
        }

        return true;
    }

    public function isHostileTerritory(Territory $territory): bool {
        return !$this->territories()
            ->where('id', $territory->getId())
            ->exists();
    }

    private function exportBalances(): array {
        $stockpiles = [];
        foreach (ResourceType::cases() as $resourceType) {
            $stockpiles[$resourceType->name] = $this->getBalance($resourceType);
        }

        return $stockpiles;
    }

    private function exportAvailableProduction(): array {
        $stockpiles = [];
        foreach (ResourceType::cases() as $resourceType) {
            $stockpiles[$resourceType->name] = $this->getAvailableProduction($resourceType);
        }

        return $stockpiles;
    }

    private function exportExpenses(): array {
        $stockpiles = [];
        foreach (ResourceType::cases() as $resourceType) {
            $stockpiles[$resourceType->name] = $this->getExpenses($resourceType);
        }

        return $stockpiles;
    }

    private function exportUpkeep(): array {
        $stockpiles = [];
        foreach (ResourceType::cases() as $resourceType) {
            $stockpiles[$resourceType->name] = $this->getUpkeep($resourceType);
        }

        return $stockpiles;
    }

    private function exportProduction(): array {
        $stockpiles = [];
        foreach (ResourceType::cases() as $resourceType) {
            $stockpiles[$resourceType->name] = $this->getProduction($resourceType);
        }

        return $stockpiles;
    }

    private function exportStockpiles(): array {
        $stockpiles = [];
        foreach (ResourceType::cases() as $resourceType) {
            $stockpiles[$resourceType->name] = $this->getStockpiledQuantity($resourceType);
        }

        return $stockpiles;
    }

    public function exportBudget(): BudgetInfo {
        return new BudgetInfo(
            nation_id: $this->getNation()->getId(),
            turn_number: $this->getTurn()->getId(),
            production: $this->exportProduction(),
            stockpiles: $this->exportStockpiles(),
            upkeep: $this->exportUpkeep(),
            expenses: $this->exportExpenses(),
            available_production: $this->exportAvailableProduction(),
            balances: $this->exportBalances(),
        );
    }

    public function onNextTurn(NationDetail $current): void {
        $nation = $this->getNation();
        $turn = $this->getTurn();
        $stockpiles = $current->getStockpiles();

        foreach (ResourceType::cases() as $resourceType) {
            $resourceInfo = ResourceType::getMeta($resourceType);

            if (!$resourceInfo->canBeStocked) {
                continue;
            }

            if ($stockpiles->has($resourceType->value)) {
                $stockpile = $stockpiles->get($resourceType->value);
                assert($stockpile instanceof NationResourceStockpile);
                $newStockpile = $stockpile->replicateForTurn($turn);
                $newStockpile->onNextTurn($current->getBalance($resourceType));
            }
            else {
                $balance = max(0, $current->getBalance($resourceType));
                $stockpile = NationResourceStockpile::create($nation, $turn, $resourceType, $balance);
            }
        }

        $this->save();
    }

    public static function whereUsualNameIgnoreCase(string $usualName): Closure {
        return fn (Builder $builder) => $builder->whereRaw('LOWER(usual_name) = ?', strtolower($usualName));
    }

    public static function create(
        Nation $nation,
        ?string $formalName = null,
        string|GameSharedStaticAsset|null $flagSrcOrAsset = null,
    ): NationDetail {
        if ($flagSrcOrAsset instanceof GameSharedStaticAsset && !$flagSrcOrAsset->getType() == SharedAssetType::Flag) {
            throw new InvalidArgumentException("flagSrcOrAsset: expecting Flag asset, got " . $flagSrcOrAsset->getType());
        }

        $nation_details = new NationDetail();
        $nation_details->game_id = $nation->getGame()->getId();
        $nation_details->nation_id = $nation->getId();
        $nation_details->turn_id = Turn::getCurrentForGame($nation->getGame())->getId();
        $nation_details->usual_name = $nation->getInternalName();
        $nation_details->formal_name = is_null($formalName) ? $nation_details->usual_name : $formalName;
        $nation_details->flag_src = match(true) {
            is_string($flagSrcOrAsset) => $flagSrcOrAsset,
            $flagSrcOrAsset instanceof GameSharedStaticAsset => $flagSrcOrAsset->getSrc(),
            is_null($flagSrcOrAsset) => null,
        };
        if ($flagSrcOrAsset instanceof GameSharedStaticAsset) {
            $flagSrcOrAsset->leaseTo($nation);
        }

    	$nation_details->save();

        return $nation_details;
    }
}
