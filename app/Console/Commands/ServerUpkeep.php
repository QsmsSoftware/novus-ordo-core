<?php

namespace App\Console\Commands;

use App\Facades\Metacache;
use App\Models\Game;
use App\Services\StaticJavascriptResource;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ServerUpkeep extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:server-upkeep';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Does server upkeep (end turn if this is called after the turn expiration time).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $games = Game::where('is_active', true)->get();

        assert($games instanceof Collection);

        $wasAnExpiredGame = false;

        $games->each(function (Game $game) use (&$wasAnExpiredGame) {
            $turn = $game->getCurrentTurn();

            if ($turn->hasExpired()) {
                echo "Game {$game->getId()}, turn {$turn->getNumber()} has expired, moving to next turn." . PHP_EOL;
                $newTurn = $game->tryNextTurn($turn);
                $this->info("Game {$game->getId()}, is now on turn {$newTurn->getNumber()}.");
                $wasAnExpiredGame = true;
            }
            else {
                echo "Game {$game->getId()}, turn {$turn->getNumber()} has not expired." . PHP_EOL;
            }
        });

        if (!$wasAnExpiredGame) {
            echo "Purging expired cache entries and unreferenced cached static files." . PHP_EOL;
            $results = Metacache::purgeExpiredData();
            $this->info("Purge finished, $results.");
        }
    }
}
