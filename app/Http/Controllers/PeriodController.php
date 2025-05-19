<?php

namespace App\Http\Controllers;

use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PeriodController extends Controller
{
    public function index(): JsonResponse
    {
        $period = Period::all();
        return response()->json([
            'success' => true,
            'data' => $period
        ]);
    }

    public function getCriteriaForPeriod(int $id): JsonResponse
    {
        try {
            $period = Period::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $period->criteria
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Période non trouvée.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function attachCriteriaToPeriod(Request $request, int $periodId): JsonResponse
    {
        $request->validate([
            'criteria_ids' => 'required|array',
            'criteria_ids.*' => 'exists:criterias,id'
        ]);

        try {
            $period = Period::findOrFail($periodId);
            $period->criteria()->syncWithoutDetaching($request->criteria_ids);

            return response()->json([
                'success' => true,
                'message' => 'Critères attachés avec succès.',
                'data' => $period->load('criteria')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Période non trouvée.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'attachement des critères.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
