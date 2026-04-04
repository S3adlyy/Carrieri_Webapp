<?php

namespace App\Repository;

use App\Entity\Cours;
use App\Entity\Lecon;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lecon>
 */
class LeconRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lecon::class);
    }

    /**
     * @param int[] $moduleIds
     * @return Lecon[]
     */
    public function findByModuleIds(array $moduleIds): array
    {
        if ($moduleIds === []) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->andWhere('l.moduleId IN (:moduleIds)')
            ->setParameter('moduleIds', $moduleIds)
            ->orderBy('l.ordre', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Lecon[]
     */
    public function findByCours(Cours $cours): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.module', 'm')
            ->andWhere('m.cours = :cours')
            ->setParameter('cours', $cours)
            ->orderBy('m.ordre', 'ASC')
            ->addOrderBy('l.ordre', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Lecon[]
     */
    public function findForRecruiter(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.module', 'm')
            ->innerJoin('m.cours', 'c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getNextOrdreForModule(int $moduleId): int
    {
        $maxOrdre = $this->createQueryBuilder('l')
            ->select('MAX(l.ordre)')
            ->andWhere('l.moduleId = :moduleId')
            ->setParameter('moduleId', $moduleId)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $maxOrdre) + 1;
    }
}
