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
     * Find all missions with filters and sorting
     *
     * @param string|null $type Filter by mission type
     * @param string|null $search Search in description
     * @param string $sort Sort field (recent, score_asc, score_desc)
     * @return Mission[]
     */
    public function findAllWithFilters(?string $type = null, ?string $search = null, string $sort = 'recent'): array
    {
        $qb = $this->createQueryBuilder('m');

        // Filter by type
        if ($type !== null) {
            $qb->andWhere('LOWER(m.type) = LOWER(:type)')
                ->setParameter('type', $type);
        }

        // Search in description
        if ($search !== null) {
            $qb->andWhere('LOWER(m.description) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply sorting
        switch ($sort) {
            case 'score_asc':
                $qb->orderBy('m.scoreMin', 'ASC');
                break;
            case 'score_desc':
                $qb->orderBy('m.scoreMin', 'DESC');
                break;
            case 'recent':
            default:
                $qb->orderBy('m.createdAt', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique mission types
     *
     * @return string[]
     */
    public function findAllTypes(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('DISTINCT m.type')
            ->orderBy('m.type', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'type');
    }

    /**
     * @return Mission[]
     */
    public function findRecentWithLimit(int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Mission[]
     */
    public function findByCreatedById(?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get missions by user with filters
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
