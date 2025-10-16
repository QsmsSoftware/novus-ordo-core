<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Game;
use App\Models\Nation;
use App\Utils\HttpStatusCode;
use Illuminate\Http\JsonResponse;

class NationController extends Controller
{
    public function ownNationInfo() :JsonResponse {
        $currentNationOrNull = Nation::getCurrentOrNull();
        if ($currentNationOrNull === null) {
            return response()->json(['errors' => ["User has not created their nation yet."]], HttpStatusCode::Conflict);
        }
        $nation = Nation::notNull($currentNationOrNull);

        return response()->json($nation->getDetail()->exportForOwner());
    }

    public function budgetInfo() :JsonResponse {
        $currentNationOrNull = Nation::getCurrentOrNull();
        if ($currentNationOrNull === null) {
            return response()->json(['errors' => ["User has not created their nation yet."]], HttpStatusCode::Conflict);
        }
        $nation = Nation::notNull($currentNationOrNull);

        return response()->json($nation->getDetail()->exportBudget());
    }

    public function lastTurnBattleLogs() :JsonResponse {
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
    public function info(int $nationId) :JsonResponse {
        $nationOrNull = Game::getCurrent()->getNationWithIdOrNull($nationId);
        if ($nationOrNull === null) {
            return response()->json(['errors' => ['nationId' => "No nation with that ID in the current game."]], HttpStatusCode::UnprocessableContent);
        }
        $nation = Nation::notNull($nationOrNull);

        return response()->json($nation->getDetail()->export());
    }
}
