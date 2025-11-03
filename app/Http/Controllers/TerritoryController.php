<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Territory;
use App\Models\Turn;
use App\Services\NationContext;
use App\Services\PublicGameContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsArrayToInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class TerritoryForTurnParams {
    use MapsArrayToInstance;
    public function __construct(
        public int $turn_number,
    ) {}
}

class TerritoryController extends Controller
{
    public function allTerritoriesSuitableAsHomeIds(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();
        $territories = $game->freeSuitableTerritoriesInTurn()->pluck('id')->all();

        return response()->json($territories);
    }

    public function allTerritories(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();
        $territories = Territory::exportAll($game, $game->getCurrentTurn());

        return response()->json($territories);
    }

    public function allOwnedTerritories(NationContext $context) :JsonResponse {
        $nation = $context->getNation();
        $territories = $nation->getDetail()->territories()->get()->map(fn (Territory $t) => $t->getDetail()->exportForOwner())->all();

        return response()->json($territories);
    }

    public function info(PublicGameContext $context, Request $request, int $territoryId) :JsonResponse {
        $validated = $request->validate([
            'turn_number' => 'nullable|integer',
        ]);

        $game = $context->getGame();
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
