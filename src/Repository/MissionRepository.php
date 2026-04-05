<?php

namespace App\Repository;

use App\Entity\Mission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mission>
 */
class MissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mission::class);
    }

    /**
     * @return Mission[]
     */
    public function findRecent(int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCreatedById(int $createdById): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.createdById = :createdById')
            ->setParameter('createdById', $createdById)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
}
