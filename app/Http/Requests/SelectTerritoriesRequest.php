<?php

namespace App\Http\Requests;

use App\Models\Game;
use App\Models\Territory;
use App\Services\NationSetupContext;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class SelectTerritoriesRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly array $territory_ids;

    public function __construct(
        private readonly NationSetupContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'territory_ids' => [
                'required',
                'array',
                'min:' . Game::NUMBER_OF_STARTING_TERRITORIES,
                'max:' . Game::NUMBER_OF_STARTING_TERRITORIES,
                Territory::createValidationSuitableHomeTerritory($this->context->getGame())
            ],
        ];
    }
}