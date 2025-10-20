<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\ProvisionedUser;
use App\Models\User;
use App\Models\UserAlreadyExists;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LogicException;

class CommissionServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commission-server {--admin-user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Commission a new server.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $resultMigrate = Artisan::call('migrate');
        if ($resultMigrate != Command::SUCCESS) {
            return $resultMigrate;
        }

        $adminUser = $this->option('admin-user');
        if (empty($adminUser)) {
            echo "Error: --admin-user=ADMIN_USER_NAME must be specified." . PHP_EOL;
            return Command::FAILURE;
        }
        $resultProvisionAdmin = Artisan::call('provision-admin', ["userName" => $adminUser]);
        if ($resultProvisionAdmin != Command::SUCCESS) {
            return $resultProvisionAdmin;
        }

        Game::createNew();

        return Command::SUCCESS;
    }
}
