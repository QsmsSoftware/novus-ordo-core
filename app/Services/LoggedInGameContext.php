<?php
namespace App\Services;

use App\Models\Game;
use App\Models\User;
use App\Utils\HttpStatusCode;
use Illuminate\Support\Facades\Auth;

class LoggedInGameContext {
    private readonly Game $game;
    private readonly User $user;
    public function __construct()
    {
        $userOrNull = Auth::user();
        if(is_null($userOrNull)) {
            abort(HttpStatusCode::Unauthorized, 'Bad context: need an authenticated user that joined a game. No authenticated user.');
        }
        $this->user = $userOrNull;
        
        $gameOrNull = Game::getCurrentOrNull();
        if(is_null($gameOrNull)) {
            abort(HttpStatusCode::BadRequest, 'Bad context: need an authenticated user that joined a game. No active game.');
        }
        $this->game = $gameOrNull;
    }

    public function getGame(): Game {
        return $this->game;
    }
    public function getUser(): User {
        return $this->user;
    }
}