<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Turn;
use App\Utils\HttpStatusCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameController extends Controller
{
    public function info() : JsonResponse {
        $gameOrNull = Game::getCurrentOrNull();
        if ($gameOrNull === null) {
            return response()->json(['errors' => ["No active game."]], HttpStatusCode::Conflict);
        }
        $game = Game::notNull($gameOrNull);

        return response()->json($game->exportForTurn());
    }
}