<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\FeedbackRepository;

class FeedbackService
{
    public function __construct(
        private FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Note moyenne d'un recruteur
     */
    public function getAverageNoteForRecruiter(User $recruiter): float
    {
        $feedbacks = $this->feedbackRepository->findBy(['user' => $recruiter]);
        
        if (count($feedbacks) === 0) return 0;
        
        $total = array_sum(array_map(fn($f) => $f->getNote(), $feedbacks));
        
        return round($total / count($feedbacks), 1);
    }

    /**
     * Distribution des notes
     * @return array<int, int>
     */
    public function getNoteDistribution(User $recruiter): array
    {
        $feedbacks = $this->feedbackRepository->findBy(['user' => $recruiter]);
        
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        
        foreach ($feedbacks as $feedback) {
            $note = $feedback->getNote();
            if (isset($distribution[$note])) {
                $distribution[$note]++;
            }
        }
        
        return $distribution;
    }

    /**
     * Derniers feedbacks
     * @return array<\App\Entity\Feedback>
     */
    public function getLatestFeedbacks(User $recruiter, int $limit = 5): array
    {
        return $this->feedbackRepository->findBy(
            ['user' => $recruiter],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Statistiques globales des feedbacks pour un recruteur
     * @return array<string, mixed>
     */
    public function getStats(User $recruiter): array
    {
        $distribution = $this->getNoteDistribution($recruiter);
        $total = array_sum($distribution);
        
        return [
            'note_moyenne' => $this->getAverageNoteForRecruiter($recruiter),
            'total_feedbacks' => $total,
            'distribution' => $distribution,
            'meilleure_note' => $this->getBestNote($distribution),
            'pire_note' => $this->getWorstNote($distribution),
        ];
    }

    /**
     * @param array<int, int> $distribution
     */
    private function getBestNote(array $distribution): int
    {
        for ($i = 5; $i >= 1; $i--) {
            if ($distribution[$i] > 0) return $i;
        }
        return 0;
    }

    /**
     * @param array<int, int> $distribution
     */
    private function getWorstNote(array $distribution): int
    {
        for ($i = 1; $i <= 5; $i++) {
            if ($distribution[$i] > 0) return $i;
        }
        return 0;
    }
}