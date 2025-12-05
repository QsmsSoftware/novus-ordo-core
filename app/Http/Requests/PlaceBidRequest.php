<?php

namespace App\Http\Requests;

use App\Domain\ProductionBidConstants;
use App\Domain\ResourceType;
use App\Models\ProductionBid;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class PlaceBidRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly string $resource_type;
    public readonly int $max_quantity;
    public readonly int $max_labor_allocation_per_unit;

    public function __construct(
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'resource_type' => [
                'required',
                'string',
                ResourceType::createValidationByName()
            ],
            'max_quantity' => [
                'required',
                'integer',
                'min:0',
                'max:' . ProductionBidConstants::MAX_QUANTITY_LIMIT,
            ],
            'max_labor_allocation_per_unit' => [
                'required',
                'integer',
                'min:0',
                'max:' . ProductionBidConstants::MAX_LABOR_PER_UNIT_LIMIT,
            ]
        ];
    }
}