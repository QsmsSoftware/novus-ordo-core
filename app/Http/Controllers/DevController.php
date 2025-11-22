<?php

namespace App\Http\Controllers;

use App\Domain\Password;
use App\Models\Deployment;
use App\Models\Division;
use App\Models\Game;
use App\Models\Nation;
use App\Models\Territory;
use App\Models\Turn;
use App\Models\User;
use App\Models\UserAlreadyExists;
use App\Services\JavascriptClientServicesGenerator;
use App\Services\JavascriptStaticServicesGenerator;
use App\Services\LoggedInGameContext;
use App\Services\NationContext;
use App\Utils\HttpStatusCode;
use App\Utils\MapsValidatedDataToFormRequest;
use App\Utils\MapsValidatorToInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

readonly class AddUserRequest {
    use MapsValidatorToInstance;
    public function __construct(
        public string $username,
        public ?string $password
    ) {}
}

readonly class LoginUserRequest {
    use MapsValidatorToInstance;
    public function __construct(
        public int $user_id
    ) {}
}

readonly class SetPasswordRequest {
    use MapsValidatorToInstance;
    public function __construct(
        public int $user_id,
        public string $new_password
    ) {}
}

readonly class DivisionRequest {
    use MapsValidatorToInstance;
    public function __construct(
        public int $division_id
    ) {}
}

readonly class DeploymentRequest {
    use MapsValidatorToInstance;
    public function __construct(
        public int $deployment_id
    ) {}
}

readonly class DivisionInfo {
    public function __construct(
        public int $division_id,
        public int $turn_id,
        public int $nation_id,
        public int $territory_id,
        public bool $is_active,
    ) {}
}

readonly class DevDeploymentInfo {
    public function __construct(
        public int $deployment_id,
        public int $turn_id,
        public int $nation_id,
        public int $territory_id,
    ) {}
}

class DevForceNextTurnRequest extends FormRequest {
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

class DevController extends Controller
{   
    public function generateServices(JavascriptStaticServicesGenerator $staticServices): Response {

        return new Response($staticServices->getStaticJsServices()->renderAsCode());
    }

    public function ajaxDivision(Request $request): JsonResponse {
         $validator = Validator::make($request->all(),
            [
                'division_id' => 'required|integer'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], HttpStatusCode::BadRequest);
        }

        $divisionRequest = DivisionRequest::fromValidator($validator);

        $game = Game::getCurrent();
        $divisionOrNull = $game->getDivisionWithIdOrNull($divisionRequest->division_id);
        if ($divisionOrNull === null) {
            return response()->json(['errors' => ['division_id' => 'No divisions with that ID in the current game.']], HttpStatusCode::NotFound);
        }
        $division = Division::notNull($divisionOrNull);

        return response()->json(new DivisionInfo(
            $division->getId(),
            Turn::getCurrentForGame($game)->getId(),
            $division->getNation()->getId(),
            $division->getMostRecentDetail()->getTerritory()->getId(),
            $division->getMostRecentDetail()->isActive()));
    }

    public function ajaxDeployment(Request $request): JsonResponse {
         $validator = Validator::make($request->all(),
            [
                'deployment_id' => 'required|integer|min:1'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], HttpStatusCode::BadRequest);
        }

        $deploymentRequest = DeploymentRequest::fromValidator($validator);

        $game = Game::getCurrent();
        $deploymentOrNull = $game->getDeploymentWithIdOrNull($deploymentRequest->deployment_id);
        if ($deploymentOrNull === null) {
            return response()->json(['errors' => ['deployment_id' => 'No deployment with that ID in the current game.']], HttpStatusCode::NotFound);
        }
        $deployment = Deployment::notNull($deploymentOrNull);

        return response()->json(new DevDeploymentInfo(
            deployment_id: $deployment->getId(),
            turn_id: $deployment->getTurn()->getId(),
            nation_id: $deployment->getNation()->getId(),
            territory_id: $deployment->getTerritory()->getId(),
        ));
    }

    public function panel(JavascriptClientServicesGenerator $servicesGenerator): View {
        $gameOrNull = Game::getCurrentOrNull();

        if ($gameOrNull === null) {
            $game_id = "no active game";
            $turn_number = "";
        }
        else {
            $game_id = $gameOrNull->getId();
            $turn_number = Turn::getCurrentForGame($gameOrNull)->getNumber();
        }

        return view('dev.panel', [
            'game_id' => $game_id,
            'turn_number' => $turn_number,
            'js_dev_client_services' => $servicesGenerator->generateClientService("DevPanelServices", "dev.ajax"),
            'js_client_services' => $servicesGenerator->generateClientService("NovusOrdoServices", "ajax"),
            'users' => User::all(),
        ]);
    }
    
    public function startGame(): RedirectResponse {
        Game::createNew();

        return redirect()->route('dev.panel');
    }

    public function nextTurn(LoggedInGameContext $context): RedirectResponse {
        $context->getGame()->tryNextTurn($context->getGame()->getCurrentTurn());

        return redirect()->back();
    }

    public function rollbackTurn(): RedirectResponse {
        Game::getCurrent()->rollbackLastTurn();

        return redirect()->route('dev.panel');
    }
    
    public function addUser(Request $request): RedirectResponse {
        $validator = Validator::make($request->all(),
            [
                'username' => 'required|string|min:1',
                'password' => 'nullable|string'
            ]
        );
        $validator->validate();
        $addRequest = AddUserRequest::fromValidator($validator);

        $userOrError = User::create($addRequest->username, $addRequest->password ? Password::fromString($addRequest->password):  Password::randomize());

        if ($userOrError instanceof UserAlreadyExists) {
            $validator->errors()->add('username', 'A user with that name already exists.');
        }

        if ($validator->errors()->any()) {
            return redirect()->route('dev.panel')->withErrors($validator->errors())->withInput();
        }

        $user = User::as($userOrError);

        return redirect()->route('dev.panel')->with('message', "User {$user->getName()} added.");
    }

    public function loginUser(Request $request): RedirectResponse {
        $validator = Validator::make($request->all(),
            ['user_id' => 'required|integer']
        );
        $validator->validate();
        $loginRequest = LoginUserRequest::fromValidator($validator);

        $user = User::notNull(User::find($loginRequest->user_id));

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function userSpa(JavascriptClientServicesGenerator $generator, int $userId): View {
        $user = User::asOrNotFound(User::find($userId), "Invalid user ID: $userId");

        Auth::login($user);

        return view('dev.spa', [
            "js_client_services" => $generator->generateClientService('NovusOrdoServices', 'ajax'),
        ]);
    }

    public function ajaxSetUserPassword(Request $request): JsonResponse {
        $validator = Validator::make($request->all(),
            [
                'user_id' => 'required|integer',
                'new_password' => 'required|string|min:1'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], HttpStatusCode::UnprocessableContent);
        }

        $setPasswordRequest = SetPasswordRequest::fromValidator($validator);

        $user = User::notNull(User::find($setPasswordRequest->user_id));

        $user->setPassword(Password::fromString($setPasswordRequest->new_password));

        return response()->json([]);
    }

    public function ajaxGeneratePassword(): JsonResponse {
        return response()->json(['password' => Password::randomize()->value]);
    }

    public function ajaxForceNextTurn(DevForceNextTurnRequest $request, LoggedInGameContext $context): JsonResponse {
        $turn = $context->getGame()->tryNextTurn(Turn::getForGameByNumberOrNull($context->getGame(), $request->turn_number));

        return response()->json(["turn_number" => $turn->getNumber()]);
    }
}
