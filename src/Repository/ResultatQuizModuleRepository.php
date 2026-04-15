<?php

namespace App\Repository;

use App\Entity\ResultatQuizModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResultatQuizModule>
 */
class ResultatQuizModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResultatQuizModule::class);
    }

    public function findLatestForCandidateAndModule(int $candidatId, int $moduleId): ?ResultatQuizModule
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.candidatId = :candidatId')
            ->andWhere('r.moduleId = :moduleId')
            ->setParameter('candidatId', $candidatId)
            ->setParameter('moduleId', $moduleId)
            ->orderBy('r.dateCompletion', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
