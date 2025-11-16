<?php

namespace App\Http\Requests;

use App\Domain\DivisionType;
use App\Models\TerritoryDetail;
use App\Services\NationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeployRequest extends FormRequest {
    public readonly array $deployments;

    private readonly array $validatedDeployments;

    public function passedValidation(): void {
        $deployments = $this->validated('deployments');

        $this->validatedDeployments = array_map(fn (array $data) => SentDeploymentOrder::fromArray($data), $deployments);
    }

    public function getDeployments(): array {
        return $this->validatedDeployments;
    }

    public function __construct(
        private readonly NationContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'deployments' => 'required|array|min:1',
            'deployments.*.division_type' => [
                'required',
                'string',
                DivisionType::createValidationByName()
            ],
            'deployments.*.territory_id' => [
                'required',
                'integer',
                TerritoryDetail::createRuleOwnedByNation($this->context->getNation(), $this->context->getCurrentTurn()),
            ],
        ];
    }
}