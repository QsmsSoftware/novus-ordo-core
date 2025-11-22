<?php

namespace App\Facades;

use App\Models\DivisionDetail;
use App\Models\Game;
use App\Models\Nation;
use App\Models\NationDetail;
use App\Models\TerritoryDetail;
use App\Models\Turn;
use App\Services\StaticJavascriptResource;
use App\Utils\Check;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionFunction;
use stdClass;

/**
 * Utility class that offers an unified interface to caches systems.
 */
final class Metacache {
    private const int TIME_TO_LIVE_SECONDS = 72 * 60 * 60; // 72 hours, enough to keep current turn and previous one in cache.

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

    public static function purgeExpiredData(): string {
        $results = [];

        $countExpired = DB::table('cache')
            ->selectRaw('count(*) as count_expired, coalesce(max(expiration), 0) as max_expiration')
            ->where('expiration', '<=', CarbonImmutable::now('UTC')->getTimestamp())
            ->first();
        
        assert($countExpired instanceof stdClass);
        
        DB::table('cache')
            ->where('expiration', '<=', $countExpired->max_expiration)
            ->delete();

        $results[] = "{$countExpired->count_expired} expired entries deleted";

        $purgeResult = StaticJavascriptResource::purgeUnreferencedFiles();

        $results[] = "{$purgeResult->numberOfFilesPurged} unreferenced cached static files deleted";
        
        return join(", ", $results);
    }

    private static function deriveKey(array $values): string {
        if (count($values) < 1) {
            throw new InvalidArgumentException("values: array must have at least 1 element.");
        }

        $hashedKey = hash('xxh128', $values[0]);

        for($i = 1; $i < count($values); $i++) {
            $hashedKey = hash('xxh128', $hashedKey . $values[1]);
        }

        return $hashedKey;
    }

    public static function remember(Closure $fallback, bool $alsoCacheInMemory = true): mixed {
        $fct = new ReflectionFunction($fallback);

        $keyComponents = [$fct->getName()];

        $objectOrNull = $fct->getClosureThis();

        $tags = [];
        
        if ($objectOrNull instanceof NationDetail) {
            $tags[] = "game_{$objectOrNull->getGame()->getId()}";
            $tags[] = "nation_{$objectOrNull->getNation()->getId()}";
            $tags[] = "turn_{$objectOrNull->getTurn()->getId()}";
        }
        else if ($objectOrNull instanceof TerritoryDetail) {
            $tags[] = "game_{$objectOrNull->getGame()->getId()}";
            $tags[] = "territory_{$objectOrNull->getTerritoryId()}";
            $tags[] = "turn_{$objectOrNull->getTurn()->getId()}";
        }
        else if ($objectOrNull instanceof DivisionDetail) {
            $tags[] = "game_{$objectOrNull->getGame()->getId()}";
            $tags[] = "nation_{$objectOrNull->getNation()->getId()}";
            $tags[] = "division_{$objectOrNull->getDivision()->getId()}";
            $tags[] = "turn_{$objectOrNull->getTurn()->getId()}";
        }
        else if ($objectOrNull instanceof Turn) {
            $tags[] = "game_{$objectOrNull->getGame()->getId()}";
            $tags[] = "turn_{$objectOrNull->getId()}";
        }
        else if ($objectOrNull instanceof Game) {
            $tags[] = "game_{$objectOrNull->getId()}";
        }
        else {
            throw new InvalidArgumentException("fallback: fallback is an instance method from an unsupported class (remember doesn't suport " . Check::typeOrClassOf($objectOrNull) . ")");
        }

        $keyComponents[] = $objectOrNull->getKey();
        $keyComponents[] = get_class($objectOrNull);

        $hashedKey = Metacache::deriveKey($keyComponents);

        if ($alsoCacheInMemory) {
            return Cache::memo()->remember(
                "metacache-$hashedKey-" . join("-", $tags) . "-",
                Metacache::TIME_TO_LIVE_SECONDS,
                $fallback
            );
        }
        else {
            return Cache::remember(
                "metacache-$hashedKey-" . join("-", $tags) . "-",
                Metacache::TIME_TO_LIVE_SECONDS,
                $fallback
            );
        }
    }
}