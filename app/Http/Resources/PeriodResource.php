<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\DB;
use App\Models\Interview;
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
        $this->loadCount('criteria');
        return [
            "id" => $this->id,
            "year" => $this->year,
            "status" => $this->status,
            "evaluators_count" => DB::table('evaluators')
                ->where('period_id', $this->id)
                ->distinct('user_id')
                ->count('user_id'),
            "criteria_count" => $this->criteria_count,
            "candidats_count" => DB::table('candidats')
                ->where('is_allowed', true)
                ->where('period_id', $this->id)
                ->count('id'),
            "preselection_count" => Interview::count(),
            "selection_count" => 0,
            "created_at" => "2025-07-11T10:42:29.000000Z",
            "updated_at" => "2025-07-11T10:42:29.000000Z",
        ];
    }
}
