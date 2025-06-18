<?php

namespace App\Http\Controllers;

use App\Http\Requests\periodCriteriaAttache;
use App\Models\Criteria;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CriteriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Criteria::query();
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('periodId') && $request->input('periodId') != null) {
                $periodId = $request->input('periodId');
                $query->leftJoin('period_criteria', function ($join) use ($periodId) {
                    $join->on('criterias.id', '=', 'period_criteria.criteria_id')
                        ->where('period_criteria.period_id', '=', $periodId);
                })
                    ->where('criterias.status', '=', 'actif')
                    ->select(
                        'criterias.id',
                        'criterias.name',
                        'criterias.description',
                        'criterias.status',
                        'period_criteria.type',
                        'period_criteria.ponderation'
                    );



                $query = $query->where('period_id', $request->input('periodId'));
            }
            $perPage = $request->input('per_page', 3);
            $query = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $query
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'actif'
        ]);

        try {
            $criteria = Criteria::create($request->only(['name', 'description']));

            return response()->json([
                'success' => true,
                'message' => 'Critère créé avec succès.',
                'data' => $criteria
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du critère.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Critère non trouvé.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $criteria
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'description' => 'string|nullable|max:500'
        ]);

        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Critère non trouvé.'
            ], 404);
        }

        try {
            $criteria->update($request->only(['description']));

            return response()->json([
                'success' => true,
                'message' => 'Critère mis à jour avec succès.',
                'data' => $criteria
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du critère.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $criteria = Criteria::find($id);

        if (!$criteria) {
            return response()->json([
                'success' => false,
                'message' => 'Critère non trouvé.'
            ], 404);
        }

        try {
            if ($criteria->status === 'actif') {
                $criteria->status = 'inactif';
                $message = 'Critère désactivé avec succès.';
            } else {
                $criteria->status = 'actif';
                $message = 'Critère activé avec succès.';
            }
            $criteria->save();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $criteria
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut du critère.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function attachCriteriaToPeriod(PeriodCriteriaAttache $request, int $periodId): JsonResponse
    {
        try {
            $period = Period::findOrFail($periodId);
            if ($period->status != 'Dispatch') {
                return response()->json([
                'success' => false,
                'message' => 'Impossible d\'exécuter cette action car le status n\'est plus en dispatch.',
            ], 500);
            } else {
                $period->criteria()->wherePivot('type', $request->type)->detach();

                $pivotData = [];

                foreach ($request->criteria as $criterion) {
                    $pivotData[$criterion['id']] = [
                        'type' => $request->type,
                        'ponderation' => $criterion['ponderation'],
                    ];
                }

                $period->criteria()->syncWithoutDetaching($pivotData);

                return response()->json([
                    'success' => true,
                    'message' => 'Critères attachés avec succès.',
                    'data' => $period->load('criteria'),
                ]);
            }
        } catch (ModelNotFoundException $e) {
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


    public function getCriteriaWithPeriodData(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id'
        ]);

        try {
            $periodId = $request->input('period_id');

            $query = DB::table('criterias')
                ->leftJoin('period_criteria', function ($join) use ($periodId) {
                    $join->on('criterias.id', '=', 'period_criteria.criteria_id')
                        ->where('period_criteria.period_id', '=', $periodId);
                })
                ->where('criterias.status', '=', 'actif')
                ->select(
                    'criterias.id',
                    'criterias.name',
                    'criterias.description',
                    'criterias.status',
                    'period_criteria.type',
                    'period_criteria.ponderation'
                );

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('criterias.name', 'LIKE', "%{$search}%")
                        ->orWhere('criterias.description', 'LIKE', "%{$search}%");
                });
            }

            $results = $query->get();

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }
}
