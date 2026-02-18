<?php

namespace App\Http\Controllers;
use App\Services\EvaluatorNotificationService;
use App\Enums\EvaluatorTypeEnum;
use App\Enums\PeriodStatusEnum;
use App\Http\Requests\DispatchRequest;
use App\Http\Requests\CandidaciesDispatchEvaluator;
use App\Models\Candidacy;
use App\Models\DispatchPreselection;
use App\Models\Evaluator;
use App\Models\Period;
use App\Models\Preselection;
use App\Notifications\DispatchNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\NotifyRequest;

/**
 * @OA\Tag(
 *     name="dispatch",
 *     description="Opérations de dispatch des candidatures aux évaluateurs"
 * )
 */
class DispatchController extends Controller
{

      /**
     * @OA\Get(
     *     path="/api/evaluators/{period}/is-dispatched",
     *     summary="Vérifier si un dispatch a été effectué",
     *     description="Vérifie si des candidatures ont déjà été dispatchées pour une période donnée",
     *     tags={"dispatch"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=34)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vérification effectuée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="isDispatch", type="boolean", example=true)
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
    public function hasEvaluatorBeenDispatched(int $period)
    {

        $candidacies = Candidacy::query()
            ->where("period_id", $period)
            ->whereHas("dispatch")
            ->exists();


        return response()->json([
            "isDispatch" => $candidacies
        ]);
    }

     /**
     * @OA\Post(
     *     path="/api/evaluators/{period}/dispatch",
     *     summary="Effectuer le dispatch de présélection",
     *     description="OPERATION IRREVERSIBLE : Dispatch automatique des candidatures aux évaluateurs de présélection.
     *         - Répartit équitablement les candidatures (is_allowed=true) entre les évaluateurs PRESELECTION
     *         - Chaque candidature est assignée à 2-3 évaluateurs selon le nombre total d'évaluateurs
     *         - Ne peut être effectué qu'une seule fois par période (statut DISPATCH requis)
     *         - Cette opération modifie définitivement les relations candidature-évaluateur",
     *     tags={"dispatch"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Période pour laquelle effectuer le dispatch",
     *         @OA\JsonContent(
     *             required={"periodId"},
     *             @OA\Property(property="periodId", type="integer", description="ID de la période en statut DISPATCH", example=34)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dispatch effectué avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Le dispatch de la présélection a été effectué avec succès.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Période non en statut DISPATCH",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Vous n'avez plus le droit de dispatcher : la présélection a déjà commencé.")
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
    public function dispatchPreselection(DispatchRequest $request,EvaluatorNotificationService $notificationService): JsonResponse
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
            ->where("is_allowed", true)
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

        // $notificationService->notifyPreselectionEvaluators($evaluators, $evaluatorsDispatch, $period);
        return response()->json([
            "message" => "Le dispatch de la présélection a été effectué avec succès."
        ]);
    }

      /**
     * @OA\Post(
     *     path="/api/dispatch/notify-preselection-evaluators",
     *     summary="Notifier les évaluateurs de présélection",
     *     description="Envoie des notifications par email aux évaluateurs de présélection après le dispatch.
     *          Prérequis :
     *         - Période doit être en statut DISPATCH
     *         - Le dispatch des candidatures doit avoir été effectué préalablement
     *         - Les évaluateurs doivent avoir un email valide",
     *     tags={"dispatch"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Période pour laquelle envoyer les notifications",
     *         @OA\JsonContent(
     *             required={"periodId"},
     *             @OA\Property(property="periodId", type="integer", description="ID de la période", example=34)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications envoyées avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Les notifications ont été envoyées avec succès.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Conditions non remplies (statut ou dispatch absent)",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Impossible d'envoyer les notifications : le dispatch n'est pas actif.")
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
    public function notifyPreselectionEvaluators(NotifyRequest $request, EvaluatorNotificationService $notificationService): JsonResponse
    {
        Log::info("Je suis dans le controller");
        $periodId = $request->post('periodId');
        $period = Period::findOrFail($periodId);
        Log::info("periodId reçu : " . $request->post('periodId'));
        
        Log::info("le statut de la periode : $period->status");
        if (
            $period->status !== PeriodStatusEnum::STATUS_DISPATCH->value
            && $period->status !== PeriodStatusEnum::STATUS_PRESELECTION->value
        ) {
            return response()->json([
                'error' => 'Impossible d’envoyer les notifications : le dispatch ou la présélection doivent être actifs.'
            ], 400);
        }


        // Vérifier qu’il y a bien des dispatchs
        $dispatches = DB::table('dispatch_preselections')
            ->join('candidats', 'dispatch_preselections.candidacy_id', '=', 'candidats.id')
            ->where('candidats.period_id', $periodId)
            ->exists();
        
        Log::info("Les dispatchs  : $dispatches");

        if (!$dispatches) {
            return response()->json(['error' => 'Aucun dispatch trouvé pour cette période.'], 400);
        }

        // Récupérer les données nécessaires
        $candidacies = Candidacy::where('period_id', $periodId)->where('is_allowed', true)->get();
        $evaluators = Evaluator::where('period_id', $periodId)
            ->where('type', EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value)
            ->get();

        // Reconstituer $evaluatorsDispatch (ou stockez-le en cache/session si possible)
        $evaluatorsDispatch = [];
        foreach ($candidacies as $candidacy) {
            $evals = DB::table('dispatch_preselections')
                ->where('candidacy_id', $candidacy->id)
                ->pluck('evaluator_id')
                ->toArray();
            $evaluatorsDispatch[$candidacy->id] = $evals;
        }

        $notificationService->notifyPreselectionEvaluators($evaluators, $evaluatorsDispatch, $period);

        return response()->json(['message' => 'Les notifications ont été envoyées avec succès.']);
    }


       /**
     * @OA\Get(
     *     path="/api/CandidaciesDispatchEvaluator",
     *     summary="Récupérer les candidatures dispatchées à l'évaluateur connecté",
     *     description="Récupère la liste paginée des candidatures assignées à l'évaluateur de présélection connecté pour une période donnée.
     *         Deux cas de réponse 200 possibles :
     *         1. Évaluateur assigné : Retourne les candidatures paginées ou complètes
     *         2. Évaluateur non assigné : Retourne un message explicatif avec tableau vide",
     *     tags={"dispatch"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="query",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=34)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Terme de recherche (nom, prénom, postnom, ville)",
     *         required=false,
     *         @OA\Schema(type="string", example="Kinshasa")
     *     ),
     *     @OA\Parameter(
     *         name="ville",
     *         in="query",
     *         description="Filtrer par ville",
     *         required=false,
     *         @OA\Schema(type="string", example="Kinshasa")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre de résultats par page (ou 'all' pour tout récupérer)",
     *         required=false,
     *         @OA\Schema(type="string", example="10")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Succès - Candidatures récupérées OU évaluateur non assigné",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Cas 1 : Évaluateur assigné - Réponse paginée",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="etn_nom", type="string", example="MUTOMBO"),
     *                             @OA\Property(property="etn_prenom", type="string", example="Jean"),
     *                             @OA\Property(property="etn_postnom", type="string", example="KABONGO"),
     *                             @OA\Property(property="ville", type="string", example="Kinshasa"),
     *                             @OA\Property(property="candidaciesPreselection", type="integer", description="Nombre total de candidatures déjà évaluées par cet évaluateur", example=3),
     *                             @OA\Property(property="statusCandidacy", type="boolean", description="Indique si cette candidature a déjà été évaluée", example=false),
     *                             @OA\Property(property="totalCandidats", type="integer", description="Nombre total de candidatures assignées à cet évaluateur", example=15),
     *                             @OA\Property(property="periodStatus", type="string", description="Statut actuel de la période", example="PRESELECTION"),
     *                             @OA\Property(
     *                                 property="dispatch",
     *                                 type="array",
     *                                 @OA\Items(
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=1),
     *                                     @OA\Property(property="evaluator_id", type="integer", example=5)
     *                                 )
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=5),
     *                     @OA\Property(property="per_page", type="integer", example=10),
     *                     @OA\Property(property="total", type="integer", example=50)
     *                 ),
     *                 @OA\Schema(
     *                     type="array",
     *                     description="Cas 2 : Évaluateur assigné - Réponse non paginée (per_page=all)",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="etn_nom", type="string", example="MUTOMBO"),
     *                         @OA\Property(property="etn_prenom", type="string", example="Jean"),
     *                         @OA\Property(property="etn_postnom", type="string", example="KABONGO"),
     *                         @OA\Property(property="ville", type="string", example="Kinshasa"),
     *                         @OA\Property(property="candidaciesPreselection", type="integer", description="Nombre total de candidatures déjà évaluées par cet évaluateur", example=3),
     *                         @OA\Property(property="statusCandidacy", type="boolean", description="Indique si cette candidature a déjà été évaluée", example=false),
     *                         @OA\Property(property="totalCandidats", type="integer", description="Nombre total de candidatures assignées à cet évaluateur", example=15),
     *                         @OA\Property(property="periodStatus", type="string", description="Statut actuel de la période", example="PRESELECTION"),
     *                         @OA\Property(
     *                             property="dispatch",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="evaluator_id", type="integer", example=5)
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Cas 3 : Évaluateur NON assigné à cette période",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Vous n'êtes pas assigné comme évaluateur pour cette période."),
     *                     @OA\Property(property="data", type="array", @OA\Items())
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la récupération des périodes."),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function CandidaciesDispatchByEvaluator(Request $request)
    {
        $periodId = $request->input("periodId");

        $dataEvaluateur = Evaluator::query()
            ->where("user_id", auth()->id())
            ->where("type", EvaluatorTypeEnum::EVALUATOR_PRESELECTION->value)
            ->where("period_id", $periodId)
            ->first();

        if (!$dataEvaluateur) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas assigné comme évaluateur pour cette période.',
                'data' => []
            ], 200);
        }

        $evaluateurId = $dataEvaluateur->id;

        $query = Candidacy::with(['dispatch' => function ($q) use ($evaluateurId) {
            $q->where('evaluator_id', $evaluateurId);
        }])
        ->where("period_id", $periodId)
        ->whereHas("dispatch", function ($q) use ($evaluateurId) {
            $q->where("evaluator_id", $evaluateurId);
        });

        // Recherche
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('etn_nom', 'like', "%$search%")
                ->orWhere('etn_prenom', 'like', "%$search%")
                ->orWhere('etn_postnom', 'like', "%$search%")
                ->orWhere('ville', 'like', "%$search%");
            });
        }

        // Filtre ville
        if ($ville = $request->input('ville')) {
            $query->where('ville', 'like', "%$ville%");
        }

        $candidaciesPreselection = DispatchPreselection::where('evaluator_id', $evaluateurId)
            ->has("preselections")
            ->count();

        $periodStatus = Period::query()
            ->where("id", $periodId)
            ->value("status");

        $count = $query->count();
        $perPage = $request->input('per_page', 5);

        // Gestion du cas "all"
        if ($perPage === 'all') {
            $results = $query->get();

            $results->transform(function ($item) use ($candidaciesPreselection, $count, $evaluateurId, $periodStatus) {
                $item->dispatch = $item->dispatch->first(); // garder seulement le premier dispatch

                $item->statusCandidacy = DispatchPreselection::where('candidacy_id', $item->id)
                    ->where('evaluator_id', $evaluateurId)
                    ->has('preselections')
                    ->exists();

                $item->candidaciesPreselection = $candidaciesPreselection;
                $item->totalCandidats = $count;
                $item->periodStatus = $periodStatus;

                return $item;
            });

            return $results;
        }

        // Pagination classique
        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function ($item) use ($candidaciesPreselection, $count, $evaluateurId, $periodStatus) {
            $item->dispatch = $item->dispatch->first();

            $item->statusCandidacy = DispatchPreselection::where('candidacy_id', $item->id)
                ->where('evaluator_id', $evaluateurId)
                ->has('preselections')
                ->exists();

            $item->candidaciesPreselection = $candidaciesPreselection;
            $item->totalCandidats = $count;
            $item->periodStatus = $periodStatus;

            return $item;
        });

        return $paginated;
    }



    public function sendDispatchNotification()
    {
        $preselections = DispatchPreselection::with('evaluator.user')->get();

        $users = $preselections->map(function ($preselection) {
            return $preselection->evaluator?->user;
        })->filter()->unique('id');

        $urlFront = 'http://localhost:4200';

        Notification::send($users, new DispatchNotification($urlFront));
        return response()->json(['success' => true, 'message' => 'Notifications envoyées.']);
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
