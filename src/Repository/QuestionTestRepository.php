<?php

namespace App\Repository;

use App\Entity\QuestionTest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionTest>
 */
class QuestionTestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionTest::class);
    }

    /**
     * @return QuestionTest[]
     */
    public function findByCoursOrdered(int $coursId, int $limit = 15): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.coursId = :coursId')
            ->setParameter('coursId', $coursId)
            ->orderBy('q.ordre', 'ASC')
            ->addOrderBy('q.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countForCours(int $coursId): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->andWhere('q.coursId = :coursId')
            ->setParameter('coursId', $coursId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
