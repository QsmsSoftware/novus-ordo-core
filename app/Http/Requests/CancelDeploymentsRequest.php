<?php

namespace App\Http\Requests;

use App\Models\Deployment;
use App\Services\NationContext;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class CancelDeploymentsRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly array $deployment_ids;

    public readonly ?string $testnullable;

    public function __construct(
        private readonly NationContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'deployment_ids' => 'required|array|min:1',
            'deployment_ids.*' => [
                'required',
                'integer',
                Deployment::createRuleValidDeployment($this->context->getNation())
            ],
        ];
    }
}