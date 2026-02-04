<?php

namespace App\Http\Controllers;

use App\Enums\EvaluatorTypeEnum;
use App\Enums\PeriodStatusEnum;
use App\Http\Requests\EvaluatorRequest;
use App\Http\Resources\CandidacyResource;
use App\Http\Resources\EvaluatorCandidaciesResource;
use App\Http\Resources\EvaluatorRessource;
use App\Models\Evaluator;
use App\Models\Period;
use App\Models\User;
use App\Services\EvaluatorService;
use App\Services\UserLdapService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Évaluateurs",
 *     description="Opérations sur les évaluateurs"
 * )
 */
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
     * @OA\Get(
     *     path="/api/evaluators",
     *     summary="Lister tous les évaluateurs",
     *     description="Récupère la liste paginée des évaluateurs avec des options de filtrage",
     *     tags={"Évaluateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="query",
     *         description="ID de la période pour filtrer les évaluateurs",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Terme de recherche pour filtrer par nom ou email de l'utilisateur",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type d'évaluateur pour filtrer (SELECTION ou PRESELECTION)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"SELECTION", "PRESELECTION"}, example="SELECTION")
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des évaluateurs récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", example="SELECTION"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john.doe@ubora.com")
     *                 ),
     *                 @OA\Property(property="period", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Période 2024"),
     *                     @OA\Property(property="year", type="integer", example=2024)
     *                 )
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="total", type="integer", example=50)
     *         )
     *     )
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {

        $perPage = 10;

        if ($request->has('perPage')) {
            $perPage = $request->input('perPage');
        }

        $evaluators = Evaluator::query()
            ->with(["user", "period"]);

        if ($request->has('periodId')) {
            $periodId = $request->input('periodId');
            $evaluators = $evaluators->where('period_id', $periodId);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $evaluators = $evaluators->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }
        if ($request->has('type') && $request->input('type') != "") {
            $type = $request->input('type');
            $evaluators = $evaluators->where('type', "=", $type);
        }

        $evaluators = $evaluators->paginate($perPage);

        return EvaluatorRessource::collection($evaluators);
    }

    /**
     * @OA\Post(
     *     path="/api/evaluators",
     *     summary="Créer un nouvel évaluateur",
     *     description="Ajoute un utilisateur en tant qu'évaluateur pour une période spécifique",
     *     tags={"Évaluateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de l'évaluateur à créer",
     *         @OA\JsonContent(
     *             required={"email", "type", "periodId"},
     *             @OA\Property(property="email", type="string", format="email", description="Email de l'évaluateur", example="john.doe@ubora.com"),
     *             @OA\Property(property="name", type="string", description="Nom de l'évaluateur", example="John Doe"),
     *             @OA\Property(property="cuid", type="string", description="Identifiant unique de l'utilisateur", example="JD123456"),
     *             @OA\Property(property="type", type="string", enum={"SELECTION", "PRESELECTION"}, description="Type d'évaluateur", example="SELECTION"),
     *             @OA\Property(property="periodId", type="integer", description="ID de la période", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Évaluateur créé avec succès"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur lors de la création de l'évaluateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="string", example="L'utilisateur est déjà enregistré en tant qu'évaluateur pour l'épreuve de SELECTION.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation des données",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The email field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(EvaluatorRequest $request): void
    {
        try {
            $type = $request->type;
            $period = Period::query()->findOrFail($request->periodId);

            if ($period->status == PeriodStatusEnum::STATUS_CLOSE->value) {
                throw new \Exception("Vous n'avez plus le droit d'ajouter un evaluateur pour cette periode.");
            }


            $exists = User::where('email', $request->email)->exists();
            Log::info("L'utilisateur trouvé $exists");
            if ($exists) {
                $user = User::where('email', $request->email)->first();
            } else {
                $user = $this->userService->create($request->email, $request->cuid, "evaluator", $request->name);
            }

            $evaluator = Evaluator::query()
                ->where('user_id', $user->id)
                ->where('period_id', $request->periodId)
                ->where(function ($query) use ($type, $request) {
                    if ($type === 'SELECTION') {
                        $query->where('type', 'SELECTION')
                            ->where('period_id', $request->periodId);
                    } elseif ($type === 'PRESELECTION') {
                        $query->where('type', 'PRESELECTION')
                            ->where('period_id', $request->periodId);
                    }
                })
                ->first();

            if ($evaluator) {
                throw new \Exception("L'utilisateur est déjà enregistré en tant qu'évaluateur pour l'épreuve de {$type}.");
            }


            if ($period->status != PeriodStatusEnum::STATUS_DISPATCH->value && $type == EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value) {
                throw new \Exception("Vous n'avez plus le droit d'ajouter un evaluateur de PRESELECTION pour cette periode.");
            }

            $this->evaluatorService->addEvaluator($user->id, $request->periodId, $type);
        } catch (\Exception $e) {
            throw  new HttpResponseException(
                response: response()->json(['errors' => $e->getMessage()], 400)
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/evaluators/{id}",
     *     summary="Afficher un évaluateur spécifique",
     *     description="Récupère les détails d'un évaluateur par son ID avec ses candidatures",
     *     tags={"Évaluateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'évaluateur",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Évaluateur récupéré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="SELECTION"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@ubora.com")
     *             ),
     *             @OA\Property(property="period", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Période 2024"),
     *                 @OA\Property(property="year", type="integer", example=2024)
     *             ),
     *             @OA\Property(property="candidacies", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Candidature 1")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Évaluateur non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Not Found")
     *         )
     *     )
     * )
     */
    public function show(string $id): EvaluatorRessource
    {
        $evaluator = Evaluator::query()
            ->with(["candidacies"])
            ->findOrFail($id);
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
     * @OA\Delete(
     *     path="/api/evaluators/{id}",
     *     summary="Supprimer un évaluateur",
     *     description="Supprime un évaluateur de la base de données (uniquement si la période est en statut DISPATCH)",
     *     tags={"Évaluateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'évaluateur",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Évaluateur supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur lors de la suppression",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="string", example="Vous n'avez pas le droit d'effacer cet evaluateur car le status de la periode est PRESELECTION.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Évaluateur non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Not Found")
     *         )
     *     )
     * )
     */
    public function destroy(string $id)
    {
        try {
            $evaluator = Evaluator::query()
                ->findOrFail($id);

            $period = Period::query()->findOrFail($evaluator->period_id);

            if ($period->status != PeriodStatusEnum::STATUS_DISPATCH->value) {
                throw new \Exception("Vous  n'avez pas le droit d'effacer cet evaluateur car le status de la periode est PRESELECTION.");
            }

            $evaluator->delete();
        } catch (\Exception $e) {
            throw  new HttpResponseException(
                response: response()->json(['errors' => $e->getMessage()], 400)
            );
        }
    }

    
    public function evaluatorCandidacy(int $id): AnonymousResourceCollection
    {
        $candidacies = Evaluator::query()
            ->with("dispatch")
            ->findOrFail($id);

        return EvaluatorCandidaciesResource::collection($candidacies->dispatch);
    }

       /**
     * @OA\Get(
     *     path="/api/evaluators/{id}/candidacies",
     *     summary="Récupérer les candidatures détaillées d'un évaluateur",
     *     description="Récupère la liste détaillée des candidatures d'un évaluateur avec toutes les informations",
     *     tags={"Évaluateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'évaluateur",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidatures récupérées avec succès",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Candidature 1"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Évaluateur non trouvé"
     *     )
     * )
     */
    public function getEvaluatorCandidacies(int $id): AnonymousResourceCollection
    {
        $candidacies = Evaluator::query()
            ->with("candidacies")
            ->findOrFail($id)
            ->candidacies;

        return CandidacyResource::collection($candidacies);
    }

    
    /**
     * @OA\Get(
     *     path="/api/evaluators/is-selector-evaluator",
     *     summary="Vérifier si l'utilisateur est évaluateur de sélection",
     *     description="Vérifie si l'utilisateur connecté est un évaluateur de sélection pour une période donnée ou la plus récente",
     *     tags={"Évaluateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="query",
     *         description="ID de la période (optionnel, si non fourni utilise la période la plus récente)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vérification effectuée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="isSelectorEvaluator", type="boolean", example=true),
     *             @OA\Property(property="periodId", type="integer", example=1, nullable=true),
     *             @OA\Property(property="periodName", type="string", example="Période 2024", nullable=true),
     *             @OA\Property(property="periodYear", type="integer", example=2024, nullable=true),
     *             @OA\Property(property="usedProvidedPeriod", type="boolean", example=true, nullable=true),
     *             @OA\Property(property="usedLatestPeriod", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="availablePeriods", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="year", type="integer", example=2024),
     *                 @OA\Property(property="name", type="string", example="Période 2024"),
     *                 @OA\Property(property="status", type="string", example="active")
     *             ), nullable=true),
     *             @OA\Property(property="message", type="string", example="Évaluateur de sélection trouvé pour la période 2024", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur lors de la vérification",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="isSelectorEvaluator", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la vérification")
     *         )
     *     )
     * )
     */
    public function isSelectorEvaluator(Request $request): JsonResponse
    {
        try {
            $userId = auth()->user()->id;
            $user = auth()->user();
            Log::info("L'utilisateur connecté : $user et son id : $userId");
            // Si periodId fourni, l'utiliser
            if ($request->has('periodId') && $request->periodId) {
                Log::info("PeriodeId est là");
                $evaluators = Evaluator::query()
                    ->where('type', EvaluatorTypeEnum::EVALUATOR_SELECTION->value)
                    ->where('user_id', $userId)
                    ->where('period_id', $request->periodId)
                    ->exists();

                Log::info("Evaluateur trouvé : $evaluators");

                return response()->json([
                    "success" => true,
                    "isSelectorEvaluator" => $evaluators,
                    "periodId" => $request->periodId,
                    "usedProvidedPeriod" => true
                ]);
            }

            Log::info("On passe à l'etape 2");
            // Trouver TOUTES les périodes où l'utilisateur est évaluateur de sélection
            $evaluatorPeriods = Evaluator::query()
                ->where('type', EvaluatorTypeEnum::EVALUATOR_SELECTION->value)
                ->where('user_id', $userId)
                ->with('period')
                ->get()
                ->pluck('period')
                ->filter()
                ->sortByDesc('year')
                ->values();

            if ($evaluatorPeriods->isNotEmpty()) {
                // Prendre la période la plus récente
                $latestPeriod = $evaluatorPeriods->first();

                return response()->json([
                    "success" => true,
                    "isSelectorEvaluator" => true,
                    "periodId" => $latestPeriod->id,
                    "periodName" => $latestPeriod->year,
                    "periodYear" => $latestPeriod->year,
                    "availablePeriods" => $evaluatorPeriods->map(function($period) {
                        return [
                            'id' => $period->id,
                            'year' => $period->year,
                            'name' => $period->name,
                            'status' => $period->status
                        ];
                    }),
                    "usedLatestPeriod" => true,
                    "message" => "Évaluateur de sélection trouvé pour la période " . $latestPeriod->year
                ]);
            }

            // Aucune période trouvée
            return response()->json([
                "success" => true,
                "isSelectorEvaluator" => false,
                "availablePeriods" => [],
                "message" => "Vous n'êtes pas évaluateur de sélection"
            ]);

        } catch (\Throwable $th) {
            \Log::error('Erreur isSelectorEvaluator', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "isSelectorEvaluator" => false,
                "message" => "Une erreur est survenue lors de la vérification"
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/evaluators/is-preselector-evaluator",
     *     summary="Vérifier si l'utilisateur est évaluateur de présélection",
     *     description="Vérifie si l'utilisateur connecté est un évaluateur de présélection pour une période donnée ou la plus récente",
     *     tags={"Évaluateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="query",
     *         description="ID de la période (optionnel, si non fourni utilise la période la plus récente)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vérification effectuée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="isPreselectorEvaluator", type="boolean", example=true),
     *             @OA\Property(property="periodId", type="integer", example=1, nullable=true),
     *             @OA\Property(property="periodName", type="string", example="Période 2024", nullable=true),
     *             @OA\Property(property="periodYear", type="integer", example=2024, nullable=true),
     *             @OA\Property(property="usedProvidedPeriod", type="boolean", example=true, nullable=true),
     *             @OA\Property(property="usedLatestPeriod", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="availablePeriods", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="year", type="integer", example=2024),
     *                 @OA\Property(property="name", type="string", example="Période 2024"),
     *                 @OA\Property(property="status", type="string", example="active")
     *             ), nullable=true),
     *             @OA\Property(property="message", type="string", example="Évaluateur de présélection trouvé pour la période 2024", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur lors de la vérification",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="isPreselectorEvaluator", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la vérification")
     *         )
     *     )
     * )
     */
    public function isPreselectorEvaluator(Request $request): JsonResponse
    {
        try {
            $userId = auth()->user()->id;
            $user = auth()->user();
            Log::info("L'utilisateur connecté : $user");
            // Si periodId fourni, l'utiliser
            if ($request->has('periodId') && $request->periodId) {
                Log::info("PeriodeId est là");
                $evaluators = Evaluator::query()
                    ->where('type', EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value)
                    ->where('user_id', $userId)
                    ->where('period_id', $request->periodId)
                    ->exists();
                
                Log::info("Evaluateurs trouvé : $evaluators");

                return response()->json([
                    "success" => true,
                    "isPreselectorEvaluator" => $evaluators,
                    "periodId" => $request->periodId,
                    "usedProvidedPeriod" => true
                ]);
            }

            // Trouver TOUTES les périodes où l'utilisateur est évaluateur de présélection
            $evaluatorPeriods = Evaluator::query()
                ->where('type', EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value)
                ->where('user_id', $userId)
                ->with('period')
                ->get()
                ->pluck('period')
                ->filter()
                ->sortByDesc('year')
                ->values();

            if ($evaluatorPeriods->isNotEmpty()) {
                // Prendre la période la plus récente
                $latestPeriod = $evaluatorPeriods->first();

                return response()->json([
                    "success" => true,
                    "isPreselectorEvaluator" => true,
                    "periodId" => $latestPeriod->id,
                    "periodName" => $latestPeriod->year,
                    "periodYear" => $latestPeriod->year,
                    "availablePeriods" => $evaluatorPeriods->map(function($period) {
                        return [
                            'id' => $period->id,
                            'year' => $period->year,
                            'name' => $period->name,
                            'status' => $period->status
                        ];
                    }),
                    "usedLatestPeriod" => true,
                    "message" => "Évaluateur de présélection trouvé pour la période " . $latestPeriod->year
                ]);
            }

            // Aucune période trouvée
            return response()->json([
                "success" => true,
                "isPreselectorEvaluator" => false,
                "availablePeriods" => [],
                "message" => "Vous n'êtes pas évaluateur de présélection"
            ]);

        } catch (\Throwable $th) {
            \Log::error('Erreur isPreselectorEvaluator', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                "success" => false,
                "isPreselectorEvaluator" => false,
                "message" => "Une erreur est survenue lors de la vérification"
            ], 500);
        }
    }

    /**
     * Récupère toutes les périodes où l'utilisateur est évaluateur
     * (Pour le sélecteur de période dans le frontend)
     */
    public function getEvaluatorPeriods(Request $request): JsonResponse
    {
        try {
            $userId = auth()->user()->id;

            $periods = Period::whereHas('evaluators', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('year', 'desc')
            ->get(['id', 'year', 'name', 'status', 'created_at']);

            return response()->json([
                "success" => true,
                "periods" => $periods,
                "count" => $periods->count()
            ]);

        } catch (\Throwable $th) {
            \Log::error('Erreur getEvaluatorPeriods', [
                'error' => $th->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "periods" => [],
                "message" => "Une erreur est survenue"
            ], 500);
        }
    }
}
