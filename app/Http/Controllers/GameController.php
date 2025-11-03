<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Turn;
use App\Services\LoggedInGameContext;
use App\Utils\HttpStatusCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameController extends Controller
{
    public function info(LoggedInGameContext $context) : JsonResponse {
        return response()->json($context->getGame()->exportForTurn());
    }

    public function readyStatus(LoggedInGameContext $context) : JsonResponse {
        return response()->json($context->getGame()->exportReadyStatus());
    }
}