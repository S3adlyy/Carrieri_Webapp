<?php

namespace App\Repository;

use App\Entity\Cours;
use App\Entity\User;
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
        $items = $this->findBy([], ['id' => 'DESC']);

        $items = array_values(array_filter($items, static function (Cours $cours) use ($query, $niveau): bool {
            if ($niveau !== null && $niveau !== '' && $cours->getNiveau() !== $niveau) {
                return false;
            }

            if ($query === null || $query === '') {
                return true;
            }

            $needle = mb_strtolower($query);
            $haystack = mb_strtolower(trim((string) $cours->getTitre() . ' ' . (string) $cours->getDescription() . ' ' . (string) $cours->getCompetencesVisees()));

            return str_contains($haystack, $needle);
        }));

        return array_slice($items, max(0, ($page - 1) * $limit), $limit);
    }

    public function countForCandidateFilters(?string $query, ?string $niveau): int
    {
        return count(array_filter($this->findBy([], ['id' => 'DESC']), static function (Cours $cours) use ($query, $niveau): bool {
            if ($niveau !== null && $niveau !== '' && $cours->getNiveau() !== $niveau) {
                return false;
            }

            if ($query === null || $query === '') {
                return true;
            }

            $needle = mb_strtolower($query);
            $haystack = mb_strtolower(trim((string) $cours->getTitre() . ' ' . (string) $cours->getDescription() . ' ' . (string) $cours->getCompetencesVisees()));

            return str_contains($haystack, $needle);
        }));
    }

    /**
     * @return string[]
     */
    public function findDistinctNiveaux(): array
    {
        return ['Débutant', 'Intermédiaire', 'Avancé'];
    }

    /**
     * @return string[]
     */
    public function findDistinctNiveauxBackOffice(?User $user, bool $isAdmin): array
    {
        return ['Débutant', 'Intermédiaire', 'Avancé'];
    }

    /**
     * @return Cours[]
     */
    public function searchForBackOffice(?User $user, bool $isAdmin, ?string $query, ?string $niveau): array
    {
        $items = $isAdmin || $user === null
            ? $this->findBy([], ['id' => 'DESC'])
            : $this->findBy(['user' => $user], ['id' => 'DESC']);

        return array_values(array_filter($items, static function (Cours $cours) use ($query, $niveau): bool {
            if ($niveau !== null && $niveau !== '' && $cours->getNiveau() !== $niveau) {
                return false;
            }

            if ($query === null || $query === '') {
                return true;
            }

            $needle = mb_strtolower($query);
            $haystack = mb_strtolower((string) $cours->getTitre() . ' ' . (string) $cours->getDescription() . ' ' . (string) $cours->getCompetencesVisees());

            return str_contains($haystack, $needle);
        }));
    }
}