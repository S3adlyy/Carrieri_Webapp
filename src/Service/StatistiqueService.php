<?php

namespace App\Service;

use App\Repository\ReclamationRepository;
use App\Repository\FeedbackRepository;
use App\Repository\UserRepository;

class StatistiqueService
{
    public function __construct(
        private ReclamationRepository $reclamationRepository,
        private FeedbackRepository $feedbackRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * Tableau de bord global
     * @return array<string, mixed>
     */
    public function getDashboardStats(): array
    {
        return [
            'total_reclamations' => count($this->reclamationRepository->findAll()),
            'reclamations_en_attente' => count($this->reclamationRepository->findBy(['statut' => 'En attente'])),
            'reclamations_traitees' => count($this->reclamationRepository->findBy(['statut' => 'Traité'])),
            'total_feedbacks' => count($this->feedbackRepository->findAll()),
            'note_moyenne_globale' => $this->getGlobalAverageNote(),
            'total_recruteurs' => count($this->userRepository->findBy(['type' => 'RECRUITER'])),
            'total_candidats' => count($this->userRepository->findBy(['type' => 'CANDIDATE'])),
        ];
    }

    /**
     * Note moyenne globale de tous les feedbacks
     */
    public function getGlobalAverageNote(): float
    {
        $feedbacks = $this->feedbackRepository->findAll();
        
        if (count($feedbacks) === 0) return 0;
        
        $total = array_sum(array_map(fn($f) => $f->getNote(), $feedbacks));
        
        return round($total / count($feedbacks), 1);
    }

    /**
     * Statistiques par mois (pour les graphiques)
     * @return array<int, array<string, int>>
     */
    public function getMonthlyStats(int $year): array
    {
        $stats = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $start = new \DateTimeImmutable("{$year}-{$month}-01");
            $end = $start->modify('last day of this month');
            
            $stats[$month] = [
                'reclamations' => $this->countReclamationsBetween($start, $end),
                'feedbacks' => $this->countFeedbacksBetween($start, $end),
            ];
        }
        
        return $stats;
    }

    private function countReclamationsBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $result = $this->reclamationRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.dateCreation BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) $result;
    }

    private function countFeedbacksBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $result = $this->feedbackRepository->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) $result;
    }
}