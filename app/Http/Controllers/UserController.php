<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

readonly class UserInfo {
    public function __construct(
        public string $user_name,
    ) {}
} 

class UserController extends Controller
{
    public function info() :JsonResponse {
        $user = User::getCurrent();
        
        return response()->json(new UserInfo($user->getName()));
    }
    
    public function logoutCurrentUser(Request $request) :Response {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response('Logged out. <a href="' . route('login') . '">go to login</a>');
    }
}
