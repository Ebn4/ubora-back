<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Models\EvaluationFinale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EvaluationFinaleController extends Controller
{
    public function saveEvaluators(Request $r){
        try {
            $candidacy = Candidacy::find($r->candidacyId);
            $candidacy->evaluateur1 = $r->evaluateur1;
            $candidacy->evaluateur2 = $r->evaluateur2;
            $candidacy->evaluateur3 = $r->evaluateur3;

            
            $saved = $candidacy->save();

            if ($saved == true) {
                info('evaluation updated');
                return response()->json([
                    'code' => 200,
                    'description' => 'Success',
                    'message' => "Evaluateurs enregistrés",

                ]);
            } else {
                info('error when updating evaluation');
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
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'

            ]);
        }
    }
    
    
    
    public function createEvaluationFinale(Request $r){
       
        try {
            
            $evaluation = EvaluationFinale::create([
                'evaluateur' => $r->evaluateur,
                'candidature'  => $r->candidacyId,
                'critere_doss_academique' => $r->crt_doss_academique,
                'critere_lettre_motivation' => $r->crt_lettre_motivation,
                'critere_communication_skills'=> $r->crt_communication_skills,
                'critere_cv'=> $r->crt_cv,
                'total'=>($r->crt_doss_academique + $r->crt_lettre_motivation + $r->crt_communication_skills + $r->crt_cv),
            ]);

            if ($evaluation->id) {
                info('evaluation saved: ' . $evaluation->id);

                /* return redirect()->route('users')->with("action_success", 'Utilisateur enregistré')->with('modal', true); */
                return response()->json([
                    'code' => 200,
                    'description' => "Success",
                    'message' => "Evaluation enregistrée"
                ]);
            } else {

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
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'

            ]);
        }
    }

    public function updateEvaluationFinale(Request $r){
        try {
            $evaluation = EvaluationFinale::find($r->evaluationId);

            $evaluation->critere_doss_academique = $r->crt_doss_academique;
            $evaluation->critere_lettre_motivation = $r->crt_lettre_motivation;
            $evaluation->critere_communication_skills = $r->crt_communication_skills;
            $evaluation->critere_cv = $r->crt_cv;
            $evaluation->total = ($r->crt_doss_academique+$r->crt_lettre_motivation+$r->crt_communication_skills+$r->crt_cv);
            $saved =$evaluation->save();

            if ($saved == true) {
                info('evaluation updated');
                /* return redirect()->route('user', ['user' => $r->id])->with('user', $r->id)->with('modal', $modal)->with("action_success", "Utilisateur mis à jour"); */
                return response()->json([
                    'code' => 200,
                    'description' => 'Success',
                    'message' => "Evaluation mise à jour",

                ]);
            } else {
                info('error when updating evaluation');
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

    public function deleteEvaluationFinale(Request $r){
        try {
           
            $user = EvaluationFinale::destroy($r->evaluationId);
            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'message' => "Evaluation supprimée",

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
