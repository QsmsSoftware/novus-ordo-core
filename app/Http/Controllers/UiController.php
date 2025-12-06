<?php

namespace App\Http\Controllers;

use App\Facades\ImageProcessing;
use App\Facades\ImageProcessingError;
use App\Models\Battle;
use App\Models\Deployment;
use App\Models\Division;
use App\Models\Game;
use App\Models\Nation;
use App\Models\NewNation;
use App\Models\GameHasNotEnoughFreeTerritories;
use App\Models\LeaderDetail;
use App\Models\ProductionBid;
use App\Models\Territory;
use App\Models\TerritoryDetail;
use App\Models\Turn;
use App\Models\User;
use App\Models\UserCredentials;
use App\Models\UserCredentialsRejected;
use App\Models\UserLogedIn;
use App\ReadModels\GameReadyStatusInfo;
use App\Services\JavascriptClientServicesGenerator;
use App\Services\JavascriptStaticServicesGenerator;
use App\Services\LoggedInGameContext;
use App\Services\NationContext;
use App\Services\StaticJavascriptResource;
use App\Utils\Annotations\Summary;
use App\Utils\ImageSource;
use App\Utils\MapsValidatedDataToFormRequest;
use App\Utils\MapsValidatorToInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreateNationUiRequest extends FormRequest {
    use MapsValidatedDataToFormRequest;

    public string $nation_name;
    public ?string $nation_formal_name;
    public string $leader_name;
    public ?string $leader_title;
    public readonly array $territory_ids;

    public function __construct(
        private readonly LoggedInGameContext $context
    )
    {
        
    }

    public function getFlagFileOrNull(): ?UploadedFile {
        return ImageProcessing::getFileOrNull($this->file('nation_flag'));
    }

    public function getLeaderPictureFileOrNull(): ?UploadedFile {
        return ImageProcessing::getFileOrNull($this->file('leader_picture'));
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
            'nation_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                NewNation::createRuleNoNationWithSameNameInGameUnlessItsOwner($this->context->getGame(), $this->context->getUser())
            ],
            'nation_formal_name' => [
                'nullable',
                'string',
                'min:2',
                'max:1024',
            ],
            'leader_name' => [
                'required',
                'string',
                'min:2',
                'max:1024',
            ],
            'leader_title' => [
                'nullable',
                'string',
                'min:2',
                'max:1024',
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
            $newNation = NewNation::notNull(NewNation::tryCreate($game, $user, $request->nation_name));
        }
        else {
            $newNation = $newNationOrNull;
            $newNation->rename($request->nation_name);
        }

        $formalNameOrNull = empty($request->nation_formal_name) ? null : $request->nation_formal_name;

        $flagFileOrNull = $request->getFlagFileOrNull();
        $flagSrcOrNull = null;

        if (!is_null($flagFileOrNull)) {
            $flagSrcOrError = ImageProcessing::cropFitToPublicImage(
                imageFile: $flagFileOrNull,
                targetImageWidthPixels: 300,
                targetImageHeightPixels: 200,
                destinationSrc: "var/game_{$context->getGame()->getId()}-nation_{$newNation->getId()}-initial_flag.png",
            );

            if ($flagSrcOrError instanceof ImageProcessingError) {
                return back()->withErrors([
                        'nation_flag' => $flagSrcOrError->message,
                ])->withInput();
            }

            $flagSrcOrNull = $flagSrcOrError;
        }

        $leaderPictureFileOrNull = $request->getLeaderPictureFileOrNull();
        $leaderPictureSrcOrNull = null;

        if (!is_null($leaderPictureFileOrNull)) {
            $leaderPictureSrcOrError = ImageProcessing::cropFitToPublicImage(
                imageFile: $leaderPictureFileOrNull,
                targetImageWidthPixels: 200,
                targetImageHeightPixels: 400,
                destinationSrc: "var/game_{$context->getGame()->getId()}-nation_{$newNation->getId()}-initial_leader_picture.png",
            );

            if ($leaderPictureSrcOrError instanceof ImageProcessingError) {
                return back()->withErrors([
                        'nation_flag' => $leaderPictureSrcOrError->message,
                ])->withInput();
            }

            $leaderPictureSrcOrNull = $leaderPictureSrcOrError;
        }

        $leaderTitleOrNull = empty($request->leader_title) ? null : $request->leader_title;

        $newNation->finishSetup(
            homeTerritoryIds: $request->territory_ids,
            flagSrc: $flagSrcOrNull,
            leaderName: $request->leader_name,
            formalName: $formalNameOrNull,
            leaderTitleOrNull: $leaderTitleOrNull,
            leaderPictureSrcOrNull: $leaderPictureSrcOrNull,
        );

        return redirect()->route('dashboard');
    }
    private function generateNotEnoughTerritoriesResponse(GameHasNotEnoughFreeTerritories $error) :Response {
        return new Response("Unable to create nation: There are not enough free territories remaining. {$error->required} required, {$error->remaining} remaining.");
    }
    public function createNation(JavascriptStaticServicesGenerator $staticServices, LoggedInGameContext $context) : View|Response {
        if (Nation::getForUserOrNull($context->getGame(), $context->getUser()) !== null) {
            return response("User already has a nation (you should not have landed on this page).");
        }

        $enoughTerritoryValidation = $context->getGame()->hasEnoughTerritoriesForNewNation();
        if ($enoughTerritoryValidation instanceof GameHasNotEnoughFreeTerritories) {
            return $this->generateNotEnoughTerritoriesResponse($enoughTerritoryValidation);
        }

        return view('new_nation', [
            'static_js_services' => $staticServices->getStaticJsServices(),
            'static_js_territories_base_info' => Territory::getAllTerritoriesBaseInfoClientResource($context->getGame()),
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
        $turn = $game->getCurrentTurn();
        $previousTurn = $turn->getPreviousTurn();
        $nationDetail = $nation->getDetail($turn);
        $previousDetailTurn = $nationDetail->getPreviousDetail()->getTurn();
        $nationsById = $game->nations()->get()->mapWithKeys(fn (Nation $nation) => [$nation->getId() => $nation->getDetail()->export()]);

        return view('dashboard', [
            'context' => new NationContext,
            'territories_last_turn_info' => TerritoryDetail::exportAllTurnPublicInfo($previousTurn),
            'own_nation' => $nationDetail->exportForOwner(),
            'own_territories_turn_info' => TerritoryDetail::exportAllTurnOwnerInfo($nation, $turn),
            'own_territories_last_turn_info' => TerritoryDetail::exportAllTurnOwnerInfo($nation, $previousDetailTurn),
            'ready_status' => $game->exportReadyStatus(),
            'nations' => $nationsById->values(),
            'leaders' => LeaderDetail::getAll($turn)->map(fn (LeaderDetail $leaderDetail) => $leaderDetail->export()),
            'deployments' => $nationDetail->deployments()->get()
                ->map(fn (Deployment $d) => $d->export())
                ->values(),
            'divisions' => $nationDetail->activeDivisions()->get()
                ->map(fn (Division $d) => $d->getDetail()->exportForOwner())
                ->values(),
            'static_js_services' => $staticServices->getStaticJsServices(),
            'static_js_dev_services' => StaticJavascriptResource::permanent('devservices', fn () => $servicesGenerator->generateClientService('DevServices', 'dev.ajax')),
            'static_js_territories_base_info' => Territory::getAllTerritoriesBaseInfoClientResource($game),
            'static_js_territories_turn_info' => TerritoryDetail::getAllTerritoriesTurnInfoClientResource($turn),
            'static_js_rankings' => $game->getRankingsClientResource($turn),
            'victory_status' => $game->exportVictoryStatus(),
            'budget' => $nationDetail->exportBudget(),
            'budget_items' => ['production' => new Asset('Production'), 'stockpiles' => new Asset('Reserves'), 'upkeep' => new Liability('Upkeep'), 'expenses' => new Liability('Expenses'), 'available_production' => new Asset('Available Production')],
            'production_bids' => ProductionBid::getAllCommandBids($nationDetail)->map(fn (ProductionBid $b) => $b->exportForOwner()),
            'battle_logs' => $nationDetail
                    ->getAllBattlesWhereParticipant()
                    ->map(fn (Battle $b) => $b->exportForParticipant()),
        ]);
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

    #[Summary('Notify the server that the current nation\'s owner  is ready to current end turn and move to next turn. If this triggers the end of the turn, this query will wait for the upkeep to be complete. It returns a GameReadyStatusInfo so the client can immediately check if the turn had ended.')]
    #[Response(GameReadyStatusInfo::class)]
    public function readyForNextTurn(NationContext $context, ReadyForNextTurnRequest $request): JsonResponse {
        $game = $context->getGame();
        $turn = Turn::getForGameByNumberOrNull($game, $request->turn_number);
        $context->getNation()->readyForNextTurn($turn);
        $game->tryNextTurnIfNationsReady($turn);

        return response()->json($context->getGame()->exportReadyStatus());
    }
}
