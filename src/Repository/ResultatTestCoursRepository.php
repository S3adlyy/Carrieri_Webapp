<?php

namespace App\Repository;

use App\Entity\ResultatTestCours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResultatTestCours>
 */
class ResultatTestCoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResultatTestCours::class);
    }

    public function findLatestForCandidateAndCours(int $candidatId, int $coursId): ?ResultatTestCours
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.candidatId = :candidatId')
            ->andWhere('r.coursId = :coursId')
            ->setParameter('candidatId', $candidatId)
            ->setParameter('coursId', $coursId)
            ->orderBy('r.dateCompletion', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
