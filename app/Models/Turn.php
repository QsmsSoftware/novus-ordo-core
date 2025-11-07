<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Carbon\Carbon;
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

    public function deployments(): HasMany {
        return $this->hasMany(Deployment::class);
    }

    public function orders(): HasMany {
        return $this->hasMany(Order::class);
    }

    public function getId(): int {
        return $this->getKey();
    }

    public function getNumber(): int {
        return $this->number;
    }

    private static function calculateUltimatum(): Carbon {
        return Carbon::now()->endOfDay();
    }

    public function end(): void {
        $this->ended_at = Carbon::now();
        $this->save();
    }

    public function reset(): void {
        $this->ended_at = null;
        $this->expires_at = Turn::calculateUltimatum();
        $this->save();
    }

    public function hasExpired(): bool {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    public function createNext(): Turn {
        $next = $this->replicate();
        $next->number++;
        $next->expires_at = Turn::calculateUltimatum();
        $next->save();

        return $next;
    }

    public static function createFirst(Game $game): Turn {
        $turn = new Turn;
        $turn->game_id = $game->getId();
        $turn->number = 1;
        $turn->expires_at = Turn::calculateUltimatum();
        $turn->save();

        return $turn;
    }

    public static function getCurrentForGame(Game $game): Turn {
        return Turn::where('game_id', $game->getId())
            ->orderByDesc('number')
            ->first();
    }

    public static function getForGameByNumberOrNull(Game $game, int $number): ?Turn {
        return Turn::where('game_id', $game->getId())
            ->where('number', $number)
            ->first();
    }
}
