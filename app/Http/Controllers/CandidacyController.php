<?php

namespace App\Http\Controllers;

use App\Enums\EvaluatorTypeEnum;
use App\Enums\PeriodStatusEnum;
use App\Http\Requests\CandidateSelectionRequest;
use App\Http\Resources\CandidacyResource;
use App\Http\Resources\EvaluatorRessource;
use App\Http\Resources\InterviewResource;
use App\Models\Candidacy;
use App\Models\Criteria;
use App\Models\EvaluationFinale;
use App\Models\Evaluator;
use App\Models\Interview;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use ZanySoft\Zip\Zip;
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

    public function uploadCandidacies(Request $request)
    {
        $validated = $request->validate([
            'year' => [
                'nullable',
                'integer',
                Rule::exists('periods', 'year'),
            ],
            'rows' => 'required|array|min:1',
        ]);

        try {
            $rows = $validated['rows'];
            $year = $request->input('year');
            $currentYear = Carbon::createFromFormat('Y', $year);
            $processedEmails = [];
            $period = Period::firstOrCreate(['year' => $currentYear->year]);

            if ($period->status !== PeriodStatusEnum::STATUS_DISPATCH->value) {
                return response()->json([
                    'message' => "La période pour l'année $year est fermée et ne peut pas recevoir de candidatures.",
                ], 403);
            }

            DB::transaction(function () use ($rows, &$processedEmails, $period, $currentYear) {

                foreach ($rows as $index => $row) {

                    $is_allowed = true;

                    $birthDate = !empty($row['naissance']) ? Carbon::createFromFormat('d/m/Y', $row['naissance']) : null;
                    $age = $birthDate ? $birthDate->age : null;

                    $pourcentage = floatval($row['pourcentage_obtenu'] ?? 0);

                    if ($pourcentage < 70 || $age !== null && $age < 21) {
                        $is_allowed = false;
                    }

                    try {
                        try {
                            $createdOn = Carbon::createFromFormat('d/m/Y H:i', $row['created_on']);
                        } catch (\Exception $e) {
                            Log::warning("Ligne $index ignorée : format de date invalide → {$row['created_on']}");
                            continue;
                        }

                        if ($createdOn->year !== $currentYear->year) {
                            Log::info("Ligne $index ignorée : année {$createdOn->year} différente de l'année en cours {$currentYear->year}");
                            continue;
                        }

                        $email = $row['email'];
                        if (in_array($email, $processedEmails)) {
                            Log::info("Ligne $index ignorée : email déjà traité dans cette importation → $email");
                            continue;
                        }

                        if (Candidacy::where('etn_email', $email)
                            ->where('period_id', $period->id)
                            ->exists()
                        ) {
                            Log::info("Ligne $index ignorée : email déjà existant pour cette période en base → $email");
                            continue;
                        }

                        $processedEmails[] = $email;

                        Candidacy::create([
                            'post_work_id' => $row['post_work_id'],
                            'form_id' => $row['formulaire_dinscriptionbourseubora_id'],
                            'form_submited_at' => $createdOn->format('Y-m-d H:i'),
                            'etn_nom' => $row['etn_nom'],
                            'etn_email' => $email,
                            'etn_prenom' => $row['etn_prenom'],
                            'etn_postnom' => $row['postnom'],
                            'etn_naissance' => !empty($row['naissance']) ? Carbon::createFromFormat('d/m/Y', $row['naissance'])->format('Y-m-d') : null,
                            'ville' => $row['ville'],
                            'telephone' => $row['telephone'],
                            'adresse' => $row['adresse'],
                            'province' => $row['province'],
                            'nationalite' => $row['nationalite'],
                            'cv' => is_array($row['cv']) ? implode(', ', $row['cv']) : $row['cv'],
                            'releve_note_derniere_annee' => is_array($row['relev_denotesdeladernireannedecours']) ? implode(', ', $row['relev_denotesdeladernireannedecours']) : $row['relev_denotesdeladernireannedecours'],
                            'en_soumettant' => $row['en_soumettant'],
                            'section_option' => $row['sectionoption'],
                            'j_atteste' => $row['jatteste_quelesinfor'],
                            'degre_parente_agent_orange' => $row['si_ouiquelleestvotredegrderelation'],
                            'annee_diplome_detat' => $row['anne_dobtentiondudiplmedtat'],
                            'diplome_detat' => is_array($row['diplme_detat']) ? implode(', ', $row['diplme_detat']) : $row['diplme_detat'],
                            'autres_diplomes_atttestation' => is_array($row['autres_diplmesattestations']) ? implode(', ', $row['autres_diplmesattestations']) : $row['autres_diplmesattestations'],
                            'universite_institut_sup' => $row['nom_universitouinstitutsuprieur'],
                            'pourcentage_obtenu' => $row['pourcentage_obtenu'],
                            'lettre_motivation' => is_array($row['lettre_demotivation']) ? implode(', ', $row['lettre_demotivation']) : $row['lettre_demotivation'],
                            'adresse_universite' => $row['adresse_universit'],
                            'parente_agent_orange' => $row['etesvous_apparentunagentdeorangerdc'],
                            'institution_scolaire' => $row['institution_scolairefrquente'],
                            'faculte' => $row['facult'],
                            'montant_frais' => $row['montants_desfrais'],
                            'sexe' => $row['sexe'],
                            'attestation_de_reussite_derniere_annee' => is_array($row['attestation_derussitedeladernireannedtude']) ? implode(', ', $row['attestation_derussitedeladernireannedtude']) : $row['attestation_derussitedeladernireannedtude'],
                            'user_last_login' => is_array($row['user_last_login']) ? implode(', ', $row['user_last_login']) : $row['user_last_login'],
                            'period_id' => $period->id,
                            'is_allowed' => $is_allowed,
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Erreur en traitant la ligne : " . $e->getMessage());
                        continue;
                    }
                }
            });

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
                'message' => $th->getMessage(),
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


    public function index(Request $request)
    {
        try {
            $query = Candidacy::query()->where('is_allowed', true);
            $periodId = Period::where('year', now()->year)->first()->id;

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
                $periodId = $request->input('periodId');
                $query = $query->where('period_id', $periodId);
            } else {
                $query = $query->where('period_id', $periodId);
            }

            $perPage = $request->input('per_page', 5);

            $paginated = $query->paginate($perPage);

            return CandidacyResource::collection($paginated);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }

    public function rejetedCandidacies(Request $request)
    {
        try {
            $query = Candidacy::query()->where('is_allowed', false);
            $periodId = Period::where('year', now()->year)->first()->id;

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
                $periodId = $request->input('periodId');
                $query = $query->where('period_id', $periodId);
            } else {
                $query = $query->where('period_id', $periodId);
            }

            $perPage = $request->input('per_page', 5);

            $paginated = $query->paginate($perPage);

            return CandidacyResource::collection($paginated);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }

    public function show(Request $request, int $id): CandidacyResource
    {
        $evaluator_id = (int)$request->get('evaluator_id');
        $candidacy = Candidacy::query()
            ->findOrFail($id);
        return new CandidacyResource($candidacy, $evaluator_id);
    }

    public function getDoc(Request $request)
    {
        try {
            $docName = $request->query('docName'); // e.g. "fichier.pdf"

            // Sécurité contre les chemins relatifs
            if (str_contains($docName, '..')) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Nom de fichier invalide',
                ], 400);
            }

            $filePath = 'documents/' . $docName;

            if (!Storage::disk('public')->exists($filePath)) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Document introuvable',
                ], 404);
            }

            $file = Storage::disk('public')->path($filePath);
            $mimeType = mime_content_type($file);

            return response()->file($file, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($file) . '"',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'code' => 500,
                'message' => 'Erreur interne',
            ], 500);
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
            $periodId = $request->post('periodId');
            $interview = Interview::query()->findOrFail($interviewId);

            $evaluator = Evaluator::query()
                ->where('period_id', $periodId)
                ->where("user_id", auth()->user()->id)
                ->first();

            if (!$evaluator) {
                throw new \Exception("Action non autorisée : seul un évaluateur de sélection de la periode encours peut effectuer cette opération.");
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

    public function getCandidateInterview(int $id): InterviewResource
    {
        $interview = Interview::query()
            ->where("candidacy_id", $id)
            ->firstOrFail();

        return new InterviewResource($interview);
    }

    public function candidateHasSelection(int $id): \Illuminate\Http\JsonResponse
    {
        $hasSelection = Interview::query()
            ->where("candidacy_id", $id)
            ->whereHas("selectionResults")
            ->exists();

        return response()
            ->json([
                "hasSelection" => $hasSelection
            ]);
    }

    public function getCandidateEvaluators(int $id): AnonymousResourceCollection
    {
        $evaluators = Evaluator::query()
            ->whereHas('candidacies', function ($query) use ($id) {
                $query->where("candidacy_id", $id);
            })->get();

        return EvaluatorRessource::collection($evaluators);
    }

    public function getSelectedCandidates(Request $request): AnonymousResourceCollection
    {
        $perPage = 10;

        if ($request->has('perPage')) {
            $perPage = $request->input('perPage');
        }

        $currentYear = date("Y");

        $period = Period::query()->where("year", $currentYear)->firstOrFail();

        $candidates = Candidacy::query()
            ->where("period_id", $period->id)
            ->whereHas('interview');

        if ($request->has('search')) {
            $search = $request->input('search');
            $candidates = $candidates->whereLike("etn_nom", "%$search%");
        }

        $candidates = $candidates
            ->paginate($perPage);

        return CandidacyResource::collection($candidates);
    }

    public function uploadZipFile(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip',
        ]);

        try {
            $zipPath = $request->file('zip_file')->store('documents', 'public');
            $fullPath = storage_path("app/public/{$zipPath}");

            $zip = new Zip();
            $zip = $zip->open($fullPath);

            if (!$zip->check($fullPath)) {
                throw new \Exception("Invalid zip file");
            }

            $zip->extract(storage_path('app/public/documents'));

            return response()->json(['message' => 'Fichier ZIP téléchargé et extrait avec succès.']);

        } catch (\Exception $e) {
            throw  new HttpResponseException(
                response: response()->json(['errors' => $e->getMessage()], 400)
            );
        }
    }
}
