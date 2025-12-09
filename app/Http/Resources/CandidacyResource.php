<?php

namespace App\Http\Resources;

use App\Models\DispatchPreselection;
use App\Models\Interview;
use App\Models\Preselection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *     schema="CandidacyResource",
 *     type="object",
 *     title="Candidacy Resource",
 *     description="Ressource de candidature formatée",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="post_work_id", type="integer", nullable=true, example=5),
 *     @OA\Property(property="form_id", type="integer", nullable=true, example=10),
 *     @OA\Property(property="form_submited_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="etn_nom", type="string", example="Doe"),
 *     @OA\Property(property="etn_email", type="string", format="email", example="john.doe@example.com"),
 *     @OA\Property(property="etn_prenom", type="string", example="John"),
 *     @OA\Property(property="etn_postnom", type="string", example="Smith"),
 *     @OA\Property(property="etn_naissance", type="string", format="date", example="1990-01-01"),
 *     @OA\Property(property="ville", type="string", example="Kinshasa"),
 *     @OA\Property(property="telephone", type="string", example="+243812345678"),
 *     @OA\Property(property="adresse", type="string", example="123 Avenue de la Paix"),
 *     @OA\Property(property="province", type="string", example="Kinshasa"),
 *     @OA\Property(property="nationalite", type="string", example="Congolaise"),
 *     @OA\Property(property="cv", type="string", nullable=true, example="uploads/cv/document.pdf"),
 *     @OA\Property(property="releve_note_derniere_annee", type="string", nullable=true, example="uploads/notes/releve.pdf"),
 *     @OA\Property(property="en_soumettant", type="string", nullable=true, example="Oui"),
 *     @OA\Property(property="section_option", type="string", nullable=true, example="Informatique"),
 *     @OA\Property(property="j_atteste", type="boolean", example=true),
 *     @OA\Property(property="degre_parente_agent_orange", type="string", nullable=true, example="Aucun"),
 *     @OA\Property(property="annee_diplome_detat", type="string", example="2010"),
 *     @OA\Property(property="diplome_detat", type="string", example="Diplôme d'État"),
 *     @OA\Property(property="autres_diplomes_atttestation", type="string", nullable=true),
 *     @OA\Property(property="universite_institut_sup", type="string", example="Université de Kinshasa"),
 *     @OA\Property(property="pourcentage_obtenu", type="string", example="75%"),
 *     @OA\Property(property="lettre_motivation", type="string", nullable=true),
 *     @OA\Property(property="adresse_universite", type="string", nullable=true),
 *     @OA\Property(property="parente_agent_orange", type="string", nullable=true),
 *     @OA\Property(property="institution_scolaire", type="string", nullable=true),
 *     @OA\Property(property="montant_frais", type="string", nullable=true),
 *     @OA\Property(property="sexe", type="string", example="Masculin"),
 *     @OA\Property(property="attestation_de_reussite_derniere_annee", type="string", nullable=true),
 *     @OA\Property(property="user_last_login", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="faculte", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="period_id", type="integer", example=3),
 *     @OA\Property(property="period_year", type="string", example="2023-2024"),
 *     @OA\Property(property="period_status", type="string", example="active"),
 *     @OA\Property(
 *         property="evaluators",
 *         type="array",
 *         @OA\Items(type="object")
 *     ),
 *     @OA\Property(property="evalutors_number", type="integer", example=2),
 *     @OA\Property(property="nb_evaluation", type="integer", example=3),
 *     @OA\Property(property="institute_count", type="integer", example=15),
 *     @OA\Property(property="candidacy_count", type="integer", example=250),
 *     @OA\Property(property="city_count", type="integer", example=10),
 *     @OA\Property(property="preselection_count", type="integer", example=50),
 *     @OA\Property(property="selection_count", type="integer", example=20),
 *     @OA\Property(property="candidacy_preselection", type="boolean", example=true),
 *     @OA\Property(property="hasSelected", type="boolean", example=false),
 *     @OA\Property(property="selectionMean", type="number", format="float", example=85.5)
 * )
 */
