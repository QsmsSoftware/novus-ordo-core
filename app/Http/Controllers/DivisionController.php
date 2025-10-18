<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMoveOrdersRequest;
use App\Models\Division;
use App\Models\DivisionDetail;
use App\Models\Nation;
use App\Models\Territory;
use App\Models\TerritoryDetail;
use App\Services\NationContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsArrayToInstance;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
    public function sendMoveOrders(Request $request, NationContext $context) :JsonResponse {
        $nation = $context->getNation();

        $validated = $request->validate([
            'orders' => 'required|array|min:1',
            'orders.*.division_id' => [
                'required',
                'integer',
                DivisionDetail::createRuleValidActiveDivision($nation, $context->getCurrentTurn()),
            ],
            'orders.*.destination_territory_id' => [
                'required',
                'integer',
                Rule::exists(Territory::class, 'id'),
            ],
        ]);

        $orderRequest = SendOrdersRequest::fromArray($validated);

        $sentOrderIds = [];

        foreach($orderRequest->orders as $moveOrderData) {
            $moveOrder = MoveOrAttackSentOrder::fromArray($moveOrderData);
            $division = $nation->getDetail()->getActiveDivisionWithId($moveOrder->division_id);
            $destination = $nation->getGame()->getTerritoryWithId($moveOrder->destination_territory_id);
            $sentOrderIds[] = $division->sendMoveOrder($destination)->getId();
        };
        
        return response()->json($sentOrderIds);
    }

    public function cancelOrders(Request $request, NationContext $context) :JsonResponse {
        $nation = $context->getNation();

        $validated = $request->validate([
            'division_ids' => 'required|array|min:1',
            'division_ids.*' => [
                'required',
                'integer',
                DivisionDetail::createRuleValidActiveDivision($nation, $context->getCurrentTurn())
            ]
        ]);

        $cancelRequest = CancelOrdersRequest::fromArray($validated);

        $hadOrderCancelled = [];

        foreach($cancelRequest->division_ids as $divisionId) {
            $division = $nation->getDetail()->getActiveDivisionWithId($divisionId);
            $division->cancelOrder();
            $hadOrderCancelled[] = $division->getId();
        };
        
        return response()->json($hadOrderCancelled);
    }
    
    public function allOwnedDivisions(NationContext $context) : JsonResponse {
        $nation = $context->getNation();

        $divisions = $nation->getDetail()->activeDivisions()->get()->map(fn (Division $d) => $d->getDetail()->exportForOwner())->all();

        return response()->json($divisions);
    }

    public function ownedDivision(NationContext $context, int $divisionId) : JsonResponse {
        $nation = $context->getNation();

        $division = Division::asOrNotFound($nation->getDetail()->activeDivisions()->find($divisionId), "Current nation doesn't own an active division with that ID: $divisionId");

        return response()->json($division->getDetail()->exportForOwner());
    }
}
