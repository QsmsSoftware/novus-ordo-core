<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\ProvisionedUser;
use App\Models\User;
use App\Models\UserAlreadyExists;
use Illuminate\Console\Command;
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
        $adminUser = $this->option('admin-user');
        if (empty($adminUser)) {
            echo "Error: --admin-user=ADMIN_USER_NAME must be specified." . PHP_EOL;
            return 1;
        }
        $provisionedOrError = User::provisionAdministrator($adminUser); 
        if ($provisionedOrError instanceof ProvisionedUser) {
            echo "Admin user $adminUser provisioned with password: {$provisionedOrError->password->value}\n";
        }
        else if ($provisionedOrError instanceof UserAlreadyExists) {
            echo "User $adminUser already exists.\n";
            return 1;
        }
        else {
            throw new LogicException("Unexpected result.");
        }

        Game::createNew();

        return 0;
    }
}
