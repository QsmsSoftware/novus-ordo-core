<?php

namespace App\Http\Controllers;

use App\Models\AssetInfo;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    public function assetInfo(string $encodedUri): JsonResponse {
        $uri = urldecode($encodedUri);
        return response()->json(AssetInfo::asOrNotFound(AssetInfo::getBySrcOrNull($uri), "Asset not found: $uri")
            ->exportInfo());
    }
}
