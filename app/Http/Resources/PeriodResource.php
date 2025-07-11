<?php

namespace App\Http\Resources;

use App\Models\DispatchPreselection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeriodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadCount('evaluators', 'criteria', 'candidats');
        return [
            "id" => $this->id,
            "year" => $this->year,
            "status" => $this->status,
            "evaluators_count" => $this->evaluators_count,
            "criteria_count" => $this->criteria_count,
            "candidats_count" => $this->candidats_count,
            "preselection_count" => 0,
            "selection_count" => 0,
            "created_at" => "2025-07-11T10:42:29.000000Z",
            "updated_at" => "2025-07-11T10:42:29.000000Z",
        ];
    }
}
