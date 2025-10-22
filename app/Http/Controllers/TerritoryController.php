<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Nation;
use App\Models\Territory;
use App\Models\Turn;
use App\Utils\HttpStatusCode;
use App\Utils\MapsArrayToInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

readonly class TerritoryForTurnParams {
    use MapsArrayToInstance;
    public function __construct(
        public int $turn_number,
    ) {}
}

class TerritoryController extends Controller
{
    public function allTerritories() :JsonResponse {
        $game = Game::getCurrent();
        $territories = Territory::exportAll($game, $game->getCurrentTurn());

        return response()->json($territories);
    }

    public function allOwnedTerritories() :JsonResponse {
        $nation = Nation::getCurrent();
        $territories = $nation->getDetail()->territories()->get()->map(fn (Territory $t) => $t->getDetail()->exportForOwner())->all();

        return response()->json($territories);
    }

    public function info(Request $request, int $territoryId) :JsonResponse {
        $validated = $request->validate([
            'turn_number' => 'nullable|integer',
        ]);

        $game = Game::getCurrent();
        $territory = Territory::asOrNotFound($game->territories()->find($territoryId), "No territory with ID $territoryId in the current game.");

        if (isset($validated['turn_number'])) {
            $params = TerritoryForTurnParams::fromArray($validated);

            $turnOrNull = Turn::getForGameByNumberOrNull($game, $params->turn_number);
            if ($turnOrNull === null) {
                return response()->json(['errors' => ['turn_number' => "Invalid turn number for the current game."]], HttpStatusCode::UnprocessableContent);
            }
            $turn = $turnOrNull;
        }
        else {
            $turn = Turn::getCurrentForGame($game);
        }

        return response()->json($territory->getDetail($turn)->export());
    }
}
