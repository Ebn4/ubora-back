<?php

namespace App\Http\Controllers;

use App\Enums\PeriodStatusEnum;
use App\Helpers\FileHelper;
use App\Http\Requests\CandidateSelectionRequest;
use App\Http\Resources\CandidacyResource;
use App\Http\Resources\EvaluatorRessource;
use App\Http\Resources\InterviewResource;
use App\Http\Resources\SelectionResultResource;
use App\Models\Candidacy;
use App\Models\Criteria;
use App\Models\EvaluationFinale;
use App\Models\Evaluator;
use App\Models\Interview;
use App\Models\Period;
use App\Models\SelectionResult;
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
     *     tags={"Candidatures"},
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
            'id' => [
                'nullable',
                'integer',
                Rule::exists('periods', 'id'),
            ],
            'rows' => 'required|array|min:1',
        ]);

        try {
            $rows = $validated['rows'];
            $id = $request->input('id');
            $processedEmails = [];
            $period = Period::findOrFail($id);
            $year = $period->year;
            $currentYear = Carbon::createFromFormat('Y', $year);

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
                            'cv' => is_array($row['cv'])
                                ? implode(', ', array_map([FileHelper::class, 'normalizeFileName'], $row['cv']))
                                : FileHelper::normalizeFileName($row['cv']),
                            'releve_note_derniere_annee' => is_array($row['relev_denotesdeladernireannedecours'])
                                ? implode(', ', array_map([FileHelper::class, 'normalizeFileName'], $row['relev_denotesdeladernireannedecours']))
                                : FileHelper::normalizeFileName($row['relev_denotesdeladernireannedecours']),
                            'en_soumettant' => $row['en_soumettant'],
                            'section_option' => $row['sectionoption'],
                            'j_atteste' => $row['jatteste_quelesinfor'],
                            'degre_parente_agent_orange' => $row['si_ouiquelleestvotredegrderelation'],
                            'annee_diplome_detat' => $row['anne_dobtentiondudiplmedtat'],
                            'diplome_detat' => is_array($row['diplme_detat'])
                                ? implode(', ', array_map([FileHelper::class, 'normalizeFileName'], $row['diplme_detat']))
                                : FileHelper::normalizeFileName($row['diplme_detat']),
                            'autres_diplomes_atttestation' => is_array($row['autres_diplmesattestations'])
                                ? implode(', ', array_map([FileHelper::class, 'normalizeFileName'], $row['autres_diplmesattestations']))
                                : FileHelper::normalizeFileName($row['autres_diplmesattestations']),
                            'universite_institut_sup' => $row['nom_universitouinstitutsuprieur'],
                            'pourcentage_obtenu' => $row['pourcentage_obtenu'],
                            'lettre_motivation' => is_array($row['lettre_demotivation'])
                                ? implode(', ', array_map([FileHelper::class, 'normalizeFileName'], $row['lettre_demotivation']))
                                : FileHelper::normalizeFileName($row['lettre_demotivation']),
                            'adresse_universite' => $row['adresse_universit'],
                            'parente_agent_orange' => $row['etesvous_apparentunagentdeorangerdc'],
                            'institution_scolaire' => $row['institution_scolairefrquente'],
                            'faculte' => $row['facult'],
                            'montant_frais' => $row['montants_desfrais'],
                            'sexe' => $row['sexe'],
                            'attestation_de_reussite_derniere_annee' => is_array($row['attestation_derussitedeladernireannedtude'])
                                ? implode(', ', array_map([FileHelper::class, 'normalizeFileName'], $row['attestation_derussitedeladernireannedtude']))
                                : FileHelper::normalizeFileName($row['attestation_derussitedeladernireannedtude']),
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
                'message' => "Erreur lors de l'importation des candidatures",
            ]);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/uploadCandidaciesDocs",
     *     summary="Enregistrement des fichiers attachés aux formulaires",
     *     operationId="uploadDocs",
     *     tags={"Candidatures"},
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


    /**
     * @OA\Get(
     *     path="/api/rejeted_candidacies",
     *     tags={"Candidatures"},
     *     summary="Lister les candidatures rejetées (non autorisées)",
     *     description="Récupère la liste paginée des candidatures avec `is_allowed = false`. Filtres identiques à `/candidacies`.",
     *     operationId="listRejectedCandidacies",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche textuelle (nom, prénom, postnom, ville)",
     *         required=false,
     *         @OA\Schema(type="string", example="Lubumbashi")
     *     ),
     *     @OA\Parameter(
     *         name="ville",
     *         in="query",
     *         description="Filtrer par ville",
     *         required=false,
     *         @OA\Schema(type="string", example="Kinshasa")
     *     ),
     *     @OA\Parameter(
     *         name="periodId",
     *         in="query",
     *         description="ID de la période académique",
     *         required=false,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=5, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des candidatures rejetées",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CandidacyResource")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur interne du serveur"),
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Get(
     *     path="/api/getDoc",
     *     tags={"Documents"},
     *     summary="Télécharger un document candidat",
     *     description="Permet de récupérer un fichier PDF/PNG/JPEG stocké (ex: CV, relevé, diplôme). Le fichier est servi en ligne (inline).",
     *     operationId="getDocument",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="docName",
     *         in="query",
     *         description="Nom exact du fichier (ex: `cv_marcel.pdf`)",
     *         required=true,
     *         @OA\Schema(type="string", example="cv_AB12345.pdf")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fichier retourné en pièce jointe",
     *         content={
     *             @OA\MediaType(
     *                 mediaType="application/pdf",
     *                 @OA\Schema(type="string", format="binary")
     *             )
     *         },
     *         headers={
     *             @OA\Header(
     *                 header="Content-Type",
     *                 @OA\Schema(type="string", example="application/pdf")
     *             ),
     *             @OA\Header(
     *                 header="Content-Disposition",
     *                 @OA\Schema(type="string", example="inline; filename='cv_AB12345.pdf'")
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Requête invalide",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Paramètre docName manquant")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Document non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Document introuvable")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
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


   /**
     * @OA\Delete(
     *     path="/api/candidacies",
     *     tags={"Candidatures"},
     *     summary="Supprimer une candidature (et ses données associées)",
     *     description="Supprime définitivement une candidature, ses pré-sélections, ses évaluations finales. Action irréversible.",
     *     operationId="deleteCandidacy",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"candidacyId"},
     *             @OA\Property(property="candidacyId", type="integer", example=123, description="ID de la candidature à supprimer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="description", type="string", example="Success"),
     *             @OA\Property(property="message", type="string", example="Candidature supprimée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
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


    /**
     * @OA\Post(
     *     path="/api/candidate/selections",
     *     tags={"Candidatures"},
     *     summary="Soumettre les notes de sélection pour un candidat",
     *     description="Permet à un évaluateur de saisir les résultats d'entretien (notes par critère) pour une candidature. L'évaluateur doit être assigné à la période en cours.",
     *     operationId="submitCandidateSelection",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"interviewId", "periodId", "evaluations"},
     *             @OA\Property(property="interviewId", type="integer", example=45),
     *             @OA\Property(property="periodId", type="integer", example=3),
     *             @OA\Property(
     *                 property="evaluations",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"key", "value"},
     *                     @OA\Property(property="key", type="integer", description="ID du critère", example=7),
     *                     @OA\Property(property="value", type="integer", description="Note attribuée (entier)", example=18)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notes enregistrées avec succès",
     *         @OA\JsonContent(@OA\Property(property="data", type="boolean", example=true))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides",
     *         @OA\JsonContent(@OA\Property(property="errors", type="string"))
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Non authentifié"))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Action non autorisée (évaluateur non assigné)",
     *         @OA\JsonContent(@OA\Property(property="errors", type="string"))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur"),
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/candidates/{id}/interviews",
     *     tags={"Entretiens"},
     *     summary="Récupérer l'entretien d'un candidat",
     *     description="Renvoie les détails de l'entretien associé à une candidature (date, évaluateurs, statut).",
     *     operationId="getCandidateInterview",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la candidature",
     *         required=true,
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Entretien trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="candidacy_id", type="integer", example=123),
     *             @OA\Property(property="date", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *             @OA\Property(property="status", type="string", example="planned"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Entretien non trouvé",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Not Found"))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur"),
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/candidates/{id}/evaluators",
     *     tags={"Évaluateurs"},
     *     summary="Lister les évaluateurs assignés à un candidat",
     *     description="Récupère la liste des évaluateurs (évaluateur 1, 2, 3) attribués à une candidature spécifique.",
     *     operationId="getCandidateEvaluators",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la candidature",
     *         required=true,
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des évaluateurs",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *                 @OA\Property(property="role", type="string", example="evaluator")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="Erreur"),
     *             @OA\Property(property="message", type="string", example="Erreur interne du serveur")
     *         )
     *     )
     * )
     */
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
        $perPageInput = $request->input('per_page', 10);

        if ($request->has('per_page')) {
            $perPage = $request->input('per_page');
        }

        if ($request->has('periodId')) {
            $periodId = $request->get('periodId');
            $period = Period::query()->findOrFail($periodId);
        } else {
            $currentYear = date("Y");
            $period = Period::query()->where("year", $currentYear)->firstOrFail();
        }

        $candidates = Candidacy::query()
            ->where("period_id", $period->id)
            ->whereHas('interview');

        if ($request->has('search')) {
            $search = $request->input('search');
            $candidates = $candidates->whereLike("etn_nom", "%$search%");
        }

         if (is_string($perPageInput) && strtolower($perPageInput) === 'all') {
            $candidates = $candidates->get();
        } else {
            // Conversion en int avec valeur par défaut
            $perPage = (int)$perPageInput;
            if ($perPage <= 0) {
                $perPage = 10;
            }
            $candidates = $candidates->paginate($perPage);
        }

        return CandidacyResource::collection($candidates);
    }

    public function getSelectionCandidates(Request $request, int $periodId): AnonymousResourceCollection
    {
        $perPage = 10;

        if ($request->has('per_page')) {
            $perPage = $request->input('per_page');
        }

        $candidates = Candidacy::query()
            ->with(['interview.selectionResults'])
            ->where('period_id', $periodId)
            ->whereHas('interview.selectionResults');

        if ($request->has('search')) {
            $search = $request->input('search');
            $candidates = $candidates->whereLike("etn_nom", "%$search%");
        }

        // Gestion du cas "all"
        if ($perPage === 'all') {
            // Récupérer tous les candidats sans pagination
            $candidates = $candidates->get();

            // Appliquer la même transformation que votre code original
            $candidates->transform(function ($candidate) {
                $selectionsResults[] = $candidate->interview->selectionResults;
                foreach ($selectionsResults as $selectionResult) {
                    $sum = 0;
                    foreach ($selectionResult as $result) {
                        $sum += $result->pivot->result;
                    }
                    $candidate->selectionMean = $sum / count($selectionsResults);
                }
                return $candidate;
            });

            // Retourner sans pagination
            return CandidacyResource::collection($candidates);
        } else {
            // Pagination normale (votre code original inchangé)
            $candidates = $candidates->paginate($perPage);

            // Votre transformation originale exactement comme elle était
            $candidates->getCollection()->transform(function ($candidate) {
                $selectionsResults[] = $candidate->interview->selectionResults;
                foreach ($selectionsResults as $selectionResult) {
                    $sum = 0;
                    foreach ($selectionResult as $result) {
                        $sum += $result->pivot->result;
                    }
                    $candidate->selectionMean = $sum / count($selectionsResults);
                }
                return $candidate;
            });

            return CandidacyResource::collection($candidates);
        }
    }

    public function getCandidateSelectionResultByCriteria(int $id, int $criterionId): SelectionResultResource
    {
        try {
            $result = SelectionResult::query()
                ->where('interview_id', $id)
                ->where('criteria_id', $criterionId)
                ->first();

            if (!$result) {
                return new SelectionResultResource(null);
            }

            return new SelectionResultResource($result);
        } catch (\Exception $e) {
            throw  new HttpResponseException(
                response: response()->json(['errors' => $e->getMessage()], 400)
            );
        }
    }

    public function uploadZipFile(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('ZIP file upload request received', [
            'request_data' => $request->all(),
            'files' => $request->allFiles(),
            'user_id' => auth()->id() ?? 'unauthenticated',
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'php_upload_settings' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit')
            ]
        ]);

        // Check if we can handle the file size
        $contentLength = $request->header('Content-Length') ? (int)$request->header('Content-Length') : 0;
        $uploadMaxSize = $this->parseSize(ini_get('upload_max_filesize'));

        if ($contentLength > $uploadMaxSize) {
            Log::error('ZIP file upload failed: File too large for current PHP settings', [
                'content_length' => $contentLength,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ]);

            return response()->json([
                'errors' => [
                    'zip_file' => [
                        'File size (' . round($contentLength / 1024 / 1024, 2) . 'MB) exceeds server limit (' . ini_get('upload_max_filesize') . '). ' .
                        'Please contact administrator to increase upload limits or compress your file.'
                    ]
                ],
                'message' => 'File upload failed due to server configuration limits.',
                'server_limits' => [
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'content_length' => $contentLength,
                    'file_size_mb' => round($contentLength / 1024 / 1024, 2)
                ]
            ], 422);
        }

        try {
            // Validate the request with more specific rules
            $request->validate([
                'zip_file' => [
                    'required',
                    'file',
                    'mimes:zip',
                    'max:10240', // 10MB in kilobytes (10 * 1024)
                ],
            ], [
                'zip_file.max' => 'The ZIP file size must not exceed 10MB.',
                'zip_file.mimes' => 'The file must be a valid ZIP archive.',
                'zip_file.required' => 'Please select a ZIP file to upload.',
            ]);

            // Check if file exists and is valid
            if (!$request->hasFile('zip_file')) {
                Log::error('ZIP file upload failed: No file in request');
                throw new \Exception('No ZIP file provided in the request');
            }

            $file = $request->file('zip_file');

            // Log file details
            Log::info('ZIP file details', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'error' => $file->getError(),
                'is_valid' => $file->isValid()
            ]);

            // Check if file upload was successful
            if (!$file->isValid()) {
                $errorCode = $file->getError();
                $errorMessage = $file->getErrorMessage();

                // Map PHP upload error codes to user-friendly messages
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File size exceeds PHP upload limit',
                    UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];

                $userMessage = $errorMessages[$errorCode] ?? $errorMessage;

                Log::error('ZIP file upload failed: Invalid file', [
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'user_message' => $userMessage,
                    'file_details' => [
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ]
                ]);

                throw new \Exception($userMessage);
            }

            // Store the file
            $zipPath = $file->store('documents', 'public');
            if (!$zipPath) {
                Log::error('ZIP file storage failed: Could not store file');
                throw new \Exception('Failed to store the ZIP file');
            }

            $fullPath = storage_path("app/public/{$zipPath}");
            Log::info('ZIP file stored successfully', ['path' => $fullPath]);

            // Open and validate ZIP
            $zip = new Zip();
            $zip = $zip->open($fullPath);

            if (!$zip->check($fullPath)) {
                Log::error('ZIP file validation failed: Invalid ZIP format');
                throw new \Exception("Invalid ZIP file format");
            }

            // Extract ZIP contents
            $extractPath = storage_path('app/public/documents');
            $zip->extract($extractPath);

            $files = $zip->listFiles();
            foreach ($files as $fileInZip) {
                $normalizedFileName = FileHelper::normalizeFileName($fileInZip);
                $targetPath = $extractPath . '/' . $normalizedFileName;

                // Crée les dossiers si nécessaire
                $dir = dirname($targetPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // Extrait avec le nouveau nom
                $zip->extract($extractPath, [$fileInZip]); // Extrait d'abord avec nom original

                // Renomme le fichier extrait
                $originalExtractedPath = $extractPath . '/' . $fileInZip;
                if (file_exists($originalExtractedPath)) {
                    rename($originalExtractedPath, $targetPath);
                }
            }

            Log::info('ZIP file extracted successfully', [
                'extract_path' => $extractPath,
                'stored_path' => $zipPath
            ]);

            return response()->json(['message' => 'Fichier ZIP téléchargé et extrait avec succès.']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors specifically
            Log::error('ZIP file validation error', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'user_id' => auth()->id() ?? 'unauthenticated'
            ]);

            throw new HttpResponseException(
                response: response()->json(['errors' => $e->errors()], 422)
            );

        } catch (\Exception $e) {
            // Log the complete error with stack trace
            Log::error('Error uploading ZIP file', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'user_id' => auth()->id() ?? 'unauthenticated',
                'file_upload_info' => $request->hasFile('zip_file') ? [
                    'file_exists' => true,
                    'file_size' => $request->file('zip_file')->getSize(),
                    'file_error' => $request->file('zip_file')->getError(),
                    'file_mime' => $request->file('zip_file')->getMimeType()
                ] : ['file_exists' => false]
            ]);

            throw new HttpResponseException(
                response: response()->json(['errors' => $e->getMessage()], 400)
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/upload-settings",
     *     summary="Get current PHP upload settings",
     *     operationId="getUploadSettings",
     *     tags={"File Upload"},
     *     @OA\Response(
     *         response=200,
     *         description="Current PHP upload settings",
     *         @OA\JsonContent(
     *             @OA\Property(property="upload_max_filesize", type="string"),
     *             @OA\Property(property="post_max_size", type="string"),
     *             @OA\Property(property="max_execution_time", type="string"),
     *             @OA\Property(property="memory_limit", type="string")
     *         )
     *     )
     * )
     */
    public function getUploadSettings()
    {
        return response()->json([
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'file_uploads' => ini_get('file_uploads')
        ]);
    }

    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgt]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9.]/', '', $size); // Remove all non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which has the highest unit.
            // This determines the size multiplier.
            return $size * pow(1024, stripos('bkmgt', $unit[0]));
        }
        return (int)$size;
    }

    public function getSelectionStats(Request $request)
    {
        $periodId = $request->input('periodId');
        if (!$periodId || !is_numeric($periodId)) {
            return response()->json([
                'error' => 'Le paramètre periodId est requis et doit être un nombre.'
            ], 400);
        }

        $periodId = (int) $periodId;

        try {
            // Vérifier que la période existe
            $period = Period::findOrFail($periodId);

            // Total des candidats en phase de sélection (avec entretien)
            $total = Candidacy::where("period_id", $period->id)
                ->whereHas('interview')
                ->count();

            // Total des candidats évalués (avec au moins un SelectionResult)
            $evaluated = Candidacy::where("period_id", $period->id)
                ->whereHas('interview.selectionResults') //
                ->count();

            return response()->json([
                'total' => $total,
                'evaluated' => $evaluated,
                'pending' => $total - $evaluated,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du calcul des statistiques: ' . $e->getMessage()
            ], 500);
        }
    }
}
