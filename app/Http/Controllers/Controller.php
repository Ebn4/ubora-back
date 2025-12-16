<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;



/**
 * @OA\Info(
 *     title="Ubora API Documentation",
 *     version="1.0.0",
 *     description="Documentation de l'API Ubora Backend",
 *     @OA\Contact(
 *         email="support@ubora.com",
 *         name="Support Ubora"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://ubora.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Serveur API Ubora"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Utilisez le token JWT obtenu lors de l'authentification"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
