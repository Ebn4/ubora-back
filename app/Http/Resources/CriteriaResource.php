<?php

namespace App\Http\Resources;


/**
 * @OA\Schema(
 *     schema="CriteriaResource",
 *     type="object",
 *     title="Criteria Resource",
 *     description="Ressource de critère d'évaluation formatée",
 *     @OA\Property(property="id", type="integer", example=7),
 *     @OA\Property(property="name", type="string", example="Compétences techniques"),
 *     @OA\Property(property="description", type="string", example="Évaluation des compétences techniques spécifiques"),
 *     @OA\Property(property="ponderation", type="integer", example=25)
 * )
 */
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CriteriaResource extends JsonResource
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
            "name" => $this->name,
            "description" => $this->description,
            "ponderation" => $this->pivot->ponderation
        ];
    }
}
