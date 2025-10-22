<?php
namespace App\Services;

use App\Models\Game;
use App\Models\NewNation;
use App\Models\User;
use App\Utils\HttpStatusCode;
use Illuminate\Support\Facades\Auth;

class NationSetupContext {
    public function getGame(): Game {
        $gameOrNull = Game::getCurrentOrNull();
        if(is_null($gameOrNull)) {
            abort(HttpStatusCode::BadRequest, 'Bad context: need an authenticated user with a nation to set up.');
        }

        return $gameOrNull;
    }

    public function getNewNation(): NewNation {
        $game = $this->getGame();

        $userOrNull = Auth::user();
        if(is_null($userOrNull)) {
            abort(HttpStatusCode::Unauthorized, 'Bad context: need an authenticated user with a nation to set up.');
        }
        $user = User::notNull($userOrNull);

        $nationOrNull = NewNation::where('user_id', $user->getId())
            ->where('game_id', $game->getId())
            ->first();
        if (is_null($nationOrNull)) {
            abort(HttpStatusCode::BadRequest, 'Bad context: this user has not created their nation yet or has finished setting their nation up.');
        }

        return $nationOrNull;
    }
}