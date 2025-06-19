<?php

namespace App\Http\Controllers;

use App\Enums\EvaluatorTypeEnum;
use App\Enums\PeriodStatusEnum;
use App\Http\Requests\DispatchRequest;
use App\Models\Candidacy;
use App\Models\Evaluator;
use App\Models\Period;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class DispatchController extends Controller
{

    public function hasEvaluatorBeenDispatched(int $periodId)
    {

        $candidacies = Candidacy::query()
            ->where("period_id", $periodId)
            ->whereHas("dispatch")
            ->exists();


        return response()->json([
            "isDispatch" => $candidacies
        ]);
    }

    public function dispatchPreselection(DispatchRequest $request): JsonResponse
    {

        $periodId = $request->post("periodId");

        $period = Period::query()->findOrFail($periodId);

        if ($period->status != PeriodStatusEnum::STATUS_DISPATCH->value) {
            throw new HttpResponseException(
                response: response()->json([
                    "error" => "Vous n'avez plus le droit de dispatcher : la présélection a déjà commencé."
                ])
            );
        }

        $candidacies = Candidacy::query()
            ->where("period_id", $periodId)
            ->get();

        $evaluators = Evaluator::query()
            ->where("period_id", $periodId)
            ->where("type", EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value)
            ->get();

        $candidaciesIds = $candidacies
            ->pluck('id')
            ->toArray();

        $evaluatorsIds = $evaluators
            ->pluck('id')
            ->toArray();

        $evaluatorsDispatch = dispatch(
            candidatesIds: $candidaciesIds,
            evaluatedIds: $evaluatorsIds
        );

        foreach ($evaluatorsDispatch as $candidacyId => $evaluatorsIds) {
            $candidacy = Candidacy::query()->findOrFail($candidacyId);
            $candidacy->dispatch()->toggle($evaluatorsIds);
        }

        return response()->json([
            "message" => "Le dispatch de la présélection a été effectué avec succès."
        ]);
    }
}

function dispatch($candidatesIds, $evaluatedIds): array
{
    $result = [];
    $n = count($evaluatedIds);

    // Déterminer la taille du groupe (3 ou 2 selon la taille de e)
    $group_size = ($n >= 3) ? 3 : max(1, $n);

    foreach ($candidatesIds as $i => $ci) {
        $start = $i % $n;
        $group = [];

        for ($j = 0; $j < $group_size; $j++) {
            $index = ($start + $j) % $n;
            $group[] = $evaluatedIds[$index];
        }

        $result["$ci"] = $group;
    }

    return $result;
}
