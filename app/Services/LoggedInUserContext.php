<?php
namespace App\Services;

use App\Models\User;
use App\Utils\HttpStatusCode;
use Illuminate\Support\Facades\Auth;

class LoggedInUserContext {
    private readonly User $user;
    public function __construct()
    {
        $userOrNull = Auth::user();
        if(is_null($userOrNull)) {
            abort(HttpStatusCode::Unauthorized, 'Bad context: need an authenticated user.');
        }
        $this->user = $userOrNull;
    }

    public function getUser(): User {
        return $this->user;
    }
}