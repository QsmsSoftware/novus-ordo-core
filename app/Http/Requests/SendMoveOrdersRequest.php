<?php

namespace App\Http\Requests;

use App\Models\DivisionDetail;
use App\Models\Territory;
use App\Services\NationContext;
use Illuminate\Foundation\Http\FormRequest;

class SendMoveOrdersRequest extends FormRequest {
    public readonly array $orders;

    private readonly array $moveOrders;

    public function passedValidation(): void {
        $orders = $this->validated('orders');

        $this->moveOrders = array_map(fn (array $data) => SentMoveOrder::fromArray($data), $orders);
    }

    public function getMoveOrders(): array {
        return $this->moveOrders;
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