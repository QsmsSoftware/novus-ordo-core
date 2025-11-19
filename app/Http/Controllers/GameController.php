<?php

namespace App\Http\Controllers;

use App\ReadModels\GameInfo;
use App\ReadModels\GameReadyStatusInfo;
use App\ReadModels\RankingInfo;
use App\Services\PublicGameContext;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\Summary;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    #[Summary('Returns information about current game.')]
    #[Response(GameInfo::class)]
    public function info(PublicGameContext $context): JsonResponse {
        return response()->json($context->getGame()->exportForTurn());
    }

    #[Summary('Returns useful when checking if the current turn has ended and if turn upkeep is done and the next turn is ready.')]
    #[Response(GameReadyStatusInfo::class)]
    public function readyStatus(PublicGameContext $context): JsonResponse {
        return response()->json($context->getGame()->exportReadyStatus());
    }

    #[Summary('Returns the nation rankings.')]
    #[Response(RankingInfo::class)]
    public function rankings(PublicGameContext $context): JsonResponse {
        return response()->json($context->getGame()->exportRankings($context->getGame()->getCurrentTurn()));
    }
}