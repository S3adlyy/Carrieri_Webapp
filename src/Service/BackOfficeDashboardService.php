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
}
