<?php

use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DevController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\NationController;
use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\UiController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureWhenRunningInDevelopmentOnly;
use App\Models\User;
use App\Services\NationContext;
use Illuminate\Support\Facades\Route;

// Laravel default index.
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
    Route::post('/dev-panel/ajax-ready-for-next-turn', [DevController::class, 'ajaxReadyForNextTurn'])
        ->name('dev.ajax.ready-for-next-turn');
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
});

//Game routes
Route::get('/game', [GameController::class, 'info']);
Route::get('/game/ready-status', [GameController::class, 'readyStatus'])
    ->name('ajax.get-game-ready-status');

//Nation routes.
Route::middleware('auth')->group(function () {
    Route::get('/nation', [NationController::class, 'ownNationInfo'])
        ->name('ajax.get-nation-info');
    Route::post('/nation', [NationController::class, 'createNation'])
        ->name('ajax.create-nation');
    Route::post('/nation:select-home-territories', [NationController::class, 'selectHomeTerritories'])
        ->name('ajax.select-home-territories');
    Route::get('/nation/budget', [NationController::class, 'budgetInfo'])
        ->name('ajax.get-nation-budget');
    Route::get('/nation/battle-logs/', [NationController::class, 'lastTurnBattleLogs'])
        ->name('ajax.get-nation-battle-logs');
});
Route::get('/nations/{nationId}', [NationController::class, 'info'])
    ->whereNumber('nationId');

//Territory routes.
Route::middleware('auth')->group(function () {
    Route::get('/nation/territories', [TerritoryController::class, 'allOwnedTerritories'])
        ->name('ajax.get-nation-territories');
});
Route::get('/territories', [TerritoryController::class, 'allTerritories'])
    ->name('ajax.get-all-territories');
Route::get('/territories/suitable-as-home-ids', [TerritoryController::class, 'allTerritoriesSuitableAsHomeIds'])
    ->name('ajax.get-all-territories-suitable-as-home-ids');
Route::get('/territories/{territoryId}', [TerritoryController::class, 'info'])
    ->whereNumber('territoryId')
    ->name('ajax.get-territory');

//Division routes.
Route::middleware('auth')->group(function () {
    Route::get('/nation/divisions', [DivisionController::class, 'allOwnedDivisions'])
        ->name('ajax.get-nation-divisions');
    Route::post('/nation/divisions/orders', [DivisionController::class, 'sendMoveOrders'])
        ->name('ajax.send-move-orders');
    Route::post('/nation/divisions:cancel-orders', [DivisionController::class, 'cancelOrders'])
        ->name('ajax.cancel-orders');
    Route::get('/nation/divisions/{divisionId}', [DivisionController::class, 'ownedDivision'])
        ->whereNumber('divisionId')
        ->name('ajax.get-nation-division');
});

// Deployment routes
Route::middleware('auth')->group(function () {
    Route::get('nation/deployments', [DeploymentController::class, 'allDeployments'])
        ->name('ajax.get-all-deployments');
    // Route::post('nation/deployments', [DeploymentController::class, 'deployDivisions'])
    //     ->name('ajax.deployDivisions');
    Route::post('nation/deployments/cancel-deployment-requests', [DeploymentController::class, 'cancelDeployments'])
        ->name('ajax.cancel-deployments');
    Route::get('nation/territories/{territoryId}/deployments', [DeploymentController::class, 'allDeploymentsInOwnedTerritory'])
        ->whereNumber('territoryId')
        ->name('ajax.get-territory-deployments');
    Route::post('nation/territories/{territoryId}/deployments', [DeploymentController::class, 'deployInOwnedTerritory'])
        ->whereNumber('territoryId')
        ->name('ajax.deploy-in-territory');
});

//UI
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [UiController::class, 'dashboard'])
        ->name('dashboard');
    Route::get('/create-nation', [UiController::class, 'createNation'])
        ->name('nation.create');
    Route::post('/create-nation', [UiController::class, 'storeNation'])
        ->name('nation.store');
});