<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameSharedStaticAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class UpdateGameSharedStaticAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-game-shared-static-assets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reread metadata in res/bundled and res/local and add or update the ressources for the active games.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $games = Game::where('is_active', true)->get();

        assert($games instanceof Collection);

        $games->each(function (Game $game) {
            GameSharedStaticAsset::inventory($game);
            $this->info("Updated shared static resources for game {$game->getId()}.");
        });
    }
}
