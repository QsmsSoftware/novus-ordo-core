<?php

use App\Models\Game;
use App\Models\ProvisionedUser;
use App\Models\Turn;
use App\Models\User;
use App\Models\UserAlreadyExists;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('next-turn', function () {
    $game = Game::getCurrent();

    $game->nextTurn();
})->purpose('Move the game to next turn.');

Artisan::command('rollback-turn', function () {
    $game = Game::getCurrent();
    $game->rollbackLastTurn();
})->purpose('Move the game to next turn.');

Artisan::command('start-game', function () {
    Game::createNew();
})->purpose('Start a new game.');

Artisan::command('provision-admin {userName}', function (string $userName): int {
    $provisionedOrError = User::provisionAdministrator($userName);

    if ($provisionedOrError instanceof ProvisionedUser) {
        echo "Admin user $userName provisioned with password: {$provisionedOrError->password->value}\n";
        return Command::SUCCESS;
    }
    else if ($provisionedOrError instanceof UserAlreadyExists) {
        echo "User $userName already exists.\n";
        return Command::FAILURE;
    }
    else {
        throw new LogicException("Unexpected result.");
    }
})->purpose('Provision a new administrator account.');