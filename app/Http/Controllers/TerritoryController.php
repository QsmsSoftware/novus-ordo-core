<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Territory;
use App\Models\TerritoryDetail;
use App\Models\Turn;
use App\ReadModels\TerritoryBasePublicInfo;
use App\ReadModels\TerritoryFullPublicInfo;
use App\ReadModels\TerritoryTurnPublicInfo;
use App\Services\NationContext;
use App\Services\PublicGameContext;
use App\Services\StaticJavascriptResource;
use App\Utils\Annotations\QueryParameter;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\ResponseCollection;
use App\Utils\Annotations\ResponseElement;
use App\Utils\Annotations\RouteParameter;
use App\Utils\Annotations\Summary;
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

    #[Summary('Get all the IDs of the territories that can be selected as home territories.')]
    #[ResponseCollection('data', 'int', 'The list of all the IDs of the territories that can be selected as home territories.')]
    public function allTerritoriesSuitableAsHomeIds(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();
        $territories = $game->freeSuitableTerritoriesInTurn()->pluck('id')->all();

        return response()->json(['data' => $territories]);
    }

    #[Summary('Get combined (public) information (base + turn) information on all territories.')]
    #[ResponseCollection('data', TerritoryFullPublicInfo::class, 'Full information on all territories ')]
    public function allTerritories(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();

        $baseInfo = collect(json_decode($this->getAllTerritoriesBaseInfoResource($game)->renderAsCode()))
            ->mapWithKeys(fn ($t) => [$t->territory_id => $t]);

        $territories = array_map(fn ($t) => TerritoryController::merge($baseInfo->get($t->territory_id), $t), TerritoryDetail::exportAllTurnPublicInfo($game->getCurrentTurn()));

        return response()->json(['data' => $territories]);
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

    #[Summary('Get base (public) information on all territories. This is information on territory that doesn\'t ever change. It can be combined with turn information to have complete information for a given turn.')]
    #[ResponseCollection("data", TerritoryBasePublicInfo::class, "Base information on all territories.")]
    public function allTerritoriesBaseInfo(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();

        return new JsonResponse('{"data":' . $this->getAllTerritoriesBaseInfoResource($game)->renderAsCode() . '}', json: true);
    }

    #[Summary('Returns a link to a static JS file with base information on territories.')]
    #[ResponseElement('href', 'string', 'The relative URI of the static JS file with base information on territories.')]
    public function allTerritoriesBaseInfoStaticLink(PublicGameContext $context) :JsonResponse {
        $game = $context->getGame();

        return response()->json(["href" => $this->getAllTerritoriesBaseInfoResource($game)->renderAsRelativeUri()]);
    }

    #[Summary('Get turn specific (public) information on all territories.')]
    #[ResponseCollection("data", TerritoryTurnPublicInfo::class, "Turn specific information on all territories.")]
    public function allTerritoriesTurnInfo(PublicGameContext $context) :JsonResponse {
        $territories = TerritoryDetail::exportAllTurnPublicInfo($context->getGame()->getCurrentTurn());

        return response()->json(['data' => $territories]);
    }
    
    #[Summary('Base information on a territory')]
    #[RouteParameter('territoryId', 'Territory ID')]
    #[Response(TerritoryBasePublicInfo::class)]
    public function info(PublicGameContext $context, int $territoryId) :JsonResponse {
        $game = $context->getGame();
        $territory = Territory::asOrNotFound($game->territories()->find($territoryId), "No territory with ID $territoryId in the current game.");

        return response()->json($territory->exportBase());
    }

    #[Summary('Turn information on a territory')]
    #[RouteParameter('territoryId', 'Territory ID')]
    #[QueryParameter('turn_number', 'int', 'Turn number for which to return information.')]
    #[Response(TerritoryTurnPublicInfo::class)]
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
