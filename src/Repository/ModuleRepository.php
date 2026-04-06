<?php

namespace App\Repository;

use App\Entity\Cours;
use App\Entity\Module;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Module>
 */
class ModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Module::class);
    }

    /**
     * @return Module[]
     */
    public function findByCours(Cours $cours): array
    {
        return $this->findBy(['cours' => $cours], ['ordre' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @param int[] $coursIds
     * @return Module[]
     */
    public function findByCoursIds(array $coursIds): array
    {
        if ($coursIds === []) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.coursId IN (:coursIds)')
            ->setParameter('coursIds', $coursIds)
            ->orderBy('m.ordre', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Module[]
     */
    public function findForRecruiter(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.cours', 'c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getNextOrdreForCours(int $coursId): int
    {
        $maxOrdre = $this->createQueryBuilder('m')
            ->select('MAX(m.ordre)')
            ->andWhere('m.coursId = :coursId')
            ->setParameter('coursId', $coursId)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $maxOrdre) + 1;
    }
}