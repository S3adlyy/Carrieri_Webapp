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

    /**
     * @return int[]
     */
    public function findPassedCoursIdsForCandidate(int $candidatId): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.coursId AS cours_id')
            ->andWhere('r.candidatId = :candidatId')
            ->andWhere('r.reussite = 1')
            ->setParameter('candidatId', $candidatId)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_unique(array_map(
            static fn (array $row): int => (int) ($row['cours_id'] ?? 0),
            $rows
        )), static fn (int $id): bool => $id > 0));
    }
}
