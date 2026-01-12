<?php

namespace App\Services;

use App\Models\User;

interface AuthenticationService
{

    public function login(string $cuid, string $password);

    public function logout(): void;

    public function verifyOtp(string $cuid, string $otp): array;

    public function resendOtp(string $cuid): void;


}
