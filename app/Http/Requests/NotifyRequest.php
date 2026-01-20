<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ou votre logique d'autorisation
    }

    public function rules(): array
    {
        return [
            'periodId' => 'required|integer|exists:periods,id',
        ];
    }
}