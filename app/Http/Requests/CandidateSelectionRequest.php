<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CandidateSelectionRequest extends FormRequest
{
    public function rules()
    {
        return [
            'interviewId' => 'required|integer|exists:interviews,id',
            'periodId' => 'required|integer|exists:periods,id',
            'generalObservation' => 'nullable|string|max:2000', // Ajouté ici
            'evaluations' => 'required|array|min:1',
            'evaluations.*.key' => 'required|integer|exists:criterias,id',
            'evaluations.*.value' => 'required|numeric|min:0'
        ];
    }

    public function messages()
    {
        return [
            'generalObservation.max' => 'L\'observation ne peut pas dépasser 2000 caractères.',
            'evaluations.required' => 'Au moins un critère doit être évalué.',
            'evaluations.*.key.required' => 'L\'ID du critère est requis.',
            'evaluations.*.key.exists' => 'Le critère spécifié n\'existe pas.',
            'evaluations.*.value.required' => 'La note est requise pour chaque critère.',
            'evaluations.*.value.numeric' => 'La note doit être un nombre.',
            'evaluations.*.value.min' => 'La note ne peut pas être négative.'
        ];
    }
}
