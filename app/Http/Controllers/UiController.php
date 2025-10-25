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
use App\Services\NationSetupContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsValidatedDataToFormRequest;
use App\Utils\MapsValidatorToInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use LogicException;

class CreateNationUiRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public string $name;
    public readonly array $territory_ids;

    public function __construct(
        private readonly NationSetupContext $context
    )
    {
        
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'territory_ids' => json_decode($this->territory_ids_as_json),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                NewNation::createRuleNoNationWithSameNameInGame($this->context->getGame())
            ],
            'territory_ids' => [
                'required',
                'array',
                'min:' . Game::NUMBER_OF_STARTING_TERRITORIES,
                'max:' . Game::NUMBER_OF_STARTING_TERRITORIES,
                Territory::createValidationSuitableHomeTerritory($this->context->getGame())
            ],
        ];
    }
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
    public function storeNation(CreateNationUiRequest $request, LoggedInGameContext $context) : View|RedirectResponse|Response {
        $game = $context->getGame();
        $user = $context->getUser();

        $territories = $game->freeSuitableTerritoriesInTurn()->whereIn('id', $request->territory_ids)->get();

        $createResult = NewNation::tryCreate($game, $user, $request->name);
        
        $newNation = NewNation::notNull($createResult);

        $newNation->finishSetup($territories);

        return redirect()->route('dashboard');
    }
    private function generateNotEnoughTerritoriesResponse(NotEnoughFreeTerritories $error) :Response {
        return new Response("Unable to create nation: There are not enough free territories remaining. {$error->required} required, {$error->remaining} remaining.");
    }
    public function createNation(LoggedInGameContext $context, JavascriptClientServicesGenerator $servicesGenerator) : View|Response {
        if (Nation::getCurrentOrNull() !== null) {
            return response("User already has a nation (you should not have landed on this page).");
        }

        $enoughTerritoryValidation = $context->getGame()->hasEnoughTerritoriesForNewNation();
        if ($enoughTerritoryValidation instanceof NotEnoughFreeTerritories) {
            return $this->generateNotEnoughTerritoriesResponse($enoughTerritoryValidation);
        }

        $territoriesByRowThenColumn = [];

        $context->getGame()->territories()->get()->each(function (Territory $t) use (&$territoriesByRowThenColumn) {
            $territoriesByRowThenColumn[$t->getY()][$t->getX()] = $t;
        });

        return view('new_nation', [
            'territories_by_row_column' => $territoriesByRowThenColumn,
            'number_of_home_territories' => Game::NUMBER_OF_STARTING_TERRITORIES,
            'suitable_as_home_ids' => $context->getGame()->freeSuitableTerritoriesInTurn()->pluck('id'),
            'js_client_services' => $servicesGenerator->generateClientService("NovusOrdoServices", "ajax"),
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
