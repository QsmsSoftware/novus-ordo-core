<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Deployment;
use App\Models\Division;
use App\Models\Game;
use App\Models\Nation;
use App\Models\NationWithSameNameAlreadyExists;
use App\Models\NewNation;
use App\Models\NotEnoughFreeTerritories;
use App\Models\Territory;
use App\Models\TerritoryInfo;
use App\Models\Turn;
use App\Models\User;
use App\Models\UserCredentials;
use App\Models\UserCredentialsRejected;
use App\Models\UserLogedIn;
use App\Models\VictoryStatus;
use App\Services\JavascriptClientServicesGenerator;
use App\Services\LoggedInGameContext;
use App\Services\LoggedInUserContext;
use App\Utils\MapsValidatorToInstance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use LogicException;

readonly class NewNationRequest {
    use MapsValidatorToInstance;

    public function __construct(
        public string $name,
        public string $territories_ids_json,
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
    public function storeNation(Request $request, LoggedInGameContext $context) : View|RedirectResponse|Response {
        $validator = Validator::make($request->all(),
            [
                'name' => 'required|string|min:2',
                'territories_ids_json' => 'required|string',
            ]
        );
        $validator->validate();
        $requestData = NewNationRequest::fromValidator($validator);
        $selectedHomeTerritories = json_decode($request->territories_ids_json);

        if (!is_array($selectedHomeTerritories) || sizeof($selectedHomeTerritories) != Game::NUMBER_OF_STARTING_TERRITORIES) {
            throw new LogicException("territories_ids_json is not an array of the right length");
        }

        $game = $context->getGame();
        $user = $context->getUser();

        $territories = $game->freeSuitableTerritoriesInTurn()->whereIn('id', $selectedHomeTerritories)->get();

        if ($territories->count() != Game::NUMBER_OF_STARTING_TERRITORIES) {
            throw new LogicException("One or more IDs in territories_ids_json are invalid");
        }

        $numberOfTerritoriesValidation = $game->hasEnoughTerritoriesForNewNation();

        if ($numberOfTerritoriesValidation instanceof NotEnoughFreeTerritories) {
            return $this->generateNotEnoughTerritoriesResponse($numberOfTerritoriesValidation);
        }

        $createResult = NewNation::tryCreate($game, $user, $requestData->name);
        
        if ($createResult instanceof NationWithSameNameAlreadyExists) {
            $validator->errors()->add('name', 'A nation with that name already exists.');
        }
        if ($validator->errors()->any()) {
            return redirect('create-nation')->withErrors($validator->errors())->withInput();
        }
        $newNation = NewNation::notNull($createResult);

        //$territories = $game->freeSuitableTerritoriesInTurn()->take(Game::NUMBER_OF_STARTING_TERRITORIES)->get();

        $newNation->finishSetup($territories);

        return redirect()->route('dashboard');
    }
    private function generateNotEnoughTerritoriesResponse(NotEnoughFreeTerritories $error) :Response {
        return new Response("Unable to create nation: There are not enough free territories remaining. {$error->required} required, {$error->remaining} remaining.");
    }
    public function createNation(LoggedInGameContext $context) : View|Response {
        if (Nation::getCurrentOrNull() !== null) {
            return response("User already has a nation (you should not have landed on this page).");
        }

        $enoughTerritoryValidation = $context->getGame()->hasEnoughTerritoriesForNewNation();
        if ($enoughTerritoryValidation instanceof NotEnoughFreeTerritories) {
            return $this->generateNotEnoughTerritoriesResponse($enoughTerritoryValidation);
        }

        $territories = [];

        $context->getGame()->territories()->get()->each(function (Territory $t) use (&$territories) {
            $territories[$t->getY()][$t->getX()] = $t;
        });

        return view('new_nation', [
            'territories' => $territories,
            'number_of_home_territories' => Game::NUMBER_OF_STARTING_TERRITORIES,
            'suitable_as_home_ids' => $context->getGame()->freeSuitableTerritoriesInTurn()->pluck('id'),
        ]);
    }

    public function dashboard(JavascriptClientServicesGenerator $servicesGenerator) : View|RedirectResponse {
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
                'territoriesById' => collect(Territory::exportAll($game, $game->getCurrentTurn()))
                    ->mapWithKeys(fn (TerritoryInfo $t) => [$t->territory_id => $t]),
                'battleLogs' => $nation->getDetail()
                    ->getAllBattlesWhereParticipant()
                    ->map(fn (Battle $b) => $b->exportForParticipant()),
                'js_client_services' => $servicesGenerator->generateClientService("NovusOrdoServices", "ajax"),
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
