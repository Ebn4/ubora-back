<?php

namespace App\Http\Resources;

use App\Models\Candidacy;
use App\Models\DispatchPreselection;
use Illuminate\Support\Facades\DB;
use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;



/**
 * @OA\Schema(
 *     schema="PeriodResource",
 *     type="object",
 *     title="Period Resource",
 *     description="Ressource de période académique formatée",
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="year", type="string", example="2024-2025"),
 *     @OA\Property(property="status", type="string", example="dispatch", enum={"dispatch", "preselection", "selection", "close"}),
 *     @OA\Property(property="evaluators_count", type="integer", example=15),
 *     @OA\Property(property="criteria_count", type="integer", example=8),
 *     @OA\Property(property="candidats_count", type="integer", example=250),
 *     @OA\Property(property="preselection_count", type="integer", example=180),
 *     @OA\Property(property="selection_count", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-11T10:42:29.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-11T10:42:29.000000Z"),
 *     @OA\Property(property="progression_preselection", type="string", example="72%")
 * )
 */
class PeriodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $id = $this->id;
        $countCandidaciesAllow = Candidacy::where('period_id', $id)
            ->where('is_allowed', true)
            ->count();

        $countCandidaciesPreselection = DispatchPreselection::whereHas('candidacy', function ($query) use ($id) {
            $query->where('period_id', $id);
        })
            ->distinct('candidacy_id')
            ->count();

        if ($countCandidaciesAllow == 0) {
            $progressionPreselection = 0;
        } else {
            $progressionPreselection = ceil(($countCandidaciesPreselection / $countCandidaciesAllow) * 100);
        }

        $this->loadCount('criteria');
        return [
            "id" => $this->id,
            "year" => $this->year . "-" . ($this->year + 1),
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
            "preselection_count" => Interview::whereHas('candidacy', function ($query) use ($id) {
                $query->where('period_id', $id);
            })->count(),
            "selection_count" => 0,
            "created_at" => "2025-07-11T10:42:29.000000Z",
            "updated_at" => "2025-07-11T10:42:29.000000Z",
            "progression_preselection" => $progressionPreselection . "%",
        ];
    }
}
