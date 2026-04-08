<?php

namespace App\Repository;

use App\Entity\QuestionQuiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionQuiz>
 */
class QuestionQuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionQuiz::class);
    }

    /**
     * @return QuestionQuiz[]
     */
    public function findByModuleOrdered(int $moduleId, int $limit = 5): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.moduleId = :moduleId')
            ->setParameter('moduleId', $moduleId)
            ->orderBy('q.ordre', 'ASC')
            ->addOrderBy('q.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countForModule(int $moduleId): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->andWhere('q.moduleId = :moduleId')
            ->setParameter('moduleId', $moduleId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
