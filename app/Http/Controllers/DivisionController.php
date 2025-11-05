<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\DivisionDetail;
use App\Models\Order;
use App\Models\Territory;
use App\Services\NationContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsArrayToInstance;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

readonly class SentDisbandOrder {
    use MapsArrayToInstance;
    public function __construct(
        public int $division_id,
    )
    {
        
    }
}

readonly class SentMoveOrder {
    use MapsArrayToInstance;
    public function __construct(
        public int $division_id,
        public string $destination_territory_id,
    )
    {
        
    }
}

class SendDisbandOrdersRequest extends FormRequest {
    public readonly array $disbandOrders;

    public function passedValidation(): void {
        $orders = $this->validated('orders');

        $this->disbandOrders = array_map(fn (array $data) => SentDisbandOrder::fromArray($data), $orders);
    }

    public function __construct(
        private readonly NationContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'orders' => 'required|array|min:1',
            'orders.*.division_id' => [
                'required',
                'integer',
                DivisionDetail::createRuleValidActiveDivision($this->context->getNation()),
            ],
        ];
    }
}

class SendMoveOrdersRequest extends FormRequest {
    public readonly array $moveOrders;

    public function passedValidation(): void {
        $orders = $this->validated('orders');

        $this->moveOrders = array_map(fn (array $data) => SentMoveOrder::fromArray($data), $orders);
    }

    public function __construct(
        private readonly NationContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'orders' => 'required|array|min:1',
            'orders.*.division_id' => [
                'required',
                'integer',
                DivisionDetail::createRuleValidActiveDivision($this->context->getNation()),
            ],
            'orders.*.destination_territory_id' => [
                'required',
                'integer',
                Territory::createRuleExistsInGame($this->context->getGame()),
            ],
        ];
    }
}

class CancelOrdersRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly array $division_ids;

    public function __construct(
        private readonly NationContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'division_ids' => 'required|array|min:1',
            'division_ids.*' => [
                'required',
                'integer',
                DivisionDetail::createRuleValidActiveDivision($this->context->getNation())
            ]
        ];
    }
}

class DivisionController extends Controller
{
    public function sendDisbandOrders(SendDisbandOrdersRequest $request, NationContext $context): JsonResponse {
        $nation = $context->getNation();

        $sentOrders = [];

        foreach($request->disbandOrders as $order) {
            $division = $nation->getDetail()->getActiveDivisionWithId($order->division_id);
            $sentOrders[] = $division->sendDisbandOrder();
        };

        return response()->json(array_map(fn (Order $o) => $o->exportForOwner(), $sentOrders), HttpStatusCode::Created);
    }

    public function sendMoveOrders(SendMoveOrdersRequest $request, NationContext $context): JsonResponse {
        $nation = $context->getNation();
        
        $validatedOrders = [];

        $errors = new MessageBag();

        for($i = 0; $i < count($request->moveOrders); $i++) {
            $moveOrder = $request->moveOrders[$i];
            assert($moveOrder instanceof SentMoveOrder);

            $division = $nation->getDetail()->getActiveDivisionWithId($moveOrder->division_id);
            if ($division->getDetail()->accessibleTerritories()->pluck('id')->contains($moveOrder->destination_territory_id)) {
                $validatedOrders[] = $moveOrder;
            }
            else {
                $errors->add("orders.$i.destination_territory_id", "Division ID {$division->getId()} can't reach Territory ID {$moveOrder->destination_territory_id}.");
            }
        }

        if ($errors->any()) {
            return response()->json(
                [
                    'message' => 'Validation failed',
                    'errors' => $errors
                ],
                HttpStatusCode::UnprocessableContent
            );
        }

        $sentOrders = [];

        foreach($validatedOrders as $validatedOrder) {
            $division = $nation->getDetail()->getActiveDivisionWithId($validatedOrder->division_id);
            $destination = $nation->getGame()->getTerritoryWithId($validatedOrder->destination_territory_id);
            $sentOrders[] = $division->sendMoveOrder($destination);
        };
        
        return response()->json(array_map(fn (Order $o) => $o->exportForOwner(), $sentOrders), HttpStatusCode::Created);
    }

    public function cancelOrders(CancelOrdersRequest $request, NationContext $context): JsonResponse {
        $nation = $context->getNation();

        foreach($request->division_ids as $divisionId) {
            $division = $nation->getDetail()->getActiveDivisionWithId($divisionId);
            $division->cancelOrder();
        };
        
        return response()->json(null, HttpStatusCode::NoContent);
    }
    
    public function allOwnedDivisions(NationContext $context): JsonResponse {
        $nation = $context->getNation();

        $divisions = $nation->getDetail()->activeDivisions()->get()->map(fn (Division $d) => $d->getDetail()->exportForOwner())->all();

        return response()->json($divisions);
    }

    public function ownedDivision(NationContext $context, int $divisionId): JsonResponse {
        $nation = $context->getNation();

        $division = Division::asOrNotFound($nation->getDetail()->activeDivisions()->find($divisionId), "Current nation doesn't own an active division with that ID: $divisionId");

        return response()->json($division->getDetail()->exportForOwner());
    }
}
