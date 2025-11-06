<?php

namespace App\Facades;

use App\Models\Game;
use App\Models\Turn;
use App\Services\StaticJavascriptResource;

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
    }

    public static function expireAllforTurn(Turn $turn): void {
        StaticJavascriptResource::expireAllForTurn($turn);
    }
}