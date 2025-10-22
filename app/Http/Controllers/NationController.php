<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Game;
use App\Models\Nation;
use App\Models\NewNation;
use App\Models\Territory;
use App\Services\LoggedInGameContext;
use App\Services\LoggedInUserContext;
use App\Services\NationContext;
use App\Services\NationSetupContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsValidatedDataToFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

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

    public readonly array $territories_ids;

    public function __construct(
        private readonly NationSetupContext $context
    )
    {
        
    }

    public function rules(): array
    {
        return [
            'territories_ids' => [
                'required',
                'array',
                'min:' . Game::NumberOfStartingTerritories,
                'max:' . Game::NumberOfStartingTerritories,
            ],
            'territories_ids.*' => [
                Rule::exists(Territory::class, 'id')->where('game_id', $this->context->getGame()->getId())
            ]
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
        $newNation = $context->getNewNation();
        $territories = $context->getGame()->freeSuitableTerritoriesInTurn()->whereIn('id', $request->territories_ids)->get();

        if ($territories->count() != Game::NumberOfStartingTerritories) {
            return response()->json(['errors' => ["One or more territories aren't suitable."]], HttpStatusCode::UnprocessableContent);
        }

        $newNation->finishSetup($territories);

        return response()->json();
    }

    public function ownNationInfo(): JsonResponse {
        $currentNationOrNull = Nation::getCurrentOrNull();
        if ($currentNationOrNull === null) {
            return response()->json(['errors' => ["User has not created their nation yet."]], HttpStatusCode::Conflict);
        }
        $nation = Nation::notNull($currentNationOrNull);

        return response()->json($nation->getDetail()->exportForOwner());
    }

    public function budgetInfo(): JsonResponse {
        $currentNationOrNull = Nation::getCurrentOrNull();
        if ($currentNationOrNull === null) {
            return response()->json(['errors' => ["User has not created their nation yet."]], HttpStatusCode::Conflict);
        }
        $nation = Nation::notNull($currentNationOrNull);

        return response()->json($nation->getDetail()->exportBudget());
    }

    public function lastTurnBattleLogs(): JsonResponse {
        $currentNationOrNull = Nation::getCurrentOrNull();
        if ($currentNationOrNull === null) {
            return response()->json(['errors' => ["User has not created their nation yet."]], HttpStatusCode::Conflict);
        }
        $nation = Nation::notNull($currentNationOrNull);

        return response()->json($nation->getDetail()
            ->getAllBattlesWhereParticipant()
            ->map(fn (Battle $b) => $b->exportForParticipant())
        );
    }

    // Questionnement: dédier un controlleur pour les routes publiques?
    public function info(int $nationId): JsonResponse {
        $nationOrNull = Game::getCurrent()->getNationWithIdOrNull($nationId);
        if ($nationOrNull === null) {
            return response()->json(['errors' => ['nationId' => "No nation with that ID in the current game."]], HttpStatusCode::UnprocessableContent);
        }
        $nation = Nation::notNull($nationOrNull);

        return response()->json($nation->getDetail()->export());
    }
}
