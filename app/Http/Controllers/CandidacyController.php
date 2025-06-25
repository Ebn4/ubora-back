<?php

namespace App\Http\Controllers;

use App\Enums\EvaluatorTypeEnum;
use App\Http\Requests\CandidateSelectionRequest;
use App\Models\Candidacy;
use App\Models\Criteria;
use App\Models\EvaluationFinale;
use App\Models\Evaluator;
use App\Models\Interview;
use App\Models\Period;
use App\Models\Preselection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Stmt\TryCatch;
use Spatie\SimpleExcel\SimpleExcelReader;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use function PHPUnit\Framework\isEmpty;

class CandidacyController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/uploadCandidacies",
     *     summary="Enregistrement en batch des données des formulaires",
     *     operationId="uploadForms",
     *     tags={"Enregistrement des candidatures"},
     *     @OA\RequestBody(
     *         description="Cette interface d'API permet l'enregistrement en batch des données issues des formulaires, collectées dans un fichier CSV.",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     description="The file to upload",
     *                     property="fichier",
     *                     type="file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean"  
     *             )
     *         )
     *     )
     * )
     */

    public function uploadCandidacies(Request $request, FileService $fileService)
    {
        try {
            $filename = time() . '.csv';
            $candidacies = $request->file('fichier');
            $fichier = $fileService->uploadFichier($candidacies, 'candidacies', $filename);

            $reader = SimpleExcelReader::create($fichier)->useDelimiter(';');

            $rows = $reader->getRows()->toArray();
            $currentYear = now()->year;

            $validated = $request->validate([
                'year' => [
                    'nullable',
                    'integer',
                    Rule::exists('periods', 'year'),
                ],
            ]);


            if (isset($validated['year']) && $validated['year'] != null) {
                $currentYear = Carbon::createFromFormat('Y', $validated['year']);
            }

            $processedEmails = [];

            DB::transaction(function () use ($rows, &$processedEmails, $currentYear) {
                $period = Period::firstOrCreate(['year' => $currentYear]);

                foreach ($rows as $row) {
                    try {
                        $createdOn = Carbon::createFromFormat('d/m/Y H:i', $row['created_on']);
                        if ($createdOn->year !== $currentYear->year) {
                            info("Skipping row: not current year - " . $createdOn->year);
                            continue;
                        }

                        $email = $row['email'];

                        // Vérification si déjà traité dans ce fichier ou existant en base
                        if (
                            in_array($email, $processedEmails) ||
                            Candidacy::where('etn_email', $email)->exists()
                        ) {
                            info("Skipping row: duplicate email - " . $email);
                            continue;
                        }

                        $processedEmails[] = $email;

                        Candidacy::create([
                            'post_work_id' => $row['post_work_id'],
                            'form_id' => $row['formulaire_dinscriptionbourseubora_id'],
                            'form_submited_at' => $createdOn->format('Y-m-d H:i'),
                            'etn_nom' => $row['_etn_nom'],
                            'etn_email' => $email,
                            'etn_prenom' => $row['_etn_prenom'],
                            'etn_postnom' => $row['postnom'],
                            'etn_naissance' => Carbon::createFromFormat('d/m/Y', $row['naissance'])->format('Y-m-d'),
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
                            'faculte' => $row['facult_'],
                            'montant_frais' => $row['montants_desfrais'],
                            'sexe' => $row['sexe'],
                            'attestation_de_reussite_derniere_annee' => $row['attestation_derussitedeladernireannedtude'],
                            'user_last_login' => $row['user_last_login'],
                            'period_id' => $period->id,
                        ]);
                    } catch (\Exception $e) {
                        info("Skipping row: error parsing data - " . $e->getMessage());
                    }
                }
            });

            $reader->close();

            if (file_exists($fichier->getPathname())) {
                Log::info("Fichier encore présent sur le disque à : " . $fichier->getPathname());
            } else {
                Log::warning("Fichier manquant après traitement.");
            }

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
                'message' => $th,
            ]);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/uploadCandidaciesDocs",
     *     summary="Enregistrement des fichiers attachés aux formulaires",
     *     operationId="uploadDocs",
     *     tags={"Enregistrement des candidatures"},
     *     @OA\RequestBody(
     *         description="Cette interface d'API est conçue pour l'enregistrement des fichiers attachés aux formulaires.",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *               type="object",
     *                 @OA\Property(
     *                     description="The files to upload",
     *                     property="fichier",
     *                     type="array",
     *                     @OA\Items(
     *                        type="file",
     *
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean"
     *             )
     *         )
     *     )
     * )
     */
    public function uploadCandidaciesDocs(Request $r)
    {
        info('uploading files');
        try {
            $validatedData = $r->validate([
                'files.*' => 'required|file|mimes:pdf,png,gpg,jpeg'
            ]);

            foreach ($r->file() as $f) {
                $fileName = $f->getClientOriginalName();
                $filePath = 'documents/' . $fileName;

                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }

                Storage::disk('public')->put($filePath, file_get_contents($f));
            }

            info('files uploaded');
            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'message' => "Documents importés avec succès",
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


    public function index(Request $request): JsonResponse
    {
        try {
            $query = Candidacy::select('candidats.*', 'preselections.pres_validation as preselection')
                ->leftJoin('preselections', 'candidats.id', '=', 'preselections.candidature');

            // Application de la recherche si le paramètre 'search' est présent
            if ($request->has('search') && $request->input('search') != null) {
                $search = $request->input('search');

                $query = $query->where(function ($q) use ($search) {
                    $q->where('etn_nom', 'like', "%$search%")
                        ->orWhere('etn_prenom', 'like', "%$search%")
                        ->orWhere('etn_postnom', 'like', "%$search%")
                        ->orWhere('ville', 'like', "%$search%");
                });
            }
            if ($request->has('ville') && $request->input('ville') != null) {
                $ville = $request->input('ville');
                $query = $query->where('ville', 'LIKE', "%{$ville}%");
            }
            if ($request->has('periodId') && $request->input('periodId') != null) {
                $query = $query->where('period_id', $request->input('periodId'));
            } else {
                $currentPeriod = Period::where('year', now()->year)->first();
            }
            $perPage = $request->input('per_page', 5);
            $paginated = $query->paginate($perPage);

            $evaluations = EvaluationFinale::select('id', 'candidature', 'total')->get();

            $paginated->getCollection()->transform(function ($item) use ($evaluations) {
                return $this->calulateAverage(collect([$item]), $evaluations)->first();
            });

            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'data' => $paginated,
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


    public function getDoc(Request $r)
    {

        try {
            info('get File');
            $storagePath = storage_path('app/public/documents');
            $file = File::glob($storagePath . $r->docName);

            info($file);


            $filename = "";
            $fileContent = "";
            $code = "";
            $message = "";

            if (count($file) === 0) {
                $filename = "";
                $fileContent = "";
                $code = 404;
                $description = "Not found";
                $message = "Document introuvable";
            } elseif (count($file) === 1) {
                $filename = pathinfo($file[0])['basename'];
                $code = 200;
                $description = "Success";
                $message = "OK";
            } elseif (count($file) > 1) {
                $filename = pathinfo($file[0])['basename'];
                $fileSource = (config('app.url') . '/' . $storagePath . $r->docName);
                $code = 200;
                $description = "Success";
                $message = "OK";
            }


            info('get File Ok');
            return response()->json([
                'code' => $code,
                'description' => $description,
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

    public function getPreselectedCandidacies(Request $r)
    {

        try {
            info('get Preselected candidacies ' . $r->userProfile);
            if ($r->userProfile == 'Evaluateur') {
                $candidacies = Candidacy::select('candidats.*', 'preselections.pres_validation as preselection')
                    ->join('preselections', 'candidats.id', '=', 'preselections.candidature')
                    ->where('preselections.pres_validation', '=', true)
                    ->where('candidats.evaluateur1', '=', $r->userId)
                    ->orWhere('candidats.evaluateur2', '=', $r->userId)
                    ->orWhere('candidats.evaluateur3', '=', $r->userId)
                    ->get();

                $evaluations = EvaluationFinale::select('id', 'candidature', 'total')->where('evaluateur', $r->userId)->get();
            } elseif ($r->userProfile == 'Admin' || $r->userProfile == 'Lecteur') {
                $candidacies = Candidacy::select('candidats.*', 'preselections.pres_validation as preselection')
                    ->join('preselections', 'candidats.id', '=', 'preselections.candidature')
                    ->where('preselections.pres_validation', '=', true)
                    ->get();

                $evaluations = EvaluationFinale::select('id', 'candidature', 'total')->get();
            }

            $candidacies = $this->calulateAverage($candidacies, $evaluations);
            info('get Preselected candidacies Ok');

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


    private function calulateAverage($candidacies, $evaluations)
    {

        info('calculate average');
        foreach ($candidacies as $c) {
            $noteTotale = 0;
            $nbrEv = 0;
            $c->evaluations_effectuées = 0;
            foreach ($evaluations as $ev) {

                if ($ev->candidature == $c->id) {
                    $nbrEv += 1;
                    $noteTotale += $ev->total;
                    $moyenne = $noteTotale / $nbrEv;
                    $c->moyenne = $moyenne;
                }


                $c->evaluations_effectuées = $nbrEv;
            }
        }
        info('average Ok');
        return $candidacies;
    }

    public function getCandidacy(Request $r)
    {
        try {
            /* $candidacy = Candidacy::select('candidats.*',
            'u1.name as evaluateur1Name',
            'u1.id as evaluateur1Id',
            'u2.name as evaluateur2Name',
            'u2.id as evaluateur2Id',
            'u3.name as evaluateur3Name',
            'u3.id as evaluateur3Id')
            ->where('candidats.id',$r->candidacyId)
            ->join('users as u1','candidats.evaluateur1','=','u1.id')
            ->join('users as u2','candidats.evaluateur2','=','u2.id')
            ->join('users as u3','candidats.evaluateur3','=','u3.id')
            ->first(); */
            info('get Candidacy');
            if ($r->userProfile == 'Evaluateur') {
                $candidacy = Candidacy::where('candidats.id', $r->candidacyId)->first();
                info("Candidat sélectionné" . $candidacy);
                $preselection = Preselection::where('candidature', $r->candidacyId)->first();
                $evaluationFinale = EvaluationFinale::select('evaluationsfinales.*', 'users.name as evaluateurName')
                    ->where('candidature', $r->candidacyId)
                    ->where('evaluateur', '=', $r->userId)
                    ->join('users', 'evaluationsfinales.evaluateur', '=', 'users.id')->get();
                $evaluateursSelect = User::select('id', 'name')->where('profil', 'evaluateur')->orWhere('profil', 'admin')->get();
            } elseif ($r->userProfile == 'Admin' || $r->userProfile == 'Lecteur') {
                $candidacy = Candidacy::where('candidats.id', $r->candidacyId)->first();
                $preselection = Preselection::where('candidature', $r->candidacyId)->first();
                $evaluationFinale = EvaluationFinale::select('evaluationsfinales.*', 'users.name as evaluateurName')->where('candidature', $r->candidacyId)->join('users', 'evaluationsfinales.evaluateur', '=', 'users.id')->get();
                $evaluateursSelect = User::select('id', 'name')->where('profil', 'evaluateur')->orWhere('profil', 'admin')->get();
            }


            info('get Candidacy ok');
            if ($candidacy != '') {
                return response()->json([
                    'code' => 200,
                    'description' => "Success",
                    'candidacy' => $candidacy,
                    'preselection' => $preselection,
                    'evaluationFinale' => $evaluationFinale,
                    'evaluateursSelect' => $evaluateursSelect,

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

    public function destroy(Request $r)
    {
        try {
            info('delete Candidacy');
            DB::transaction(function () use ($r) {
                $preselection = DB::table('preselections')->where('candidature', $r->candidacyId)->delete();
                $evaluation = DB::table('evaluationsfinales')->where('candidature', $r->candidacy)->delete();
                $candidacy = Candidacy::destroy($r->candidacyId);

                info($preselection);
                info($evaluation);
                info($candidacy);
            });
            info('Candidacy deleted');
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
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }

    public function candidateSelections(CandidateSelectionRequest $request)
    {

        try {

            DB::beginTransaction();

            $interviewId = $request->post('interviewId');
            $interview = Interview::query()->findOrFail($interviewId);

            $evaluator = Evaluator::query()
                ->where("user_id", auth()->user()->id)
                ->firstOrFail();

            if ($evaluator->type != EvaluatorTypeEnum::EVALUATOR_SELECTION->value) {
                throw new \Exception("Action non autorisée : seul un évaluateur de sélection peut effectuer cette opération.");
            }

            foreach ($request->post('evaluations') as $evaluation) {
                $criteria = Criteria::query()->findOrFail($evaluation['key']);
                $result = $evaluation['value'];

                if (!is_int($result) || !isset($result) || !isEmpty($result)) {
                    throw new \Exception("Résultat illégal : la valeur fournie doit être un entier numérique.");
                }

                $interview->selectionResults()->attach([
                    $criteria->id => [
                        "evaluator_id" => $evaluator->id,
                        "result" => $result
                    ]
                ]);
            }

            DB::commit();

            return response()
                ->json([
                    "data" => true
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw new HttpResponseException(
                response: response(
                    ["errors" => $e->getMessage()]
                )
            );
        }
    }
}
