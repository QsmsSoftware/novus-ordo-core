<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Territory;
use App\Models\TerritoryDetail;
use App\Models\Turn;
use App\Services\NationContext;
use App\Services\PublicGameContext;
use App\Services\StaticJavascriptResource;
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
    private function getAllTerritoriesBaseInfoResource(Game $game) {
        return StaticJavascriptResource::permanentForGame(
            StaticJavascriptResource::generateStaticResourceNameFromMethodName(__METHOD__),
            fn () => json_encode(Territory::exportAllBasePublicInfo($game)),
            $game
        );
    }

    public function allTerritoriesSuitableAsHomeIds(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();
        $territories = $game->freeSuitableTerritoriesInTurn()->pluck('id')->all();

        return response()->json($territories);
    }

    public function allTerritories(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();

        $baseInfo = collect(json_decode($this->getAllTerritoriesBaseInfoResource($game)->renderAsCode()))
            ->mapWithKeys(fn ($t) => [$t->territory_id => $t]);

        $territories = array_map(fn ($t) => TerritoryController::merge($baseInfo->get($t->territory_id), $t), TerritoryDetail::exportAllTurnPublicInfo($game->getCurrentTurn()));

        return response()->json($territories);
    }

    private static function merge(object $territory, object $supplemental): object {
        foreach ($supplemental as $k => $v) {
            if (isset($territory->$k) && is_array($territory->$k)) {
                $territory->$k = array_merge($territory->$k, $v);
            }
            else {
                $territory->$k = $v;
            }
        }

        return $territory;
    }

    public function allTerritoriesBaseInfo(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();

        return $this->getAllTerritoriesBaseInfoResource($game)->renderAsJsonResponse();
    }

    public function allTerritoriesBaseInfoStaticLink(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();

        return response()->json(["href" => $this->getAllTerritoriesBaseInfoResource($game)->renderAsRelativeUri()]);
    }

    public function allTerritoriesTurnInfo(PublicGameContext $context) :JsonResponse {
        $territories = TerritoryDetail::exportAllTurnPublicInfo($context->getGame()->getCurrentTurn());

        return response()->json($territories);
    }

    public function allOwnedTerritories(NationContext $context) :JsonResponse {
        $nation = $context->getNation();
        $territories = $nation->getDetail()->territories()->get()->map(fn (Territory $t) => $t->getDetail()->exportForOwner())->all();

        return response()->json($territories);
    }

    public function info(PublicGameContext $context, int $territoryId) :JsonResponse {
        $game = $context->getGame();
        $territory = Territory::asOrNotFound($game->territories()->find($territoryId), "No territory with ID $territoryId in the current game.");

        return response()->json($territory->exportBase());
    }

    public function turnInfo(PublicGameContext $context, Request $request, int $territoryId) :JsonResponse {
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
            $turn = $game->getCurrentTurn();
        }

        return response()->json($territory->getDetail($turn)->export());
    }
}
