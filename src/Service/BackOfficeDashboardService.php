<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\CertificationRepository;
use App\Repository\CoursRepository;
use App\Repository\MissionRepository;
use App\Repository\OffreEmploiRepository;
use App\Repository\ReclamationRepository;
use App\Repository\UserRepository;
use App\Repository\RenduMissionRepository;  // ← AJOUTEZ CETTE LIGNE

/**
 * Aggregates stats and listings for the back-office (admin vs recruiter scope).
 */
final class BackOfficeDashboardService
{
    public function __construct(
        private UserRepository $userRepository,
        private CoursRepository $coursRepository,
        private MissionRepository $missionRepository,
        private OffreEmploiRepository $offreEmploiRepository,
        private ReclamationRepository $reclamationRepository,
        private CertificationRepository $certificationRepository,
        private RenduMissionRepository $renduMissionRepository,  // ← AJOUTEZ CETTE LIGNE
    ) {
    }

    public function isAdmin(User $user): bool
    {
        return \in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * @return array{users: int, cours: int, missions: int, offres: int, reclamations: int, certifications: int}
     */
    public function getStats(User $user): array
    {
        if ($this->isAdmin($user)) {
            return [
                'users' => $this->userRepository->count([]),
                'cours' => $this->coursRepository->count([]),
                'missions' => $this->missionRepository->count([]),
                'offres' => $this->offreEmploiRepository->count([]),
                'reclamations' => $this->reclamationRepository->count([]),
                'certifications' => $this->certificationRepository->count([]),
            ];
        }

        return [
            'users' => $this->userRepository->countCandidatesForRecruiter(),
            'cours' => $this->coursRepository->count(['user' => $user]),
            'missions' => $this->missionRepository->count(['user' => $user]),
            'offres' => $this->offreEmploiRepository->count(['user' => $user]),
            'reclamations' => $this->reclamationRepository->count(['user' => $user]),
            'certifications' => $this->certificationRepository->countForRecruiterCourses($user),
        ];
    }

    /**
     * @return list<array{nom: string, email: string, date: \DateTimeInterface|null}>
     */
    public function getRecentUsersPreview(User $user, int $limit = 5): array
    {
        $users = $this->isAdmin($user)
            ? $this->userRepository->findRecent($limit)
            : $this->userRepository->findRecentCandidates($limit);

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'nom' => trim(($u->getFirstName() ?? '') . ' ' . ($u->getLastName() ?? '')) ?: ($u->getEmail() ?? '—'),
                'email' => (string) ($u->getEmail() ?? ''),
                'date' => $u->getCreatedAt(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, detail: string, date: \DateTimeInterface|null}>
     */
    public function getRecentMissionActivity(User $user, int $limit = 5): array
    {
        $missions = $this->isAdmin($user)
            ? $this->missionRepository->findRecent($limit)
            : $this->missionRepository->findBy(['user' => $user], ['id' => 'DESC'], $limit);

        $rows = [];
        foreach ($missions as $m) {
            $creator = $m->getUser();
            $name = $creator
                ? trim(($creator->getFirstName() ?? '') . ' ' . ($creator->getLastName() ?? ''))
                : '—';
            $desc = $m->getDescription() ?? '';
            if (\strlen($desc) > 80) {
                $desc = substr($desc, 0, 77) . '…';
            }
            $rows[] = [
                'label' => $name !== '' ? $name : ($creator?->getEmail() ?? '—'),
                'detail' => 'Mission : ' . ($desc !== '' ? $desc : '#' . $m->getId()),
                'date' => $m->getCreatedAt(),
            ];
        }

        return $rows;
    }

    /**
     * @return \App\Entity\Mission[]
     */
    public function listMissions(User $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->missionRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->missionRepository->findBy(['user' => $user], ['id' => 'DESC']);
    }

    /**
     * @return \App\Entity\Cours[]
     */
    public function listCours(User $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->coursRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->coursRepository->findBy(['user' => $user], ['id' => 'DESC']);
    }

    /**
     * @return \App\Entity\OffreEmploi[]
     */
    public function listOffresEmploi(User $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->offreEmploiRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->offreEmploiRepository->findBy(['user' => $user], ['id' => 'DESC']);
    }

    /**
     * @return \App\Entity\Reclamation[]
     */
    public function listReclamations(User $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->reclamationRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->reclamationRepository->findBy(['user' => $user], ['id' => 'DESC']);
    }

    /**
     * @return \App\Entity\Certification[]
     */
    public function listCertifications(User $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->certificationRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->certificationRepository->findForRecruiterCoursesOrdered($user);
    }

    /**
     * @return \App\Entity\User[]
     */
    public function listUsers(User $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->userRepository->findBy([], ['id' => 'DESC']);
        }

        return $this->userRepository->findAllCandidatesOrdered();
    }

    public function getMissionStats(User $user): array
    {
        $missions = $this->missionRepository->findByUserWithSearchAndSort($user, '', 'id', 'DESC');

        $totalMissions = count($missions);
        $totalSubmissions = 0;
        $totalAccepted = 0;
        $totalRejected = 0;
        $totalPending = 0;
        $scores = [];
        $missionsWithSubmissions = 0;

        foreach ($missions as $mission) {
            $submissions = $this->renduMissionRepository->findBy(['missionId' => $mission->getId()]);
            $submissionCount = count($submissions);
            $totalSubmissions += $submissionCount;

            if ($submissionCount > 0) {
                $missionsWithSubmissions++;
            }

            foreach ($submissions as $submission) {
                if ($submission->getScore()) {
                    $scores[] = $submission->getScore();
                }

                if ($submission->getStatut() === 'accepte') {
                    $totalAccepted++;
                } elseif ($submission->getStatut() === 'refuse') {
                    $totalRejected++;
                } elseif ($submission->getStatut() === 'en_attente') {
                    $totalPending++;
                }
            }
        }

        $averageScore = !empty($scores) ? array_sum($scores) / count($scores) : 0;
        $maxScore = !empty($scores) ? max($scores) : 0;
        $minScore = !empty($scores) ? min($scores) : 0;

        $scoreDistribution = [
            'excellent' => 0,
            'good' => 0,
            'average' => 0,
            'poor' => 0,
            'very_poor' => 0,
        ];

        foreach ($scores as $score) {
            if ($score >= 90) $scoreDistribution['excellent']++;
            elseif ($score >= 75) $scoreDistribution['good']++;
            elseif ($score >= 50) $scoreDistribution['average']++;
            elseif ($score >= 25) $scoreDistribution['poor']++;
            else $scoreDistribution['very_poor']++;
        }

        return [
            'total_missions' => $totalMissions,
            'total_submissions' => $totalSubmissions,
            'missions_with_submissions' => $missionsWithSubmissions,
            'submission_rate' => $totalMissions > 0 ? round(($missionsWithSubmissions / $totalMissions) * 100) : 0,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'total_pending' => $totalPending,
            'acceptance_rate' => $totalSubmissions > 0 ? round(($totalAccepted / $totalSubmissions) * 100) : 0,
            'average_score' => round($averageScore, 1),
            'max_score' => round($maxScore, 1),
            'min_score' => round($minScore, 1),
            'score_distribution' => $scoreDistribution,
        ];
    }
}