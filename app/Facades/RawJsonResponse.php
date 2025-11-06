<?php

namespace App\Facades;

use App\Utils\HttpStatusCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Factory class to create raw JSON responses.
 */
final class RawJsonResponse {
    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }
    
    public static function make(string $rawJson, int $status = HttpStatusCode::OK, array $headers = []): JsonResponse {
        return new JsonResponse($rawJson, $status, $headers, 0, true);
    }
}
