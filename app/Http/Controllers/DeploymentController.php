<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Models\Nation;
use App\Models\Territory;
use App\Services\NationContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsArrayToInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

readonly class DeployInTerritoryRequest {
    use MapsArrayToInstance;
    public function __construct(
        public int $number_of_divisions
    )
    {
        
    }
}

readonly class CancelDeploymentsRequest {
    use MapsArrayToInstance;
    public function __construct(
        public array $deployment_ids
    )
    {
        
    }
}

class DeploymentController extends Controller
{
    public function cancelDeployments(Request $request, NationContext $context): JsonResponse {
        $nation = $context->getNation();

        $validated = $request->validate([
            'deployment_ids' => 'required|array|min:1',
            'deployment_ids.*' => [
                'required',
                'integer',
                Deployment::createRuleValidDeployment($nation)
            ],
        ]);

        $cancelRequest = CancelDeploymentsRequest::fromArray($validated);

        $foundDeployments = $nation->deploymentByIds(...$cancelRequest->deployment_ids)->get();
        
        $foundDeployments->each(fn (Deployment $d) => $d->cancel());
        
        return response()->json();
    }
    public function allDeploymentsInOwnedTerritory(NationContext $context, int $territoryId): JsonResponse {
        $nation = $context->getNation();
        $territory = Territory::asOrNotFound($nation->getDetail()->territories()->find($territoryId), "Current nation doesn't own territory: $territoryId");

        return response()->json($nation->getDetail()->deploymentsInTerritory($territory)->get()->map(fn (Deployment $d) => $d->export()));
    }

    public function deployInOwnedTerritory(Request $request, NationContext $context, int $territoryId): JsonResponse {
        $validated = $request->validate([
            'number_of_divisions' => 'required|int|min:1',
        ]);
        $request = DeployInTerritoryRequest::fromArray($validated);
        $nation = $context->getNation();
        $territory = Territory::asOrNotFound($nation->getDetail()->territories()->find($territoryId), "Current nation doesn't own territory: $territoryId");
        
        $deployments = array_map(fn (Deployment $d) => $d->export(), $nation->deploy($territory, $request->number_of_divisions));
        return response()->json($deployments, HttpStatusCode::Created);
    }
}
