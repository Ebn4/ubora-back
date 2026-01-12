<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cuid' => ['required','string','max:50'],
            'otp' => ['required','string']
        ];
    }

    public function messages():array{
        return [
            'cuid.required' => 'Le cuid est requis',
            'otp.required' => "Le code OTP est requis",
            'otp.size' => 'Le code OTP doit contenir exactement 6 chiffres',
            'otp.regex' => 'Le code OTP doit contenir uniquements des chiffres'
        ];
    }
}
