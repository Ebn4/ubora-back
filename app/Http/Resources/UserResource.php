<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *     schema="UserResource",
 *     title="User Resource",
 *     type="object",
 *     @OA\Property(property="email", type="email", example="exampple@gmail.com"),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="cuid", type="string", example="1234556"),
 *    @OA\Property(property="role", type="string", example="admin"),
 *    @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
 * )
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "email" => $this->email,
            "name" => $this->name,
            "cuid" => $this->cuid,
            "role" => $this->role,
            "id" => $this->id,
            "token" => $this->token
        ];
    }
}
