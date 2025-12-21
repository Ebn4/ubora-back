<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthenticationRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthenticationService;
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
     *     tags={"Authentication"},
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
            $user = $this->authenticationService->login($request->cuid, $request->password);
            return new UserResource($user);
        } catch (Exception $e) {
            return throw new HttpResponseException(
                response()->json(data: [
                    "errors" => $e->getMessage()
                ], status: 400)
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Authentication"},
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
}
