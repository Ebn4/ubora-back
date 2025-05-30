<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluatorRequest;
use App\Services\EvaluatorService;
use App\Services\UserLdapService;
use App\Services\UserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class EvaluatorController extends Controller
{
    public function __construct(
        private EvaluatorService $evaluatorService,
        private UserService      $userService,
        private UserLdapService  $userLdapService
    )
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EvaluatorRequest $request): void
    {
        try {
            $ldapUser = $this->userLdapService->findUserByCuid($request->cuid);
            $user = $this->userService->create($ldapUser->email, $ldapUser->cuid, "evaluator", $ldapUser->name);
            $this->evaluatorService->addEvaluator($user->id, $request->periodId, $request->type);
        } catch (\Exception $e) {
            throw  new HttpResponseException(
                response: response()->json(['errors' => $e->getMessage()], 400)
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
