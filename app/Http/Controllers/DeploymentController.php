<?php

namespace App\Http\Controllers;

use App\Domain\DivisionType;
use App\Http\Requests\CancelDeploymentsRequest;
use App\Http\Requests\DeployInTerritoryRequest;
use App\Models\Deployment;
use App\Models\Territory;
use App\ReadModels\DeploymentInfo;
use App\Services\NationContext;
use App\Utils\Annotations\Payload;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\ResponseCollection;
use App\Utils\Annotations\Summary;
use App\Utils\HttpStatusCode;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class DeploymentController extends Controller
{
    #[Summary('Cancels deployments.')]
    #[Payload(CancelDeploymentsRequest::class)]
    public function cancelDeployments(CancelDeploymentsRequest $cancelRequest, NationContext $context): JsonResponse {
        $nation = $context->getNation();

        $foundDeployments = $nation->deploymentByIds(...$cancelRequest->deployment_ids)->get();
        
        $foundDeployments->each(fn (Deployment $d) => $d->cancel());
        
        return response()->json(null, HttpStatusCode::NoContent);
    }
    
    #[Summary('Returns all deployments for the current nation.')]
    #[ResponseCollection('data', DeploymentInfo::class, 'A list of all the current nation\'s deployments.')]
    public function allDeployments(NationContext $context): JsonResponse {
        return response()->json(['data' => $context->getNation()->getDetail()->deployments()->get()->map(fn (Deployment $d) => $d->export())]);
    }

    #[Summary('Returns all deployments for the current nation in the specified territory.')]
    #[ResponseCollection('data', DeploymentInfo::class, 'A list of all the current nation\'s deployments in the territory.')]
    public function allDeploymentsInOwnedTerritory(NationContext $context, int $territoryId): JsonResponse {
        $nation = $context->getNation();
        $territory = Territory::asOrNotFound($nation->getDetail()->territories()->find($territoryId), "Current nation doesn't own territory: $territoryId");

        return response()->json(['data' => $nation->getDetail()->deploymentsInTerritory($territory)->get()->map(fn (Deployment $d) => $d->export())]);
    }

    #[Summary('Deploys divisions and returns the deployments.')]
    #[ResponseCollection('data', DeploymentInfo::class, 'A list of all the new deployments.')]
    public function deployInOwnedTerritory(DeployInTerritoryRequest $request, NationContext $context, int $territoryId): JsonResponse {
        $nation = $context->getNation();
        $territory = Territory::asOrNotFound($nation->getDetail()->territories()->find($territoryId), "Current nation doesn't own territory: $territoryId");
        
        $deployments = array_map(fn (Deployment $d) => $d->export(), $nation->deploy($territory, DivisionType::fromName($request->division_type), $request->number_of_divisions));
        return response()->json(['data' => $deployments], HttpStatusCode::Created);
    }
}
