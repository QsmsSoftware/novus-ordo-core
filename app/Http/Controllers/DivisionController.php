<?php

namespace App\Http\Controllers;

use App\Domain\DivisionType;
use App\Domain\OrderType;
use App\Http\Requests\CancelOrdersRequest;
use App\Http\Requests\SendDisbandOrdersRequest;
use App\Http\Requests\SendMoveOrdersRequest;
use App\Http\Requests\SentDisbandOrder;
use App\Http\Requests\SentMoveOrder;
use App\Models\Division;
use App\Models\Order;
use App\ReadModels\DisbandOrderInfo;
use App\ReadModels\OwnedDivisionInfo;
use App\Services\NationContext;
use App\Utils\Annotations\Payload;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\Summary;
use App\Utils\HttpStatusCode;
use Illuminate\Http\JsonResponse;
use App\Utils\Annotations\ResponseCollection;

class DivisionController extends Controller
{
    #[Summary('Send disband orders.')]
    #[Payload(SendDisbandOrdersRequest::class)]
    #[ResponseCollection('data', DisbandOrderInfo::class, 'List of disband orders sent.')]
    public function sendDisbandOrders(SendDisbandOrdersRequest $request, NationContext $context): JsonResponse {
        $nation = $context->getNation();

        $sentOrders = [];

        foreach($request->getDisbandOrders() as $order) {
            assert($order instanceof SentDisbandOrder);
            $division = $nation->getDetail()->getActiveDivisionWithId($order->division_id);
            $sentOrders[] = $division->sendDisbandOrder();
        };

        return response()->json(['data' => array_map(fn (Order $o) => $o->exportForOwner(), $sentOrders)], HttpStatusCode::Created);
    }

    #[Summary('Send move/attack orders.')]
    #[Payload(SendMoveOrdersRequest::class)]
    #[ResponseCollection('data', DisbandOrderInfo::class, 'List of move/attack orders sent.')]
    public function sendMoveOrders(SendMoveOrdersRequest $request, NationContext $context): JsonResponse {
        $game = $context->getGame();
        $nation = $context->getNation();
        $detail = $nation->getDetail();

        $startAttackingTypes = collect($request->getMoveOrders())
            ->filter(function (SentMoveOrder $mo) use ($detail, $game) {
                return $detail->isHostileTerritory($game->getTerritoryWithId($mo->destination_territory_id));
            })
            ->map(fn (SentMoveOrder $mo) => $detail->getActiveDivisionWithId($mo->division_id))
            ->filter(fn (Division $d) => !$d->getDetail()->isAttacking())
            ->map(fn (Division $d) => $d->getDivisionType());

        if (!$nation->getDetail()->canAffordCosts(DivisionType::calculateTotalAttackCostsByResourceType(...$startAttackingTypes))) {
            abort(HttpStatusCode::UnprocessableContent, "Nation doesn't have enough resources to afford move/attack costs.");
        }

        $sentOrders = [];

        foreach($request->getMoveOrders() as $order) {
            assert($order instanceof SentMoveOrder);
            $division = $detail->getActiveDivisionWithId($order->division_id);
            $destination = $game->getTerritoryWithId($order->destination_territory_id);
            $sentOrders[] = $division->sendMoveAttackOrder($destination);
        };
        
        return response()->json(['data' => array_map(fn (Order $o) => $o->exportForOwner(), $sentOrders)], HttpStatusCode::Created);
    }

    #[Summary('Cancel orders.')]
    #[Payload(CancelOrdersRequest::class)]
    #[Response('HTTP status code 204 NoContent on success.')]
    public function cancelOrders(CancelOrdersRequest $request, NationContext $context): JsonResponse {
        $nation = $context->getNation();

        foreach($request->division_ids as $divisionId) {
            $division = $nation->getDetail()->getActiveDivisionWithId($divisionId);
            $division->cancelOrder();
        };
        
        return response()->json(null, HttpStatusCode::NoContent);
    }
    
    #[Summary('Get all the current nation\'s divisions.')]
    #[ResponseCollection("data", OwnedDivisionInfo::class, "Base information on all territories.")]
    public function allOwnedDivisions(NationContext $context): JsonResponse {
        $nation = $context->getNation();

        $divisions = $nation->getDetail()->activeDivisions()->get()->map(fn (Division $d) => $d->getDetail()->exportForOwner())->all();

        return response()->json(['data' => $divisions]);
    }

    #[Summary('Get one of the current nation\'s divisions.')]
    #[Response(OwnedDivisionInfo::class)]
    public function ownedDivision(NationContext $context, int $divisionId): JsonResponse {
        $nation = $context->getNation();

        $division = Division::asOrNotFound($nation->getDetail()->activeDivisions()->find($divisionId), "Current nation doesn't own an active division with that ID: $divisionId");

        return response()->json($division->getDetail()->exportForOwner());
    }
}
