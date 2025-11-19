<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DevController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\NationController;
use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\UiController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureGameIsNotUpkeeping;
use App\Http\Middleware\EnsureWhenRunningInDevelopmentOnly;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Default index.
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Development panel and endpoints
Route::middleware(['auth', EnsureWhenRunningInDevelopmentOnly::class])->group(function () {
    Route::get('/dev-panel', [DevController::class, 'panel'])->name('dev.panel');
    Route::post('/dev-panel/start-game', [DevController::class, 'startGame'])->name('dev.start-game');
    Route::post('/dev-panel/next-turn', [DevController::class, 'nextTurn'])->name('dev.next-turn');
    Route::post('/dev-panel/rollback-turn', [DevController::class, 'rollbackTurn'])->name('dev.rollback-turn');
    Route::post('/dev-panel/add-user', [DevController::class, 'addUser'])->name('dev.add-user');
    Route::get('/dev-panel/login-user', [DevController::class, 'loginUser'])->name('dev.login-user');
    Route::post('/dev-panel/ajax-set-user-password', [DevController::class, 'ajaxSetUserPassword'])->name('dev.ajax.set-user-password');
    Route::get('/dev-panel/ajax-generate-password', [DevController::class, 'ajaxGeneratePassword'])->name('dev.ajax.generate-password');
    Route::get('/dev-panel/ajax-division', [DevController::class, 'ajaxDivision'])->name('dev.ajax.division');
    Route::get('/dev-panel/ajax-deployment', [DevController::class, 'ajaxDeployment'])->name('dev.ajax.deployment');
    Route::get('/dev-panel/services', [DevController::class, 'generateServices'])->name('dev.generate-js-client-services');
    Route::get('/dev-panel/spa/{userId}', [DevController::class, 'userSpa'])->name('dev.spa');
    Route::post('/dev-panel/ajax-force-next-turn', [DevController::class, 'ajaxForceNextTurn'])
        ->name('dev.ajax.force-next-turn');
    //Temporary endpoints:
    // Route::get('/dev/territory-assign/{territoryId}/{nationId}', [DevController::class, 'assignTerritory'])
    //     ->whereNumber('territoryId', 'nationId');
    // Route::get('/dev/division-deploy/{territoryId}/{nationId}', [DevController::class, 'deployDivision'])
    //     ->whereNumber('territoryId', 'nationId');
});

// User and login/logout routes.
Route::get('login', function () {
    return view('login', ['adminExists' => User::adminExists()]);
})->name('login');
Route::middleware('throttle:login')->group(function () {
    Route::post('/login-user', [UiController::class, 'loginUser'])
        ->name('user.login');
});
Route::middleware('auth')->group(function () {
    Route::get('/logout', [UserController::class, 'logoutCurrentUser'])->name('logout');
    Route::get('/user', [UserController::class, 'info'])
        ->name('ajax.get-user-info');
    Route::get('/user/nation-setup-status', [UserController::class, 'nationSetupStatus'])
        ->name('ajax.get-user-nation-setup-status');
});

// Game routes
Route::get('/game', [GameController::class, 'info']);
Route::get('/game/ready-status', [GameController::class, 'readyStatus'])
    ->name('ajax.get-game-ready-status');
Route::get('/game/rankings', [GameController::class, 'rankings'])
    ->name('ajax.get-game-rankings');

// Nation routes.
Route::middleware('auth')->group(function () {
    Route::get('/nation', [NationController::class, 'ownNationInfo'])
        ->name('ajax.get-nation-info');
    Route::get('/nation/budget', [NationController::class, 'budgetInfo'])
        ->name('ajax.get-nation-budget');
    Route::get('/nation/battle-logs/', [NationController::class, 'lastTurnBattleLogs'])
        ->name('ajax.get-nation-battle-logs');
    Route::middleware(EnsureGameIsNotUpkeeping::class)->group(function () {
        Route::post('/nation', [NationController::class, 'createNation'])
            ->name('ajax.create-nation');
        Route::post('/nation:select-home-territories', [NationController::class, 'selectHomeTerritories'])
            ->name('ajax.select-home-territories');
    });
});
Route::get('/nations/{nationId}', [NationController::class, 'info'])
    ->whereNumber('nationId');

