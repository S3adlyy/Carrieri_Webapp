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

    public function findExistingSubmission(int $missionId, int $candidatId): ?RenduMission
    {
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
    public function findAcceptedSubmissionsByRecruiter(int $recruiterId): array
    {
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
}