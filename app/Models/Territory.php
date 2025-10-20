<?php

namespace App\Models;

use App\Domain\MapData;
use App\Domain\TerrainType;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Territory extends Model
{
    use GuardsForAssertions;

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

    public function getName() :string {
        return $this->name;
    }

    public function onNextTurn(Turn $currentTurn, Turn $nextTurn) :void {
        $currentDetail = $this->getDetail($currentTurn);
        $newDetail = $currentDetail->replicateForTurn($nextTurn);
        $newDetail->onNextTurn($currentDetail);
    }

    public static function create(Game $game, int $x, int $y, TerrainType $terrainType, float $usableLandRatio) :Territory {
        if ($x < 0 || $x > MapData::WIDTH) {
            throw new LogicException("x coordinate is invalid: $x");
        }
        if ($y < 0 || $y > MapData::HEIGHT) {
            throw new LogicException("y coordinate is invalid: $y");
        }
        if ($usableLandRatio < 0 || $usableLandRatio > 1) {
            throw new LogicException("Usable land ration not between 0.00 - 1.00: $usableLandRatio");
        }

        $territory = new Territory();
        $territory->game_id = $game->getId();
        $territory->x = $x;
        $territory->y = $y;
        $territory->terrain_type = $terrainType;
        $territory->usable_land_ratio = $usableLandRatio;
        $territory->name = TerrainType::getDescription($terrainType);
        $territory->save();
        $territory->name = "{$territory->name} #{$territory->id}";
        $territory->save();

        TerritoryDetail::create($territory);
        
        return $territory;
    }
}
