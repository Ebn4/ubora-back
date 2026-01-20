<?php

namespace App\Services;

use App\Models\User;

interface MailService
{
    public function  sendMail(array $payload) : bool;
}
