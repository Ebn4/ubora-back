<?php

namespace App;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserServiceImpl implements Services\UserService
{

    /**
     * @throws \Exception
     */
    public function findByEmail(string $email): User
    {
        try {
            return User::query()
                ->where('email', $email)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
