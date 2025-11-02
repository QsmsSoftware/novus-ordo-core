<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turn extends Model
{
    use GuardsForAssertions;

    public const string FIELD_TURN_NUMBER = 'number';

    public function game(): BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame(): Game {
        return $this->game;
    }

    public function deployments() :HasMany {
        return $this->hasMany(Deployment::class);
    }

    public function orders() :HasMany {
        return $this->hasMany(Order::class);
    }

    public function getId() :int {
        return $this->getKey();
    }

    public function getNumber() :int {
        return $this->number;
    }

    public function createNext() :Turn {
        $next = $this->replicate();
        $next->number++;
        $next->save();

        return $next;
    }

    public static function createFirst(Game $game) :Turn {
        $turn = new Turn;
        $turn->game_id = $game->getId();
        $turn->number = 1;
        $turn->save();

        return $turn;
    }

    public static function getCurrent() :Turn {
        return Turn::getCurrentForGame(Game::getCurrent());
    }

    public static function getCurrentForGame(Game $game) :Turn {
        return Turn::where('game_id', $game->getId())
            ->orderByDesc('number')
            ->first();
    }

    public static function getForGameByNumberOrNull(Game $game, int $number) :?Turn {
        return Turn::where('game_id', $game->getId())
            ->where('number', $number)
            ->first();
    }
}
