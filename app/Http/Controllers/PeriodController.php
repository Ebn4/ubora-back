<?php

namespace App\Http\Controllers;

use App\Enums\EvaluatorTypeEnum;
use App\Enums\PeriodStatusEnum;
use App\Http\Requests\ChangePeriodStatusRequest;
use App\Http\Resources\CriteriaResource;
use App\Http\Resources\PeriodResource;
use App\Models\Candidacy;
use App\Models\Evaluator;
use App\Models\Period;
use App\Models\StatusHistorique;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PeriodController extends Controller
{
       /**
     * @OA\Get(
     *     path="/api/period",
     *     tags={"Périodes"},
     *     summary="Lister les périodes académiques",
     *     description="Récupère la liste paginée des périodes académiques avec possibilité de recherche et filtrage.",
     *     operationId="listPeriods",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par année",
     *         required=false,
     *         @OA\Schema(type="string", example="2024")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", example="dispatch")
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
     *         description="Liste paginée des périodes",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PeriodResource")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Period::orderBy('year', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('year', 'LIKE', "%{$search}%");
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->where('status', 'LIKE', "%{$status}%");
        }

        $perPage = $request->input('per_page', 5);

        $paginated = $query->paginate($perPage);
        return PeriodResource::collection($paginated);
    }

    /**
     * @OA\Get(
     *     path="/api/getYearsPeriod",
     *     tags={"Périodes"},
     *     summary="Obtenir la liste des années avec périodes",
     *     description="Récupère toutes les périodes au format 'année1-année2' pour les sélecteurs/dropdowns.",
     *     operationId="getYearsPeriod",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des années avec périodes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="year", type="string", example="2024-2025")
     *             )
     *         )
     *     )
     * )
     */
    public function getYearsPeriod()
    {
        $query = Period::orderBy('year', 'desc')->get(['id', 'year']);

        $yearsWithPeriods = $query->map(function ($period) {
            $nextYear = $period->year + 1;

            return [
                'id' => $period->id,
                'year' => "{$period->year}-{$nextYear}",
            ];
        });

        return response()->json($yearsWithPeriods);
    }


    /**
     * @OA\Post(
     *     path="/api/period",
     *     tags={"Périodes"},
     *     summary="Créer une nouvelle période académique",
     *     description="Crée une nouvelle période académique. Si aucune année n'est spécifiée, utilise l'année courante.",
     *     operationId="createPeriod",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={},
     *             @OA\Property(property="year", type="integer", example=2025, description="Année de début de la période (optionnel)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Période créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/PeriodResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation échouée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la création de la période.")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $user_id = $request->user()->id;
        $data = $request->validate([
            'year' => 'integer|unique:periods,year|nullable',
        ]);

        $yearNow = now()->year;
        $status = PeriodStatusEnum::STATUS_DISPATCH->value;

        if (isset($data['year']) && $data['year'] != null) {
            $yearNow = $data['year'];
        }

        try {
            $period = Period::create([
                'year' => $yearNow,
                'status' => $status
            ]);
            StatusHistorique::create([
                'period_id' => $period->id,
                'user_id' => $user_id,
                'status' => $period->status
            ]);
            return response()->json([
                'success' => true,
                'data' => $period
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la période.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * @OA\Get(
     *     path="/api/period/{id}",
     *     tags={"Périodes"},
     *     summary="Obtenir les détails d'une période",
     *     description="Récupère les informations détaillées d'une période académique spécifique.",
     *     operationId="getPeriod",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la période",
     *         @OA\JsonContent(ref="#/components/schemas/PeriodResource")
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
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue.")
     *         )
     *     )
     * )
     */
    public function show(int $id)
    {
        try {
            $period = Period::findOrFail($id);
            return new PeriodResource($period);
        } catch (ModelNotFoundException $e) {
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

      /**
     * @OA\Put(
     *     path="/api/periods/{period}/status",
     *     tags={"Périodes"},
     *     summary="Changer le statut d'une période",
     *     description="Met à jour le statut d'une période académique selon le workflow prédéfini (dispatch → présélection → sélection → close).",
     *     operationId="changePeriodStatus",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="preselection",
     *                 description="Nouveau statut",
     *                 enum={"dispatch", "preselection", "selection", "close"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Statut mis à jour avec succès."),
     *             @OA\Property(property="period", ref="#/components/schemas/PeriodResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Modification non autorisée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modification non autorisée pour ce statut.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Période non trouvée"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation échouée"
     *     )
     * )
     */
    public function changePeriodStatus(ChangePeriodStatusRequest $request, int $id): JsonResponse
    {
        $period = Period::findOrFail($id);
        $currentStatus = $period->status;

        $newStatus = $request->post('status');

        $allowedTransitions = [
            PeriodStatusEnum::STATUS_DISPATCH->value => PeriodStatusEnum::STATUS_PRESELECTION->value,
            PeriodStatusEnum::STATUS_PRESELECTION->value => PeriodStatusEnum::STATUS_SELECTION->value,
            PeriodStatusEnum::STATUS_SELECTION->value => PeriodStatusEnum::STATUS_CLOSE->value,
        ];

        if (isset($allowedTransitions[$currentStatus]) && $allowedTransitions[$currentStatus] === $newStatus) {

            $period->update([
                "status" => $request->post('status')
            ]);

            return response()->json(['message' => 'Statut mis à jour avec succès.', 'period' => $period]);
        }


        return response()->json(['message' => 'Modification non autorisée pour ce statut.'], 403);
    }


    /**
     * @OA\Get(
     *     path="/api/periods/criteria/{id}",
     *     tags={"Périodes"},
     *     summary="Obtenir les critères d'évaluation d'une période",
     *     description="Récupère les critères d'évaluation associés à une période, filtrés par type (présélection ou sélection).",
     *     operationId="getPeriodCriteria",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type d'évaluation",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"PRESELECTION", "SELECTION"},
     *             default="PRESELECTION",
     *             example="SELECTION"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des critères",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/CriteriaResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Période non trouvée"
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
    public function getCriteriaPeriod(Request $request, int $id)
    {
        try {

            $type = EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value;
            if ($request->has('type') && $request->input('type') === 'SELECTION') {
                $type = EvaluatorTypeEnum::EVALUATOR_SELECTION->value;
            } elseif ($request->has('type') && $request->input('type') === 'PRESELECTION') {
                $type = EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value;
            }

            $periods = Period::with(['criteria' => function ($query) use ($type) {
                $query->wherePivot('type', $type);
            }])->findOrFail($id);

            return CriteriaResource::collection($periods->criteria);
        } catch (\Throwable $th) {
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }

      /**
     * @OA\Get(
     *     path="/api/periods/{periodId}/has-evaluators",
     *     tags={"Périodes"},
     *     summary="Vérifier si une période a des évaluateurs",
     *     description="Vérifie si au moins un évaluateur est assigné à la période spécifiée.",
     *     operationId="hasEvaluators",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="État des évaluateurs",
     *         @OA\JsonContent(
     *             @OA\Property(property="hasEvaluators", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function hasEvaluators(int $periodId): JsonResponse
    {
        $evaluators = Evaluator::query()->where('period_id', $periodId)->exists();
        return response()->json([
            "hasEvaluators" => $evaluators
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/periods/{periodId}/selection-criteria-max-score",
     *     summary="Récupérer le score maximum des critères de sélection",
     *     operationId="getSelectionCriteriaMaxScore",
     *     tags={"Périodes"},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="path",
     *         required=true,
     *         description="ID de la période",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Score maximum récupéré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Max score fetched successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="max_score", type="number", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function getSelectionCriteriaMaxScore($periodId)
    {
        try {
            $maxScore = DB::table('period_criteria')
                ->where('period_id', $periodId)
                ->where('type', 'SELECTION')
                ->sum('ponderation');

            // Si pas de critères, utiliser 100 comme valeur par défaut
            if (!$maxScore || $maxScore == 0) {
                $maxScore = 100;
            }

            return response()->json([
                'success' => true,
                'message' => 'Max score fetched successfully',
                'data' => ['max_score' => (float) $maxScore]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching max score: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching max score'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/periods/{periodId}/state",
     *     summary="Récupérer l'état détaillé d'une période",
     *     description="Récupère l'état complet d'une période avec la liste paginée des évaluateurs, 
     *         l'indicateur de dispatch et la présence d'évaluateurs.
     *         Permet de filtrer par type d'évaluateur et de rechercher par nom/email.",
     *     tags={"Périodes"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=34)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page pour la pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Nombre d'évaluateurs par page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Terme de recherche pour filtrer par nom ou email de l'évaluateur",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type d'évaluateur pour filtrer",
     *         required=false,
     *         @OA\Schema(type="string", enum={"SELECTION", "PRESELECTION"}, example="SELECTION")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="État de la période récupéré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="evaluators", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=5),
     *                         @OA\Property(property="type", type="string", enum={"SELECTION", "PRESELECTION"}, example="SELECTION"),
     *                         @OA\Property(property="period_id", type="integer", example=34),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john.doe@ubora.com")
     *                     )
     *                 ),
     *                 @OA\Property(property="isDispatched", type="boolean", description="Indique si le dispatch a été effectué pour cette période", example=true),
     *                 @OA\Property(property="hasEvaluators", type="boolean", description="Indique si la période a au moins un évaluateur", example=true),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=3),
     *                     @OA\Property(property="per_page", type="integer", example=10),
     *                     @OA\Property(property="total", type="integer", example=25),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=10)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation des paramètres",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The perPage must not be greater than 100."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Période non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Not Found")
     *         )
     *     )
     * )
     */
    public function getPeriodState($periodId, Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|min:1|max:100',
            'search' => 'nullable|string',
            'type' => 'nullable|string|in:SELECTION,PRESELECTION'
        ]);

        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', '');
        $type = $request->input('type', '');

        // Récupérer les évaluateurs avec pagination
        $evaluatorsQuery = Evaluator::with(['user:id,name,email'])
            ->where('period_id', $periodId);

        // Recherche sur le nom ou email de l'utilisateur
        if ($search) {
            $evaluatorsQuery->whereHas('user', function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $evaluatorsQuery->where('type', $type);
        }

        $evaluators = $evaluatorsQuery->paginate($perPage, ['*'], 'page', $page);

        // Vérifier si dispatché
        $isDispatched = Candidacy::query()
            ->where("period_id", $periodId)
            ->whereHas("dispatch")
            ->exists();

        // Vérifier si la période a des évaluateurs
        $hasEvaluators = Evaluator::where('period_id', $periodId)->exists();

        // Formater les évaluateurs avec les informations utilisateur
        $formattedEvaluators = $evaluators->map(function($evaluator) {
            return [
                'id' => $evaluator->id,
                'user_id' => $evaluator->user_id,
                'type' => $evaluator->type,
                'period_id' => $evaluator->period_id,
                'name' => $evaluator->user->name ?? null,
                'email' => $evaluator->user->email ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'evaluators' => $formattedEvaluators,
                'isDispatched' => $isDispatched,
                'hasEvaluators' => $hasEvaluators,
                'pagination' => [
                    'current_page' => $evaluators->currentPage(),
                    'last_page' => $evaluators->lastPage(),
                    'per_page' => $evaluators->perPage(),
                    'total' => $evaluators->total(),
                    'from' => $evaluators->firstItem(),
                    'to' => $evaluators->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/periods/{year}",
     *     summary="Supprimer une période par année",
     *     description="Supprime une période en fonction de l'année fournie",
     *     tags={"Périodes"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="year",
     *         in="path",
     *         required=true,
     *         description="Année de la période à supprimer",
     *         @OA\Schema(
     *             type="integer",
     *             example=2024
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Période supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Période 2024 supprimée avec succès")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Période non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Aucune période trouvée pour 2024")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function delete(int $year)
    {
        $deleted = Period::where('year', $year)->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => "Aucune période trouvée pour {$year}"
            ], 404);
        }

        return response()->json([
            'message' => "Période {$year} supprimée avec succès"
        ]);
    }

}
