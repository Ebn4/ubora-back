<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @OA\Schema(
 *     schema="EvaluatorResource",
 *     type="object",
 *     title="Evaluator Resource",
 *     description="Ressource d'évaluateur formatée",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
 *     @OA\Property(property="type", type="string", example="preselection", enum={"preselection", "selection"}),
 *     @OA\Property(property="period", type="string", example="2024-2025")
 * )
 */
class EvaluatorRessource extends JsonResource
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
            "name" => $this->user->name,
            "email" => $this->user->email,
            "type" => $this->type,
            "period" => $this->period->year
        ];
    }
}
