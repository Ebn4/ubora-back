<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Period;
use App\Models\Candidacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use FileService;
use Spatie\SimpleExcel\SimpleExcelReader;

class UploadCandidaciesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, FileService $fileService)
    {
        try {
            info("uploading candidacies");

            $filename = time() . '.csv';
            $candidacies = $request->file('fichier');
            $fichier = $fileService->uploadFichier($candidacies, 'storage/app', $filename);

            $reader = SimpleExcelReader::create($fichier)->useDelimiter(';');

            $rows = $reader->getRows()->toArray();
            $currentYear = now()->year;

            $existingEmails = Candidacy::pluck('etn_email')->toArray();

            $processedEmails = [];

            DB::transaction(function () use ($rows, $existingEmails, &$processedEmails, $currentYear) {
                $period = Period::firstOrCreate(['year' => $currentYear]);

                foreach ($rows as $row) {
                    try {
                        $createdOn = Carbon::createFromFormat('d/m/Y H:i', $row['created_on']);
                        if ($createdOn->year !== $currentYear) {
                            info("Skipping row: not current year - " . $row['created_on']);
                            continue;
                        }

                        $email = $row['email'];

                        if (in_array($email, $existingEmails)) {
                            info("Skipping row: email already exists in DB - " . $email);
                            continue;
                        }

                        if (in_array($email, $processedEmails)) {
                            info("Skipping row: duplicate email in file - " . $email);
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
}
