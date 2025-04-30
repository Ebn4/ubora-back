<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthenticationRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthenticationService;
use Illuminate\Http\Request;
use \Exception;
use Illuminate\Support\Facades\DB;

class AuthenticationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AuthenticationRequest $request, AuthenticationService $authenticationService)
    {
        try {
            $user = $authenticationService->login($request->cuid, $request->password);
            return new UserResource($user);
        } catch (Exception $e) {
            return response(['message' => $e->getMessage()], 400);
        }
    }
}
