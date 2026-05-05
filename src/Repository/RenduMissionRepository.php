<?php
// src/Repository/RenduMissionRepository.php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RenduMission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RenduMission>
 */
class RenduMissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RenduMission::class);
    }

    public function findExistingSubmission(?int $missionId, ?int $candidatId): ?RenduMission
    {
        if ($missionId === null || $candidatId === null) {
            return null;
        }

        return $this->createQueryBuilder('r')
            ->where('r.missionId = :missionId')
            ->andWhere('r.candidatId = :candidatId')
            ->setParameter('missionId', $missionId)
            ->setParameter('candidatId', $candidatId)
            ->orderBy('r.dateRendu', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return RenduMission[]
     */
    public function findByCandidat(int $candidatId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.candidatId = :candidatId')
            ->setParameter('candidatId', $candidatId)
            ->orderBy('r.dateRendu', 'DESC')
            ->getQuery()
            ->getResult();
    }
    /**
    * @return RenduMission[]
    */
    public function findAcceptedSubmissionsByRecruiter(?int $recruiterId): array
    {
        if ($recruiterId === null) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->innerJoin('r.mission', 'm')
            ->where('r.statut = :statut')
            ->andWhere('m.user = :recruiterId')
            ->setParameter('statut', 'accepte')
            ->setParameter('recruiterId', $recruiterId)
            ->orderBy('r.dateRendu', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $missionIds
     * @return RenduMission[]
     */
    public function findActiveSessionsByMissionIds(array $missionIds): array
    {
        if ($missionIds === []) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->where('r.missionId IN (:missionIds)')
            ->andWhere('r.statut = :statut')
            ->setParameter('missionIds', $missionIds)
            ->setParameter('statut', 'en_attente')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $candidatId
     * @return RenduMission[]
     */
    public function findActiveSessions(int $candidatId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.candidatId = :candidatId')
            ->andWhere('r.statut = :statut')
            ->setParameter('candidatId', $candidatId)
            ->setParameter('statut', 'en_attente')
            ->orderBy('r.dateRendu', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
