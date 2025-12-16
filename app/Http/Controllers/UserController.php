<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;


class UserController extends Controller
{

     /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Utilisateurs"},
     *     summary="Lister les utilisateurs",
     *     description="Récupère la liste paginée des utilisateurs avec possibilité de recherche et filtrage par rôle.",
     *     operationId="listUsers",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche textuelle (nom ou email)",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filtrer par rôle",
     *         required=false,
     *         @OA\Schema(type="string", example="admin")
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des utilisateurs",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserResource")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Accès interdit")
     *         )
     *     )
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {

        $perPage = 10;
        $users = User::query();

        if ($request->has('perPage')) {
            $perPage = $request->input('perPage');
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $users = $users->whereLike('name', "%$search%")
                ->orWhereLike('email', "%$search%");
        }

        if ($request->has('role')) {
            $role = $request->input('role');
            $users = $users->whereLike('role', "%$role%");
        }

        $users = $users->paginate($perPage);
        return UserResource::collection($users);
    }



    /**
     * @OA\Get(
     *     path="/api/user",
     *     tags={"Utilisateurs"},
     *     summary="Obtenir le profil de l'utilisateur courant",
     *     description="Récupère les informations de l'utilisateur actuellement authentifié.",
     *     operationId="getCurrentUser",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profil utilisateur",
     *         @OA\JsonContent(ref="#/components/schemas/UserResource")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     )
     * )
     */
    public function show(Request $request)
    {
        return new UserResource($request->user());
    }


    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     tags={"Utilisateurs"},
     *     summary="Supprimer un utilisateur",
     *     description="Supprime définitivement un utilisateur du système.",
     *     operationId="deleteUser",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'utilisateur à supprimer",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Utilisateur supprimé avec succès (pas de contenu)"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Accès interdit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $user->delete();
    }
}
