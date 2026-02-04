<?php

namespace App\Http\Controllers;

use App\Enums\EvaluatorTypeEnum;
use App\Enums\PeriodStatusEnum;
use App\Http\Requests\periodCriteriaAttache;
use App\Models\Criteria;
use App\Models\Evaluator;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="criteria",
 *     description="Opérations sur les critères d'évaluation"
 * )
 */
class CriteriaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/criteria",
     *     summary="Lister tous les critères",
     *     description="Récupère la liste paginée des critères avec des options de filtrage",
     *     tags={"criteria"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="query",
     *         description="ID de la période pour filtrer les critères",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type de critère pour filtrer (ex: 'preselection', 'final')",
     *         required=false,
     *         @OA\Schema(type="string", example="preselection")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Terme de recherche pour filtrer par nom ou description",
     *         required=false,
     *         @OA\Schema(type="string", example="performance")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=5, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des critères récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Performance"),
     *                 @OA\Property(property="description", type="string", example="Critère de performance"),
     *                 @OA\Property(property="status", type="string", example="actif"),
     *                 @OA\Property(property="type", type="string", example="preselection"),
     *                 @OA\Property(property="ponderation", type="number", example=30)
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=5),
     *             @OA\Property(property="total", type="integer", example=25)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur interne du serveur"),
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = Criteria::query();

            if ($request->has('periodId') && $request->input('periodId') != null) {
                $periodId = $request->input('periodId');
                $query = $query->leftJoin('period_criteria', function ($join) use ($periodId) {
                    $join->on('criterias.id', '=', 'period_criteria.criteria_id')
                        ->where('period_criteria.period_id', '=', $periodId);
                })
                    ->where('period_id', $request->input('periodId'))
                    ->select(
                        'criterias.id',
                        'criterias.name',
                        'criterias.description',
                        'criterias.status',
                        'period_criteria.type',
                        'period_criteria.ponderation'
                    );

                if ($request->has('type')) {
                    $type = $request->input('type');
                    $query = $query->where('period_criteria.type', "{$type}");
                }
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query = $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }
            $perPage = $request->input('per_page', 5);
            $query = $query->paginate($perPage);

            return $query;
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }

     /**
     * @OA\Post(
     *     path="/api/criteria",
     *     summary="Créer un nouveau critère",
     *     description="Crée un nouveau critère d'évaluation",
     *     tags={"criteria"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données du critère à créer",
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", description="Nom du critère", example="Performance"),
     *             @OA\Property(property="description", type="string", description="Description du critère", example="Critère d'évaluation de la performance", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Critère créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Critère créé avec succès."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Performance"),
     *                 @OA\Property(property="description", type="string", example="Critère d'évaluation de la performance"),
     *                 @OA\Property(property="status", type="string", example="actif")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The name field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur lors de la création",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de la création du critère."),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        try {
            $criteria = Criteria::create(array_merge($request->only(['name', 'description']), ['status' => 'actif']));

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

     /**
     * @OA\Get(
     *     path="/api/criteria/{id}",
     *     summary="Afficher un critère spécifique",
     *     description="Récupère les détails d'un critère par son ID",
     *     tags={"criteria"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du critère",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Critère récupéré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Performance"),
     *             @OA\Property(property="description", type="string", example="Critère d'évaluation de la performance"),
     *             @OA\Property(property="status", type="string", example="actif")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Critère non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Critère non trouvé.")
     *         )
     *     )
     * )
     */
    public function show(int $id)
    {
        $criteria = Criteria::find($id);

        if (!$criteria) {
            return 'Critère non trouvé.';
        }

        return $criteria;
    }

      /**
     * @OA\Put(
     *     path="/api/criteria/{id}",
     *     summary="Mettre à jour un critère",
     *     description="Met à jour la description d'un critère existant",
     *     tags={"criteria"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du critère à mettre à jour",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de mise à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="description", type="string", description="Nouvelle description du critère", example="Nouvelle description du critère de performance", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Critère mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Critère mis à jour avec succès."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Performance"),
     *                 @OA\Property(property="description", type="string", example="Nouvelle description du critère de performance"),
     *                 @OA\Property(property="status", type="string", example="actif")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Critère non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Critère non trouvé.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur lors de la mise à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de la mise à jour du critère."),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
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

      /**
     * @OA\Delete(
     *     path="/api/criteria/{id}",
     *     summary="Activer/Désactiver un critère",
     *     description="Bascule le statut d'un critère entre actif et inactif",
     *     tags={"criteria"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du critère",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut du critère mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Critère désactivé avec succès."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Performance"),
     *                 @OA\Property(property="status", type="string", example="inactif")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Critère non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Critère non trouvé.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur lors de la mise à jour du statut",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de la mise à jour du statut du critère."),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Post(
     *     path="/api/periods/attach-criteria/{id}",
     *     summary="Attacher des critères à une période",
     *     description="Attache des critères existants à une période avec leur type et pondération. La pondération est OBLIGATOIRE pour le type 'SELECTION' mais IGNORÉE pour le type 'PRESELECTION'.",
     *     tags={"criteria"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données des critères à attacher",
     *         @OA\JsonContent(
     *             required={"type", "criteria"},
     *             @OA\Property(property="type", type="string", enum={"SELECTION", "PRESELECTION"}, description="Type d'évaluation", example="SELECTION"),
     *             @OA\Property(property="criteria", type="array", description="Liste des critères avec leur pondération (obligatoire pour SELECTION, optionnel pour PRESELECTION)",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id"},
     *                     @OA\Property(property="id", type="integer", description="ID du critère existant", example=1),
     *                     @OA\Property(property="ponderation", type="number", description="⚠️ Requis pour SELECTION, ignoré pour PRESELECTION", example=30, nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Critères attachés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Critères attachés avec succès."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Période 1"),
     *                 @OA\Property(property="criteria", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Performance"),
     *                     @OA\Property(property="type", type="string", example="SELECTION"),
     *                     @OA\Property(property="ponderation", type="number", example=30, nullable=true)
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Période non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Période non trouvée.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur ou période non en statut dispatch",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Impossible d'exécuter cette action car le status n'est plus en dispatch."),
     *             @OA\Property(property="error", type="string", example="Error message", nullable=true)
     *         )
     *     )
     * )
     */
    public function attachCriteriaToPeriod(PeriodCriteriaAttache $request, int $periodId): JsonResponse
    {
        try {
            $period = Period::findOrFail($periodId);
            if ($period->status != PeriodStatusEnum::STATUS_DISPATCH->value) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'exécuter cette action car le status n\'est plus en dispatch.',
                ], 500);
            } else {
                $period->criteria()->wherePivot('type', $request->type)->detach();
                $pivotData = [];

                foreach ($request->criteria as $critere) {
                    $pivotData[$critere['id']] = [
                        'type' => $request->type,
                        'ponderation' => $critere['ponderation'],
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

     /**
     * @OA\Get(
     *     path="/api/period/join/criteria",
     *     summary="Récupérer les critères avec données de période",
     *     description="Récupère les critères actifs avec leurs données de période et éventuellement les valeurs d'évaluation",
     *     tags={"criteria"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="period_id",
     *         in="query",
     *         description="ID de la période (requis)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="dispatch_preselections_id",
     *         in="query",
     *         description="ID de la dispatch preselection (optionnel)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Terme de recherche pour filtrer par nom ou description",
     *         required=false,
     *         @OA\Schema(type="string", example="performance")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Critères récupérés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Performance"),
     *                 @OA\Property(property="description", type="string", example="Critère de performance"),
     *                 @OA\Property(property="status", type="string", example="actif"),
     *                 @OA\Property(property="type", type="string", example="preselection"),
     *                 @OA\Property(property="ponderation", type="number", example=30),
     *                 @OA\Property(property="period_criteria_id", type="integer", example=1),
     *                 @OA\Property(property="valeur", type="number", example=85, nullable=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation des paramètres",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The period_id field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur interne du serveur"),
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
    public function getCriteriaWithPeriodData(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id',
            'dispatch_preselections_id' => 'nullable|exists:dispatch_preselections,id',
        ]);

        try {
            $periodId = $request->input('period_id');
            $dispatchPreselectionsId = $request->input('dispatch_preselections_id');

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
                    'period_criteria.ponderation',
                    'period_criteria.id as period_criteria_id',
                    DB::raw('NULL as valeur')
                );

            if ($dispatchPreselectionsId) {
                $dataEvaluateur = Evaluator::query()
                    ->where("user_id", auth()->user()->id)
                    ->where("type", EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value)
                    ->where("period_id", $periodId)
                    ->firstOrFail();

                $evaluateurId = $dataEvaluateur->id;

                $query->leftJoin('preselections', function ($join) use ($dispatchPreselectionsId) {
                    $join->on('period_criteria.id', '=', 'preselections.period_criteria_id')
                        ->where('preselections.dispatch_preselections_id', '=', $dispatchPreselectionsId);
                })
                    ->leftJoin('dispatch_preselections', function ($join) {
                        $join->on('preselections.dispatch_preselections_id', '=', 'dispatch_preselections.id');
                    })
                    ->leftJoin('evaluators', function ($join) use ($evaluateurId) {
                        $join->on('dispatch_preselections.evaluator_id', '=', 'evaluators.id')
                            ->where('evaluators.id', '=', $evaluateurId);
                    })
                    ->selectRaw("CASE WHEN dispatch_preselections.evaluator_id IS NULL THEN NULL ELSE preselections.valeur END as valeur");
            }

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
