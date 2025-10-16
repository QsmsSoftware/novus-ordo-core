<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Nation;
use App\Utils\HttpStatusCode;
use App\Utils\MapsArrayToInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class MoveOrAttackSentOrder {
    use MapsArrayToInstance;
    public function __construct(
        public int $division_id,
        public string $destination_territory_id,
    )
    {
        
    }
}

readonly class SendOrdersRequest {
    use MapsArrayToInstance;
    public function __construct(
        public array $orders,
    ) {
    }
}

readonly class CancelOrdersRequest {
    use MapsArrayToInstance;
    public function __construct(
        public array $division_ids,
    ) {
    }
}

class DivisionController extends Controller
{
    public function sendMoveOrders(Request $request) :JsonResponse {
        $nation = Nation::getCurrent();

        $validated = $request->validate([
            'orders' => 'required|array|min:1',
            'orders.*.division_id' => 'required|integer',
            'orders.*.destination_territory_id' => 'required|integer',
        ]);

        $orderRequest = SendOrdersRequest::fromArray($validated);

        $sentOrderIds = [];

        foreach($orderRequest->orders as $moveOrderData) {
            $moveOrder = MoveOrAttackSentOrder::fromArray($moveOrderData);
            $division = $nation->getDetail()->getActiveDivisionWithId($moveOrder->division_id); // TODO: gérer erreur not found
            $destination = $nation->getGame()->getTerritoryWithId($moveOrder->destination_territory_id); // TODO: gérer erreur not found
            $sentOrderIds[] = $division->sendMoveOrder($destination)->getId();
        };
        
        return response()->json($sentOrderIds);
    }

    public function cancelOrders(Request $request) :JsonResponse {
        $nation = Nation::getCurrent();

        $validated = $request->validate([
            'division_ids' => 'required|array|min:1',
            'division_ids.*' => 'required|integer',
        ]);

        $cancelRequest = CancelOrdersRequest::fromArray($validated);

        $hadOrderCancelled = [];

        foreach($cancelRequest->division_ids as $divisionId) {
            $division = $nation->getDetail()->getActiveDivisionWithId($divisionId); // TODO: gérer erreur not found
            $division->cancelOrder();
            $hadOrderCancelled[] = $division->getId();
        };
        
        return response()->json($hadOrderCancelled);
    }
    
    public function allOwnedDivisions() : JsonResponse {
        $nation = Nation::getCurrent();

        $divisions = $nation->getDetail()->activeDivisions()->get()->map(fn (Division $d) => $d->getDetail()->exportForOwner())->all();

        return response()->json($divisions);
    }

    public function ownedDivision(int $divisionId) : JsonResponse {
        $nation = Nation::getCurrent();

        $divisionOrNull = Division::asOrNull($nation->getDetail()->activeDivisions()->find($divisionId));
        if ($divisionOrNull === null) {
            return response()->json(['errors' => ['divisionId' => 'Current nation doesn\'t own an active division with that ID.']], HttpStatusCode::NotFound);
        }
        $division = Division::notNull($divisionOrNull);

        return response()->json($division->getDetail()->exportForOwner());
    }
}
