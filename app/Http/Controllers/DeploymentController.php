<?php

namespace App\Http\Controllers;

use App\Domain\DivisionType;
use App\Models\Deployment;
use App\Models\Territory;
use App\Services\NationContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

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

class CancelDeploymentsRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly array $deployment_ids;

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

class DeploymentController extends Controller
{
    public function cancelDeployments(CancelDeploymentsRequest $cancelRequest, NationContext $context): JsonResponse {
        $nation = $context->getNation();

        $foundDeployments = $nation->deploymentByIds(...$cancelRequest->deployment_ids)->get();
        
        $foundDeployments->each(fn (Deployment $d) => $d->cancel());
        
        return response()->json();
    }
    
    public function allDeployments(NationContext $context) :JsonResponse {
        return response()->json($context->getNation()->getDetail()->deployments()->get()->map(fn (Deployment $d) => $d->export()));
    }

    public function allDeploymentsInOwnedTerritory(NationContext $context, int $territoryId): JsonResponse {
        $nation = $context->getNation();
        $territory = Territory::asOrNotFound($nation->getDetail()->territories()->find($territoryId), "Current nation doesn't own territory: $territoryId");

        return response()->json($nation->getDetail()->deploymentsInTerritory($territory)->get()->map(fn (Deployment $d) => $d->export()));
    }

    public function deployInOwnedTerritory(DeployInTerritoryRequest $request, NationContext $context, int $territoryId): JsonResponse {
        $nation = $context->getNation();
        $territory = Territory::asOrNotFound($nation->getDetail()->territories()->find($territoryId), "Current nation doesn't own territory: $territoryId");
        
        $deployments = array_map(fn (Deployment $d) => $d->export(), $nation->deploy($territory, DivisionType::fromName($request->division_type), $request->number_of_divisions));
        return response()->json($deployments, HttpStatusCode::Created);
    }
}
