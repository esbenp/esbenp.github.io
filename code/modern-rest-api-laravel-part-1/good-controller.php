<?php

namespace App\Http\Controllers;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    public function create(CreateUserRequest $request)
    {
        $user = $this->userService->create($request->get('user'));

        return response()->json($user, 201);
    }
}
