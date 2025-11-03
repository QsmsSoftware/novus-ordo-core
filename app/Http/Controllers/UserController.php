<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LoggedInGameContext;
use App\Services\LoggedUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function info(LoggedUserContext $context) :JsonResponse {
        $user = $context->getUser();
        
        return response()->json($user->exportForOwner());
    }

    public function nationSetupStatus(LoggedInGameContext $context) :JsonResponse {
        $user = $context->getUser();
        
        return response()->json($user->exportNationSetupSatusForOwner($context->getGame()));
    }
    
    public function logoutCurrentUser() :Response {
        User::logoutCurrentUser();

        return response('Logged out. <a href="' . route('login') . '">go to login</a>');
    }
}
