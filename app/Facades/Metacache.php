<?php

namespace App\Facades;

use App\Models\Game;
use App\Models\Nation;
use App\Models\Turn;
use App\Services\StaticJavascriptResource;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Utility class that offers an unified interface to caches systems.
 */
final class Metacache {
    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }

    public static function expireAllForGame(Game $game): void {
        StaticJavascriptResource::expireAllForGame($game);
        DB::table('cache')
            ->where('key', 'like', config('cache.prefix') . "-game_{$game->getId()}-%")
            ->delete();
    }

    public static function expireAllforTurn(Turn $turn): void {
        StaticJavascriptResource::expireAllForTurn($turn);
        DB::table('cache')
            ->where('key', 'like', config('cache.prefix') . "-turn_{$turn->getId()}-%")
            ->delete();
    }

    public static function getForNationTurn(string $key, Nation $nation, Turn $turn, callable $fallback): mixed {
        $hashedKey = hash('xxh128', $key);
        return Cache::rememberForever("$hashedKey-game_{$nation->getGame()->getId()}-nation_{$nation->getId()}-turn_{$turn->getId()}-", $fallback);
    }
}