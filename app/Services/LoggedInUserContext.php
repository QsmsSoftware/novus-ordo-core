<?php
namespace App\Services;

use App\Models\Game;
use App\Models\Nation;
use App\Models\Turn;
use App\Models\User;
use App\Utils\HttpStatusCode;
use Illuminate\Support\Facades\Auth;

class LoggedInUserContext {
    public function getUser(): User {
        $userOrNull = Auth::user();
        if(is_null($userOrNull)) {
            abort(HttpStatusCode::Unauthorized, 'Bad context: need an authenticated user.');
        }

        return $userOrNull;
    }
}