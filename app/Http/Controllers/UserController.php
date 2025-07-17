<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Models\User;


class UserController extends Controller
{
    public function index(Request $r): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $users = User::all();
        return UserResource::collection($users);
    }

    public function destroy(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $user->delete();
    }
}
