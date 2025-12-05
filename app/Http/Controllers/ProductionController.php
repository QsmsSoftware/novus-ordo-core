<?php

namespace App\Http\Controllers;

use App\Domain\ResourceType;
use App\Http\Requests\PlaceBidRequest;
use App\Models\ProductionBid;
use App\ReadModels\ProductionBidInfo;
use App\Services\NationContext;
use App\Utils\Annotations\Payload;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\Summary;
use App\Utils\HttpStatusCode;
use Illuminate\Http\JsonResponse;

class ProductionController extends Controller
{
    #[Summary('Sets the bid (maximum quantity and maximum labor) for the production of a resource.')]
    #[Payload(PlaceBidRequest::class)]
    #[Response(ProductionBidInfo::class)]
    public function placeProductionBid(NationContext $context, PlaceBidRequest $request): JsonResponse {
        $resourceType = ResourceType::fromName($request->resource_type);

        $info = ResourceType::getMeta($resourceType);

        if (!$info->canPlaceCommand) {
            abort(HttpStatusCode::UnprocessableContent, "Can't place a bid for resource type {$resourceType->name}");
        }

        $nationDetail = $context->getNation()->getDetail();

        $nationDetail->placeProductionBid($resourceType, $request->max_quantity, $request->max_labor_allocation_per_unit);

        $bids = collect([ ProductionBid::getCommandBid($nationDetail, $resourceType) ]);

        return response()->json(['data' => $bids->map(fn (ProductionBid $b) => $b->exportForOwner())]);
    }
}
