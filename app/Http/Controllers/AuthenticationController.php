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
     *     summary="Authentifier un utilisateur et initier l'OTP",
     *     description="Permet de se connecter avec un CUID et un mot de passe. En cas de succès, un code OTP est envoyé par email ou SMS. La réponse est toujours en 200, avec un champ 'success' indiquant le statut.",
     *     operationId="login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cuid","password"},
     *             @OA\Property(property="cuid", type="string", example="BNJK2032", description="Identifiant unique de l'utilisateur"),
     *             @OA\Property(property="password", type="string", format="password", example="s3cr3tP@ss", description="Mot de passe de l'utilisateur")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Réponse structurée (succès ou échec métier)",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="data", ref="#/components/schemas/LoginSuccessResponse")
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="error", type="string", example="Cuid ou mot de passe incorrect.")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur technique imprévue (LDAP injoignable, timeout, etc.)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Service temporairement indisponible. Veuillez réessayer plus tard.")
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

    /**
     * @OA\Post(
     *     path="/api/resend-otp",
     *     tags={"Authentification"},
     *     summary="Renvoyer un nouveau code OTP",
     *     description="Renvoie un nouveau code OTP pour le CUID fourni (doit être dans la session active).",
     *     operationId="resendOtp",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cuid"},
     *             @OA\Property(property="cuid", type="string", example="DNHG8720")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Succès ou échec",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Un nouveau code a été envoyé via email.")
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="error", type="string", example="Session expirée. Veuillez vous reconnecter.")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
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


     /**
     * @OA\Post(
     *     path="/api/verify-otp",
     *     tags={"Authentification"},
     *     summary="Vérifier le code OTP et finaliser la connexion",
     *     description="Valide le code OTP fourni et retourne les données utilisateur + token en cas de succès.",
     *     operationId="verifyOtp",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cuid","otp"},
     *             @OA\Property(property="cuid", type="string", example="DHJS3256"),
     *             @OA\Property(property="otp", type="string", example="123456", description="Code OTP à 6 chiffres")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Succès ou échec de vérification",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="error", type="string", example="Code OTP incorrect.")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
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
