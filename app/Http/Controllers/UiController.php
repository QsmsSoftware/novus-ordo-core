<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Deployment;
use App\Models\Division;
use App\Models\Game;
use App\Models\Nation;
use App\Models\NationWithSameNameAlreadyExists;
use App\Models\NotEnoughFreeTerritories;
use App\Models\Territory;
use App\Models\Turn;
use App\Models\User;
use App\Models\UserCredentials;
use App\Models\UserCredentialsRejected;
use App\Models\UserLogedIn;
use App\Models\VictoryStatus;
use App\Utils\MapsValidatorToInstance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

readonly class NewNationRequest {
    use MapsValidatorToInstance;

    public function __construct(
        public string $name
    ) {}
}

readonly class UserLoginRequest {
    use MapsValidatorToInstance;

    public function __construct(
        public string $username,
        public string $password,
    ) {}
}

readonly class Asset {
    public function __construct(
        public string $description,
    ) {}
}

readonly class Liability {
    public function __construct(
        public string $description,
    ) {}
}

class UiController extends Controller
{
    public function storeNation(Request $request) : View|RedirectResponse|Response {
        $validator = Validator::make($request->all(),
            ['name' => 'required|string|min:2']
        );
        $validator->validate();
        $requestData = NewNationRequest::fromValidator($validator);

        $game = Game::getCurrent();
        $user = User::getCurrent();
        $createResult = Nation::create($game, $user, $requestData->name);
        
        if ($createResult instanceof NationWithSameNameAlreadyExists) {
            $validator->errors()->add('name', 'A nation with that name already exists.');
        }
        else if ($createResult instanceof NotEnoughFreeTerritories) {
            return $this->generateNotEnoughTerritoriesResponse($createResult);
        }

        if ($validator->errors()->any()) {
            return redirect('create-nation')->withErrors($validator->errors())->withInput();
        }

        return redirect()->route('dashboard');
    }
    private function generateNotEnoughTerritoriesResponse(NotEnoughFreeTerritories $error) :Response {
        return new Response("Unable to create nation: There are not enough free territories remaining. {$error->required} required, {$error->remaining} remaining.");
    }
    public function createNation() : View|Response {
        if (Nation::getCurrentOrNull() !== null) {
            return response("User already has a nation (you should not have landed on this page).");
        }

        $enoughTerritoryValidation = Game::getCurrent()->hasEnoughTerritoriesForNewNation();
        if ($enoughTerritoryValidation instanceof NotEnoughFreeTerritories) {
            return $this->generateNotEnoughTerritoriesResponse($enoughTerritoryValidation);
        }

        return view('new_nation');
    }

    public function dashboard() : View|RedirectResponse {
        $nationOrNull = Nation::getCurrentOrNull();
        if ($nationOrNull === null) {
            return redirect()->route('nation.create');
        }
        $nation = Nation::notNull($nationOrNull);
        $game = $nation->getGame();
        $nationsById = $game->nations()->get()->mapWithKeys(fn (Nation $nation) => [$nation->getId() => $nation->getDetail()->export()]);

        return match ($game->getVictoryStatus()) {
            VictoryStatus::HasNotBeenWon => view('dashboard', [
                'game' => $game->exportForTurn(),
                'ownNation' => $nation->getDetail()->exportForOwner(),
                'max_remaining_deployments' => $nation->getDetail()->getMaxRemainingDeployments(),
                'budget' => $nation->getDetail()->exportBudget(),
                'budget_items' => ['production' => new Asset('Production'), 'reserves' => new Asset('Reserves'), 'upkeep' => new Liability('Upkeep'), 'expenses' => new Asset('Expenses'), 'available_production' => new Asset('Available Production')],
                'ownTerritoriesById' => $nation->getDetail()->territories()->get()
                    ->mapWithKeys(fn (Territory $t) => [$t->getId() => $t->getDetail()->exportForOwner()]),
                'ownDivisionsById' => $nation->getDetail()->activeDivisions()->get()
                    ->mapWithKeys(fn (Division $d) => [$d->getId() => $d->getDetail()->exportForOwner()]),
                'deploymentsById' => $nation->activeDeployments()->get()
                    ->mapWithKeys(fn (Deployment $d) => [$d->getId() => $d->export()]),
                'victory_progresses' => $game->getVictoryProgression(),
                'nationsById' => $nationsById,
                'territoriesById' => $game->territories()->get()
                    ->mapWithKeys(fn (Territory $t) => [$t->getId() => $t->getDetail()->export()]),
                'battleLogs' => $nation->getDetail()
                    ->getAllBattlesWhereParticipant()
                    ->map(fn (Battle $b) => $b->exportForParticipant())
            ]),
            VictoryStatus::HasBeenWon => view('gameover', [
                'winner' => Nation::notNull($game->getWinnerOrNull())->getUsualName(),
                'victory_progresses' => $game->getVictoryProgression(),
                'nationsById' => $nationsById,
            ])
        };
    }
    public function loginUser(Request $request) :RedirectResponse {
        $validated = $request->validate([
            'username' => 'required|string|min:1',
            'password' => 'required|string|min:1',
        ]);
        $request = UserLoginRequest::fromArray($validated);

        $loginResult = User::login(new UserCredentials($request->username, $request->password));

        return match (true) {
            $loginResult instanceof UserLogedIn => User::notNull(Auth::user())->isAdmin() ? redirect()->route('dev.panel') : redirect()->route('dashboard'),
            $loginResult instanceof UserCredentialsRejected => back()->withErrors([
                    'username' => 'Credentials rejected.',
                ])->onlyInput('username'),
        };
    }
}
