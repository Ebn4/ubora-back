<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Models\EvaluationFinale;
use App\Models\Preselection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Stmt\TryCatch;
use Spatie\SimpleExcel\SimpleExcelReader;

class CandidacyController extends Controller
{
    public function uploadCandidacies(Request $r)
    {

        try {
            /*  info(Auth::user()->cuid .' is charging batch.'); */
            $filename = time() . '.csv';
           
            $candidacies = $r->file('fichier');
            

            $fichier = $candidacies->move(base_path('storage/app'), $filename);
            

            $reader = SimpleExcelReader::create($fichier)->useDelimiter(';');
            $rows = $reader->getRows()->toArray();
           
            DB::transaction(function () use ($rows, $r) {
                foreach ($rows as $row) {
                    /*   $charged_msisdn = Charged_Account::create($row); */
                    info($row);
                    $charged_msisdn = Candidacy::upsert([
                        'post_work_id' => $row['post_work_id'],
                        'form_id' => $row['formulaire_dinscriptionbourseubora_id'],
                        'form_submited_at' => $row['created_on'],
                        'etn_nom' => $row['_etn_nom'],
                        'etn_email' => $row['email'],
                        'etn_prenom' => $row['_etn_prenom'],
                        'etn_postnom' => $row['postnom'],
                        'etn_naissance' => $row['naissance'],
                        'ville' => $row['ville'],
                        'telephone' => $row['telephone'],
                        'adresse' => $row['adresse'],
                        'province' => $row['province'],
                        'nationalite' => $row['nationalite'],
                        'cv' => $row['cv'],
                        'releve_note_derniere_annee' => $row['relev_denotesdeladernireannedecours'],
                        'en_soumettant' => $row['en_soumettant'],
                        'section_option' => $row['sectionoption_'],
                        'j_atteste' => $row['jatteste_quelesinfor'],
                        'degre_parente_agent_orange' => $row['si_ouiquelleestvotredegrderelation'],
                        'annee_diplome_detat' => $row['anne_dobtentiondudiplmedtat'],
                        'diplome_detat' => $row['diplme_detat'],
                        'autres_diplomes_atttestation' => $row['autres_diplmesattestations'],
                        'universite_institut_sup' => $row['nom_universitouinstitutsuprieur'],
                        'pourcentage_obtenu' => $row['pourcentage_obtenu'],
                        'lettre_motivation' => $row['lettre_demotivation'],
                        'adresse_universite' => $row['adresse_universit'],
                        'parente_agent_orange' => $row['etesvous_apparentunagentdeorangerdc'],
                        'institution_scolaire' => $row['institution_scolairefrquente'],
                        'faculte'=>$row['facult_'],
                        'montant_frais' => $row['montants_desfrais'],
                        'sexe' => $row['sexe'],
                        'attestation_de_reussite_derniere_annee' => $row['attestation_derussitedeladernireannedtude'],
                        'user_last_login' => $row['user_last_login'],

                    ],['post_work_id'], ['form_id']);
                }
            });
            // 5. On supprime le fichier uploadé
            $reader->close(); // On ferme le $reader
            unlink($fichier);
            info("candidacies charged");
            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'message' => "Candidatures importées avec succès",

            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'code' => 500,
                'description' => 'Error',
                'message' => "Erreur interne du serveur",

            ]);
        }
    }

    public function uploadCandidaciesDocs(Request $r){
       /*  $fichiers= $r->file('fichiers'); */
        info($r->file());
        try{
            foreach($r->file() as $f){
                if (Storage::disk('public')->exists($f->getClientOriginalName())) {

                    Storage::disk('public')->delete([$f->getClientOriginalName()]);
                }
                Storage::disk('public')->put($f->getClientOriginalName(), file_get_contents($f));
            }
            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'message' => "Documents importés avec succès",

            ]);
        }catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'code' => 500,
                'description' => 'Error',
                'message' => "Erreur interne du serveur",

            ]);
        }
        
    }


    public function getAllCandidacies(Request $r){

        try {
            $candidacies = Candidacy::select('candidats.*','preselections.pres_validation as preselection')
            ->leftJoin('preselections','candidats.id','=', 'preselections.candidature')
            ->get();

            $evaluations = EvaluationFinale::select('id','candidature','total')->get();

            $candidacies = $this->calulateAverage( $candidacies,$evaluations);

            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'candidacies' => $candidacies,
                /* 'evaluations' => $evaluations */

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

    public function getDoc(Request $r){
        
        try {
            $storagePath = storage_path('app/public/');           
            $file = File::glob($storagePath.$r->docName);
          
            info($file);
         

            $filename = "" ;
            $fileContent = "";
            $code = "";
            $message = "";
           
           if(count($file)=== 0){
               $filename = "" ;
               $fileContent = "";
               $code = 404;
               $description = "Not found";
               $message = "Document introuvable";
            }elseif(count($file) === 1){
               $filename = pathinfo($file[0])['basename'] ;
               $code = 200;
               $description = "Success";
               $message = "OK";
            }elseif(count($file)>1){
                $filename = pathinfo($file[0])['basename'] ;
               $fileSource = (config('app.url').'/'.$storagePath.$r->docName);
               $code = 200;
               $description = "Success";
               $message = "OK";
            }

           

            return response()->json([
                'code' =>  $code,
                'description' =>  $description,
                'filename' => $filename,
                'message' => $message
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

    public function getPreselectedCandidacies(Request $r){

        try {
            info($r);
            if($r-> userProfile == 'Evaluateur'){
                $candidacies = Candidacy::select('candidats.*','preselections.pres_validation as preselection')
                ->join('preselections','candidats.id','=', 'preselections.candidature')
                ->where('preselections.pres_validation','=',true)
                ->where('candidats.evaluateur1','=',$r->userId)
                ->orWhere('candidats.evaluateur2','=',$r->userId)
                ->orWhere('candidats.evaluateur3','=',$r->userId)
                ->get();

                $evaluations = EvaluationFinale::select('id','candidature','total')->where('evaluateur',$r->userId)->get();

            }elseif($r-> userProfile == 'Admin' || $r-> userProfile == 'Lecteur'){
                $candidacies = Candidacy::select('candidats.*','preselections.pres_validation as preselection')
                ->join('preselections','candidats.id','=', 'preselections.candidature')
                ->where('preselections.pres_validation','=',true)
                ->get();

                $evaluations = EvaluationFinale::select('id','candidature','total')->get();

            }

            $candidacies = $this->calulateAverage( $candidacies,$evaluations);
           

            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'candidacies' => $candidacies,
                'evaluations' => $evaluations

            ]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'

            ]);
        }
    }


    private function calulateAverage($candidacies,$evaluations){

        foreach($candidacies as $c){
            $noteTotale = 0;
            $nbrEv = 0;
            foreach ($evaluations as $ev){
                
                if($ev->candidature == $c->id){
                    $nbrEv += 1;
                    $noteTotale += $ev->total;
                    $moyenne = $noteTotale/$nbrEv;
                    $c->moyenne = $moyenne;  
                }
            
             
             $c->evaluations_effectuées =$nbrEv;
            }
        }

        return $candidacies;

    }

    public function getCandidacy(Request $r){
        try {           
            /* $candidacy = Candidacy::select('candidats.*',
            'u1.fullname as evaluateur1Name',
            'u1.id as evaluateur1Id',
            'u2.fullname as evaluateur2Name',
            'u2.id as evaluateur2Id',
            'u3.fullname as evaluateur3Name',
            'u3.id as evaluateur3Id')
            ->where('candidats.id',$r->candidacyId)
            ->join('users as u1','candidats.evaluateur1','=','u1.id')
            ->join('users as u2','candidats.evaluateur2','=','u2.id')
            ->join('users as u3','candidats.evaluateur3','=','u3.id')
            ->first(); */
            if($r-> userProfile == 'Evaluateur'){
                $candidacy = Candidacy::where('candidats.id',$r->candidacyId)->first();
                $preselection = Preselection::where('candidature',$r->candidacyId)->first();
                $evaluationFinale = EvaluationFinale::select('evaluationsfinales.*','users.fullname as evaluateurName')
                                    ->where('candidature',$r->candidacyId)
                                    ->where('evaluateur','=',$r->userId)
                                    ->join('users','evaluationsfinales.evaluateur','=','users.id')->get();
                $evaluateursSelect = User::select('id','fullname')->where('profil','evaluateur')->orWhere('profil','admin')->get();
            }elseif($r-> userProfile == 'Admin' || $r-> userProfile == 'Lecteur' ){
                $candidacy = Candidacy::where('candidats.id',$r->candidacyId)->first();
                $preselection = Preselection::where('candidature',$r->candidacyId)->first();
                $evaluationFinale = EvaluationFinale::select('evaluationsfinales.*','users.fullname as evaluateurName')->where('candidature',$r->candidacyId)->join('users','evaluationsfinales.evaluateur','=','users.id')->get();
                $evaluateursSelect = User::select('id','fullname')->where('profil','evaluateur')->get();
            }
                
          
          
         
            if ($candidacy != '') {
                return response()->json([
                    'code' => 200,
                    'description' => "Success",
                    'candidacy' => $candidacy,
                    'preselection' => $preselection,
                    'evaluationFinale' => $evaluationFinale,
                    'evaluateursSelect'=> $evaluateursSelect,
                    
                ]);
            } else {
                return response()->json([
                    'code' => 404,
                    'description' => "Not found",
                    'candidacy' => $candidacy
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

    public function deleteCandidacy(Request $r){
        try {
           info($r);
            DB::transaction(function () use ($r){
               $preselection= DB::table('preselections')->where('candidature',$r->candidacyId)->delete();
                $evaluation = DB::table('evaluationsfinales')->where('candidature', $r->candidacy)->delete();
               $candidacy = Candidacy::destroy($r->candidacyId);

               info($preselection);
               info( $evaluation);
               info($candidacy);
            });
            
            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'message' => "Candidature supprimée",

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
