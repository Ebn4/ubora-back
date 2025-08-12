<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelectionResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'criteriaId' => $this->criteria_id,
            'interviewId' => $this->interview_id,
            'evaluatorId' => $this->evaluator_id,
            'result' => $this->result
        ];
    }
}
