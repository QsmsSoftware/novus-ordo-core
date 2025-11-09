<?php

namespace App\Http\Controllers;

use App\Models\GameSharedStaticAsset;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    public function assetInfo(string $encodedUri): JsonResponse {
        $uri = urldecode($encodedUri);
        return response()->json(GameSharedStaticAsset::asOrNotFound(GameSharedStaticAsset::getAssetByUriOrNull($uri), "Asset not found: $")
            ->exportInfo());
    }
}
