<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluatorRequest;
use App\Http\Resources\EvaluatorCandidaciesResource;
use App\Http\Resources\EvaluatorRessource;
use App\Models\Evaluator;
use App\Services\EvaluatorService;
use App\Services\UserLdapService;
use App\Services\UserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
    public function index(Request $request): AnonymousResourceCollection
    {

        $perPage = 10;

        if ($request->has('perPage')) {
            $perPage = $request->input('perPage');
        }

        $evaluators = Evaluator::query()
            ->with(["user", "period"]);

        if ($request->has('type')) {
            $type = $request->input('type');
            $evaluators = $evaluators->where('type', $type);
        }

        if ($request->has('periodId')) {
            $periodId = $request->input('periodId');
            $evaluators = $evaluators->where('period_id', $periodId);
        }

        $evaluators = $evaluators->paginate($perPage);

        return EvaluatorRessource::collection($evaluators);
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
    public function show(string $id): EvaluatorRessource
    {
        $evaluator = Evaluator::query()->findOrFail($id);
        return new EvaluatorRessource($evaluator);
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

    public function evaluatorCandidacy(int $id): AnonymousResourceCollection
    {
        $candidacies = Evaluator::query()
            ->with("dispatch")
            ->findOrFail($id);

        return EvaluatorCandidaciesResource::collection($candidacies->dispatch);
    }
}
