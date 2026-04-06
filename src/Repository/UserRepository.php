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

    // src/Repository/UserRepository.php

    // src/Repository/UserRepository.php

    // src/Repository/UserRepository.php

    // src/Repository/UserRepository.php

    public function findByRole(string $role): array
    {
        // Version qui gère les deux formats
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role_json')
            ->orWhere('u.roles = :role_string')
            ->setParameter('role_json', '%"' . $role . '"%')
            ->setParameter('role_string', str_replace('ROLE_', '', $role))
            ->getQuery()
            ->getResult();
    }

// Ajoutez aussi cette méthode pour trouver par type (si vous utilisez le champ "type")
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.type = :type')
            ->setParameter('type', $type)
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
