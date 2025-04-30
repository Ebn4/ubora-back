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
            info("Saving/updating evaluators");
            $candidacy = Candidacy::find($r->candidacyId);
            $candidacy->evaluateur1 = $r->evaluateur1;
            $candidacy->evaluateur2 = $r->evaluateur2;
            $candidacy->evaluateur3 = $r->evaluateur3;


            $saved = $candidacy->save();

            if ($saved == true) {
                info('evaluators saved/updated');
                return response()->json([
                    'code' => 200,
                    'description' => 'Success',
                    'message' => "Evaluateurs enregistrés",

                ]);
            } else {
                info('error when updating evaluatiors');
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

    public function store(Request $r){

        try {

            info("creating evaluation");
            $userId= $r->userId;
            $chekEvaluateur =  Candidacy::where('id','=',$r->candidacyId)
            ->where(function($query) use($userId){
                $query->where('evaluateur1','=',$userId)
                ->orWhere('evaluateur2','=',$userId)
                ->orWhere('evaluateur3','=',$userId);
            })->count();




            if($chekEvaluateur <= 0){
                return response()->json([
                    'code' => 401,
                    'description' => "Error",
                    'message' => "Vous n'êtes pas autorisé à évaluer cette candidature"
                ]);
            }
            $evaluation = EvaluationFinale::create([
                'evaluateur' => $r->evaluateur,
                'candidature'  => $r->candidacyId,
                'critere_doss_academique' => $r->crt_doss_academique,
                'critere_lettre_motivation' => $r->crt_lettre_motivation,
                'critere_communication_skills'=> $r->crt_communication_skills,
                'critere_cv'=> $r->crt_cv,
                'commentaire'=> $r->commentaire,
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

    public function update(Request $r){
        try {
            info("updating evaluation");
            $userId= $r->userId;
            $chekEvaluateur =  Candidacy::where('id','=',$r->candidacyId)
            ->where(function($query)use($userId){
                $query->where('evaluateur1','=',$userId)
                ->orWhere('evaluateur2','=',$userId)
                ->orWhere('evaluateur3','=',$userId);
            })->count();

            if($chekEvaluateur <= 0){
                info("401");
                return response()->json([
                    'code' => 401,
                    'description' => "Error",
                    'message' => "Vous n'êtes pas autorisé à évaluer cette candidature"
                ]);
            }

            $evaluation = EvaluationFinale::find($r->evaluationId);

            $evaluation->critere_doss_academique = $r->crt_doss_academique;
            $evaluation->critere_lettre_motivation = $r->crt_lettre_motivation;
            $evaluation->critere_communication_skills = $r->crt_communication_skills;
            $evaluation->critere_cv = $r->crt_cv;
            $evaluation->total = ($r->crt_doss_academique+$r->crt_lettre_motivation+$r->crt_communication_skills+$r->crt_cv);
            $evaluation->commentaire = $r->commentaire;
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

    public function destroy(Request $r){
        try {
            info('deleting evaluation');
            $user = EvaluationFinale::destroy($r->evaluationId);
            info('evaluation deleted');
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
