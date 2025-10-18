<?php
namespace App\Services;

use App\Models\Game;
use App\Models\Nation;
use App\Models\Turn;
use App\Utils\HttpStatusCode;
use Illuminate\Support\Facades\Auth;

class NationContext {
    public function getCurrentTurn(): Turn {
        return Turn::getCurrentForGame(Game::getCurrent());
    }

    public function getNation() :Nation {
        $userOrNull = Auth::user();
        if(is_null($userOrNull)) {
            abort(HttpStatusCode::Unauthorized, 'Bad context: need an authenticated user with a nation.');
        }

        $nationOrNull = Nation::getCurrentOrNull();
        if (is_null($nationOrNull)) {
            abort(HttpStatusCode::BadRequest, 'Bad context: this user has not created their nation yet.');
        }

        return $nationOrNull;
    }
}