<?php

namespace App\Repository;

use App\Entity\Reponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reponse>
 */
class ReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reponse::class);
    }

    /**
     * @return Reponse[]
     */
    public function findByQuestionAndType(int $questionId, string $questionType): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.questionId = :questionId')
            ->andWhere('r.questionType = :questionType')
            ->setParameter('questionId', $questionId)
            ->setParameter('questionType', strtoupper($questionType))
            ->orderBy('r.ordre', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
