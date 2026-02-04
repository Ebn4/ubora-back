<?php

namespace App\Http\Controllers;

use App\Enums\PeriodStatusEnum;
use App\Models\Candidacy;
use App\Models\Interview;
use App\Models\Period;
use App\Models\Preselection;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="preselection",
 *     description="Opérations sur les pré-sélections des candidatures"
 * )
 */
class PreselectionController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/getPreselectionsForDispatch/{dispatchId}",
     *     summary="Récupérer une pré-sélection par ID de dispatch",
     *     description="Récupère les détails d'une pré-sélection spécifique en utilisant l'ID du dispatch.
     *         Deux cas de réponse 200 possibles :
     *         1. Pré-sélection trouvée : Retourne les données de la pré-sélection
     *         2. Pré-sélection non trouvée : Retourne un message explicatif avec tableau vide",
     *     tags={"preselection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="dispatchId",
     *         in="path",
     *         description="ID du dispatch de pré-sélection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Succès - Pré-sélection trouvée OU non trouvée",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Cas 1 : Pré-sélection trouvée",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="period_criteria_id", type="integer", example=5),
     *                         @OA\Property(property="dispatch_preselections_id", type="integer", example=3),
     *                         @OA\Property(property="valeur", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-04T10:30:00Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-04T10:30:00Z")
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Cas 2 : Pré-sélection non trouvée",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Preselection not found"),
     *                     @OA\Property(property="data", type="array", @OA\Items(), example={})
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function getPreselectionsForDispatch(int $dispatchId)
    {
        $preselection = Preselection::where("dispatch_preselections_id", $dispatchId)->first();

        if ($preselection) {
            return response()->json([
                "success" => true,
                "data" => $preselection->toArray()
            ]);
        }

        return response()->json([
            "success" => false,
            "message" => "Preselection not found",
            "data" => []
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/preselection",
     *     summary="Enregistrer des pré-sélections",
     *     description="Enregistre plusieurs pré-sélections pour des critères d'évaluation.
     *         - La période actuelle ou précédente doit être en statut PRESELECTION
     *         - Chaque pré-sélection indique si un critère est validé (true) ou non (false) pour une candidature",
     *     tags={"preselection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Tableau de pré-sélections à enregistrer",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 required={"period_criteria_id", "dispatch_preselections_id", "valeur"},
     *                 @OA\Property(property="period_criteria_id", type="integer", description="ID du critère de période", example=5),
     *                 @OA\Property(property="dispatch_preselections_id", type="integer", description="ID du dispatch de pré-sélection", example=3),
     *                 @OA\Property(property="valeur", type="boolean", description="true = critère validé, false = critère non validé", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pré-sélections enregistrées avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pré-sélections enregistrées avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Période non en statut PRESELECTION",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="La période n'est pas en statut de présélection.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation des données",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The period_criteria_id field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur interne",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de l'enregistrement des pré-sélections")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        Log::info('Données reçues pour pré-sélection : ', $request->all());
        $currentYear = date("Y");
        $previousYear = $currentYear - 1;

        $period = Period::query()
            ->whereIn("year", [$currentYear, $previousYear])
            ->orderBy('year', 'desc')
            ->firstOrFail();

        $status = $period->status;

        if ($status != PeriodStatusEnum::STATUS_PRESELECTION->value) {
            return response()->json([
                'success' => false,
                'message' => 'La période n\'est pas en statut de présélection.'
            ], 400);
        }

        try {
            $validated = $request->validate([
                '*.period_criteria_id' => 'required|integer|exists:period_criteria,id',
                '*.dispatch_preselections_id' => 'required|integer|exists:dispatch_preselections,id',
                '*.valeur' => 'required|boolean',
            ]);

            $preselections = [];
            foreach ($validated as $data) {
                $preselections[] = [
                    'period_criteria_id' => $data['period_criteria_id'],
                    'dispatch_preselections_id' => $data['dispatch_preselections_id'],
                    'valeur' => $data['valeur']
                ];
            }

            Preselection::insert($preselections);

            return response()->json([
                'success' => true,
                'message' => 'Pré-sélections enregistrées avec succès',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement des pré-sélections : ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des pré-sélections'
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/preselection/{preselection}",
     *     summary="Mettre à jour une pré-sélection (déprécié)",
     *     description="ENDPOINT DÉPRÉCIÉ - Cette méthode utilise des champs obsolètes.
     *         Utilisez plutôt la méthode POST /api/preselections pour créer de nouvelles pré-sélections.
     *         Cette méthode met à jour manuellement les critères de validation d'une pré-sélection existante.",
     *     tags={"preselection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de mise à jour",
     *         @OA\JsonContent(
     *             required={"preselectionId"},
     *             @OA\Property(property="preselectionId", type="integer", description="ID de la pré-sélection à mettre à jour", example=1),
     *             @OA\Property(property="crt_nationalite", type="boolean", description="Critère nationalité", example=true),
     *             @OA\Property(property="crt_age", type="boolean", description="Critère âge", example=true),
     *             @OA\Property(property="crt_annee_diplome", type="boolean", description="Critère année diplôme d'état", example=true),
     *             @OA\Property(property="crt_pourcentage", type="boolean", description="Critère pourcentage", example=true),
     *             @OA\Property(property="crt_cursus_choisi", type="boolean", description="Critère cursus choisi", example=true),
     *             @OA\Property(property="crt_univeriste_institution", type="boolean", description="Critère université/institution choisie", example=true),
     *             @OA\Property(property="crt_cycle_etude", type="boolean", description="Critère cycle d'étude", example=true),
     *             @OA\Property(property="pres_commentaire", type="string", description="Commentaire de pré-sélection", example="Candidat éligible"),
     *             @OA\Property(property="pres_validate", type="boolean", description="Validation finale", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pré-sélection mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="description", type="string", example="Success"),
     *             @OA\Property(property="message", type="string", example="Validation mise à jour")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur lors de la mise à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur"),
     *             @OA\Property(property="message", type="string", example="Erreur lors de la mise à jour")
     *         )
     *     )
     * )
     */
    public function update(Request $r)
    {
        try {
            info("update préselection");
            $preselection = Preselection::find($r->preselectionId);

            $preselection->critere_nationalite = $r->crt_nationalite;
            $preselection->critere_age = $r->crt_age;
            $preselection->critere_annee_diplome_detat = $r->crt_annee_diplome;
            $preselection->critere_pourcentage = $r->crt_pourcentage;
            $preselection->critere_cursus_choisi = $r->crt_cursus_choisi;
            $preselection->critere_universite_institution_choisie = $r->crt_univeriste_institution;
            $preselection->critere_cycle_etude = $r->crt_cycle_etude;
            $preselection->pres_commentaire = $r->pres_commentaire;
            $preselection->pres_validation = $r->pres_validate;

            $saved = $preselection->save();

            if ($saved == true) {
                info('validation updated');
                /* return redirect()->route('user', ['user' => $r->id])->with('user', $r->id)->with('modal', $modal)->with("action_success", "Utilisateur mis à jour"); */
                return response()->json([
                    'code' => 200,
                    'description' => 'Success',
                    'message' => "Validation mise à jour",

                ]);
            } else {
                info('error when updating validation');
                /*    return redirect()->route('user', ['user' => $r->id])->with('user', $r->id)->with('modal', $modal)->with("action_error", "Erreur lors de la mise à jour"); */
                return response()->json([
                    'code' => 500,
                    'description' => 'Erreur',
                    'message' => "Erreur lors de la mise à jour",

                ]);
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            return response()->json([
                'code' => 500,
                'description' => 'Erreur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }


      /**
     * @OA\Delete(
     *     path="/api/preselection/{preselection}",
     *     summary="Supprimer une pré-sélection (déprécié)",
     *     description="ENDPOINT DÉPRÉCIÉ - Supprime une pré-sélection par son ID.
     *         Cette opération est irréversible.",
     *     tags={"preselection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="ID de la pré-sélection à supprimer",
     *         @OA\JsonContent(
     *             required={"preselectionId"},
     *             @OA\Property(property="preselectionId", type="integer", description="ID de la pré-sélection", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pré-sélection supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="description", type="string", example="Success"),
     *             @OA\Property(property="message", type="string", example="Préselection supprimée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur interne",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur"),
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
    public function destroy(Request $r)
    {
        try {
            info("deleting préselection");
            $user = Preselection::destroy($r->preselectionId);
            info("préselection deleted");
            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'message' => "Préselection supprimée",

            ]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            return response()->json([
                'code' => 500,
                'description' => 'Erreur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }

    
    /**
     * @OA\Get(
     *     path="/api/preselection/periods/{periodId}/validate",
     *     summary="Vérifier si la validation de pré-sélection est possible",
     *     description="Vérifie si des pré-sélections existent pour une période donnée, 
     *         ce qui permet de déterminer si la validation peut être effectuée.",
     *     tags={"preselection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="path",
     *         description="ID de la période",
     *         required=true,
     *         @OA\Schema(type="integer", example=34)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vérification effectuée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="canValidate", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function canValidatePreselection(int $periodId): \Illuminate\Http\JsonResponse
    {
        $canValidate = Preselection::query()
            ->whereHas('periodCriteria', function ($query) use ($periodId) {
                $query->where('period_id', $periodId);
            })->exists();

        return response()->json([
            "canValidate" => $canValidate,
        ]);
    }

    
    /**
     * @OA\Post(
     *     path="/api/preselection/periods/{periodId}/validate",
     *     summary="Valider la pré-sélection et créer des entretiens",
     *     description="OPERATION TECHNIQUEMENT IRREVERSIBLE : Valide toutes les pré-sélections d'une période et crée des entretiens pour les candidats éligibles.
     *         - Un candidat est éligible s'il n'a AUCUN critère avec valeur=false pour cette période
     *         - Pour chaque candidat éligible, un entretien est créé automatiquement
     *         - Cette opération retourne TOUJOURS HTTP 200 même si :
     *           • Aucune pré-sélection n'existe pour cette période
     *           • Aucun candidat éligible n'est trouvé
     *         - Le message de succès est systématiquement retourné quelle que soit la période",
     *     tags={"preselection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="periodId",
     *         in="path",
     *         description="ID de la période à valider",
     *         required=true,
     *         @OA\Schema(type="integer", example=34)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Opération terminée (succès technique, même si 0 entretiens créés)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="La validation de la présélection s'est effectuée avec succès.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur interne",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
    public function validatePreselection(Request $r, int $periodId): \Illuminate\Http\JsonResponse
    {
        $period = Period::find($periodId);
        if(!$period){
            return response()
            ->json([
                "message" => "Periode non trouvé",
            ]);
        }
        $candidacies = Candidacy::query()
            ->whereHas('dispatchPreselections.preselections.periodCriteria', function ($query) use ($periodId) {
                $query->where('period_id', $periodId);
            })
            ->whereDoesntHave('dispatchPreselections.preselections', function ($query) use ($periodId) {
                $query->whereHas('periodCriteria', function ($q) use ($periodId) {
                    $q->where('period_id', $periodId);
                })->where('valeur', false);
            })
            ->get();

            
        foreach ($candidacies as $candidacy) {
            Interview::query()->create([
                'candidacy_id' => $candidacy->id,
            ]);
        }

        return response()
            ->json([
                "message" => "La validation de la présélection s’est effectuée avec succès.",
            ]);
    }
}
