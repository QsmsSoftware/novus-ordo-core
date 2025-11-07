<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Carbon\CarbonImmutable;
use DateTimeZone;
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

    private static function calculateUltimatum(): CarbonImmutable {
        $expiration = CarbonImmutable::now(config('novusordo.timezone_for_turn_expiration'))->endOfDay()->setTimezone('UTC');

        if ($expiration->subMinutes(config('novusordo.minimum_delay_before_turn_expiration_minutes'))->isBefore(CarbonImmutable::now())) {
            $expiration = $expiration->addDay();
        }

        return $expiration;
    }

    public function end(): void {
        $this->ended_at = CarbonImmutable::now('UTC');
        $this->save();
    }

    public function reset(): void {
        $this->ended_at = null;
        $this->expires_at = Turn::calculateUltimatum();
        $this->save();
    }

    public function getExpiration(): CarbonImmutable {
        return CarbonImmutable::createFromTimeString($this->expires_at);
    }

    public function hasExpired(): bool {
        return CarbonImmutable::now()->greaterThan($this->expires_at);
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
