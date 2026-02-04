<?php

namespace App\Http\Controllers;

use App\Http\Resources\LdapUserResource;
use App\Services\UserLdapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class LdapUserController extends Controller
{

    public function __construct(
        private UserLdapService $userLdapService
    )
    {

    }

    /**
     * @OA\Get(
     *     path="/api/users/ldap/{user}",
     *     summary="Rechercher des utilisateurs dans LDAP",
     *     description="Effectue une recherche d'utilisateurs dans l'annuaire LDAP en fonction d'un terme de recherche",
     *     tags={"Utilisateurs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Terme de recherche pour l'utilisateur (nom, prénom, cuid)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             minLength=2,
     *             example="Orange"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des utilisateurs trouvés",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="count", type="integer", example=3),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="username", type="string", example="ABCF3265"),
     *                     @OA\Property(property="cn", type="string", example="Test Orange"),
     *                     @OA\Property(property="displayName", type="string", example="Test Orange"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="exemple.extOrange.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation - terme de recherche trop court",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="La recherche doit contenir au moins 2 caractères")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé - Token JWT invalide ou manquant",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur interne",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Server Error")
     *         )
     *     )
     * )
     *
     * Handle the incoming request.
     */
   public function __invoke(Request $request)
    {
        if (strlen($request->user ?? '') < 2) {
            return response()->json([
                'message' => 'La recherche doit contenir au moins 2 caractères'
            ], 422);
        }

        $users = $this->userLdapService->searchUser($request->user);


        return LdapUserResource::collection($users);
    }

}
