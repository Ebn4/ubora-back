<?php

namespace App\Http\Resources;

use App\Models\DispatchPreselection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidacyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            "evaluators" => $this->dispatch,
            "nb_evaluation" => DispatchPreselection::where('candidacy_id', $this->id)->has("preselections")->get()->count()
        ];
    }
}
