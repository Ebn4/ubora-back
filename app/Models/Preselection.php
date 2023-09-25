<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preselection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidature',
        'critere_nationalite',
        'critere_age',
        'critere_annee_diplome_detat',
        'critere_pourcentage',
        'critere_cursus_choisi',
        'critere_universite_institution_choisie',
        'critere_cycle_etude',
        'pres_commentaire',
        'pres_validation',
    ];

    protected $table = 'preselections';
}
