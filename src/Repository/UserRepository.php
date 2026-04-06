<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return User[]
     */
    public function findRecent(int $limit): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findRecentCandidates(int $limit): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.type IN (:types)')
            ->setParameter('types', ['CANDIDATE', 'CANDIDAT'])
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countCandidatesForRecruiter(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.type IN (:types)')
            ->setParameter('types', ['CANDIDATE', 'CANDIDAT'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return User[]
     */
    public function findAllCandidatesOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.type IN (:types)')
            ->setParameter('types', ['CANDIDATE', 'CANDIDAT'])
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveSuggestions(int $excludeUserId, int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.id != :excludeId')
            ->andWhere('u.isActive = 1 OR u.isActive IS NULL')
            ->setParameter('excludeId', $excludeUserId)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findActiveSuggestionsByType(int $excludeUserId, string $type, int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.id != :excludeId')
            ->andWhere('u.isActive = 1 OR u.isActive IS NULL')
            ->andWhere('u.type = :type')
            ->setParameter('excludeId', $excludeUserId)
            ->setParameter('type', $type)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
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
