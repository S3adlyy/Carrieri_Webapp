<?php

namespace App\Repository;

use App\Entity\Cours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cours>
 */
class CoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cours::class);
    }

    /**
     * @return Cours[]
     */
    public function searchForCandidate(?string $query, ?string $niveau, int $page, int $limit = 6): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        if ($niveau !== null && $niveau !== '') {
            $qb->andWhere('c.niveau = :niveau')->setParameter('niveau', $niveau);
        }

        if ($query !== null && $query !== '') {
            $qb
                ->andWhere('LOWER(c.titre) LIKE :q OR LOWER(c.description) LIKE :q OR LOWER(c.competencesVisees) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countForCandidateFilters(?string $query, ?string $niveau): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($niveau !== null && $niveau !== '') {
            $qb->andWhere('c.niveau = :niveau')->setParameter('niveau', $niveau);
        }

        if ($query !== null && $query !== '') {
            $qb
                ->andWhere('LOWER(c.titre) LIKE :q OR LOWER(c.description) LIKE :q OR LOWER(c.competencesVisees) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return string[]
     */
    public function findDistinctNiveaux(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.niveau AS niveau')
            ->andWhere('c.niveau IS NOT NULL')
            ->andWhere("c.niveau <> ''")
            ->orderBy('c.niveau', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $r): string => (string) $r['niveau'], $rows));
    }
}
