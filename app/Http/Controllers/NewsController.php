<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\ReadModels\NewsInfo;
use App\Services\PublicGameContext;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\Summary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    #[Summary('Returns last (or specified) turn\'s news.')]
    #[Response(NewsInfo::class)]
    public function news(PublicGameContext $context): JsonResponse {
        return response()->json(News::getAllForTurn($context->getGame()->getCurrentTurn())->map(fn (News $n) => $n->export()));
    }
}
