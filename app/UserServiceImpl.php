<?php

namespace App;

use App\Models\User;
use App\Services\UserService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserServiceImpl implements Services\UserService
{

    /**
     * @throws Exception
     */
    public function findByEmail(string $email): User
    {
        try {
            return User::query()
                ->where('email', $email)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function findById(string $id): User
    {
        try {
            return User::query()
                ->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new Exception($e->getMessage());
        }
    }
}
