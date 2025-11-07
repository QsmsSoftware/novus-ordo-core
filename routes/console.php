<?php

use App\Models\Game;
use App\Models\ProvisionedUser;
use App\Models\User;
use App\Models\UserAlreadyExists;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('next-turn {gameId?}', function (int $gameId = 0) {
    assert($this instanceof Command);

    if ($gameId == 0) {
        $game = Game::getCurrent();
    }
    else {
        $gameOrNull = Game::find($gameId);

        if (is_null($gameOrNull)) {
            $this->fail("Game ID '$gameId' is invalid.");
            return Command::FAILURE;
        }

        $game = Game::notNull($gameOrNull);

        if (!$game->isActive()) {
            $this->fail("Game with ID '$gameId' is not active.");
            return Command::FAILURE;
        }
    }

    $turn = $game->getCurrentTurn();

    echo "Game {$game->getId()} was on turn {$turn->getNumber()}" . PHP_EOL;

    $newTurn = $game->tryNextTurn($turn);

    $this->info("Game {$game->getId()} is now on turn {$newTurn->getNumber()}");
})->purpose('Move the game to next turn.');

Artisan::command('rollback-turn', function () {
    $game = Game::getCurrent();
    $game->rollbackLastTurn();
})->purpose('Move the game to next turn.');

Artisan::command('start-game', function () {
    Game::createNew();
})->purpose('Start a new game.');

Artisan::command('provision-admin {userName}', function (string $userName) {
    assert($this instanceof Command);

    $provisionedOrError = User::provisionAdministrator($userName);

    if ($provisionedOrError instanceof ProvisionedUser) {
        $this->info("Admin user $userName provisioned with password: {$provisionedOrError->password->value}");
    }
    else if ($provisionedOrError instanceof UserAlreadyExists) {
        $this->fail("$userName already exists.");
    }
    else {
        throw new LogicException("Unexpected result.");
    }
})->purpose('Provision a new administrator account.');