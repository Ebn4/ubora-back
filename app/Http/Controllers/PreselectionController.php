<?php

namespace App\Http\Controllers;

use App\Models\Preselection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PreselectionController extends Controller
{
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


    public function store(Request $request)
    {
        Log::info('Données reçues pour pré-sélection : ', $request->all());

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
                'message' => 'Erreur lors de l\'enregistrement des pré-sélections',
            ], 500);
        }
    }

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
}
