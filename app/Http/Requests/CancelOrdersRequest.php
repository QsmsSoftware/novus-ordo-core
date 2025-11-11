<?php

namespace App\Http\Requests;

use App\Models\DivisionDetail;
use App\Services\NationContext;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;

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