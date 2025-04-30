<?php

namespace App\Http\Controllers;

use App\Models\Preselection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PreselectionController extends Controller
{
    public function store(Request $r){
        try {
            info('create préselection');
            $preselection = Preselection::create([
                'candidature' => $r->candidacyId,
                'critere_nationalite' => $r->crt_nationalite,
                'critere_age'  => $r->crt_age,
                'critere_annee_diplome_detat'  => $r->crt_annee_diplome,
                'critere_pourcentage'  => $r->crt_pourcentage,
                'critere_cursus_choisi'  => $r->crt_cursus_choisi,
                'critere_universite_institution_choisie' => $r->crt_univeriste_institution,
                'critere_cycle_etude'  => $r->crt_cycle_etude,
                'pres_commentaire' => $r->pres_commentaire,
                'pres_validation' => $r->pres_validate,
            ]);


            if ($preselection->id) {
                info('preselection saved: ' . $preselection->id);

                /* return redirect()->route('users')->with("action_success", 'Utilisateur enregistré')->with('modal', true); */
                return response()->json([
                    'code' => 200,
                    'description' => "Success",
                    'message' => "Préselection enregistrée"
                ]);
            } else {
                info("Erreur lors de l'enregistrement");
                /* return redirect()->route('users')->with("action_error", "Erreur lors de l'enregistrement")->with('modal', true); */
                return response()->json([
                    'code' => 400,
                    'description' => "Erreur interne du serveur",
                    'message' => "Erreur lors de l'enregistrement"
                ]);
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                'code' => 500,
                'description' => "Erreur interne du serveur",
                'message' => "Erreur interne du serveur"
            ]);
        }
    }

    public function update(Request $r){
        try {
            info("update préselection");
            $preselection = Preselection::find($r->preselectionId);

            $preselection->critere_nationalite = $r->crt_nationalite;
            $preselection->critere_age  = $r->crt_age;
            $preselection->critere_annee_diplome_detat = $r->crt_annee_diplome;
            $preselection->critere_pourcentage  = $r->crt_pourcentage;
            $preselection->critere_cursus_choisi  = $r->crt_cursus_choisi;
            $preselection->critere_universite_institution_choisie = $r->crt_univeriste_institution;
            $preselection->critere_cycle_etude  = $r->crt_cycle_etude;
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
                'message' =>  'Erreur interne du serveur'
            ]);
        }
    }

    public function destroy(Request $r){
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
                'message' =>  'Erreur interne du serveur'
            ]);
        }

    }
}
