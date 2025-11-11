<?php

namespace App\Http\Requests;

use App\Domain\DivisionType;
use App\Services\NationContext;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class DeployInTerritoryRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly string $division_type;
    public readonly int $number_of_divisions;

    public function __construct(
        private readonly NationContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'division_type' => [
                'required',
                'string',
                DivisionType::createValidationByName()
            ],
            'number_of_divisions' => 'required|int|min:1',
        ];
    }
}