<?php

namespace App\Http\Controllers;

use App\Domain\NationSetupStatus;
use App\Models\Battle;
use App\Models\Game;
use App\Models\Nation;
use App\Models\NewNation;
use App\Models\Territory;
use App\Services\LoggedInGameContext;
use App\Services\NationContext;
use App\Services\NationSetupContext;
use App\Services\PublicGameContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

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

class NationController extends Controller
{
    public function createNation(NewNationRequest $request, LoggedInGameContext $context): JsonResponse {
        $game = $context->getGame();
        $user = $context->getUser();
        $createResult = NewNation::create($game, $user, $request->usual_name);

        return response()->json(["nation_id" => $createResult->getId()], HttpStatusCode::Created);
    }

    public function selectHomeTerritories(SelectTerritoriesRequest $request, NationSetupContext $context): JsonResponse {
        if ($context->getUser()->getNationSetupStatus($context->getGame()) != NationSetupStatus::HomeTerritoriesSelection) {
            abort(HttpStatusCode::BadRequest, "User's nation setup status is not " . NationSetupStatus::HomeTerritoriesSelection->name);
        }
        $newNation = $context->getNewNation();

        $newNation->finishSetup(...$request->territory_ids);

        return response()->json();
    }

    public function ownNationInfo(NationContext $context): JsonResponse {
        $nation = $context->getNation();

        return response()->json($nation->getDetail()->exportForOwner());
    }

    public function budgetInfo(NationContext $context): JsonResponse {
        $nation = $context->getNation();

        return response()->json($nation->getDetail()->exportBudget());
    }

    public function lastTurnBattleLogs(NationContext $context): JsonResponse {
        $nation = $context->getNation();

        return response()->json($nation->getDetail()
            ->getAllBattlesWhereParticipant()
            ->map(fn (Battle $b) => $b->exportForParticipant())
        );
    }

    public function info(PublicGameContext $context, int $nationId): JsonResponse {
        $nationOrNull = $context->getGame()->getNationWithIdOrNull($nationId);
        if ($nationOrNull === null) {
            return response()->json(['errors' => ['nationId' => "No nation with that ID in the current game."]], HttpStatusCode::UnprocessableContent);
        }
        $nation = Nation::notNull($nationOrNull);

        return response()->json($nation->getDetail()->export());
    }
}