// Territory routes.
Route::get('/territories', [TerritoryController::class, 'allTerritories'])
    ->name('ajax.get-all-territories');
Route::get('/territories/base-infos', [TerritoryController::class, 'allTerritoriesBaseInfo'])
    ->name('ajax.get-all-territories-base-info');
Route::get('/territories/base-infos/ref', [TerritoryController::class, 'allTerritoriesBaseInfoStaticLink'])
    ->name('ajax.get-all-territories-base-info-ref');
Route::get('/territories/turn-infos', [TerritoryController::class, 'allTerritoriesTurnInfo'])
    ->name('ajax.get-all-territories-turn-info');
Route::get('/territories/suitable-as-home-ids', [TerritoryController::class, 'allTerritoriesSuitableAsHomeIds'])
    ->name('ajax.get-all-territories-suitable-as-home-ids');
Route::get('/territories/{territoryId}/base-info', [TerritoryController::class, 'info'])
    ->whereNumber('territoryId')
    ->name('ajax.get-territory-base-info');
Route::get('/territories/{territoryId}/turn-info', [TerritoryController::class, 'turnInfo'])
    ->whereNumber('territoryId')
    ->name('ajax.get-territory-turn-info');
Route::middleware('auth')->group(function () {
    Route::get('/nation/territories/turn-infos', [TerritoryController::class, 'nationTerritoriesTurnInfo'])
        ->name('ajax.get-nation-territories-turn-info');
});
// Division routes.
Route::middleware('auth')->group(function () {
    Route::get('/nation/divisions', [DivisionController::class, 'allOwnedDivisions'])
        ->name('ajax.get-nation-divisions');
    Route::get('/nation/divisions/{divisionId}', [DivisionController::class, 'ownedDivision'])
        ->whereNumber('divisionId')
        ->name('ajax.get-nation-division');
    Route::middleware(EnsureGameIsNotUpkeeping::class)->group(function () {
            Route::post('/nation/divisions/move-orders', [DivisionController::class, 'sendMoveOrders'])
                ->name('ajax.send-move-orders');
            Route::post('/nation/divisions/disband-orders', [DivisionController::class, 'sendDisbandOrders'])
                ->name('ajax.send-disband-orders');
            Route::post('/nation/divisions:cancel-orders', [DivisionController::class, 'cancelOrders'])
                ->name('ajax.cancel-orders');
    });
});

// Deployment routes
Route::middleware('auth')->group(function () {
    Route::get('nation/deployments', [DeploymentController::class, 'allDeployments'])
        ->name('ajax.get-all-deployments');
    Route::get('nation/territories/{territoryId}/deployments', [DeploymentController::class, 'allDeploymentsInOwnedTerritory'])
        ->whereNumber('territoryId')
        ->name('ajax.get-territory-deployments');
    Route::middleware(EnsureGameIsNotUpkeeping::class)->group(function () {
        Route::post('nation/deployments/cancel-deployment-requests', [DeploymentController::class, 'cancelDeployments'])
            ->name('ajax.cancel-deployments');
        Route::post('nation/territories/deployments', [DeploymentController::class, 'deploy'])
            ->name('ajax.deploy');
    });
});

// Asset routes.
Route::get('assets/{encodedUri}', [AssetController::class, 'assetInfo'])
    ->name('ajax.get-asset-info');

// UI
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [UiController::class, 'dashboard'])
        ->name('dashboard');
    Route::post('/ready-for-next-turn', [UiController::class, 'readyForNextTurn'])
        ->name('ajax.ready-for-next-turn');
    Route::middleware(EnsureGameIsNotUpkeeping::class)->group(function () {
            Route::get('/create-nation', [UiController::class, 'createNation'])
                ->name('nation.create');
            Route::post('/create-nation', [UiController::class, 'storeNation'])
                ->name('nation.store');
    });
});