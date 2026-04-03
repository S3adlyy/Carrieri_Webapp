<?php

namespace App\Repository;

use App\Entity\Certification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Certification>
 */
class CertificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certification::class);
    }

    public function countForRecruiterCourses(User $recruiter): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.cours', 'co')
            ->where('co.user = :u')
            ->setParameter('u', $recruiter)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Certification[]
     */
    public function findForRecruiterCoursesOrdered(User $recruiter): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.cours', 'co')
            ->where('co.user = :u')
            ->setParameter('u', $recruiter)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
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
