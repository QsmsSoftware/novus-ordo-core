<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Deployment;
use App\Models\Division;
use App\Models\Game;
use App\Models\Nation;
use App\Models\NewNation;
use App\Models\NotEnoughFreeTerritories;
use App\Models\Territory;
use App\Models\Turn;
use App\Models\User;
use App\Models\UserCredentials;
use App\Models\UserCredentialsRejected;
use App\Models\UserLogedIn;
use App\Models\VictoryStatus;
use App\Services\JavascriptClientServicesGenerator;
use App\Services\JavascriptStaticServicesGenerator;
use App\Services\LoggedInGameContext;
use App\Services\NationContext;
use App\Services\StaticJavascriptResource;
use App\Utils\MapsValidatedDataToFormRequest;
use App\Utils\MapsValidatorToInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreateNationUiRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public string $name;
    public readonly array $territory_ids;

    public function __construct(
        private readonly LoggedInGameContext $context
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
                NewNation::createRuleNoNationWithSameNameInGameUnlessItsOwner($this->context->getGame(), $this->context->getUser())
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
    public string $type;
    public function __construct(
        public string $description,
    ) {
        $this->type = "Asset";
    }
}

readonly class Liability {
    public string $type;
    public function __construct(
        public string $description,
    ) {
        $this->type = "Liability";
    }
}

class ReadyForNextTurnRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public readonly int $turn_number;

    public function __construct(
        private readonly LoggedInGameContext $context,
    )
    {
        
    }
    
    public function rules(): array
    {
        return [
            'turn_number' => [
                'required',
                'int',
                'min:1',
                Rule::exists(Turn::class, Turn::FIELD_TURN_NUMBER)
                    ->where('game_id', $this->context->getGame()->getId())
            ],
        ];
    }
}

class UiController extends Controller
{
    public function storeNation(CreateNationUiRequest $request, LoggedInGameContext $context) : View|RedirectResponse|Response {
        $game = $context->getGame();
        $user = $context->getUser();

        $newNationOrNull = NewNation::getForUserOrNull($game, $user);
        if (is_null($newNationOrNull)) {
            $newNation = NewNation::notNull(NewNation::tryCreate($game, $user, $request->name));
        }
        else {
            $newNation = $newNationOrNull;
            $newNation->rename($request->name);
        }

        $newNation->finishSetup(...$request->territory_ids);

        return redirect()->route('dashboard');
    }
    private function generateNotEnoughTerritoriesResponse(NotEnoughFreeTerritories $error) :Response {
        return new Response("Unable to create nation: There are not enough free territories remaining. {$error->required} required, {$error->remaining} remaining.");
    }
    public function createNation(LoggedInGameContext $context) : View|Response {
        if (Nation::getForUserOrNull($context->getGame(), $context->getUser()) !== null) {
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
            'territories' => Territory::exportAll($context->getGame(), $context->getGame()->getCurrentTurn()),
            'static_js_territories' => Territory::getAllTerritoriesClientResource($context->getGame()->getCurrentTurn()),
            'territories_by_row_column' => $territoriesByRowThenColumn,
            'number_of_home_territories' => Game::NUMBER_OF_STARTING_TERRITORIES,
            'suitable_as_home_ids' => $context->getGame()->freeSuitableTerritoriesInTurn()->pluck('id'),
            'already_taken_ids' => $context->getGame()->alreadyTakenTerritoriesInTurn()->pluck('id'),
        ]);
    }

    public function dashboard(JavascriptStaticServicesGenerator $staticServices, JavascriptClientServicesGenerator $servicesGenerator, LoggedInGameContext $context) : View|RedirectResponse {
        $nationOrNull = Nation::getForUserOrNull($context->getGame(), $context->getUser());
        if ($nationOrNull === null) {
            return redirect()->route('nation.create');
        }
        $context = new NationContext;
        $nation = $context->getNation();
        $game = $context->getGame();
        $nationsById = $game->nations()->get()->mapWithKeys(fn (Nation $nation) => [$nation->getId() => $nation->getDetail()->export()]);

        return match ($game->getVictoryStatus()) {
            VictoryStatus::HasNotBeenWon => view('dashboard', [
                'context' => new NationContext,
                'own_nation' => $nation->getDetail()->exportForOwner(),
                'ready_status' => $game->exportReadyStatus(),
                'nations' => $nationsById->values(),
                'deployments' => $nation->activeDeployments()->get()
                    ->map(fn (Deployment $d) => $d->export())
                    ->values(),
                'divisions' => $nation->getDetail()->activeDivisions()->get()
                    ->map(fn (Division $d) => $d->getDetail()->exportForOwner())
                    ->values(),
                'static_js_services' => $staticServices->getStaticJsServices(),
                'static_js_dev_services' => StaticJavascriptResource::permanent('devservices', fn () => $servicesGenerator->generateClientService('DevServices', 'dev.ajax')),
                'static_js_territories' => Territory::getAllTerritoriesClientResource($game->getCurrentTurn()),
                'victory_ranking' => $game->getVictoryProgression()->values(),
                'budget' => $nation->getDetail()->exportBudget(),
                'budget_items' => ['production' => new Asset('Production'), 'reserves' => new Asset('Reserves'), 'upkeep' => new Liability('Upkeep'), 'expenses' => new Liability('Expenses'), 'available_production' => new Asset('Available Production')],
                'battle_logs' => $nation->getDetail()
                     ->getAllBattlesWhereParticipant()
                     ->map(fn (Battle $b) => $b->exportForParticipant()),
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

    public function readyForNextTurn(NationContext $context, ReadyForNextTurnRequest $request): JsonResponse {
        $game = $context->getGame();
        $turn = Turn::getForGameByNumberOrNull($game, $request->turn_number);
        $context->getNation()->readyForNextTurn($turn);
        $game->tryNextTurnIfNationsReady($turn);

        return response()->json($context->getGame()->exportReadyStatus());
    }
}
