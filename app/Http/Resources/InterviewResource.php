<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="InterviewResource",
 *     type="object",
 *     title="Interview Resource",
 *     description="Ressource d'entretien formatée",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="candidateId", type="integer", example=123)
 * )
 */
class InterviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "candidateId" => $this->candidacy_id
        ];
    }
}
