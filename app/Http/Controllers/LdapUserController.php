<?php

namespace App\Http\Controllers;

use App\Http\Resources\LdapUserResource;
use App\Services\UserLdapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class LdapUserController extends Controller
{

    public function __construct(
        private UserLdapService $userLdapService
    )
    {

    }

    /**
     * Handle the incoming request.
     */
   public function __invoke(Request $request)
    {
        if (strlen($request->user ?? '') < 2) {
            return response()->json([
                'message' => 'La recherche doit contenir au moins 2 caractères'
            ], 422);
        }

        $users = $this->userLdapService->searchUser($request->user);


        return LdapUserResource::collection($users);
    }

}
