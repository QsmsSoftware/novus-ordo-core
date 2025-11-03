<?php

namespace App\Http\Controllers;

use App\Services\PublicGameContext;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    public function info(PublicGameContext $context) : JsonResponse {
        return response()->json($context->getGame()->exportForTurn());
    }

    public function readyStatus(PublicGameContext $context) : JsonResponse {
        return response()->json($context->getGame()->exportReadyStatus());
    }
}