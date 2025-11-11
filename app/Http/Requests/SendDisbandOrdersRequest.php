<?php

namespace App\Http\Requests;

use App\Models\DivisionDetail;
use App\Services\NationContext;
use Illuminate\Foundation\Http\FormRequest;

class SendDisbandOrdersRequest extends FormRequest {
    public readonly array $orders;

    private readonly array $disbandOrders;

    public function passedValidation(): void {
        $orders = $this->validated('orders');

        $this->disbandOrders = array_map(fn (array $data) => SentDisbandOrder::fromArray($data), $orders);
    }

    public function getDisbandOrders(): array {
        return $this->disbandOrders;
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