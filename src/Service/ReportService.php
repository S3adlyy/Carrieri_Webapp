<?php

namespace App\Service;

use App\Entity\Reclamation;
use App\Entity\Feedback;
use App\Repository\ReclamationRepository;
use App\Repository\FeedbackRepository;

class ReportService
{
    public function __construct(
        private ReclamationRepository $reclamationRepository,
        private FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Générer un rapport de réclamations
     * @return array<string, mixed>
     */
    public function generateReclamationReport(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $reclamations = $this->reclamationRepository->createQueryBuilder('r')
            ->where('r.dateCreation BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
        
        return [
            'period' => [
                'start' => $start->format('d/m/Y'),
                'end' => $end->format('d/m/Y')
            ],
            'total' => count($reclamations),
            'by_status' => $this->groupByStatus($reclamations),
            'by_priority' => $this->groupByPriority($reclamations),
            'reclamations' => $reclamations
        ];
    }

    /**
     * Générer un rapport de feedbacks
     * @return array<string, mixed>
     */
    public function generateFeedbackReport(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $feedbacks = $this->feedbackRepository->createQueryBuilder('f')
            ->where('f.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
        
        $notes = array_map(fn($f) => $f->getNote(), $feedbacks);
        
        return [
            'period' => [
                'start' => $start->format('d/m/Y'),
                'end' => $end->format('d/m/Y')
            ],
            'total' => count($feedbacks),
            'note_moyenne' => count($feedbacks) > 0 ? round(array_sum($notes) / count($feedbacks), 1) : 0,
            'note_min' => count($notes) > 0 ? min($notes) : 0,
            'note_max' => count($notes) > 0 ? max($notes) : 0,
            'feedbacks' => $feedbacks
        ];
    }

    /**
     * @param array<Reclamation> $reclamations
     * @return array<string, int>
     */
    private function groupByStatus(array $reclamations): array
    {
        $result = [];
        foreach ($reclamations as $r) {
            $status = $r->getStatut() ?? '';
            if (!isset($result[$status])) {
                $result[$status] = 0;
            }
            $result[$status]++;
        }
        return $result;
    }

    /**
     * @param array<Reclamation> $reclamations
     * @return array<string, int>
     */
    private function groupByPriority(array $reclamations): array
    {
        $result = [];
        foreach ($reclamations as $r) {
            $priority = $r->getPriorite() ?? '';
            if (!isset($result[$priority])) {
                $result[$priority] = 0;
            }
            $result[$priority]++;
        }
        return $result;
    }
}