class CandidacyResource extends JsonResource
{

    protected $evaluator_id;

    public function __construct($resource, $evaluator_id = null)
    {
        parent::__construct($resource);
        $this->evaluator_id = $evaluator_id;
        info("Id Evaluator : " . $this->evaluator_id);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $idPivot = optional(
            $this->dispatch->firstWhere(
                fn($evaluator) => $evaluator->pivot?->candidacy_id === $this->id &&
                    $evaluator->pivot?->evaluator_id === $this->evaluator_id
            )
        )?->pivot?->id;
        $periodId = $this->period_id;

        return [
            "id" => $this->id,
            "post_work_id" => $this->post_work_id,
            "form_id" => $this->form_id,
            "form_submited_at" => $this->form_submited_at,
            "etn_nom" => $this->etn_nom,
            "etn_email" => $this->etn_email,
            "etn_prenom" => $this->etn_prenom,
            "etn_postnom" => $this->etn_postnom,
            "etn_naissance" => $this->etn_naissance,
            "ville" => $this->ville,
            "telephone" => $this->telephone,
            "adresse" => $this->adresse,
            "province" => $this->province,
            "nationalite" => $this->nationalite,
            "cv" => $this->cv,
            "releve_note_derniere_annee" => $this->releve_note_derniere_annee,
            "en_soumettant" => $this->en_soumettant,
            "section_option" => $this->section_option,
            "j_atteste" => $this->j_atteste,
            "degre_parente_agent_orange" => $this->degre_parente_agent_orange,
            "annee_diplome_detat" => $this->annee_diplome_detat,
            "diplome_detat" => $this->diplome_detat,
            "autres_diplomes_atttestation" => $this->autres_diplomes_atttestation,
            "universite_institut_sup" => $this->universite_institut_sup,
            "pourcentage_obtenu" => $this->pourcentage_obtenu,
            "lettre_motivation" => $this->lettre_motivation,
            "adresse_universite" => $this->adresse_universite,
            "parente_agent_orange" => $this->parente_agent_orange,
            "institution_scolaire" => $this->institution_scolaire,
            "montant_frais" => $this->montant_frais,
            "sexe" => $this->sexe,
            "attestation_de_reussite_derniere_annee" => $this->attestation_de_reussite_derniere_annee,
            "user_last_login" => $this->user_last_login,
            "faculte" => $this->faculte,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "period_id" => $this->period_id,
            "period_year" => $this->period?->year . "-" . ($this->period?->year+1),
            'period_status' => $this->period?->status,
            "evaluators" => $this->dispatch,
            "evalutors_number" => $this->dispatch->count(),
            "nb_evaluation" => DispatchPreselection::where('candidacy_id', $this->id)->has("preselections")->get()->count(),
            "institute_count" => DB::table('candidats')
                ->where('period_id', $this->period_id)
                ->where('is_allowed', true)
                ->distinct('universite_institut_sup')
                ->count('universite_institut_sup'),
            "candidacy_count" => DB::table('candidats')
                ->where('is_allowed', true)
                ->where('period_id', $this->period_id)
                ->count('id'),
            "city_count" => DB::table('candidats')
                ->where('period_id', $this->period_id)
                ->where('is_allowed', true)
                ->distinct('ville')
                ->count('ville'),
            "preselection_count" => Interview::whereHas('candidacy', function ($query) use ($periodId) {
                $query->where('period_id', $periodId);
            })->count(),
            "selection_count" => 0,
            "candidacy_preselection" => Preselection::where("dispatch_preselections_id", $idPivot)->exists(),
            "hasSelected" => $this->interview()->whereHas('selectionResults')->exists(),
            "selectionMean" => $this->selectionMean ?? 0
        ];
    }
}
