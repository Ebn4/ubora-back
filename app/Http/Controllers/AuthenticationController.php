<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthenticationRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;
use \Exception;

/**
 * @OA\Tag(
 *     name="Authentification",
 *     description="Opérations d'authentification (login/logout)"
 * )
 */
class AuthenticationController extends Controller
{
    public function __construct(private AuthenticationService $authenticationService)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Authentification"},
     *     summary="Authentifier un utilisateur",
     *     description="Permet de se connecter avec un CUID et un mot de passe. Retourne les informations de l'utilisateur et un token d'authentification (ex: JWT dans UserResource).",
     *     operationId="login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cuid","password"},
     *             @OA\Property(property="cuid", type="string", example="AB12345", description="Identifiant unique de l'utilisateur"),
     *             @OA\Property(property="password", type="string", format="password", example="s3cr3tP@ss", description="Mot de passe de l'utilisateur")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Authentification réussie",
     *         @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="token", type="string"),
     *           @OA\Property(property="user", ref="#/components/schemas/UserResource")
    *          )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur d'authentification (CUID invalide, mot de passe incorrect, utilisateur désactivé, etc.)",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="string", example="Invalid credentials.")
     *         )
     *     )
     * )
     */
    public function login(AuthenticationRequest $request)
    {
        try {
            $result = $this->authenticationService->login(
                $request->cuid,
                $request->password
            );
            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Technical error during login', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'error' => 'Service temporairement indisponible. Veuillez réessayer plus tard.'
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Authentification"},
     *     summary="Déconnecter l'utilisateur courant",
     *     description="Invalide le token d'authentification actif (ex: supprime le JWT du côté serveur ou blacklist).",
     *     security={{"bearerAuth": {}}},
     *     operationId="logout",
     *     @OA\Response(
     *         response=204,
     *         description="Déconnexion réussie. Aucun contenu retourné."
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur lors de la déconnexion",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="string", example="Failed to logout.")
     *         )
     *     )
     * )
     */
    public function logout()
    {
        try {
            $this->authenticationService->logout();
        } catch (Exception  $e) {
            return throw new HttpResponseException(
                response()->json(data: [
                    "errors" => $e->getMessage()
                ], status: 400)
            );
        }
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'cuid' => 'required|string'
        ]);

        try {
            // Vérifier si les données LDAP sont toujours en cache
            $ldapUser = Cache::get("ldap_user_{$request->cuid}");
            if (!$ldapUser) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session expirée. Veuillez vous reconnecter.'
                ], 401);
            }

            // Envoyer l'OTP via le service
            $channel = $this->authenticationService->resendOtp($request->cuid);

            return response()->json([
                'success' => true,
                'message' => "Un nouveau code a été envoyé via " . ($otpResult['channel'] ?? 'inconnu') . "."
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }



    public function verifyOtp(VerifyOtpRequest $request)
    {
        Log::info("Je suis dans le controller verify");

        try {
            $result = $this->authenticationService->verifyOtp(
                $request->cuid,
                $request->otp
            );

            Log::info('Résultat verifyOtp', $result);

            $result['user']->token = $result['token'];

            return response()->json([
                'success' => true,
                'user' => new UserResource($result['user']),
            ], 200);

        } catch (\Exception $e) {
            // Si le message contient "OTP" c'est un problème utilisateur
            if (str_contains($e->getMessage(), 'OTP') || str_contains($e->getMessage(), 'Session expirée')) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 200); // 200 car ce n'est pas une erreur serveur
            }

            Log::error('Erreur verifyOtp', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur interne du serveur.'
            ], 500);
        }
    }


}
