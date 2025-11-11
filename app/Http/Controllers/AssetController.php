<?php

namespace App\Http\Controllers;

use App\Models\AssetInfo;
use App\ReadModels\AssetPublicInfo;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\RouteParameter;
use App\Utils\Annotations\Summary;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    #[Summary('Returns information about an asset. Mainly used to attribute art to authors.')]
    #[RouteParameter('encodedUri', 'The URI of the asset. Because it\'s an URI in a URI, this must be twice encoded, e. g. services.getAssetInfo(encodeURIComponent(encodeURIComponent("res/bundled/flags/flag_purple.png")))')]
    #[Response(AssetPublicInfo::class)]
    public function assetInfo(string $encodedUri): JsonResponse {
        $uri = urldecode($encodedUri);
        return response()->json(AssetInfo::asOrNotFound(AssetInfo::getBySrcOrNull($uri), "Asset not found: $uri")
            ->exportInfo());
    }
}
