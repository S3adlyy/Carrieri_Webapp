<?php

namespace App\Repository;

use App\Entity\CandidatMission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CandidatMission>
 */
class CandidatMissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CandidatMission::class);
    }

    // Ajoutez vos méthodes personnalisées ici
    // public function findBySomething($value): array
    // {
    //     return $this->createQueryBuilder('e')
    //         ->andWhere('e.exampleField = :val')
    //         ->setParameter('val', $value)
    //         ->orderBy('e.id', 'ASC')
    //         ->setMaxResults(10)
    //         ->getQuery()
    //         ->getResult()
    //     ;
    // }
}
