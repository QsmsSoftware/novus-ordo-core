<?php
namespace App\Services;

use App\Models\Game;
use App\Utils\HttpStatusCode;

class PublicGameContext {
    private readonly Game $game;
    public function __construct()
    {
        $gameOrNull = Game::getCurrentOrNull();
        if(is_null($gameOrNull)) {
            abort(HttpStatusCode::BadRequest, 'Bad context: need a selected game. No active game.');
        }
        $this->game = $gameOrNull;
    }

    public function getGame(): Game {
        return $this->game;
    }
}