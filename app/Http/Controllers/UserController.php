<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;


class UserController extends Controller
{

    public function getUsers(Request $r)
    {

        /* if (!Gate::allows("only_admin", Auth::user())) {

            return ('Vous n\'êtes pas autorisé à aller sur cette page');
        } */

        try {
            info('get Users');
            $users = User::get();

            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'users' => $users

            ]);
            /*  return view('users.users')->with('users', $users); */
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'

            ]);
        }
    }

    public function getUser(Request $r)
    {

        try {
            info('get User');
            /*  dd($r->distributor); */

            $user = User::where('id', $r->userId)->first();

            if ($user != '') {
                return response()->json([
                    'code' => 200,
                    'description' => "Success",
                    'user' => $user
                ]);
            } else {
                return response()->json([
                    'code' => 404,
                    'description' => "Not found",
                    'user' => $user
                ]);
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                'code' => 500,
                'description' => 'Erreur interne du serveur',
                'message' => 'Erreur interne du serveur'

            ]);
        }
    }


    public function createUser(Request $r)
    {
        /*  $r->validate([
            'cuid' => ['unique:user']
        ]); */

        try {
            info('create User');
            /*  info(Auth::user()->cuid . 'is saving user.'); */

            $user = User::create([
                "email" => $r->email,
                "password" => Hash::make("password"),
                "name" => "test",
                "profil" => $r->profile
            ]);

//            dd($r->all());


            info('user saved: ' . $r->email);

            /* return redirect()->route('users')->with("action_success", 'Utilisateur enregistré')->with('modal', true); */
            return response()->json([
                'code' => 200,
                'description' => "Success",
                'message' => "Utilisateur enregistré"
            ]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                'code' => 500,
                'description' => $th->getMessage(),
                'message' => $th->getMessage()
            ]);
        }
    }


    public function updateUser(Request $r)
    {
        /* $r->validate([
            'cuid' => ['unique:user,cuid,' . $r->id],

        ]); */
        try {

            info('update User');
            $cuid = Str::upper($r->cuid);

            $user = User::find($r->id);
            /*  info(Auth::user()->cuid . 'is updating user' . $user->cuid); */

            $user->cuid = $cuid;
            $user->profil = $r->profile;
            $saved = $user->save();

            $modal = true;

            if ($saved == true) {
                info('user updated');
                /* return redirect()->route('user', ['user' => $r->id])->with('user', $r->id)->with('modal', $modal)->with("action_success", "Utilisateur mis à jour"); */
                return response()->json([
                    'code' => 200,
                    'description' => 'Success',
                    'message' => "Utilisateur mis à jour",

                ]);
            } else {
                info('error when updating user');
                /*    return redirect()->route('user', ['user' => $r->id])->with('user', $r->id)->with('modal', $modal)->with("action_error", "Erreur lors de la mise à jour"); */
                return response()->json([
                    'code' => 500,
                    'description' => 'Erreur',
                    'message' => "Erreur lors de la mise à jour",

                ]);
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            $modal = true;

            /* return redirect()->route('user', ['user' => $r->id])->with('modal', $modal)->with("action_error", "Erreur interne du serveur"); */
            return response()->json([
                'code' => 500,
                'description' => 'Erreur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }


    public function deleteUser(Request $r)
    {
        try {

            info('deleting user');
            $user = User::destroy($r->id);
            info('user deleted');
            return response()->json([
                'code' => 200,
                'description' => 'Success',
                'message' => "Utilisateur supprimé",

            ]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            return response()->json([
                'code' => 500,
                'description' => 'Erreur',
                'message' => 'Erreur interne du serveur'
            ]);
        }
    }
}
