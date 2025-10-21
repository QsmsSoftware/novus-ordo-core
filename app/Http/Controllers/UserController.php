<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LoggedInUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function info(LoggedInUserContext $context) :JsonResponse {
        $user = $context->getUser();
        
        return response()->json($user->exportForOwner());
    }
    
    public function logoutCurrentUser() :Response {
        User::logoutCurrentUser();

        return response('Logged out. <a href="' . route('login') . '">go to login</a>');
    }
}
