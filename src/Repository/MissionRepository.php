<?php
// src/Repository/MissionRepository.php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Mission;
use App\Entity\User;
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

    /**
     * Recherche des missions avec filtre par description et tri
     *
     * @param User $user
     * @param string $search Terme de recherche
     * @param string $sortBy Champ de tri
     * @param string $sortOrder Ordre de tri (ASC ou DESC)
     * @return Mission[]
     */
    public function findByUserWithSearchAndSort(User $user, string $search = '', string $sortBy = 'id', string $sortOrder = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->setParameter('user', $user);

        // Ajouter la recherche par description si un terme est fourni
        if (!empty($search)) {
            $qb->andWhere('m.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Ajouter le tri
        switch ($sortBy) {
            case 'description':
                $qb->orderBy('m.description', $sortOrder);
                break;
            case 'type':
                $qb->orderBy('m.type', $sortOrder);
                break;
            case 'scoreMin':
                $qb->orderBy('m.scoreMin', $sortOrder);
                break;
            case 'createdAt':
                $qb->orderBy('m.createdAt', $sortOrder);
                break;
            default:
                $qb->orderBy('m.id', $sortOrder);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupérer les missions triées par score
     *
     * @param User $user
     * @param string $order ASC ou DESC
     * @return Mission[]
     */
    public function findByUserOrderedByScore(User $user, string $order = 'DESC'): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.scoreMin', $order)
            ->getQuery()
            ->getResult();
    }
}