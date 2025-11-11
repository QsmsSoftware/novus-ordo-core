<?php

namespace App\Http\Requests;

use App\Models\NewNation;
use App\Services\LoggedInGameContext;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class NewNationRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly string $usual_name;

    public function __construct(
        private readonly LoggedInGameContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'usual_name' => [
                'required',
                'string',
                'min:2',
                NewNation::createRuleNoNationWithSameNameInGame($this->context->getGame())
            ],
        ];
    }
}