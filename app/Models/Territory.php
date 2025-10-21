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
use LogicException;

class Territory extends Model
{
    use GuardsForAssertions;

    public const float MIN_USABLE_LAND_FOR_HOMELAND = 0.05;

    public function game() :BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame() :Game {
        return Game::notNull($this->game);
    }

    public function details() :HasMany {
        return $this->hasMany(TerritoryDetail::class);
    }

    public function getDetail(?Turn $turnOrNull = null) :TerritoryDetail {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this->getGame()));
        return $this->details()->where('turn_id', $turn->getId())->first();
    }

    public function connectedTerritories() :Builder {
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

    public function connectedLands() :Builder {
        return $this->connectedTerritories()
            ->where(self::whereIsControllable());
    }

    public function getId() :int {
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

    public function getName() :string {
        return $this->name;
    }

    public function onNextTurn(Turn $currentTurn, Turn $nextTurn) :void {
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
    public static function whereIsControllable() :Closure {
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
    public static function whereIsSuitedAsHome() :Closure {
        return fn (Builder $builder) => $builder
            ->where(Territory::whereIsControllable())
            ->where('usable_land_ratio', '>=', Territory::MIN_USABLE_LAND_FOR_HOMELAND);
    }

    public static function create(Game $game, TerritoryData $territoryData) :Territory {
        

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
