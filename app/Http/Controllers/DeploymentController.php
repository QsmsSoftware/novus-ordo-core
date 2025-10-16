<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Models\Nation;
use App\Utils\HttpStatusCode;
use App\Utils\MapsArrayToInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function cancelDeployments(Request $request) :JsonResponse {
        $nation = Nation::getCurrent();

        $validated = $request->validate([
            'deployment_ids' => 'required|array|min:1',
            'deployment_ids.*' => 'integer',
        ]);

        $cancelRequest = CancelDeploymentsRequest::fromArray($validated);

        $foundDeployments = $nation->deploymentByIds(...$cancelRequest->deployment_ids)->get();

        $missingIds = collect($cancelRequest->deployment_ids)->diff($foundDeployments->map(fn (Deployment $d) => $d->getId()));

        if ($missingIds->count() > 0) {
            return response()->json(['errors' => ['deployment_ids' => 'Not found: ' . $missingIds->join(", ")]], HttpStatusCode::BadRequest);
        }

        $cancelledDeploymentIds = [];
            
        $foundDeployments->each(function (Deployment $d) use (&$cancelledDeploymentIds) {
            $cancelledDeploymentIds[] = $d->getId();
            $d->cancel();
        });
        
        return response()->json($cancelledDeploymentIds);
    }
    public function allDeploymentsInOwnedTerritory(int $territoryId) :JsonResponse {
        $nation = Nation::getCurrent();
        $territory = $nation->getDetail()->getTerritoryById($territoryId);

        return response()->json($nation->getDetail()->deploymentsInTerritory($territory)->get()->map(fn (Deployment $d) => $d->export()));
    }

    public function deployInOwnedTerritory(Request $request, int $territoryId) :JsonResponse {
        $validated = $request->validate([
            'number_of_divisions' => 'required|int|min:1',
        ]);
        $request = DeployInTerritoryRequest::fromArray($validated);
        $nation = Nation::getCurrent();
        $territory = $nation->getDetail()->getTerritoryById($territoryId); // TODO: gérer erreur not found
        
        $deployments = array_map(fn (Deployment $d) => $d->export(), $nation->deploy($territory, $request->number_of_divisions));
        return response()->json($deployments, HttpStatusCode::Created);
    }
}
