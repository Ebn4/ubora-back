<?php

namespace App\Documentation;

/**
 * @OA\Schema(
 *     schema="LoginSuccessResponse",
 *     type="object",
 *     @OA\Property(property="status", type="string", example="otp_sent"),
 *     @OA\Property(property="cuid", type="string", example="DNZL9763"),
 *     @OA\Property(property="message", type="string", example="Un code de vérification a été envoyé à votre adresse e-mail."),
 *     @OA\Property(property="transaction_id", type="string", example="b6f603d6-9ec0-4d07-83f1-e446286ad183"),
 *     @OA\Property(property="channel", type="string", example="email"),
 *     @OA\Property(property="reference_masked", type="string", example="exmple.ext@orange.com")
 * )
 */

class Schemas
{
    // Ce fichier sert uniquement à la documentation Swagger
}