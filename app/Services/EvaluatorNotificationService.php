<?php

namespace App\Services;

use App\Models\Evaluator;
use App\Models\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class EvaluatorNotificationService
{
    public function __construct(
        protected MailService $mailService
    ) {}

    /**
     * Notifie les évaluateurs de présélection après un dispatch.
     *
     * @param Collection<Evaluator> $evaluators
     * @param array $dispatchMap [candidacyId => [evaluatorId, ...]]
     * @param Period $period
     */
    public function notifyPreselectionEvaluators(Collection $evaluators, array $dispatchMap, Period $period): void
    {
        foreach ($evaluators as $evaluator) {
            $assignedCount = collect($dispatchMap)
                ->filter(fn($evalIds) => in_array($evaluator->id, $evalIds))
                ->count();

            if ($assignedCount === 0) {
                continue;
            }

            // Vérifie s’il est aussi évaluateur de sélection
            $isAlsoSelection = Evaluator::where('user_id', $evaluator->user_id)
                ->where('period_id', $period->id)
                ->where('type', 'selection') // ou EvaluatorTypeEnum::EVALUATOR_SELECTION->value
                ->exists();

            $roleLabel = $isAlsoSelection
                ? "évaluateur de présélection et de sélection"
                : "évaluateur de présélection";

            $this->sendAssignmentEmail(
                evaluator: $evaluator,
                roleLabel: $roleLabel,
                assignedCount: $assignedCount,
                periodLabel: $period->year . '-' . ($period->year + 1)?? "campagne en cours"
            );
        }
    }

    /**
     * Envoie un e-mail personnalisé à un évaluateur.
     */
    private function sendAssignmentEmail(
        Evaluator $evaluator,
        string $roleLabel,
        int $assignedCount,
        string $periodLabel
    ): void {
        if (empty($evaluator->user?->email)) {
            Log::warning('Évaluateur sans email, notification ignorée', ['evaluator_id' => $evaluator->id]);
            return;
        }

        $loginUrl = config('app.frontend_url') . '/login';
        $evaluatorName = $evaluator->user->name ?? 'Évaluateur';

        $html = View::make('emails.evaluator.dispatch-assigned', compact(
            'evaluatorName',
            'roleLabel',
            'assignedCount',
            'periodLabel',
            'loginUrl'
        ))->render();

        $payload = [
            'from' => 'Ubora.ext@orange.com',
            'to' => [$evaluator->user->email],
            'subject' => "Nouveaux dossiers à évaluer – Plateforme UBORA",
            'text' => "Bonjour {$evaluatorName}, vous avez {$assignedCount} nouveau(x) dossier(s) à évaluer sur la plateforme UBORA.",
            'html' => $html,
            'attachments' => [],
        ];

        $success = $this->mailService->sendMail($payload);

        if (!$success) {
            Log::error('Échec de la notification par e-mail', [
                'evaluator_id' => $evaluator->id,
                'email' => $evaluator->user->email,
            ]);
        }
    }
}