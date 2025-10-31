<?php

namespace App\Models;

use App\Domain\StatUnit;
use App\Domain\TerrainType;
use App\Domain\TerritoryData;
use App\ReadModels\TerritoryInfo;
use App\Utils\GuardsForAssertions;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

readonly class TerritoryStat {
    public function __construct(
        public string $title,
        public mixed $value,
        public string $unit,
    ) {}
}

class Territory extends Model
{
    use GuardsForAssertions;

    public const float MIN_USABLE_LAND_FOR_HOMELAND = 0.05;

    public const int TERRITORY_AREA_KM2 = 80_000;

    public function game(): BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame(): Game {
        return Game::notNull($this->game);
    }

    public function details(): HasMany {
        return $this->hasMany(TerritoryDetail::class);
    }

    public function getDetail(?Turn $turnOrNull = null): TerritoryDetail {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this->getGame()));
        return $this->details()->where('turn_id', $turn->getId())->first();
    }

    public function connectedTerritories(): BelongsToMany {
        return $this->belongsToMany(Territory::class, 'territory_connections', 'territory_id', 'connected_territory_id')
            ->withTimestamps();
    }

    public function connectedLands(): BelongsToMany {
        return $this->connectedTerritories()
            ->where('is_connected_by_land', true);
    }

    public function getId(): int {
        return $this->getKey();
    }

    public function getX(): int {
        return $this->x;
    }

    public function getY(): int {
        return $this->y;
    }

    public function getTerrainType(): TerrainType {
        return TerrainType::from($this->terrain_type);
    }

    public function getUsableLandRatio(): float {
        return $this->usable_land_ratio;
    }

    public function getUsableLandKm2(): int {
        return $this->usable_land_ratio * Territory::TERRITORY_AREA_KM2;
    }

    public function hasSeaAccess(): bool {
        return $this->has_sea_access;
    }

    public function getName(): string {
        return $this->name;
    }

    public function isSuitableAsHome(): bool {
        return $this->terrain_type != TerrainType::Water->value && !$this->getDetail()->isOwnedByNation();
    }

    public function onNextTurn(Turn $currentTurn, Turn $nextTurn): void {
        $currentDetail = $this->getDetail($currentTurn);
        $newDetail = $currentDetail->replicateForTurn($nextTurn);
        $newDetail->onNextTurn($currentDetail);
    }

    public static function createRuleExistsInGame(Game $game):Exists {
        return Rule::exists(Territory::class, 'id')
            ->where('game_id', $game->getId());
    }

    public static function createValidationSuitableHomeTerritory(Game $game): Closure {
        return function (string $attribute, array $value, Closure $fail) use ($game) {
            $territoryIds = array_unique($value);

            if (sizeof($territoryIds) != Game::NUMBER_OF_STARTING_TERRITORIES) {
                $fail("Wrong number of unique starting territories IDs, got " . sizeof($territoryIds) . ", was expecting " . Game::NUMBER_OF_STARTING_TERRITORIES);
                return;
            }

            $territories = $game->freeSuitableTerritoriesInTurn()->whereIn('id', $territoryIds)->get();
            assert($territories instanceof Collection);

            $notFound = collect($territoryIds)->reject(fn ($tid) => is_numeric($tid) && $territories->contains('id', intval($tid)));

            if ($notFound->count() > 0) {
                $fail("One or more territories aren't suitable home territories: " . $notFound->join(", "));
                return;
            }

            $first = $territories->shift();
            $vettedTerritories = collect([$first]);
            $territoriesToExplore = collect([$first]);

            while (!$territoriesToExplore->isEmpty()) {
                $testedTerritory = $territoriesToExplore->shift();
                assert($testedTerritory instanceof Territory);
                $connectedIds = $testedTerritory->connectedLands()->pluck('connected_territory_id')->all();

                $pContainsConnectedTerritory = fn (Territory $territory) => in_array($territory->getKey(), $connectedIds);

                $connectedToTested = $territories->filter($pContainsConnectedTerritory);

                $territoriesToExplore = $territoriesToExplore->concat($connectedToTested);

                $territories = $territories->reject($pContainsConnectedTerritory);

                $vettedTerritories = $vettedTerritories->concat($connectedToTested);
            }

            if ($territories->count() > 0) {
                $fail("One or more selected home territories aren't connected with initial territory: " . $territories->map(fn (Territory $t) => $t->getId())->join(", "));
                return;
            }
        };
    }

    /**
     * Adds a condition to the query to keep only territories that can be controlled/conquered.
     *
     * Rules:
     *  - Excludes water.
     */
    public static function whereIsControllable(): Closure {
        return fn (Builder $builder) => $builder
            ->where('terrain_type', '<>', TerrainType::Water->value);
    }

    /**
     * Adds a condition to the query to keep only territories that can be selected as a home territory.
     *
     * Rules:
     *  - Must be controllable (excludes water).
     *  - Must have at least 5% land available.
     */
    public static function whereIsSuitableAsHome(): Closure {
        return fn (Builder $builder) => $builder
            ->where(Territory::whereIsControllable())
            ->where('usable_land_ratio', '>=', Territory::MIN_USABLE_LAND_FOR_HOMELAND);
    }

    public static function whereHasSeaAccess(): Closure {
        return fn (Builder $builder) => $builder
            ->where('has_sea_access', true);
    }

    public static function exportAll(Game $game, Turn $turn): array {
        spl_autoload_call(TerritoryDetail::class);
        $territories = DB::table('territories')
            ->where('territories.game_id', $game->getId())
            ->join('territory_details', 'territories.id', '=', 'territory_details.territory_id')
            ->where('turn_id', $turn->getId())
            ->get()->all();

        $connections = DB::table('territory_connections')
            ->where('game_id', $game->getId())
            ->get()
            ->groupBy('territory_id');

        $territoriesByCoords = [];

        foreach($territories as $territory) {
            $territoriesByCoords[$territory->x][$territory->y] = $territory;
        }

        return array_map(fn ($t) => TerritoryInfo::fromObject($t, [
            'turn_number' => $turn->getNumber(),
            'terrain_type' => TerrainType::from($t->terrain_type)->name,
            'connected_territory_ids' => collect($connections[$t->territory_id])
                ->filter(fn ($c) => $c->is_connected_by_land)
                ->map(fn ($c) => $c->connected_territory_id)
                ->values()
                ->all(),
            'stats' => Territory::statsFromRow($t)
        ]), $territories);
    }

    private static function statsFromRow(object $t): array {
        return [
            new TerritoryStat('Usable land ratio', $t->usable_land_ratio, StatUnit::Percent->name),
            new TerritoryStat('Land area', $t->usable_land_ratio * Territory::TERRITORY_AREA_KM2, StatUnit::Km2->name),
        ];
    }

    public function getStats(): array {
        return Territory::statsFromRow($this);
    }

    public static function create(Game $game, TerritoryData $territoryData): Territory {
        $territory = new Territory();
        $territory->game_id = $game->getId();
        $territory->x = $territoryData->x;
        $territory->y = $territoryData->y;
        $territory->terrain_type = $territoryData->terrainType;
        $territory->usable_land_ratio = $territoryData->usableLandRatio;
        $territory->has_sea_access = $territoryData->hasSeaAccess;
        $territory->name = TerrainType::getDescription($territoryData->terrainType);
        $territory->save(); // Generates ID
        $territory->name = $territory->name . " #{$territory->id}";
        $territory->save();

        TerritoryDetail::create($territory);
        
        return $territory;
    }
}
