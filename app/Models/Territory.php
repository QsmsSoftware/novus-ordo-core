<?php

namespace App\Models;

use App\Domain\MapData;
use App\Domain\TerrainType;
use App\Domain\TerritoryData;
use App\Utils\GuardsForAssertions;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use LogicException;

class Territory extends Model
{
    use GuardsForAssertions;

    public const float MIN_USABLE_LAND_FOR_HOMELAND = 0.05;

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

    public function connectedTerritories(): Builder {
        return $this->getGame()->territories()
            ->getQuery()
            ->where(function ($query) {
                $query
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x - 1)
                            ->where('y', $this->y - 1);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x - 1)
                            ->where('y', $this->y);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x - 1)
                            ->where('y', $this->y + 1);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x)
                            ->where('y', $this->y - 1);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x)
                            ->where('y', $this->y + 1);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x + 1)
                            ->where('y', $this->y - 1);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x + 1)
                            ->where('y', $this->y);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('x', $this->x + 1)
                            ->where('y', $this->y + 1);
                    });
            });
    }

    public function connectedLands(): Builder {
        return $this->connectedTerritories()
            ->where(self::whereIsControllable());
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

    public static function exportAll(Game $game, Turn $turn): array {
        spl_autoload_call(TerritoryDetail::class);
        $territories = DB::table('territories')
            ->where('territories.game_id', $game->getCurrentTurn()->getId())
            ->join('territory_details', 'territories.id', '=', 'territory_details.territory_id')
            ->where('turn_id', $turn->getId())
            ->get()->all();

        $territoriesByCoords = [];

        foreach($territories as $territory) {
            $territoriesByCoords[$territory->x][$territory->y] = $territory;
        }

        return array_map(fn ($t) => new TerritoryInfo(
            territory_id: $t->id,
            turn_number: $turn->getNumber(),
            x: $t->x,
            y: $t->y,
            terrain_type: TerrainType::from($t->terrain_type)->name,
            usable_land_ratio: $t->usable_land_ratio,
            name: $t->name,
            owner_nation_id: $t->owner_nation_id,
            has_sea_access: $t->has_sea_access,
            connected_territories_ids: Territory::detectConnectedTerritoriesIds($t, $territoriesByCoords),
        ), $territories);
    }

    private static function detectConnectedTerritoriesIds(object $territoryInfo, array $territoriesInfosByCoords): array {
        $connected = [];

        $x = $territoryInfo->x;
        $y = $territoryInfo->y;

        if (isset($territoriesInfosByCoords[$x - 1][$y - 1])) {
            $connected[] = $territoriesInfosByCoords[$x - 1][$y - 1]->id;
        }

        if (isset($territoriesInfosByCoords[$x][$y - 1])) {
            $connected[] = $territoriesInfosByCoords[$x][$y - 1]->id;
        }

        if (isset($territoriesInfosByCoords[$x + 1][$y - 1])) {
            $connected[] = $territoriesInfosByCoords[$x + 1][$y - 1]->id;
        }

        //

        if (isset($territoriesInfosByCoords[$x - 1][$y])) {
            $connected[] = $territoriesInfosByCoords[$x - 1][$y]->id;
        }

        if (isset($territoriesInfosByCoords[$x + 1][$y])) {
            $connected[] = $territoriesInfosByCoords[$x + 1][$y]->id;
        }

        //

        if (isset($territoriesInfosByCoords[$x - 1][$y + 1])) {
            $connected[] = $territoriesInfosByCoords[$x - 1][$y + 1]->id;
        }

        if (isset($territoriesInfosByCoords[$x][$y + 1])) {
            $connected[] = $territoriesInfosByCoords[$x][$y + 1]->id;
        }

        if (isset($territoriesInfosByCoords[$x + 1][$y + 1])) {
            $connected[] = $territoriesInfosByCoords[$x + 1][$y + 1]->id;
        }

        return $connected;
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
        $territory->save();
        $territory->name = "{$territory->name} #{$territory->id}";
        $territory->save();

        TerritoryDetail::create($territory);
        
        return $territory;
    }
}
