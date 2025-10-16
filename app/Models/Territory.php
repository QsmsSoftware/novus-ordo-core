<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function getName() :string {
        return $this->name;
    }

    public function onNextTurn(Turn $currentTurn, Turn $nextTurn) :void {
        $currentDetail = $this->getDetail($currentTurn);
        $newDetail = $currentDetail->replicateForTurn($nextTurn);
        $newDetail->onNextTurn($currentDetail);
    }

    public static function create(Game $game, string $name) :Territory {
        $territory = new Territory();
        $territory->game_id = $game->getId();
        $territory->name = $name;
        $territory->save();

        TerritoryDetail::create($territory);
        
        return $territory;
    }
}
