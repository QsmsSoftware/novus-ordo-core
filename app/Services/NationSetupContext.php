<?php
namespace App\Services;

use App\Domain\NationSetupStatus;
use App\Models\Game;
use App\Models\NewNation;
use App\Models\User;
use App\Utils\HttpStatusCode;
use Illuminate\Support\Facades\Auth;

class NationSetupContext {
    public function getUser(): User {
        $userOrNull = Auth::user();
        if(is_null($userOrNull)) {
            abort(HttpStatusCode::Unauthorized, 'Bad context: need an authenticated user with a nation to set up.');
        }
        return $userOrNull;
    }

    public function getGame(): Game {
        $gameOrNull = Game::getCurrentOrNull();
        if(is_null($gameOrNull)) {
            abort(HttpStatusCode::BadRequest, 'Bad context: need an authenticated user with a nation to set up.');
        }

        return $gameOrNull;
    }

    public function getNewNation(): NewNation {
        $game = $this->getGame();
        $user = $this->getUser();

        $nationOrNull = NewNation::getForUserOrNull($game, $user);
        if (is_null($nationOrNull)) {
            abort(HttpStatusCode::BadRequest, 'Bad context: this user has not created their nation yet or has finished setting their nation up.');
        }

        return $nationOrNull;
    }
}